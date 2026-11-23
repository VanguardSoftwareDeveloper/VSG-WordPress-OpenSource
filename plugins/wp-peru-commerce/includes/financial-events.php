<?php
/**
 * IMP-09 — libro financiero append-only (sin refund API, sin acreditar por
 * callback del navegador ni cuerpo de webhook).
 *
 * El origen/verificación autoritativa se obtiene FUERA de SQL por futuros
 * módulos MP/efectivo. Sin verificador instalado este escritor DENIEGA.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Financial_Events
{
    /** @var callable|null Validación de evidencia obtenida antes de abrir SQL. */
    private static $evidenceVerifier = null;
    /** @var callable|null Prueba separada de efectivo por operador/staff. */
    private static $cashEvidenceVerifier = null;
    /**
     * R8-27: verificador exclusivo de resultado externo documentado por A.
     * No altera el verificador de acreditación MP ni el de caja.
     * La verificación presencial/documental sigue siendo responsabilidad
     * del administrador: no equivale a confirmación API automatizada.
     * @var callable|null
     */
    private static $refundEvidenceVerifier = null;

    /**
     * Registrar proveedor auténtico que compara origen, cuenta, entorno,
     * pedido, monto, moneda, fuente y privilegios, sin realizar HTTP/SMTP
     * en el callback transaccional. No puede registrarse dos veces.
     */
    public static function registerEvidenceVerifier(callable $verifier): void
    {
        if (null !== self::$evidenceVerifier) {
            throw new \LogicException('pc_financial_verifier_duplicate');
        }
        self::$evidenceVerifier = $verifier;
    }

    /**
     * Verificador exclusivamente para efectivo declarado por staff en
     * R8-24/25. No reemplaza ni amplía el verificador del worker MP.
     * El consumidor debe haber revalidado pedido, saldo y root bajo locks.
     */
    public static function registerCashEvidenceVerifier(callable $verifier): void
    {
        if (null !== self::$cashEvidenceVerifier) {
            throw new \LogicException('pc_financial_cash_verifier_duplicate');
        }
        self::$cashEvidenceVerifier = $verifier;
    }

    /**
     * R8-27: autorizar una escritura externa solo con la prueba de negocio
     * previamente cotejada por el servicio A dentro del mismo root SQL.
     * Sin registro de este callback, el ledger sigue fail-closed.
     */
    public static function registerRefundEvidenceVerifier(callable $verifier): void
    {
        if (null !== self::$refundEvidenceVerifier) {
            throw new \LogicException('pc_financial_refund_verifier_duplicate');
        }
        self::$refundEvidenceVerifier = $verifier;
    }

    /**
     * Registra UN movimiento técnico inmutable con la evidencia de origen
     * aplicable: acreditación MP autoritativa, caja verificada por staff o
     * devolución externa constatada documentalmente por A. Este callback
     * no comprueba la autenticidad bancaria de una constancia manual.
     * No altera saldo materializado ni estado de pedido.
     * Los consumidores deben acreditar/cambiar efectos y escribir evento +
     * auditoría en la MISMA transacción (IMP-17/27+/37).
     *
     * $fact es construido por código servidor autenticado, NUNCA el JSON
     * entrante del comprador/webhook directamente.
     *
     * @param array{
     *    kind:string,amount_cents:string,currency:string,provider:string,
     *    account_digest:?string,environment:?string,external_reference:string,
     *    verification_reference:string,occurred_at_utc:string,
     *    payment_attempt_id:?string,source_payment_event_id:?string
     * } $fact
     */
    public static function appendWithin(
        Transaction_Context $tx,
        Event_Actor $actor,
        string $operationId,
        string $orderId,
        array $fact,
        ?string $correctionSnapshot = null,
        ?string $refundSnapshot = null
    ): string {
        $operationId = Event_Journal::requirePendingOperation($tx, $operationId);
        $orderId = Domain_Validation::sqlId($orderId);
        $tx->lockedOrder($orderId);
        $actor->assertCurrent();

        $expected = ['kind','amount_cents','currency','provider','account_digest',
            'environment','external_reference','verification_reference','occurred_at_utc',
            'payment_attempt_id','source_payment_event_id'];
        $keys = \array_keys($fact);
        \sort($keys, \SORT_STRING);
        \sort($expected, \SORT_STRING);
        if ($keys !== $expected) {
            throw new Transaction_Failure('pc_financial_fact_invalid');
        }

        if (!\is_string($fact['kind']) || !\is_string($fact['amount_cents'])
            || !\is_string($fact['currency']) || !\is_string($fact['provider'])
            || !\is_string($fact['external_reference'])
            || !\is_string($fact['verification_reference'])
            || !\is_string($fact['occurred_at_utc'])) {
            throw new Transaction_Failure('pc_financial_fact_invalid');
        }
        Event_Journal::code($fact['kind'], 32);
        if ('PEN' !== $fact['currency']
            || !\in_array($fact['provider'], ['mercado_pago', 'cash'], true)
            || !\preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/D', $fact['external_reference'])
            || !\preg_match('/\A[A-Za-z0-9._:-]{8,200}\z/D', $fact['verification_reference'])) {
            throw new Transaction_Failure('pc_financial_fact_invalid');
        }
        $amount = Money::fromSignedLedger($fact['amount_cents']);
        if (0 === $amount) {
            throw new Transaction_Failure('pc_financial_fact_invalid');
        }
        $occurredAt = Business_Time::toDatabase(
            Business_Time::fromApi($fact['occurred_at_utc'])
        );

        if ('mercado_pago' === $fact['provider']) {
            if (!\is_string($fact['account_digest'])
                || !\preg_match('/\A[a-f0-9]{64}\z/D', $fact['account_digest'])
                || !\in_array($fact['environment'], ['sandbox', 'production'], true)
                || !\is_string($fact['payment_attempt_id'])) {
                throw new Transaction_Failure('pc_financial_fact_invalid');
            }
        } elseif (null !== $fact['account_digest'] || null !== $fact['environment']
            || null !== $fact['payment_attempt_id'] || 'staff' !== $actor->kind()) {
            // Efectivo neto registrado por persona WP comprobada (D-105).
            throw new Transaction_Failure('pc_financial_fact_invalid');
        }

        foreach (['payment_attempt_id','source_payment_event_id'] as $field) {
            if (null !== $fact[$field]) {
                if (!\is_string($fact[$field])) {
                    throw new Transaction_Failure('pc_financial_fact_invalid');
                }
                Domain_Validation::sqlId($fact[$field]);
            }
        }

        // R8-25 conserva motivo/referencia completos en un nuevo campo
        // privado del MISMO asiento. MP y otros eventos no pueden llenarlo.
        if (null !== $correctionSnapshot) {
            if ('cash' !== $fact['provider']
                || 'cash_correction' !== $fact['kind']
                || null === $fact['source_payment_event_id']
                || '' === $correctionSnapshot
                || \strlen($correctionSnapshot) > 4096
                || !\mb_check_encoding($correctionSnapshot,'UTF-8')) {
                throw new Transaction_Failure('pc_financial_fact_invalid');
            }
        } elseif ('cash_correction' === $fact['kind']) {
            throw new Transaction_Failure('pc_financial_fact_invalid');
        }
        // La recepción de dinero, su corrección y una devolución
        // externamente registrada son orígenes AUTÓNOMOS; ningún hook
        // de acreditación puede autorizar una devolución ni al revés.
        if ('external_refund' === $fact['kind']) {
            if (null === $refundSnapshot
                || null !== $correctionSnapshot
                || !\is_string($refundSnapshot)
                || '' === $refundSnapshot
                || \strlen($refundSnapshot) > 4096
                || !\mb_check_encoding($refundSnapshot,'UTF-8')
                || null === $fact['source_payment_event_id']
                || $amount >= 0
                || 'staff' !== $actor->kind()
                || 'A' !== Access_Roles::currentRole()
                || !\current_user_can('perucommerce_refunds_record_external')) {
                throw new Transaction_Failure('pc_financial_fact_invalid');
            }
            $verifier=self::$refundEvidenceVerifier;
        } else {
            if (null!==$refundSnapshot) {
                throw new Transaction_Failure('pc_financial_fact_invalid');
            }
            $verifier = 'cash' === $fact['provider']
                ? self::$cashEvidenceVerifier : self::$evidenceVerifier;
        }
        if (null === $verifier) {
            throw new Transaction_Failure('pc_financial_evidence_missing');
        }
        try {
            // El verificador legítimo se alimenta de evidencia servidor previa
            // y no hace HTTP en este callback. Debe validar histórico acumulado,
            // importe fuente, control del actor y pago realmente comprobado.
            if (true !== ('external_refund' === $fact['kind']
                ? $verifier($fact,$orderId,$actor,$refundSnapshot)
                : $verifier($fact,$orderId,$actor))) {
                throw new Transaction_Failure('pc_financial_evidence_missing');
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_financial_evidence_missing');
        }

        $dedup = \hash('sha256', \implode("\0", [
            'pc-financial-v1', $fact['provider'],
            $fact['account_digest'] ?? '',
            $fact['environment'] ?? '',
            $fact['kind'], $fact['external_reference'],
            // Un identificador de resultado externo no puede registrarse
            // de nuevo bajo OTRA fuente/cobro del mismo proveedor/cuenta.
            // DB UQ(event_dedup_hash) resuelve también carreras entre
            // diferentes pedidos; el root del usuario solo protege replay.
            'external_refund'===$fact['kind']
                ? '' : ($fact['source_payment_event_id'] ?? ''),
            // Cash original/corrección sigue deduplicando por root;
            // un refund no: su ID externo es la única fuente de verdad.
            'cash' === $fact['provider']
                && 'external_refund'!==$fact['kind'] ? $operationId : '',
        ]));

        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_financial_context_missing');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        $table = Schema::tableNames($wpdb)['pc_payment_events'];

        // Todas las columnas variables están fijadas aquí, no en entrada REST.
        $columns = ['order_id','kind','amount_cents','provider','external_reference',
            'verification_reference','event_dedup_hash','occurred_at','recorded_at'];
        $holders = ['%s','%s','%s','%s','%s','%s','UNHEX(%s)','%s','UTC_TIMESTAMP(6)'];
        $args = [$orderId,$fact['kind'],(string)$amount,$fact['provider'],
            $fact['external_reference'],$fact['verification_reference'],$dedup,$occurredAt];
        if (null !== $fact['payment_attempt_id']) {
            $columns[]='payment_attempt_id';
            $holders[]='%s';
            $args[]=$fact['payment_attempt_id'];
        }
        if (null !== $fact['source_payment_event_id']) {
            $columns[]='source_payment_event_id';
            $holders[]='%s';
            $args[]=$fact['source_payment_event_id'];
        }
        if (null !== $fact['account_digest']) {
            $columns[]='provider_account_key';
            $holders[]='UNHEX(%s)';
            $args[]=$fact['account_digest'];
        }
        if (null !== $fact['environment']) {
            $columns[]='environment';
            $holders[]='%s';
            $args[]=$fact['environment'];
        }
        if (null !== $actor->userId()) {
            $columns[]='actor_user_id';
            $holders[]='%s';
            $args[]=$actor->userId();
        }
        if (null !== $correctionSnapshot) {
            $columns[]='correction_evidence_snapshot';
            $holders[]='%s';
            $args[]=$correctionSnapshot;
        }
        if (null !== $refundSnapshot) {
            $columns[]='refund_evidence_snapshot';
            $holders[]='%s';
            $args[]=$refundSnapshot;
        }

        $query='INSERT INTO %i (' . \implode(', ', $columns) . ') VALUES ('
            . \implode(', ', $holders) . ')';
        if (1 !== $tx->execute($query, \array_merge([$table], $args))) {
            throw new Transaction_Failure('pc_financial_append_failed');
        }
        $id = $tx->rows('SELECT LAST_INSERT_ID() AS event_id');
        if (1 !== \count($id) || (!\is_string($id[0]['event_id']) && !\is_int($id[0]['event_id']))) {
            throw new Transaction_Failure('pc_financial_append_failed');
        }
        $eventId=Domain_Validation::sqlId((string)$id[0]['event_id']);
        Event_Journal::auditWithin($tx,$actor,$operationId,'payment_event',
            $eventId,'financial_event_recorded');
        return $eventId;
    }
}
