<?php
/**
 * IMP-10 — intenciones de notificación duraderas en pc_outbox.
 *
 * Nada se envía dentro de la transacción del hecho. La fuente de verdad,
 * permisos y destinatario se revalidan al despachar; no guardar emails,
 * teléfonos, tokens, URLs privadas o cuerpos de mensajes en esta tabla.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Queue_Outbox
{
    private const ROUTINE_PHYSICAL = [
        'PREPARANDO' => 'physical_preparing',
        'LISTO' => 'physical_ready',
        'DESPACHADO' => 'physical_dispatched',
        'ENTREGADO' => 'physical_delivered',
    ];

    /** @var callable|null Validación de que existe el hecho local autorizado. */
    private static $sourceVerifier = null;

    /** @var callable|null Lectura CAS/versionada de settings D-200/ DOC-QA29. */
    private static $routinePolicyReader = null;

    /**
     * El proveedor futuro verifica estado/actor/recurso y existencia del
     * evento en esta misma conexión SQL, antes de registrar intención.
     */
    public static function registerSourceVerifier(callable $verifier): void
    {
        if (null !== self::$sourceVerifier) {
            throw new \LogicException('pc_outbox_source_verifier_duplicate');
        }
        self::$sourceVerifier = $verifier;
    }

    /**
     * IMP-13 registrará una lectura efectiva de la opción versionada con CAS.
     * La ausencia de settings no equivale a "emails habilitados".
     */
    public static function registerRoutinePolicyReader(callable $reader): void
    {
        if (null !== self::$routinePolicyReader) {
            throw new \LogicException('pc_outbox_policy_reader_duplicate');
        }
        self::$routinePolicyReader = $reader;
    }

    /**
     * Registra una intención para un hecho validado por su módulo de negocio.
     * Nunca infiere pago, entrega, reembolso ni plazo legal desde eventCode.
     *
     * @param array<string, mixed> $safePayload Contrato de campos mínimos.
     * @return array{id:string,status:string}
     */
    public static function enqueueWithin(
        Transaction_Context $tx,
        string $operationId,
        string $eventCode,
        string $entityType,
        string $entityId,
        string $audience,
        string $channel,
        string $templateVersion,
        ?string $destinationRef = null,
        array $safePayload = []
    ): array {
        Event_Journal::requirePendingOperation($tx, $operationId);
        if (null === self::$sourceVerifier) {
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        try {
            if (true !== (self::$sourceVerifier)(
                $tx, $operationId, $eventCode, $entityType, $entityId, $audience
            )) {
                throw new Transaction_Failure('pc_outbox_source_unverified');
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }

        return self::insertWithin(
            $tx, $operationId, $eventCode, $entityType, $entityId,
            $audience, $channel, $templateVersion, $destinationRef, $safePayload
        );
    }

    /**
     * Congele la preferencia UNA SOLA VEZ para este hecho físico. Aun si
     * routine_buyer_emails_enabled=false se crea PANEL con la decisión
     * versionada; EMAIL solo si la preferencia lo permite Y existe email.
     * El replay reutiliza el PANEL anterior, sin leer settings actuales.
     *
     * El llamante debe haber bloqueado operación -> pedido -> línea,
     * persistido estado, evento y auditoría, y completar idempotencia al final.
     *
     * @return array{panel_id:string,email_id:?string,created_email:bool}
     */
    public static function routinePhysicalWithin(
        Transaction_Context $tx,
        string $operationId,
        string $orderId,
        string $lineId,
        string $newState
    ): array {
        Event_Journal::requirePendingOperation($tx, $operationId);
        $orderId = Domain_Validation::sqlId($orderId);
        $lineId = Domain_Validation::sqlId($lineId);
        if (!isset(self::ROUTINE_PHYSICAL[$newState])) {
            throw new Transaction_Failure('pc_outbox_routine_state_invalid');
        }

        $tx->lockedOrder($orderId);
        $lockedLine = $tx->lockedOrderLine($lineId);
        if ((string) ($lockedLine['order_id'] ?? '') !== $orderId
            || 'physical' !== ($lockedLine['kind'] ?? null)) {
            throw new Transaction_Failure('pc_outbox_routine_scope_invalid');
        }
        $rows = $tx->rows(
            'SELECT kind, fulfillment_state, order_id FROM %i WHERE id = %s',
            [self::name('pc_order_lines'), $lineId]
        );
        if (1 !== \count($rows)
            || $rows[0]['kind'] !== 'physical'
            || (string) $rows[0]['order_id'] !== $orderId
            || (string) $rows[0]['fulfillment_state'] !== $newState) {
            throw new Transaction_Failure('pc_outbox_routine_state_invalid');
        }

        $code = self::ROUTINE_PHYSICAL[$newState];
        $panelHash = self::dedup(
            $operationId, $code, 'order_line', $lineId, 'operator', 'panel', 'v1', null
        );
        $existing = self::find($tx, $panelHash);
        if ([] !== $existing) {
            // Un evento ya registrado mantiene la decisión original aunque
            // otro administrador haya cambiado la preferencia desde entonces.
            $panel = $existing[0];
            $payload = self::decodePayload((string) $panel['safe_payload']);
            if ((string) $panel['event_code'] !== $code
                || (string) $panel['entity_type'] !== 'order_line'
                || (string) $panel['entity_id'] !== $lineId
                || (string) $panel['audience_code'] !== 'operator'
                || (string) $panel['channel'] !== 'panel'
                || !isset($payload['routine_email_decision'])
                || !\is_array($payload['routine_email_decision'])) {
                throw new Transaction_Failure('pc_outbox_dedup_conflict');
            }
            $mailHash = self::dedup(
                $operationId, $code, 'order_line', $lineId, 'buyer', 'email',
                'v1', 'order:' . $orderId
            );
            $mail = self::find($tx, $mailHash);
            return [
                'panel_id' => Domain_Validation::sqlId((string) $panel['id']),
                'email_id' => [] === $mail ? null : Domain_Validation::sqlId((string) $mail[0]['id']),
                'created_email' => [] !== $mail,
            ];
        }

        if (null === self::$routinePolicyReader) {
            throw new Transaction_Failure('pc_outbox_policy_unverified');
        }
        try {
            $decision = (self::$routinePolicyReader)($tx, $orderId, $lineId);
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_outbox_policy_unverified');
        }
        if (!\is_array($decision)
            || !\is_bool($decision['enabled'] ?? null)
            || !\is_string($decision['settings_row_version'] ?? null)) {
            throw new Transaction_Failure('pc_outbox_policy_unverified');
        }
        Domain_Validation::sqlId($decision['settings_row_version']);

        $contact = $tx->rows(
            'SELECT buyer_email FROM %i WHERE id = %s',
            [self::name('pc_orders'), $orderId]
        );
        if (1 !== \count($contact)) {
            throw new Transaction_Failure('pc_outbox_routine_scope_invalid');
        }
        $address = $contact[0]['buyer_email'];
        $hasEmail = false;
        if (\is_string($address) && '' !== $address) {
            try {
                Domain_Validation::email($address);
                $hasEmail = true;
            } catch (\InvalidArgumentException $ignored) {
                // Un correo opcional inválido NO bloquea el hito físico ni
                // la intención PANEL. Solo evita crear el destino EMAIL.
            }
        }

        $payload = [
            'physical_state' => $newState,
            'routine_email_decision' => [
                'enabled' => $decision['enabled'],
                'settings_row_version' => $decision['settings_row_version'],
            ],
            'email_target_present' => $hasEmail,
        ];
        $panel = self::insertWithin(
            $tx, $operationId, $code, 'order_line', $lineId,
            'operator', 'panel', 'v1', null, $payload
        );
        $emailId = null;
        if ($decision['enabled'] && $hasEmail) {
            $email = self::insertWithin(
                $tx, $operationId, $code, 'order_line', $lineId,
                'buyer', 'email', 'v1', 'order:' . $orderId, $payload
            );
            $emailId = $email['id'];
        }
        return [
            'panel_id' => $panel['id'],
            'email_id' => $emailId,
            'created_email' => null !== $emailId,
        ];
    }

    /**
     * IMP-45: constancia DURABLE de intención para un cambio de horario.
     * El email se resolverá desde el historial inmutable vinculado a su
     * outbox_id; jamás desde la última franja actual si hubo otro cambio.
     * No envía SMTP ni afirma recepción. Si falta correo, registra omisión.
     *
     * @return array{panel_id:string,email_id:string}
     */
    public static function serviceRescheduledWithin(
        Transaction_Context $tx,string $operationId,string $appointmentId,
        string $orderId,string $lineId,string $newStart,string $newEnd
    ): array {
        Event_Journal::requirePendingOperation($tx,$operationId);
        $tx->lockedOrder($orderId);
        $line=$tx->lockedOrderLine($lineId);
        $appointment=$tx->lockedAppointment($appointmentId);
        if ('service'!==$line['kind']
            || 'CONFIRMADA'!==$line['fulfillment_state']
            || (string)$line['order_id']!==$orderId
            || (string)$appointment['order_id']!==$orderId
            || (string)$appointment['order_line_id']!==$lineId
            || !\current_user_can('perucommerce_appointments_manage')) {
            throw new Transaction_Failure('pc_outbox_routine_scope_invalid');
        }
        $rows=$tx->rows(
            'SELECT starts_at_utc,ends_at_utc,cancelled_at,'
                . 'completed_at,no_show_at FROM %i '
                . 'WHERE id=%s AND order_id=%s AND order_line_id=%s LIMIT 2',
            [self::nameAppointment(),$appointmentId,$orderId,$lineId]
        );
        if (1!==\count($rows)
            || $rows[0]['starts_at_utc']!==$newStart
            || $rows[0]['ends_at_utc']!==$newEnd
            || null!==$rows[0]['cancelled_at']
            || null!==$rows[0]['completed_at']
            || null!==$rows[0]['no_show_at']) {
            throw new Transaction_Failure('pc_outbox_routine_state_invalid');
        }
        $email=self::targetWithin($tx,$orderId);
        $panel=self::insertWithin(
            $tx,$operationId,'service_rescheduled','appointment',
            $appointmentId,'operator','panel','v1',null,[]
        );
        $mail=self::insertWithin(
            $tx,$operationId,'service_rescheduled','appointment',
            $appointmentId,'buyer','email','v1',
            $email?'order:'.$orderId:null,[]
        );
        return ['panel_id'=>$panel['id'],'email_id'=>$mail['id']];
    }

    /** Registra solamente aviso de panel; no envía multas ni dinero. */
    public static function serviceOutcomeWithin(
        Transaction_Context $tx,string $operationId,string $appointmentId,
        string $orderId,string $lineId,string $outcome
    ): string {
        Event_Journal::requirePendingOperation($tx,$operationId);
        $tx->lockedOrder($orderId);
        $line=$tx->lockedOrderLine($lineId);
        $tx->lockedAppointment($appointmentId);
        if (!\in_array($outcome,['REALIZADA','NO_ASISTIO'],true)
            || (string)$line['order_id']!==$orderId
            || 'service'!==$line['kind']
            || !\current_user_can('perucommerce_appointments_manage')
            || 'A'!==Access_Roles::currentRole()) {
            throw new Transaction_Failure('pc_outbox_routine_state_invalid');
        }
        $rows=$tx->rows(
            'SELECT a.id FROM %i a INNER JOIN %i l '
                . 'ON l.id=a.order_line_id AND l.order_id=a.order_id '
                . 'WHERE a.id=%s AND a.order_id=%s AND a.order_line_id=%s '
                . 'AND l.fulfillment_state=%s AND a.outcome_recorded_at IS NOT NULL '
                . 'LIMIT 2',
            [self::nameAppointment(),self::name('pc_order_lines'),
                $appointmentId,$orderId,$lineId,$outcome]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_outbox_routine_state_invalid');
        }
        $notice=self::insertWithin(
            $tx,$operationId,'service_outcome_recorded','appointment',
            $appointmentId,'operator','panel','v1',null,[]
        );
        return $notice['id'];
    }

    private static function targetWithin(
        Transaction_Context $tx,string $orderId
    ): bool {
        $rows=$tx->rows(
            'SELECT buyer_email FROM %i WHERE id=%s LIMIT 2',
            [self::name('pc_orders'),$orderId]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_outbox_routine_scope_invalid');
        }
        $email=$rows[0]['buyer_email'];
        if (!\is_string($email) || ''===$email) {
            return false;
        }
        try {
            Domain_Validation::email($email);
            return true;
        } catch (\InvalidArgumentException $ignored) {
            return false;
        }
    }

    private static function nameAppointment(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_outbox_schema_missing');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        return Schema::tableNames($wpdb)['pc_appointments'];
    }

    /**
     * IMP-47: un aviso esencial requiere el evento auditado EXACTO de la
     * operación actual, sin confiar en una solicitud del navegador.
     * No se recuperan enlaces privados ni se envía correo aquí.
     */
    public static function essentialWithin(
        Transaction_Context $tx,Event_Actor $actor,string $operationId,
        string $orderId,string $eventCode,string $entityId
    ): void {
        $allowed=[
            'checkout_cash_confirmed'=>['order','checkout_cash_confirmed','operator'],
            'mp_payment_confirmed'=>['order','mp_initial_credit_verified','operator'],
            'cash_received'=>['order','cash_receipt_recorded','operator'],
            'line_cancelled'=>['order_line','line_cancelled','operator'],
            'external_refund_verified'=>['payment_event','external_refund_recorded','admin'],
            'logistics_incident_opened'=>['case','logistics_case_opened','admin'],
            'delivery_change_proposed'=>['order','delivery_change_proposed','admin'],
            'delivery_change_approved'=>['order','delivery_change_approved','operator'],
            'order_financial_adjusted'=>['order','order_financial_adjusted','admin'],
            'cash_corrected'=>['order','cash_correction_recorded','admin'],
            'digital_access_enabled'=>['order','mp_initial_credit_verified','operator'],
        ];
        if(!isset($allowed[$eventCode])){
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        [$entityType,$auditCode,$audience]=$allowed[$eventCode];
        $orderId=Domain_Validation::sqlId($orderId);
        $entityId=Domain_Validation::sqlId($entityId);
        Event_Journal::requirePendingOperation($tx,$operationId);
        $actor->assertCurrent();
        $tx->lockedOrder($orderId);
        $valid=$tx->rows(
            'SELECT id FROM %i WHERE entity_type=%s AND entity_id=%s '
                .'AND operation_code=%s AND request_id=UNHEX(%s) '
                .'AND actor_kind=%s LIMIT 2',
            [self::nameAudit(),$entityType,$entityId,$auditCode,
                Event_Journal::correlationHex($operationId),$actor->kind()]
        );
        if(1!==\count($valid)){
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        if('order'===$entityType && $entityId!==$orderId){
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        if('order_line'===$entityType){
            $rows=$tx->rows(
                'SELECT id FROM %i WHERE id=%s AND order_id=%s LIMIT 2',
                [self::name('pc_order_lines'),$entityId,$orderId]
            );
            if(1!==\count($rows)){
                throw new Transaction_Failure('pc_outbox_source_unverified');
            }
        }
        if('case'===$entityType){
            $rows=$tx->rows(
                'SELECT id FROM %i WHERE id=%s AND order_id=%s '
                    .'AND case_type=%s LIMIT 2',
                [self::nameOther('pc_cases'),$entityId,$orderId,
                    'logistics_non_delivery']
            );
            if(1!==\count($rows)){
                throw new Transaction_Failure('pc_outbox_source_unverified');
            }
        }
        if('payment_event'===$entityType){
            $rows=$tx->rows(
                'SELECT id FROM %i WHERE id=%s AND order_id=%s '
                    .'AND kind=%s LIMIT 2',
                [self::nameOther('pc_payment_events'),$entityId,$orderId,
                    'external_refund']
            );
            if(1!==\count($rows)){
                throw new Transaction_Failure('pc_outbox_source_unverified');
            }
        }
        self::insertWithin($tx,$operationId,$eventCode,
            $entityType,$entityId,$audience,'panel','v1',null,[]);
        if('delivery_change_proposed'===$eventCode){
            // No notificar al comprador una dirección todavía no aprobada.
            return;
        }
        $email=self::targetWithin($tx,$orderId);
        // Una ausencia de correo esencial se documenta como omisión.
        self::insertWithin($tx,$operationId,$eventCode,
            $entityType,$entityId,'buyer','email','v1',
            $email?'order:'.$orderId:null,[]);
    }

    /** Cita efectivamente creada por COD o MP, jamás selección/hold. */
    public static function serviceConfirmedWithin(
        Transaction_Context $tx,Event_Actor $actor,string $op,string $orderId,
        string $orderAudit
    ): void {
        if(!\in_array($orderAudit,[
            'checkout_cash_confirmed','mp_initial_credit_verified'
        ],true)){
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        Event_Journal::requirePendingOperation($tx,$op);
        $actor->assertCurrent();
        $tx->lockedOrder($orderId);
        $audit=$tx->rows(
            'SELECT id FROM %i WHERE entity_type=%s AND entity_id=%s '
                .'AND operation_code=%s AND request_id=UNHEX(%s) LIMIT 2',
            [self::nameAudit(),'order',$orderId,$orderAudit,
                Event_Journal::correlationHex($op)]
        );
        if(1!==\count($audit)){
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        $appointments=$tx->rows(
            'SELECT a.id FROM %i a INNER JOIN %i l '
                .'ON l.id=a.order_line_id AND l.order_id=a.order_id '
                .'WHERE a.order_id=%s AND a.cancelled_at IS NULL '
                .'AND a.completed_at IS NULL AND a.no_show_at IS NULL '
                .'AND l.kind=%s AND l.fulfillment_state=%s LIMIT 2',
            [self::nameAppointment(),self::name('pc_order_lines'),
                $orderId,'service','CONFIRMADA']
        );
        if([]===$appointments){return;}
        if(1!==\count($appointments)){
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        $id=Domain_Validation::sqlId((string)$appointments[0]['id']);
        self::insertWithin($tx,$op,'service_confirmed','appointment',$id,
            'operator','panel','v1',null,[]);
        self::insertWithin($tx,$op,'service_confirmed','appointment',$id,
            'buyer','email','v1',
            self::targetWithin($tx,$orderId)?'order:'.$orderId:null,[]);
    }

    /** Cancelación de servicio realmente aplicada, no solo solicitada. */
    public static function serviceCancelledWithin(
        Transaction_Context $tx,Event_Actor $actor,string $op,
        string $orderId,string $lineId,string $appointmentId
    ): void {
        Event_Journal::requirePendingOperation($tx,$op);
        $actor->assertCurrent();
        $tx->lockedOrder($orderId);
        $line=$tx->lockedOrderLine($lineId);
        if('service'!==$line['kind']
            || (string)$line['order_id']!==$orderId){
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        $rows=$tx->rows(
            'SELECT id FROM %i WHERE id=%s AND order_id=%s '
                .'AND order_line_id=%s AND cancelled_at IS NOT NULL LIMIT 2',
            [self::nameAppointment(),$appointmentId,$orderId,$lineId]
        );
        $audit=$tx->rows(
            'SELECT id FROM %i WHERE entity_type=%s AND entity_id=%s '
                .'AND operation_code=%s AND request_id=UNHEX(%s) LIMIT 2',
            [self::nameAudit(),'appointment',$appointmentId,'appointment_cancelled',
                Event_Journal::correlationHex($op)]
        );
        if(1!==\count($rows) || 1!==\count($audit)){
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        self::insertWithin($tx,$op,'service_cancelled','appointment',$appointmentId,
            'operator','panel','v1',null,[]);
        self::insertWithin($tx,$op,'service_cancelled','appointment',$appointmentId,
            'buyer','email','v1',
            self::targetWithin($tx,$orderId)?'order:'.$orderId:null,[]);
    }

    /**
     * IMP-48: aviso de hecho jurídico recibido, sin texto legal/PII
     * en pc_outbox. PANEL administrativo permanece aun sin correo.
     * Nunca transforma un email aceptado en constancia formal entregada.
     */
    public static function complaintReceivedWithin(
        Transaction_Context $tx,Event_Actor $actor,string $op,string $id
    ): void {
        Event_Journal::requirePendingOperation($tx,$op);
        $actor->assertCurrent();
        if('form'!==$actor->kind()) {
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        $id=Domain_Validation::sqlId($id);
        $original=$tx->rows(
            'SELECT id,complainant_snapshot FROM %i WHERE id=%s LIMIT 2',
            [self::nameOther('pc_complaints'),$id]
        );
        $event=$tx->rows(
            'SELECT id FROM %i WHERE entity_type=%s AND entity_id=%s '
                .'AND operation_code=%s AND actor_kind=%s '
                .'AND request_id=UNHEX(%s) LIMIT 2',
            [self::nameAudit(),'complaint',$id,
                'complaint_submitted','form',
                Event_Journal::correlationHex($op)]
        );
        if(1!==\count($original)||1!==\count($event)
            || !\is_string($original[0]['complainant_snapshot'])){
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        try{
            $data=\json_decode((string)$original[0]['complainant_snapshot'],
                true,12,\JSON_THROW_ON_ERROR);
        }catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        $email=$data['claimant']['email']??null;
        $hasEmail=false;
        if(\is_string($email)){
            try{Domain_Validation::email($email);$hasEmail=true;}
            catch(\Throwable $ignored){}
        }
        self::insertWithin(
            $tx,$op,'complaint_received','complaint',$id,
            'admin','panel','v1',null,[]
        );
        self::insertWithin(
            $tx,$op,'complaint_received','complaint',$id,
            'buyer','email','v1',$hasEmail?'complaint:'.$id:null,[]
        );
    }


    /**
     * IMP-49: aviso interno SOLO A. No envía el texto legal por SMTP y
     * no confunde preparación con entrega formal acreditada.
     */
    public static function complaintResponseWithin(
        Transaction_Context $tx,Event_Actor $actor,string $op,
        string $complaintId,string $code
    ):void {
        if(!\in_array($code,[
            'complaint_response_prepared','complaint_response_evidenced'
        ],true) || 'staff'!==$actor->kind()
            || !\current_user_can('perucommerce_complaints_manage')){
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        Event_Journal::requirePendingOperation($tx,$op);
        $actor->assertCurrent();
        $complaintId=Domain_Validation::sqlId($complaintId);
        $case=$tx->rows(
            'SELECT id,response_snapshot,responded_at,response_delivered_at '
                .'FROM %i WHERE id=%s AND sequence_scope=%s LIMIT 2',
            [self::nameOther('pc_complaints'),$complaintId,'principal_v1']
        );
        $audit=$tx->rows(
            'SELECT id FROM %i WHERE entity_type=%s AND entity_id=%s '
                .'AND operation_code=%s AND actor_kind=%s '
                .'AND actor_user_id=%s AND request_id=UNHEX(%s) LIMIT 2',
            [self::nameAudit(),'complaint',$complaintId,$code,
                'staff',(string)\get_current_user_id(),
                Event_Journal::correlationHex($op)]
        );
        if(1!==\count($case)||1!==\count($audit)
            || null===$case[0]['response_snapshot']
            || ('complaint_response_evidenced'===$code
                && (null===$case[0]['responded_at']
                    || null===$case[0]['response_delivered_at']))
            || ('complaint_response_prepared'===$code
                && null!==$case[0]['responded_at'])){
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        self::insertWithin($tx,$op,$code,'complaint',
            $complaintId,'admin','panel','v1',null,[]);
    }

    /**
     * IMP-50 — alertas ARCO mínimas, nunca solicitud completa ni email.
     * Cada aviso se relaciona con la auditoría y raíz original del caso.
     */
    public static function privacyEventWithin(
        Transaction_Context $tx,Event_Actor $actor,string $op,
        string $requestId,string $code
    ):void {
        if(!\in_array($code,[
            'privacy_request_received','privacy_identity_verified',
            'privacy_evidence_linked','privacy_resolution_prepared',
            'privacy_resolution_evidenced',
            'privacy_export_requested','privacy_export_ready',
            'privacy_export_failed','privacy_export_expired',
        ],true)){
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        Event_Journal::requirePendingOperation($tx,$op);
        $actor->assertCurrent();
        $requestId=Domain_Validation::sqlId($requestId);
        $case=Privacy_Admin::rowWithin($tx,$requestId);
        if(null===$case){throw new Transaction_Failure('pc_outbox_source_unverified');}
        $audit=$tx->rows(
            'SELECT id FROM %i WHERE entity_type=%s AND entity_id=%s '
                .'AND operation_code=%s AND actor_kind=%s '
                .'AND request_id=UNHEX(%s) LIMIT 2',
            [self::nameAudit(),'privacy_request',$requestId,$code,
                $actor->kind(),Event_Journal::correlationHex($op)]
        );
        if(1!==\count($audit)
            || ('privacy_identity_verified'===$code
                && null===$case['verified_at'])
            || ('privacy_resolution_evidenced'===$code
                && (null===$case['resolved_at']
                    || null===$case['response_evidence_ref']))
            || ('privacy_resolution_prepared'===$code
                && null!==$case['resolved_at'])){
            throw new Transaction_Failure('pc_outbox_source_unverified');
        }
        self::insertWithin($tx,$op,$code,'privacy_request',$requestId,
            'admin','panel','v1',null,[]);
    }

    private static function nameOther(string $suffix):string
    {
        global $wpdb;
        if(!$wpdb instanceof \wpdb){throw new Transaction_Failure('pc_outbox_schema_missing');}
        require_once PERUCOMMERCE_PLUGIN_DIR.'includes/schema.php';
        return Schema::tableNames($wpdb)[$suffix];
    }

    private static function nameAudit():string
    {
        return self::nameOther('pc_audit_log');
    }

    /**
     * @param array<string,mixed> $safePayload
     * @return array{id:string,status:string}
     */
    private static function insertWithin(
        Transaction_Context $tx,
        string $operationId,
        string $eventCode,
        string $entityType,
        string $entityId,
        string $audience,
        string $channel,
        string $templateVersion,
        ?string $destinationRef,
        array $safePayload
    ): array {
        $operationId = Domain_Validation::sqlId($operationId);
        $entityId = Domain_Validation::sqlId($entityId);
        Event_Journal::code($eventCode, 40);
        Event_Journal::code($entityType, 30);
        Event_Journal::code($templateVersion, 32);

        if (!\in_array($audience, ['buyer', 'admin', 'operator'], true)
            || !\in_array($channel, ['email', 'panel'], true)
            || ('email' === $channel && ('buyer' !== $audience && 'admin' !== $audience))
            || ('panel' === $channel && null !== $destinationRef)) {
            throw new Transaction_Failure('pc_outbox_recipient_invalid');
        }
        if (null !== $destinationRef && !\preg_match(
            '/\A(?:order|admin|case|complaint):[1-9][0-9]{0,19}\z/D',
            $destinationRef
        )) {
            throw new Transaction_Failure('pc_outbox_destination_invalid');
        }
        if (null !== $destinationRef) {
            $parts = \explode(':', $destinationRef, 2);
            Domain_Validation::sqlId($parts[1]);
        }

        $safe = self::encodePayload($safePayload);
        $dedup = self::dedup(
            $operationId, $eventCode, $entityType, $entityId,
            $audience, $channel, $templateVersion, $destinationRef
        );
        $existing = self::find($tx, $dedup);
        if ([] !== $existing) {
            $row = $existing[0];
            if ((string) $row['event_code'] !== $eventCode
                || (string) $row['entity_type'] !== $entityType
                || (string) $row['entity_id'] !== $entityId
                || (string) $row['audience_code'] !== $audience
                || (string) $row['channel'] !== $channel
                || (string) $row['template_version'] !== $templateVersion
                || $row['destination_ref'] !== $destinationRef
                || (string) $row['safe_payload'] !== $safe) {
                throw new Transaction_Failure('pc_outbox_dedup_conflict');
            }
            return ['id' => Domain_Validation::sqlId((string) $row['id']), 'status' => 'existing'];
        }

        // PANEL permanece PENDIENTE sin transporte. EMAIL sin destino válido
        // es OMITIDO_SIN_DESTINO y jamás intenta resolver un correo inventado.
        // No aplica a omisión por preferencia de mensajes rutinarios.
        $phase = 'email' === $channel && null === $destinationRef
            ? 'omitted_no_target' : 'pending';
        $next = 'email' === $channel && null !== $destinationRef
            ? 'UTC_TIMESTAMP(6)' : 'NULL';
        $affected = $tx->execute(
            'INSERT INTO %i (deduplication_hash, event_code, entity_type, entity_id, '
                . 'audience_code, channel, template_version, destination_ref, safe_payload, '
                . 'technical_phase, attempt_count, next_attempt_at, row_version, created_at) '
                . 'VALUES (UNHEX(%s), %s, %s, %s, %s, %s, %s, '
                . (null === $destinationRef ? 'NULL' : '%s')
                . ', %s, %s, 0, ' . $next . ', 1, UTC_TIMESTAMP(6))',
            \array_merge(
                [self::name('pc_outbox'), $dedup, $eventCode, $entityType, $entityId,
                    $audience, $channel, $templateVersion],
                null === $destinationRef ? [] : [$destinationRef],
                [$safe, $phase]
            )
        );
        if (1 !== $affected) {
            throw new Transaction_Failure('pc_outbox_enqueue_failed');
        }
        $rows = $tx->rows('SELECT LAST_INSERT_ID() AS inserted_id');
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_outbox_enqueue_failed');
        }
        return [
            'id' => Domain_Validation::sqlId((string) $rows[0]['inserted_id']),
            'status' => 'new',
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function find(Transaction_Context $tx, string $hash): array
    {
        return $tx->rows(
            'SELECT id, event_code, entity_type, entity_id, audience_code, channel, '
                . 'template_version, destination_ref, safe_payload, technical_phase '
                . 'FROM %i WHERE deduplication_hash = UNHEX(%s) FOR UPDATE',
            [self::name('pc_outbox'), $hash]
        );
    }

    public static function dedup(
        string $operationId, string $eventCode, string $entityType, string $entityId,
        string $audience, string $channel, string $templateVersion, ?string $destinationRef
    ): string {
        $identity = \json_encode([
            Domain_Validation::sqlId($operationId), $eventCode, $entityType,
            Domain_Validation::sqlId($entityId), $audience, $channel,
            $templateVersion, $destinationRef,
        ], \JSON_THROW_ON_ERROR);
        return \hash('sha256', 'pc-outbox-v1:' . $identity);
    }

    /** @param array<string,mixed> $safePayload */
    private static function encodePayload(array $safePayload): string
    {
        if (\count($safePayload) > 3) {
            throw new Transaction_Failure('pc_outbox_payload_invalid');
        }
        $allowed = ['physical_state','routine_email_decision','email_target_present'];
        foreach ($safePayload as $key => $value) {
            if (!\in_array($key, $allowed, true)) {
                throw new Transaction_Failure('pc_outbox_payload_invalid');
            }
            switch ($key) {
                case 'physical_state':
                    if (!\is_string($value) || !isset(self::ROUTINE_PHYSICAL[$value])) {
                        throw new Transaction_Failure('pc_outbox_payload_invalid');
                    }
                    break;
                case 'routine_email_decision':
                    if (!\is_array($value) || \array_keys($value) !== ['enabled','settings_row_version']
                        || !\is_bool($value['enabled'])
                        || !\is_string($value['settings_row_version'])) {
                        throw new Transaction_Failure('pc_outbox_payload_invalid');
                    }
                    Domain_Validation::sqlId($value['settings_row_version']);
                    break;
                case 'email_target_present':
                    if (!\is_bool($value)) {
                        throw new Transaction_Failure('pc_outbox_payload_invalid');
                    }
                    break;
            }
        }
        \ksort($safePayload, \SORT_STRING);
        try {
            $json = \json_encode($safePayload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $ignored) {
            throw new Transaction_Failure('pc_outbox_payload_invalid');
        }
        if (!\is_string($json) || \strlen($json) > 1024) {
            throw new Transaction_Failure('pc_outbox_payload_invalid');
        }
        return $json;
    }

    /** @return array<string,mixed> */
    private static function decodePayload(string $json): array
    {
        try {
            $data = \json_decode($json, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $ignored) {
            throw new Transaction_Failure('pc_outbox_payload_invalid');
        }
        if (!\is_array($data) || \array_is_list($data)) {
            throw new Transaction_Failure('pc_outbox_payload_invalid');
        }
        return $data;
    }

    public static function name(string $suffix): string
    {
        if ('pc_outbox' !== $suffix && 'pc_order_lines' !== $suffix && 'pc_orders' !== $suffix) {
            throw new Transaction_Failure('pc_outbox_table_denied');
        }
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_outbox_schema_missing');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        return Schema::tableNames($wpdb)[$suffix];
    }
}
