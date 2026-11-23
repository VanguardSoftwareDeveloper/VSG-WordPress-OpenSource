<?php
/**
 * IMP-47 — R8-39/40. Bandeja por finalidad y reintento manual de una
 * intención EMAIL EXISTENTE. Ningún GET transmite correos ni cuerpo/PII.
 */
declare(strict_types=1);

namespace PeruCommerce;
if (!\defined('ABSPATH')) { exit; }

final class Notification_Service
{
    /** Solo hechos N6 ya representados por fuentes implementadas. */
    private const OPERATOR_EVENTS = [
        'physical_preparing','physical_ready','physical_dispatched',
        'physical_delivered','service_rescheduled','service_confirmed',
        'service_outcome_recorded','service_cancelled','checkout_cash_confirmed',
        'mp_payment_confirmed','cash_received','line_cancelled',
        'digital_access_enabled','delivery_change_approved',
    ];
    private const RESENDABLE = [
        'physical_preparing','physical_ready','physical_dispatched',
        'physical_delivered','service_rescheduled','service_confirmed',
        'service_cancelled','checkout_cash_confirmed','mp_payment_confirmed','cash_received',
        'line_cancelled','external_refund_verified',
        'logistics_incident_opened','delivery_change_approved',
        'cash_corrected','order_financial_adjusted','digital_access_enabled',
    ];
    private const PURPOSE = 'notification_resend';

    public static function guardList(array $_context, ?string $role): array
    {
        return self::staffReady($role) ? self::proof() : [];
    }

    public static function guardResend(array $ctx, ?string $role): array
    {
        if (!self::staffReady($role) ||
            !\is_string($ctx['path']['id'] ?? null)) {
            return [];
        }
        try {
            $id=Domain_Validation::sqlId($ctx['path']['id']);
            $allowed=Transaction::run(static function(Transaction_Context $tx) use($id,$role):bool {
                $rows=self::findWithin($tx,$id);
                return 1===\count($rows)
                    && self::visibleWithin($tx,$rows[0],$role)
                    && self::resendableWithin($tx,$rows[0],$role);
            });
            if(!$allowed && \is_array($ctx['body']??null)){
                // Un replay auténtico se puede consultar aunque la misma
                // petición ya haya cambiado el estado a retry/accepted.
                $body=self::input($ctx['body']);
                $payload=$body;unset($payload['idempotency_key']);
                $scope=[
                    'actor_kind'=>'staff',
                    'actor_ref'=>(string)\get_current_user_id(),
                    'resource_kind'=>'notification',
                    'resource_id'=>$id,
                    'purpose'=>self::PURPOSE,
                ];
                $saved=Idempotency::lookup($scope,self::PURPOSE,
                    $body['idempotency_key'],$payload);
                $allowed='replay'===($saved['status']??null)
                    && 'notification'===($saved['entity_type']??null)
                    && $id===($saved['entity_id']??null)
                    && 200===($saved['response_code']??null)
                    && Transaction::run(static function(Transaction_Context $tx)
                        use($id,$role):bool {
                        $rows=self::findWithin($tx,$id);
                        return 1===\count($rows)
                            && self::visibleWithin($tx,$rows[0],$role);
                    });
            }
            if(!$allowed){return [];}
            $proof=self::proof();
            if('O'===$role){
                // Access_Control::conditional(R8-40) exige ambas pruebas
                // categóricas, derivadas EXCLUSIVAMENTE del servidor.
                // Ni el ID ni un nonce autorizan un reenvío de otro tipo.
                $proof['notification_category']='operational';
                $proof['recipient_authorized']=Transaction::run(
                    static function(Transaction_Context $tx) use($id,$role):bool {
                        $rows=self::findWithin($tx,$id);
                        return 1===\count($rows)
                            && self::visibleWithin($tx,$rows[0],$role)
                            && null!==Notification_Delivery::validatedRecipientWithin(
                                $tx,$rows[0]
                            );
                    }
                );
            }
            return $proof;
        } catch (\Throwable $ignored) { return []; }
    }

    /** @return array<string,mixed> NotificationPage */
    public static function listing(array $query): array
    {
        self::requireStaff();
        if (!Module_Gates::allows('notifications','read')) {
            throw new Transaction_Failure('pc_notification_not_ready');
        }
        $page=Rest_Pagination::window($query);
        $role=Access_Roles::currentRole();
        return Transaction::run(static function(Transaction_Context $tx) use($page,$role):array{
            $events=self::eventsForRole($role);
            if ([]===$events) {
                return Rest_Pagination::envelope([],$page);
            }
            $holders=\implode(',',\array_fill(0,\count($events),'%s'));
            $rows=$tx->rows(
                'SELECT id,event_code,entity_type,entity_id,audience_code,'
                    .'channel,template_version,destination_ref,safe_payload,'
                    .'technical_phase,attempt_count,last_error_code,'
                    .'row_version,created_at '
                    .'FROM %i WHERE event_code IN ('.$holders.') '
                    .'AND ((channel=%s AND audience_code IN (%s,%s)) '
                    .'OR (channel=%s AND audience_code=%s)) '
                    .'ORDER BY created_at DESC,id DESC LIMIT %d OFFSET %d',
                \array_merge([Queue_Outbox::name('pc_outbox')],$events,
                    ['panel','operator',$role==='A'?'admin':'operator',
                        'email','buyer',$page['fetch'],$page['offset']])
            );
            $items=[];
            foreach($rows as $row){
                if(!self::visibleWithin($tx,$row,$role)) {
                    // No transformar un recurso restringido en
                    // "ítem anónimo" que filtre presencia o cantidad.
                    continue;
                }
                $id=Domain_Validation::sqlId((string)$row['id']);
                $item=[
                    'id'=>$id,
                    'event_code'=>(string)$row['event_code'],
                    'priority'=>self::priority($row),
                    'created_at'=>Business_Time::toApi(
                        Business_Time::fromDatabase((string)$row['created_at'])
                    ),
                    'can_resend'=>self::resendableWithin($tx,$row,$role),
                    'row_version'=>Domain_Validation::sqlId((string)$row['row_version']),
                ];
                $status=self::indicator($row);
                if (null!==$status) {$item['transport_indicator']=$status;}
                $resource=self::resourceWithin($tx,$row,$role);
                if (null!==$resource) {
                    $item['resource_type']=$resource[0];
                    $item['resource_id']=$resource[1];
                }
                $items[]=$item;
            }
            // El query se restringe por evento antes de paginar, y el
            // filtrado de recursos se aplica por cuenta de backend.
            // No hay información de un recurso denegado en el DTO.
            return Rest_Pagination::envelope($items,$page);
        });
    }

    /** @return array<string,string> MutationResult */
    public static function resend(string $id,array $body): array
    {
        self::requireStaff();
        if (!Module_Gates::allows('notifications','write')) {
            throw new Transaction_Failure('pc_notification_not_ready');
        }
        $id=Domain_Validation::sqlId($id);
        $body=self::input($body);
        $role=Access_Roles::currentRole();
        $actor=Event_Actor::staff(['perucommerce_notifications_operate']);
        $scope=[
            'actor_kind'=>'staff','actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'notification','resource_id'=>$id,
            'purpose'=>self::PURPOSE,
        ];
        $payload=$body;unset($payload['idempotency_key']);
        return Transaction::run(static function(Transaction_Context $tx) use(
            $id,$body,$role,$actor,$scope,$payload
        ):array{
            $claim=Idempotency::claimWithin(
                $tx,$scope,self::PURPOSE,$body['idempotency_key'],$payload
            );
            if(!\in_array($claim['status'],['new','replay'],true)){
                throw new Transaction_Failure('pc_notification_conflict');
            }
            $rows=$tx->rows(
                'SELECT id,event_code,entity_type,entity_id,audience_code,'
                    .'channel,template_version,destination_ref,safe_payload,'
                    .'technical_phase,attempt_count,last_error_code,'
                    .'row_version,created_at FROM %i WHERE id=%s FOR UPDATE',
                [Queue_Outbox::name('pc_outbox'),$id]
            );
            if(1!==\count($rows) || !self::visibleWithin($tx,$rows[0],$role)){
                throw new Transaction_Failure('pc_notification_denied');
            }
            $row=$rows[0];
            if('replay'===$claim['status']){
                if('notification'!==($claim['entity_type']??null)
                    || $id!==($claim['entity_id']??null)
                    || 200!==($claim['response_code']??null)){
                    throw new Transaction_Failure('pc_notification_conflict');
                }
                $audit=$tx->rows(
                    'SELECT id FROM %i WHERE entity_type=%s AND entity_id=%s '
                        .'AND operation_code=%s AND actor_kind=%s '
                        .'AND actor_user_id=%s AND request_id=UNHEX(%s) LIMIT 2',
                    [Orders_Domain::table('pc_audit_log'),'notification',$id,
                        'notification_resent','staff',(string)\get_current_user_id(),
                        Event_Journal::correlationHex($claim['operation_id'])]
                );
                if(1!==\count($audit)){
                    throw new Transaction_Failure('pc_notification_conflict');
                }
                return self::result($claim['operation_id'],$id,
                    Settings_Store::nextVersion($body['expected_version']));
            }
            if((string)$row['row_version']!==$body['expected_version']
                || !self::resendableWithin($tx,$row,$role)){
                throw new Transaction_Failure('pc_notification_conflict');
            }
            $next=$tx->execute(
                'UPDATE %i SET technical_phase=%s,attempt_count=0,'
                    .'next_attempt_at=UTC_TIMESTAMP(6),last_error_code=NULL,'
                    .'completed_at=NULL,row_version=row_version+1 '
                    .'WHERE id=%s AND row_version=%s AND technical_phase=%s '
                    .'AND last_error_code=%s AND channel=%s',
                [Queue_Outbox::name('pc_outbox'),'retry',$id,
                    $body['expected_version'],'final_failed',
                    'pc_transport_unavailable','email']
            );
            if(1!==$next){throw new Transaction_Failure('pc_notification_conflict');}
            $version=Settings_Store::nextVersion($body['expected_version']);
            Event_Journal::auditWithin(
                $tx,$actor,$claim['operation_id'],'notification',$id,
                'notification_resent','authorized_retry',
                \hash('sha256',$body['expected_version']),
                \hash('sha256',$version.':'.\hash('sha256',$body['reason']))
            );
            Idempotency::completeLocalWithin(
                $tx,$claim['operation_id'],'notification',$id,200
            );
            return self::result($claim['operation_id'],$id,$version);
        },true);
    }

    private static function findWithin(Transaction_Context $tx,string $id):array
    {
        return $tx->rows(
            'SELECT id,event_code,entity_type,entity_id,audience_code,channel,'
                .'template_version,destination_ref,safe_payload,technical_phase,'
                .'attempt_count,last_error_code,row_version,created_at '
                .'FROM %i WHERE id=%s LIMIT 2',
            [Queue_Outbox::name('pc_outbox'),$id]
        );
    }

    private static function eventsForRole(string $role):array
    {
        $out=\current_user_can('perucommerce_orders_read')
            ?self::OPERATOR_EVENTS:[];
        if('A'===$role && \current_user_can('perucommerce_complaints_manage')){
            $out[]='complaint_received';
            $out[]='complaint_response_prepared';
            $out[]='complaint_response_evidenced';
        }
        if('A'===$role && \current_user_can('perucommerce_privacy_manage')){
            \array_push($out,
                'privacy_request_received','privacy_identity_verified',
                'privacy_evidence_linked','privacy_resolution_prepared',
                'privacy_resolution_evidenced','privacy_export_requested',
                'privacy_export_ready','privacy_export_failed',
                'privacy_export_expired'
            );
        }
        if(!\current_user_can('perucommerce_appointments_manage')){
            $out=\array_values(\array_filter($out,
                static fn(string $code):bool=>!str_starts_with($code,'service_')));
        }
        if(!\current_user_can('perucommerce_orders_fulfill')){
            $out=\array_values(\array_filter($out,
                static fn(string $code):bool=>!str_starts_with($code,'physical_')));
        }
        if('A'===$role) {
            if(\current_user_can('perucommerce_refunds_record_external')){
                $out[]='external_refund_verified';
            }
            if(\current_user_can('perucommerce_orders_financial_adjust')){
                $out[]='order_financial_adjusted';
                $out[]='cash_corrected';
                $out[]='delivery_change_proposed';
            }
            if(\current_user_can('perucommerce_orders_fulfill')){
                $out[]='logistics_incident_opened';
            }
        }
        return $out;
    }

    private static function visibleWithin(
        Transaction_Context $tx,array $row,string $role
    ):bool{
        if(!\in_array((string)$row['event_code'],self::eventsForRole($role),true)){
            return false;
        }
        $requiredType=match((string)$row['event_code']){
            'physical_preparing','physical_ready','physical_dispatched',
            'physical_delivered','line_cancelled'=>'order_line',
            'service_rescheduled','service_confirmed','service_cancelled',
            'service_outcome_recorded'=>'appointment',
            'external_refund_verified'=>'payment_event',
            'logistics_incident_opened'=>'case',
            'complaint_received','complaint_response_prepared',
            'complaint_response_evidenced'=>'complaint',
            'privacy_request_received','privacy_identity_verified',
            'privacy_evidence_linked','privacy_resolution_prepared',
            'privacy_resolution_evidenced','privacy_export_requested',
            'privacy_export_ready','privacy_export_failed',
            'privacy_export_expired'=>'privacy_request',
            default=>'order',
        };
        if((string)$row['entity_type']!==$requiredType){return false;}
        if('panel'===$row['channel']){
            if(!\in_array($row['audience_code'],[
                'operator',...('A'===$role?['admin']:[])
            ],true)) {return false;}
        }elseif('email'===$row['channel']){
            if('buyer'!==$row['audience_code']){return false;}
        }else{return false;}
        // El ID del recurso no concede acceso: pertenencia efectiva a un
        // pedido, servicio o caso siempre se comprueba por SQL local.
        return null!==self::resourceWithin($tx,$row,$role);
    }

    private static function resourceWithin(
        Transaction_Context $tx,array $row,string $role
    ):?array{
        $type=(string)$row['entity_type'];
        $id=Domain_Validation::sqlId((string)$row['entity_id']);
        if('order'===$type){
            if(!\current_user_can('perucommerce_orders_read')){return null;}
            $rows=$tx->rows('SELECT id FROM %i WHERE id=%s LIMIT 2',
                [Orders_Domain::table('pc_orders'),$id]);
            return 1===\count($rows)?['order',$id]:null;
        }
        if('order_line'===$type){
            if(!\current_user_can('perucommerce_orders_read')){return null;}
            $rows=$tx->rows(
                'SELECT l.order_id FROM %i l INNER JOIN %i o ON o.id=l.order_id '
                    .'WHERE l.id=%s LIMIT 2',
                [Orders_Domain::table('pc_order_lines'),
                    Orders_Domain::table('pc_orders'),$id]
            );
            return 1===\count($rows)
                ? ['order',Domain_Validation::sqlId((string)$rows[0]['order_id'])]
                :null;
        }
        if('appointment'===$type){
            if(!\current_user_can('perucommerce_appointments_manage')){
                return null;
            }
            $rows=$tx->rows(
                'SELECT a.id FROM %i a INNER JOIN %i o ON o.id=a.order_id '
                    .'WHERE a.id=%s LIMIT 2',
                [self::table('pc_appointments'),
                    Orders_Domain::table('pc_orders'),$id]
            );
            return 1===\count($rows)?['appointment',$id]:null;
        }
        if('payment_event'===$type && 'A'===$role
            && \current_user_can('perucommerce_refunds_record_external')){
            $rows=$tx->rows(
                'SELECT p.order_id FROM %i p INNER JOIN %i o ON o.id=p.order_id '
                    .'WHERE p.id=%s AND p.kind=%s LIMIT 2',
                [Orders_Domain::table('pc_payment_events'),
                    Orders_Domain::table('pc_orders'),$id,'external_refund']
            );
            return 1===\count($rows)
                ? ['order',Domain_Validation::sqlId((string)$rows[0]['order_id'])]
                :null;
        }
        if('complaint'===$type && 'A'===$role
            && \current_user_can('perucommerce_complaints_manage')){
            $rows=$tx->rows(
                'SELECT id FROM %i WHERE id=%s LIMIT 2',
                [self::table('pc_complaints'),$id]
            );
            // E-08 agrupa expedientes como case: el event_code indica
            // cuál es la ruta/permiso de Libro, nunca pc_cases comercial.
            return 1===\count($rows)?['case',$id]:null;
        }
        if('privacy_request'===$type && 'A'===$role
            && \current_user_can('perucommerce_privacy_manage')){
            return Privacy_Admin::existsWithin($tx,$id)
                ?['case',$id]:null;
        }
        if('case'===$type && 'A'===$role
            && \current_user_can('perucommerce_orders_fulfill')){
            $rows=$tx->rows(
                'SELECT id FROM %i WHERE id=%s AND case_type=%s LIMIT 2',
                [Orders_Domain::table('pc_cases'),$id,'logistics_non_delivery']
            );
            return 1===\count($rows)?['case',$id]:null;
        }
        return null;
    }

    private static function resendableWithin(
        Transaction_Context $tx,array $row,string $role
    ):bool {
        if('email'!==$row['channel'] || 'buyer'!==$row['audience_code']
            || 'v1'!==$row['template_version']
            || !\in_array($row['event_code'],self::RESENDABLE,true)
            || 'final_failed'!==$row['technical_phase']
            || 'pc_transport_unavailable'!==$row['last_error_code']
            || (int)$row['attempt_count']<1
            || !\is_string($row['destination_ref'])) {
            return false;
        }
        if(!self::visibleWithin($tx,$row,$role)) {return false;}
        if(null===Notification_Delivery::validatedRecipientWithin($tx,$row)){
            return false;
        }
        $settings=Settings_Store::readWithin($tx)['settings'];
        return Notification_Delivery::configuredSender($settings)!==null;
    }

    private static function indicator(array $row):?string
    {
        if('panel'===$row['channel']){
            try{
                $snapshot=\json_decode((string)$row['safe_payload'],
                    true,8,\JSON_THROW_ON_ERROR);
                if(\is_array($snapshot)
                    && isset($snapshot['routine_email_decision'])
                    && ($snapshot['routine_email_decision']['enabled']??null)===false){
                    return 'correo_omitido_por_preferencia';
                }
                if(\is_array($snapshot)
                    && ($snapshot['email_target_present']??true)===false){
                    return 'correo_sin_destino';
                }
            }catch(\Throwable $ignored){return 'panel_registrado';}
            return 'panel_registrado';
        }
        return match($row['technical_phase']){
            'accepted'=>'aceptado_por_transporte',
            'pending'=>'pendiente',
            'retry'=>'reintento_programado',
            'processing'=>'en_proceso',
            'final_failed'=>'fallo_final',
            'omitted_no_target'=>'omitido_sin_destino',
            'needs_evidence'=>'requiere_evidencia',
            default=>'estado_no_verificado',
        };
    }

    private static function priority(array $row):string
    {
        return 'final_failed'===$row['technical_phase']
            || \in_array($row['event_code'],[
                'logistics_incident_opened','external_refund_verified',
                'complaint_received','complaint_response_prepared',
                'privacy_request_received','privacy_resolution_prepared',
                'privacy_export_failed','privacy_export_expired'
            ],true) ? 'high' :
            (\in_array($row['event_code'],[
                'service_rescheduled','service_confirmed','service_cancelled',
                'delivery_change_proposed','line_cancelled',
            ],true)?'normal':'low');
    }

    private static function table(string $name):string
    {
        global $wpdb;
        if(!$wpdb instanceof \wpdb){throw new Transaction_Failure('pc_notification_not_ready');}
        require_once PERUCOMMERCE_PLUGIN_DIR.'includes/schema.php';
        return Schema::tableNames($wpdb)[$name];
    }

    private static function input(mixed $raw):array
    {
        if(!\is_array($raw) || \array_is_list($raw)
            || \count($raw)!==3
            || !\array_key_exists('reason',$raw)
            || !\array_key_exists('expected_version',$raw)
            || !\array_key_exists('idempotency_key',$raw)){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        if(!\is_string($raw['reason'])
            || ''===\trim($raw['reason'])
            || !\mb_check_encoding($raw['reason'],'UTF-8')
            || \mb_strlen($raw['reason'],'UTF-8')>1000
            || \preg_match('/[\x00-\x1F\x7F]/u',$raw['reason'])
            || !\is_string($raw['expected_version'])
            || !\is_string($raw['idempotency_key'])
            || \strlen($raw['idempotency_key'])<16
            || \strlen($raw['idempotency_key'])>128
            || \preg_match('/[\x00-\x20\x7F]/',$raw['idempotency_key'])){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        try{Domain_Validation::sqlId($raw['expected_version']);}
        catch(\Throwable $ignored){throw new Rest_Failure('pc_field_invalid',422);}
        return $raw;
    }

    private static function staffReady(?string $role):bool
    {
        return \in_array($role,['A','O'],true)
            && Access_Roles::currentRole()===$role
            && \current_user_can('perucommerce_notifications_operate')
            && (int)\get_current_blog_id()>0
            && !(\function_exists('is_network_admin')&&\is_network_admin());
    }

    private static function requireStaff():void
    {
        if(!self::staffReady(Access_Roles::currentRole())){
            throw new Transaction_Failure('pc_notification_denied');
        }
    }

    private static function proof():array
    {
        return ['authorized'=>true,'same_site'=>true,
            'resource_checked'=>true,'state_checked'=>true,'field_checked'=>true];
    }

    private static function result(string $op,string $id,string $version):array
    {
        return ['operation_id'=>$op,'entity_id'=>$id,'row_version'=>$version,
            'result'=>'notification_requeued','public_status'=>'retry'];
    }
}
