<?php
/**
 * IMP-52 — R8-64, referencias documentales EXTERNAS e inmutables.
 * No emite ni valida CPE, no acredita dinero, no contacta SUNAT y no
 * reescribe el documento base tras una nota de crédito.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class External_Documents
{
    private const OP = 'external_document_record';
    private const KINDS = ['boleta','factura','rhe','ticket','nota_credito','guia'];
    /** Prueba P-C externa; las opciones declaradas no acreditan al emisor. */
    private static $documentVerifier=null;

    public static function registerDocumentVerifier(callable $verifier):void
    {
        if (null!==self::$documentVerifier) {
            throw new \LogicException('pc_external_document_verifier_duplicate');
        }
        self::$documentVerifier=$verifier;
    }


    public static function guard(array $context, ?string $role): array
    {
        if (!self::staffAllowed($role)) { return []; }
        try {
            $orderId = Domain_Validation::sqlId($context['path']['id'] ?? '');
            $data = self::input($context['body'] ?? null);
            $exists = Transaction::run(static function (Transaction_Context $tx) use ($orderId, $data): bool {
                $order = $tx->rows(
                    'SELECT id FROM %i WHERE id=%s LIMIT 2',
                    [Orders_Domain::table('pc_orders'), $orderId]
                );
                if (\count($order) !== 1) { return false; }
                if (isset($data['source_document_id'])) {
                    $src = $tx->rows(
                        'SELECT id FROM %i WHERE id=%s AND order_id=%s LIMIT 2',
                        [Orders_Domain::table('pc_external_documents'), $data['source_document_id'], $orderId]
                    );
                    if (\count($src) !== 1) { return false; }
                }
                return true;
            });
            return $exists ? [
                'authorized'=>true, 'same_site'=>true,
                'resource_checked'=>true, 'state_checked'=>true, 'field_checked'=>true
            ] : [];
        } catch (\Throwable $ignored) { return []; }
    }

    public static function record(string $orderId, array $body): array
    {
        if (!self::staffAllowed(Access_Roles::currentRole())) {
            throw new Rest_Failure('pc_access_denied', 403);
        }
        $orderId = Domain_Validation::sqlId($orderId);
        $body = self::input($body);
        $actor = Event_Actor::staff([
            'perucommerce_settings_manage','perucommerce_orders_read'
        ]);
        $scope = [
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'order','resource_id'=>$orderId,
            'purpose'=>self::OP,
        ];
        return Transaction::run(static function (Transaction_Context $tx) use ($orderId,$body,$actor,$scope): array {
            $claim = Idempotency::claimWithin(
                $tx, $scope, self::OP, $body['idempotency_key'], $body
            );
            if ('replay' === $claim['status']) {
                if ('external_document' !== ($claim['entity_type'] ?? null)
                    || 201 !== ($claim['response_code'] ?? null)) {
                    throw new Transaction_Failure('pc_document_conflict');
                }
                $id = Domain_Validation::sqlId((string)$claim['entity_id']);
                $found = $tx->rows(
                    'SELECT id FROM %i WHERE id=%s AND order_id=%s AND idempotency_id=%s LIMIT 2',
                    [Orders_Domain::table('pc_external_documents'),$id,$orderId,$claim['operation_id']]
                );
                if (1 !== \count($found)) {
                    throw new Transaction_Failure('pc_document_conflict');
                }
                return self::result($claim['operation_id'],$id);
            }
            if ('new' !== $claim['status']) {
                throw new Transaction_Failure('pc_document_conflict');
            }

            $order = $tx->lockOrder($orderId);
            if ((string)$order['row_version'] !== $body['expected_version']) {
                throw new Rest_Failure('pc_conflict', 409);
            }
            $settings = Settings_Store::readWithin($tx)['settings'];
            $regime = self::allowedRegime($settings['fiscal'] ?? null, $body['document_kind']);
            $sourceId = $body['source_document_id'] ?? null;
            if ('nota_credito' === $body['document_kind']) {
                if (null === $sourceId || !isset($body['evidence_ref'])
                    || !isset($body['amount_cents'])) {
                    throw new Rest_Failure('pc_field_invalid',422);
                }
                $sources = $tx->rows(
                    'SELECT id,document_kind FROM %i WHERE id=%s AND order_id=%s LIMIT 2',
                    [Orders_Domain::table('pc_external_documents'),$sourceId,$orderId]
                );
                if (1 !== \count($sources)
                    || !\in_array($sources[0]['document_kind'],['boleta','factura','ticket'],true)) {
                    throw new Rest_Failure('pc_business_ineligible',422);
                }
            } elseif (null !== $sourceId) {
                throw new Rest_Failure('pc_field_invalid',422,false,'source_document_id');
            }

            // Configurar régimen no prueba la actividad ni la referencia.
            if (null===self::$documentVerifier) {
                throw new Rest_Failure('pc_module_not_ready',423);
            }
            try {
                $legitimate=(self::$documentVerifier)(
                    $tx,$orderId,$body,$regime,(string)$order['status']
                );
                if (true!==$legitimate) {
                    throw new Rest_Failure('pc_business_ineligible',422);
                }
            } catch (Rest_Failure $error) {
                throw $error;
            } catch (\Throwable $ignored) {
                throw new Rest_Failure('pc_module_not_ready',423);
            }

            $identity = (string)\get_current_blog_id() . '|' . $regime . '|'
                . $body['document_kind'] . '|'
                . \strtolower($body['series'] ?? '') . '|'
                . \strtolower($body['number'] ?? '') . '|'
                . \strtolower($body['external_reference'] ?? '');
            $dedup = \hash('sha256',$identity);
            $previous = $tx->rows(
                'SELECT id FROM %i WHERE event_dedup_hash=UNHEX(%s) LIMIT 2',
                [Orders_Domain::table('pc_external_documents'),$dedup]
            );
            if ([] !== $previous) {
                throw new Rest_Failure('pc_conflict',409);
            }
            $date = Business_Time::toDatabase(
                Business_Time::fromApi($body['issued_at'])
            );
            if ($date > Business_Time::toDatabase(Business_Time::nowUtc())) {
                throw new Rest_Failure('pc_business_ineligible',422,false,'issued_at');
            }
            // Transaction_Context no acepta NULL como parámetro SQL.
            // Omitir columnas opcionales preserva los DEFAULT NULL del DDL.
            $columns=[
                'order_id','idempotency_id','issuer_tax_regime',
                'document_kind','issued_at','event_dedup_hash',
                'status_note','recorded_by','recorded_at'
            ];
            $placeholders=[
                '%s','%s','%s','%s','%s','UNHEX(%s)',
                '%s','%s','UTC_TIMESTAMP(6)'
            ];
            $args=[
                Orders_Domain::table('pc_external_documents'),
                $orderId,$claim['operation_id'],$regime,
                $body['document_kind'],$date,$dedup,
                'external_only',(string)\get_current_user_id()
            ];
            foreach (['source_document_id','series','number','external_reference','amount_cents'] as $optional) {
                if (isset($body[$optional])) {
                    $columns[]=$optional;
                    $placeholders[]='%s';
                    $args[]=$body[$optional];
                }
            }
            $sql='INSERT INTO %i ('.\implode(',',$columns).') VALUES ('
                .\implode(',',$placeholders).')';
            $saved=$tx->execute($sql,$args);
            if (1 !== $saved) {
                throw new Transaction_Failure('pc_document_write_failed');
            }
            $generated = $tx->rows('SELECT LAST_INSERT_ID() AS id');
            if (1 !== \count($generated)) {
                throw new Transaction_Failure('pc_document_write_failed');
            }
            $recordId = Domain_Validation::sqlId((string)$generated[0]['id']);
            if (1 !== $tx->execute(
                'UPDATE %i SET row_version=row_version+1,updated_at=UTC_TIMESTAMP(6) '
                    . 'WHERE id=%s AND row_version=%s',
                [Orders_Domain::table('pc_orders'),$orderId,$body['expected_version']]
            )) {
                throw new Transaction_Failure('pc_document_conflict');
            }
            $auditable=\hash('sha256',\json_encode([
                'site'=>(string)\get_current_blog_id(),
                'order'=>$orderId,
                'document_id'=>$recordId,
                'type'=>$body['document_kind'],
                'reference_digest'=>$dedup,
                'evidence_digest'=>\hash('sha256',$body['evidence_ref']??''),
                'amount_cents'=>$body['amount_cents']??null,
            ],\JSON_THROW_ON_ERROR));
            Event_Journal::auditWithin(
                $tx,$actor,$claim['operation_id'],'external_document',$recordId,
                'external_document_registered',null,null,$auditable
            );
            Idempotency::completeLocalWithin(
                $tx,$claim['operation_id'],'external_document',$recordId,201
            );
            return self::result($claim['operation_id'],$recordId);
        }, true);
    }

    private static function allowedRegime(mixed $fiscal, string $kind): string
    {
        if (!\is_array($fiscal)
            || !\is_string($fiscal['regime'] ?? null)
            || !\is_array($fiscal['external_document_types'] ?? null)) {
            throw new Rest_Failure('pc_module_not_ready',423);
        }
        $regime = $fiscal['regime'];
        $types = $fiscal['external_document_types'];
        $permitted = match ($regime) {
            'new_rus' => ['boleta','ticket','guia'],
            'rer','rmt','general' => ['boleta','factura','ticket','nota_credito','guia'],
            'fourth_category' => ['rhe'],
            default => [],
        };
        if (!\in_array($kind,$permitted,true)
            || (\in_array($kind,['boleta','factura','ticket','rhe'],true)
                && !\in_array($kind,$types,true))) {
            throw new Rest_Failure('pc_business_ineligible',422,false,'document_kind');
        }
        return $regime;
    }

    private static function input(mixed $value): array
    {
        if (!\is_array($value) || \array_is_list($value)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        foreach (['document_kind','issued_at','expected_version','idempotency_key'] as $name) {
            if (!\is_string($value[$name] ?? null)) {
                throw new Rest_Failure('pc_field_invalid',422,false,$name);
            }
        }
        if (!\in_array($value['document_kind'],self::KINDS,true)) {
            throw new Rest_Failure('pc_field_invalid',422,false,'document_kind');
        }
        try {
            Business_Time::fromApi($value['issued_at']);
            $value['expected_version'] = Domain_Validation::sqlId($value['expected_version']);
            if (isset($value['source_document_id'])) {
                $value['source_document_id'] = Domain_Validation::sqlId($value['source_document_id']);
            }
            foreach (['series'=>30,'number'=>64,'external_reference'=>255,'evidence_ref'=>500] as $field=>$limit) {
                if (!isset($value[$field])) { continue; }
                if (!\is_string($value[$field]) || '' === \trim($value[$field])
                    || \mb_strlen($value[$field],'UTF-8') > $limit
                    || \preg_match('/[\x00-\x1F\x7F]/',$value[$field])) {
                    throw new \InvalidArgumentException();
                }
                $value[$field] = \trim($value[$field]);
            }
            if ((!isset($value['series']) || !isset($value['number']))
                && !isset($value['external_reference'])) {
                throw new \InvalidArgumentException();
            }
            if (isset($value['amount_cents'])) {
                $value['amount_cents'] = Money::toApi(Money::fromApi($value['amount_cents']));
            }
            $key=$value['idempotency_key'];
            if (\strlen($key)<16 || \strlen($key)>128
                || \preg_match('/[\x00-\x20\x7F]/',$key)) {
                throw new \InvalidArgumentException();
            }
        } catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        return $value;
    }

    private static function staffAllowed(?string $role): bool
    {
        return 'A' === $role && 'A' === Access_Roles::currentRole()
            && \current_user_can('perucommerce_settings_manage')
            && \current_user_can('perucommerce_orders_read')
            && (int)\get_current_blog_id()>0
            && !(\function_exists('is_network_admin') && \is_network_admin())
            && Module_Gates::allows('documents','write');
    }

    private static function result(string $operationId, string $recordId): array
    {
        return [
            'operation_id'=>$operationId,
            'record_id'=>$recordId,
            'external_only'=>true,
        ];
    }
}
