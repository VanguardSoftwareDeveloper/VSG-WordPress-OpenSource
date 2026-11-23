<?php
/**
 * IMP-47 — despacho wp_mail de intenciones EMAIL con verificaciones
 * servidor. Se lee en SQL, se libera la conexión y RECIÉN se llama wp_mail.
 * La respuesta TRUE es accepted_by_transport, nunca entrega recibida.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Notification_Delivery
{
    private static bool $registered=false;
    private const PHYSICAL=[
        'physical_preparing'=>'physical_preparing',
        'physical_ready'=>'physical_ready',
        'physical_dispatched'=>'physical_dispatched',
        'physical_delivered'=>'physical_delivered',
    ];
    private const SOURCES=[
        'checkout_cash_confirmed'=>['order','checkout_cash_confirmed'],
        'mp_payment_confirmed'=>['order','mp_initial_credit_verified'],
        'cash_received'=>['order','cash_receipt_recorded'],
        'line_cancelled'=>['order_line','line_cancelled'],
        'external_refund_verified'=>['payment_event','external_refund_recorded'],
        'logistics_incident_opened'=>['case','logistics_case_opened'],
        'delivery_change_approved'=>['order','delivery_change_approved'],
        'cash_corrected'=>['order','cash_correction_recorded'],
        'order_financial_adjusted'=>['order','order_financial_adjusted'],
        'digital_access_enabled'=>['order','mp_initial_credit_verified'],
    ];

    public static function register():void
    {
        if(self::$registered){return;}
        // No registrar handler si falta evidencia P-C: evitar que el
        // worker consuma 5 intentos sin transporte operativo.
        if(!Module_Gates::allows('notifications','write')){return;}
        self::$registered=true;
        Queue_Worker::registerEmailHandler([self::class,'process']);
    }

    /** La presencia de handler no abre el transporte si falta P-C. */
    public static function process(array $job):array
    {
        Transaction::assertOutside();
        if(!Module_Gates::allows('notifications','write')){
            return ['result'=>'retry','error_code'=>'pc_transport_unavailable'];
        }
        try{
            // En WP-Cron el callback no necesita que se haya visitado REST.
            // Cargar las dependencias ANTES del callback SQL.
            Bootstrap::loadNotifications();
            $state=Transaction::run(static function(Transaction_Context $tx) use($job):array {
                return self::prepareWithin($tx,$job);
            });
            if(null!==($state['error']??null)){
                return $state['error']==='transport'
                    ? ['result'=>'retry','error_code'=>'pc_transport_unavailable']
                    : ['result'=>'final_failed','error_code'=>'pc_manual_review_required'];
            }
            $copy=Notification_Templates::render($job['event_code'],$state);
            $headers=[
                'Content-Type: text/plain; charset=UTF-8',
                'From: '.$state['from_name'].' <'.$state['from_email'].'>',
            ];
            Transaction::assertOutside();
            $accepted=\wp_mail($state['recipient'],$copy['subject'],$copy['body'],$headers);
            return true===$accepted
                ? ['result'=>'accepted','error_code'=>null]
                : ['result'=>'retry','error_code'=>'pc_transport_unavailable'];
        }catch(\Throwable $ignored){
            // No filtramos cuerpos, correos, configuración ni errores de WP.
            return ['result'=>'final_failed','error_code'=>'pc_manual_review_required'];
        }
    }

    /** @return array<string,mixed> */
    private static function prepareWithin(
        Transaction_Context $tx,array $job
    ):array {
        $id=Domain_Validation::sqlId((string)($job['outbox_id']??''));
        $rows=$tx->rows(
            'SELECT id,event_code,entity_type,entity_id,audience_code,'
                .'channel,template_version,destination_ref,safe_payload,'
                .'technical_phase FROM %i WHERE id=%s LIMIT 2',
            [Queue_Outbox::name('pc_outbox'),$id]
        );
        if(1!==\count($rows)){return ['error'=>'manual'];}
        $row=$rows[0];
        foreach([
            'event_code'=>'event_code','entity_type'=>'entity_type',
            'entity_id'=>'entity_id','audience_code'=>'audience',
            'channel'=>'channel','template_version'=>'template_version',
            'destination_ref'=>'destination_ref','safe_payload'=>'safe_payload',
        ] as $stored=>$fromJob){
            if((string)($row[$stored]??'')!==(string)($job[$fromJob]??'')){
                return ['error'=>'manual'];
            }
        }
        if('processing'!==$row['technical_phase']
            || 'email'!==$row['channel']
            || 'buyer'!==$row['audience_code']
            || 'v1'!==$row['template_version']){
            return ['error'=>'manual'];
        }
        $recipient=self::validatedRecipientWithin($tx,$row);
        if(null===$recipient){return ['error'=>'manual'];}
        $settings=Settings_Store::readWithin($tx)['settings'];
        $sender=self::configuredSender($settings);
        if(null===$sender){return ['error'=>'transport'];}
        $context=['recipient'=>$recipient,
            'from_email'=>$sender['email'],'from_name'=>$sender['name'],
            'merchant'=>$settings['business']['business_name']??'El comercio',
            'signature'=>$sender['signature']];
        $validated=self::sourceWithin($tx,$row);
        if(null===$validated){return ['error'=>'manual'];}
        return \array_merge($context,$validated);
    }

    /** No se almacena destinatario completo en pc_outbox. */
    public static function validatedRecipientWithin(
        Transaction_Context $tx,array $row
    ):?string {
        if('complaint'===($row['entity_type']??null)){
            if('email'!==($row['channel']??null)
                || 'buyer'!==($row['audience_code']??null)
                || !\is_string($row['destination_ref']??null)
                || !\preg_match('/\Acomplaint:([1-9][0-9]*)\z/D',
                    $row['destination_ref'],$legal)
                || (string)($row['entity_id']??'')!==$legal[1]){
                return null;
            }
            $legalId=Domain_Validation::sqlId($legal[1]);
            $record=$tx->rows(
                'SELECT complainant_snapshot FROM %i WHERE id=%s LIMIT 2',
                [self::table('pc_complaints'),$legalId]
            );
            if(1!==\count($record)
                || !\is_string($record[0]['complainant_snapshot'])){
                return null;
            }
            try{
                $parties=\json_decode((string)$record[0]['complainant_snapshot'],
                    true,12,\JSON_THROW_ON_ERROR);
                $email=$parties['claimant']['email']??null;
                return \is_string($email) ? Domain_Validation::email($email):null;
            }catch(\Throwable $ignored){return null;}
        }
        if('email'!==($row['channel']??null)
            || 'buyer'!==($row['audience_code']??null)
            || !\is_string($row['destination_ref']??null)
            || !\preg_match('/\Aorder:([1-9][0-9]*)\z/D',
                $row['destination_ref'],$m)){
            return null;
        }
        try{$orderId=Domain_Validation::sqlId($m[1]);}
        catch(\Throwable $ignored){return null;}
        $id=(string)($row['entity_id']??'');
        try{$id=Domain_Validation::sqlId($id);}
        catch(\Throwable $ignored){return null;}
        $type=(string)$row['entity_type'];
        $match=null;
        if('order'===$type){
            $match=$id;
        }elseif('order_line'===$type){
            $found=$tx->rows(
                'SELECT order_id FROM %i WHERE id=%s LIMIT 2',
                [Orders_Domain::table('pc_order_lines'),$id]
            );
            if(1===\count($found)){$match=(string)$found[0]['order_id'];}
        }elseif('appointment'===$type){
            $found=$tx->rows(
                'SELECT order_id FROM %i WHERE id=%s LIMIT 2',
                [self::table('pc_appointments'),$id]
            );
            if(1===\count($found)){$match=(string)$found[0]['order_id'];}
        }elseif('payment_event'===$type){
            $found=$tx->rows(
                'SELECT order_id FROM %i WHERE id=%s AND kind=%s LIMIT 2',
                [Orders_Domain::table('pc_payment_events'),
                    $id,'external_refund']
            );
            if(1===\count($found)){$match=(string)$found[0]['order_id'];}
        }elseif('case'===$type){
            $found=$tx->rows(
                'SELECT order_id FROM %i WHERE id=%s AND case_type=%s LIMIT 2',
                [Orders_Domain::table('pc_cases'),$id,'logistics_non_delivery']
            );
            if(1===\count($found)){$match=(string)$found[0]['order_id'];}
        }
        if($match!==$orderId){return null;}
        $emails=$tx->rows('SELECT buyer_email FROM %i WHERE id=%s LIMIT 2',
            [Orders_Domain::table('pc_orders'),$orderId]);
        if(1!==\count($emails) || !\is_string($emails[0]['buyer_email'])){
            return null;
        }
        try{return Domain_Validation::email($emails[0]['buyer_email']);}
        catch(\Throwable $ignored){return null;}
    }

    /** Valida remitente existente; nunca usar un dato de body/cliente. */
    public static function configuredSender(array $settings):?array
    {
        $sender=$settings['mail_sender']??null;
        if(!\is_array($sender)
            || !\is_string($sender['from_email']??null)){
            return null;
        }
        try{$email=Domain_Validation::email($sender['from_email']);}
        catch(\Throwable $ignored){return null;}
        $name=$sender['from_name']??'PeruCommerce';
        if(!\is_string($name) || ''===\trim($name)
            || \mb_strlen($name,'UTF-8')>120
            || \preg_match('/[\x00-\x1F\x7F<>]/u',$name)){
            return null;
        }
        $signature=$sender['signature']??'';
        if(!\is_string($signature) || \mb_strlen($signature,'UTF-8')>500
            || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$signature)){
            return null;
        }
        return ['email'=>$email,'name'=>$name,'signature'=>$signature];
    }

    /**
     * Releer fuente REAL antes de cada intento. Para un correo físico
     * rutinario se respeta la decisión congelada, nunca settings actuales.
     */
    private static function sourceWithin(
        Transaction_Context $tx,array $row
    ):?array{
        $code=(string)$row['event_code'];
        $type=(string)$row['entity_type'];
        $id=Domain_Validation::sqlId((string)$row['entity_id']);
        if('complaint_received'===$code && 'complaint'===$type){
            // Fuente legal propia, separada del pc_cases comercial.
            $original=$tx->rows(
                'SELECT id FROM %i WHERE id=%s LIMIT 2',
                [self::table('pc_complaints'),$id]
            );
            return 1===\count($original)
                && self::auditWithin($tx,'complaint',$id,'complaint_submitted')
                ? []:null;
        }
        if(isset(self::PHYSICAL[$code])){
            if('order_line'!==$type
                || !self::auditWithin($tx,$type,$id,$code)){
                return null;
            }
            try{$snap=\json_decode((string)$row['safe_payload'],
                true,8,\JSON_THROW_ON_ERROR);}
            catch(\Throwable $ignored){return null;}
            if(!\is_array($snap)
                || ($snap['routine_email_decision']['enabled']??null)!==true
                || !\is_string($snap['routine_email_decision']['settings_row_version']??null)
                || !\is_string($snap['physical_state']??null)
                || ($snap['physical_state']??'')!==(string)\array_search($code,
                    ['PREPARANDO'=>'physical_preparing',
                    'LISTO'=>'physical_ready','DESPACHADO'=>'physical_dispatched',
                    'ENTREGADO'=>'physical_delivered'],true)){
                return null;
            }
            try{Domain_Validation::sqlId($snap['routine_email_decision']['settings_row_version']);}
            catch(\Throwable $ignored){return null;}
            return [];
        }
        if('service_rescheduled'===$code && 'appointment'===$type){
            $rows=$tx->rows(
                'SELECT h.previous_start_utc,h.new_start_utc,h.new_end_utc,'
                    .'a.starts_at_utc,a.ends_at_utc,a.local_timezone '
                    .'FROM %i h INNER JOIN %i a ON a.id=h.appointment_id '
                    .'WHERE h.communication_outbox_id=%s AND h.appointment_id=%s '
                    .'LIMIT 2',
                [self::table('pc_appointment_history'),
                    self::table('pc_appointments'),
                    (string)$row['id'],$id]
            );
            if(1!==\count($rows)
                || (string)$rows[0]['starts_at_utc']!==(string)$rows[0]['new_start_utc']
                || (string)$rows[0]['ends_at_utc']!==(string)$rows[0]['new_end_utc']
                || !self::auditWithin($tx,'appointment',$id,'appointment_rescheduled')){
                // Horario sustituido: no notificar una franja obsoleta.
                return null;
            }
            try{
                $zone=Business_Time::businessZone((string)$rows[0]['local_timezone']);
                return [
                    'before'=>Business_Time::fromDatabase(
                        (string)$rows[0]['previous_start_utc']
                    )->setTimezone($zone)->format('d/m/Y H:i'),
                    'after'=>Business_Time::fromDatabase(
                        (string)$rows[0]['new_start_utc']
                    )->setTimezone($zone)->format('d/m/Y H:i'),
                ];
            }catch(\Throwable $ignored){return null;}
        }
        if('service_cancelled'===$code && 'appointment'===$type){
            $rows=$tx->rows(
                'SELECT a.id FROM %i a INNER JOIN %i l '
                    .'ON l.id=a.order_line_id AND l.order_id=a.order_id '
                    .'WHERE a.id=%s AND l.fulfillment_state=%s '
                    .'AND a.cancelled_at IS NOT NULL LIMIT 2',
                [self::table('pc_appointments'),
                    Orders_Domain::table('pc_order_lines'),$id,'CANCELADA']
            );
            return 1===\count($rows)
                && self::auditWithin($tx,'appointment',$id,'appointment_cancelled')
                ? []:null;
        }
        if('service_confirmed'===$code && 'appointment'===$type){
            $appointments=$tx->rows(
                'SELECT a.starts_at_utc,a.local_timezone,a.cancelled_at,'
                    .'l.fulfillment_state,o.payment_method '
                    .'FROM %i a INNER JOIN %i l ON l.id=a.order_line_id '
                    .'AND l.order_id=a.order_id '
                    .'INNER JOIN %i o ON o.id=a.order_id '
                    .'WHERE a.id=%s LIMIT 2',
                [self::table('pc_appointments'),
                    Orders_Domain::table('pc_order_lines'),
                    Orders_Domain::table('pc_orders'),$id]
            );
            if(1!==\count($appointments)
                || null!==$appointments[0]['cancelled_at']
                || !\in_array($appointments[0]['fulfillment_state'],
                    ['CONFIRMADA','REALIZADA','NO_ASISTIO'],true)){
                return null;
            }
            try{
                $zone=Business_Time::businessZone(
                    (string)$appointments[0]['local_timezone']
                );
                return ['at'=>Business_Time::fromDatabase(
                    (string)$appointments[0]['starts_at_utc']
                )->setTimezone($zone)->format('d/m/Y H:i')];
            }catch(\Throwable $ignored){return null;}
        }
        if('digital_access_enabled'===$code && 'order'===$type){
            $rights=$tx->rows(
                'SELECT r.id FROM %i r INNER JOIN %i l '
                    .'ON l.id=r.order_line_id '
                    .'WHERE l.order_id=%s AND l.kind=%s '
                    .'AND l.fulfillment_state=%s AND r.revoked_at IS NULL '
                    .'LIMIT 1',
                [self::table('pc_download_rights'),
                    Orders_Domain::table('pc_order_lines'),
                    $id,'digital','HABILITADO']
            );
            if([]===$rights){return null;}
        }
        if(isset(self::SOURCES[$code])
            && self::SOURCES[$code][0]===$type
            && self::auditWithin($tx,$type,$id,self::SOURCES[$code][1])){
            if('external_refund_verified'===$code){
                $fact=$tx->rows(
                    'SELECT amount_cents FROM %i WHERE id=%s AND kind=%s LIMIT 2',
                    [Orders_Domain::table('pc_payment_events'),
                        $id,'external_refund']
                );
                if(1!==\count($fact)){return null;}
                try{
                    $v=Money::fromSignedLedger((string)$fact[0]['amount_cents']);
                    return $v<0 && $v!==\PHP_INT_MIN
                        ? ['amount'=>Money::toProviderDecimal(-$v)] :null;
                }catch(\Throwable $ignored){return null;}
            }
            return [];
        }
        return null;
    }

    private static function auditWithin(
        Transaction_Context $tx,string $type,string $id,string $code
    ):bool{
        $rows=$tx->rows(
            'SELECT id FROM %i WHERE entity_type=%s AND entity_id=%s '
                .'AND operation_code=%s LIMIT 2',
            [Orders_Domain::table('pc_audit_log'),$type,$id,$code]
        );
        return 1===\count($rows);
    }

    private static function table(string $name):string
    {
        global $wpdb;
        if(!$wpdb instanceof \wpdb){
            throw new Transaction_Failure('pc_notification_not_ready');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR.'includes/schema.php';
        return Schema::tableNames($wpdb)[$name];
    }
}
