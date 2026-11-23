<?php
/**
 * IMP-12 — errores REST uniformes E08-M.
 * No propagar excepciones SQL, PII, body HTTP, mensajes de terceros o rutas
 * físicas. Solo códigos pc_*/estatus previstos por el OpenAPI de la ruta.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Rest_Failure extends \RuntimeException
{
    public function __construct(
        string $code = 'pc_request_invalid',
        public readonly int $status = 400,
        public readonly bool $retryable = false,
        public readonly ?string $field = null
    ) {
        parent::__construct($code);
    }
}

final class Rest_Errors
{
    /** @var array<string,array{status:int,message:string,retryable:bool}> */
    private const CATALOG = [
        'pc_request_invalid' => ['status' => 400, 'message' => 'Solicitud inválida.', 'retryable' => false],
        'pc_json_invalid' => ['status' => 400, 'message' => 'JSON inválido.', 'retryable' => false],
        'pc_request_too_large' => ['status' => 400, 'message' => 'Solicitud demasiado grande.', 'retryable' => false],
        'pc_media_unsupported' => ['status' => 400, 'message' => 'Formato de solicitud no admitido.', 'retryable' => false],
        'pc_field_invalid' => ['status' => 422, 'message' => 'Los datos no cumplen el contrato.', 'retryable' => false],
        // E04-H / DOC-QA27: unidades digitales o servicio, remanente
        // completo y otras acciones de negocio inelegibles son 422.
        'pc_business_ineligible' => ['status' => 422, 'message' => 'La operación no es elegible.', 'retryable' => false],
        'pc_auth_required' => ['status' => 401, 'message' => 'Se requiere autenticación.', 'retryable' => false],
        'pc_access_denied' => ['status' => 403, 'message' => 'Acceso denegado.', 'retryable' => false],
        'pc_not_found' => ['status' => 404, 'message' => 'Recurso no disponible.', 'retryable' => false],
        'pc_conflict' => ['status' => 409, 'message' => 'Conflicto de versión u operación.', 'retryable' => false],
        'pc_quote_changed' => ['status' => 409, 'message' => 'Es necesaria una nueva cotización.', 'retryable' => false],
        'pc_expired' => ['status' => 410, 'message' => 'La credencial o recurso expiró.', 'retryable' => false],
        'pc_module_not_ready' => ['status' => 423, 'message' => 'Operación temporalmente no disponible.', 'retryable' => true],
        'pc_rate_limited' => ['status' => 429, 'message' => 'Límite temporal de solicitudes.', 'retryable' => true],
        'pc_internal_error' => ['status' => 500, 'message' => 'No se pudo completar la operación.', 'retryable' => false],
        'pc_dependency_unavailable' => ['status' => 503, 'message' => 'Servicio temporalmente no disponible.', 'retryable' => true],
        'pc_result_invalid' => ['status' => 500, 'message' => 'Respuesta no disponible.', 'retryable' => false],
        'pc_operation_uncertain' => ['status' => 409, 'message' => 'Operación pendiente de conciliación.', 'retryable' => false],
    ];

    /** @return \WP_Error */
    public static function make(
        string $operationId,
        string $code,
        ?string $field = null,
        bool $currentQuoteRequired = false
    ): \WP_Error {
        $declared = self::CATALOG[$code] ?? self::CATALOG['pc_internal_error'];
        $knownCode = isset(self::CATALOG[$code]) ? $code : 'pc_internal_error';
        $op = Rest_Contract::operation($operationId);
        if (null === $op) {
            // No se revela existencia de una operación desconocida.
            $knownCode = 'pc_access_denied';
            $declared = self::CATALOG[$knownCode];
        }

        $status = $declared['status'];
        if (null !== $op && !\array_key_exists((string) $status, $op['responses'])) {
            // Respetar únicamente estatus previstos para este endpoint.
            foreach (['503', '500', '409', '403', '400'] as $fallback) {
                if (\array_key_exists($fallback, $op['responses'])) {
                    $status = (int) $fallback;
                    break;
                }
            }
            if ($status !== $declared['status']) {
                $knownCode = 'pc_dependency_unavailable';
                $declared = self::CATALOG[$knownCode];
                // La etiqueta de error también debe corresponder al status.
                if (403 === $status) {
                    $knownCode = 'pc_access_denied';
                    $declared = self::CATALOG[$knownCode];
                } elseif (400 === $status) {
                    $knownCode = 'pc_request_invalid';
                    $declared = self::CATALOG[$knownCode];
                } elseif (409 === $status) {
                    $knownCode = 'pc_conflict';
                    $declared = self::CATALOG[$knownCode];
                } elseif (500 === $status) {
                    $knownCode = 'pc_internal_error';
                    $declared = self::CATALOG[$knownCode];
                }
            }
        }

        $data = [
            'status' => $status,
            'retryable' => $declared['retryable'],
            'request_id' => \bin2hex(\random_bytes(16)),
        ];
        $details = [];
        if (null !== $field && \preg_match('/\A[a-zA-Z0-9_.\[\]]{1,120}\z/D', $field)) {
            $details['field'] = $field;
        }
        if ($currentQuoteRequired) {
            $details['current_quote_required'] = true;
        }
        if ([] !== $details) {
            $data['details'] = $details;
        }
        return new \WP_Error($knownCode, $declared['message'], $data);
    }

    public static function fromThrowable(string $operationId, \Throwable $error): \WP_Error
    {
        // Lista de excepciones cuyo significado es estable; jamás devolver
        // mensajes de errores SQL, PHP, WordPress, HTTP o APIs externas.
        if ($error instanceof Rest_Failure) {
            return self::make(
                $operationId,
                $error->getMessage(),
                $error->field
            );
        }
        if ($error instanceof MP_HTTP_Failure) {
            // Respuestas reales del proveedor NUNCA incluyen su body ni el
            // token. POST de resultado incierto exige recuperación/GET.
            return self::make(
                $operationId,
                $error->outcomeUncertain
                    ? 'pc_operation_uncertain' : 'pc_dependency_unavailable'
            );
        }
        if ($error instanceof Transaction_Failure) {
            return match ($error->getMessage()) {
                // IMP-43: propuestas no aprobadas no son cambios
                // de entrega, y los errores nunca revelan dirección o PII.
                'pc_delivery_change_denied'
                    => self::make($operationId,'pc_access_denied'),
                'pc_delivery_change_conflict',
                'pc_delivery_change_noop',
                'pc_delivery_change_proposal_pending',
                'pc_delivery_change_money_review',
                'pc_delivery_change_higher_fee',
                'pc_delivery_change_quote_changed'
                    => self::make($operationId,'pc_conflict'),
                'pc_delivery_change_ineligible'
                    => self::make($operationId,'pc_business_ineligible'),
                'pc_delivery_change_not_ready'
                    => self::make($operationId,'pc_module_not_ready'),
                'pc_delivery_change_invariant'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-42: no revelar saldos, proveedor, recibo,
                // cuenta de MP ni detalle de constancia en errores.
                'pc_refund_denied','pc_refund_source_invalid'
                    => self::make($operationId,'pc_access_denied'),
                'pc_refund_conflict','pc_refund_limit_exceeded',
                'pc_refund_duplicate_reference','pc_refund_money_unresolved'
                    => self::make($operationId,'pc_conflict'),
                'pc_refund_reference_invalid','pc_refund_evidence_invalid'
                    => self::make($operationId,'pc_field_invalid'),
                'pc_refund_not_ready'
                    => self::make($operationId,'pc_module_not_ready'),
                'pc_refund_invariant_invalid'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-41: R8-26 no filtra el saldo, prueba, pago ni
                // identidad de un expediente o pedido de otro sitio.
                'pc_adjust_denied','pc_adjust_case_denied'
                    => self::make($operationId,'pc_access_denied'),
                'pc_adjust_conflict','pc_adjust_amount_conflict',
                'pc_adjust_money_unresolved'
                    => self::make($operationId,'pc_conflict'),
                'pc_adjust_ineligible'
                    => self::make($operationId,'pc_business_ineligible','affected_units'),
                'pc_adjust_input_invalid'
                    => self::make($operationId,'pc_field_invalid'),
                'pc_adjust_not_ready'
                    => self::make($operationId,'pc_module_not_ready'),
                'pc_adjust_invariant_invalid','pc_adjust_write_failed'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-44: caso logístico administrado; sin detalles de
                // identidad, evidencia ni decisiones en errores públicos.
                'pc_logistics_denied'
                    => self::make($operationId,'pc_access_denied'),
                'pc_logistics_conflict','pc_logistics_already_open'
                    => self::make($operationId,'pc_conflict'),
                'pc_logistics_invalid'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-40: no revelar identidad ni cantidad de caso ajeno,
                // ni prueba privada/telefono original en errores REST.
                'pc_manual_case_denied','pc_manual_case_identity_unverified'
                    => self::make($operationId,'pc_access_denied'),
                'pc_manual_case_conflict','pc_manual_case_already_open'
                    => self::make($operationId,'pc_conflict'),
                'pc_manual_case_invalid'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-39: la autorización y los motivos no filtran datos
                // financieros ni evidencias del comprador en errores.
                'pc_cancel_denied' => self::make($operationId,'pc_access_denied'),
                'pc_cancel_conflict','pc_cancel_case_unverified',
                'pc_cancel_money_unresolved','pc_cancel_delivery_review'
                    => self::make($operationId,'pc_conflict'),
                'pc_cancel_invariant_invalid'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-38: no divulgar importes de otros pedidos o
                // referencias de evidencia en errores de caja.
                'pc_cash_denied' => self::make($operationId,'pc_access_denied'),
                'pc_cash_conflict','pc_cash_balance_conflict',
                'pc_cash_amount_mismatch'
                    => self::make($operationId,'pc_conflict'),
                'pc_cash_input_invalid'
                    => self::make($operationId,'pc_field_invalid'),
                'pc_cash_not_ready'
                    => self::make($operationId,'pc_module_not_ready'),
                'pc_cash_invariant_invalid'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-37: transición física y salida única bajo CAS.
                'pc_physical_denied' => self::make($operationId,'pc_access_denied'),
                'pc_physical_conflict' => self::make($operationId,'pc_conflict'),
                'pc_physical_evidence_required'
                    => self::make($operationId,'pc_field_invalid','evidence'),
                'pc_physical_invariant_invalid'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-36: no revelar expediente ajeno, prueba, archivo o saldo.
                'pc_digital_case_denied'
                    => self::make($operationId,'pc_access_denied'),
                'pc_digital_case_conflict'
                    => self::make($operationId,'pc_conflict'),
                'pc_digital_case_input_invalid'
                    => self::make($operationId,'pc_field_invalid'),
                'pc_digital_case_invalid'
                    => self::make($operationId,'pc_internal_error'),
                // IMP-35: token/cookie digital no enumeran derecho/archivo,
                // ni evidencias de legitimación; cuota agotada = conflicto.
                'pc_digital_access_denied','pc_digital_identity_unverified'
                    => self::make($operationId,'pc_access_denied'),
                'pc_digital_quota_exhausted','pc_digital_reissue_conflict'
                    => self::make($operationId,'pc_conflict'),
                'pc_digital_reissue_unavailable'
                    => self::make($operationId,'pc_module_not_ready'),
                // IMP-34: recuperación de seguimiento; las respuestas no
                // enumeran comprador, caso OTP, evidencia, old token o versión
                // ajena. El único secreto sale en 200 primera emisión.
                'pc_tracking_identity_unverified','pc_tracking_reissue_denied'
                    => self::make($operationId,'pc_access_denied'),
                'pc_tracking_reissue_conflict','pc_tracking_reissue_pending'
                    => self::make($operationId,'pc_conflict'),
                'pc_tracking_reissue_input_invalid'
                    => self::make($operationId,'pc_field_invalid'),
                'pc_tracking_reissue_unavailable',
                'pc_tracking_reissue_insert_failed'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-33: solicitud guest autenticada. Un expediente
                // no debe revelar en errors existencia de pedido, correo,
                // grant usado o texto original de dirección/reembolso.
                'pc_guest_request_denied'
                    => self::make($operationId,'pc_access_denied'),
                'pc_guest_request_conflict','pc_guest_request_ineligible',
                'pc_guest_request_pending'
                    => self::make($operationId,'pc_conflict'),
                'pc_guest_request_invalid'
                    => self::make($operationId,'pc_field_invalid'),
                'pc_guest_request_insert_failed'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-32: OTP/grant opaco. No revelar correo asociado,
                // desafío, intentos restantes ni grant/cookie en REST.
                'pc_guest_grant_invalid'
                    => self::make($operationId,'pc_access_denied'),
                'pc_guest_challenge_unavailable',
                'pc_guest_grant_cookie_unavailable'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-31: sesión invitada de lectura. Los errores nunca
                // enumeran número de pedido, existencia de enlace, sesión,
                // expiración individual ni valores del HMAC o cookie.
                'pc_guest_origin_invalid','pc_guest_tracking_invalid',
                'pc_guest_session_invalid'
                    => self::make($operationId,'pc_access_denied'),
                'pc_guest_cookie_unavailable','pc_guest_exchange_invalid',
                'pc_guest_order_invalid'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-30: conciliación administrativa. No filtrar
                // referencias de cuentas, datos del proveedor ni evidencias.
                'pc_mp_case_not_found'
                    => self::make($operationId,'pc_not_found'),
                'pc_mp_reconciliation_preorder',
                'pc_mp_reconciliation_conflict',
                'pc_mp_reconciliation_pending',
                'pc_mp_reconciliation_agreement',
                'pc_mp_reconciliation_evidence',
                'pc_mp_case_conflict'
                    => self::make($operationId,'pc_conflict'),
                'pc_mp_reconciliation_scope'
                    => self::make($operationId,'pc_access_denied'),
                'pc_mp_reconciliation_not_ready'
                    => self::make($operationId,'pc_module_not_ready'),
                'pc_mp_case_invalid'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-29: firma HMAC y recepción durable. Un webhook
                // inválido jamás recibe ACK, recursos ni detalles de cuenta.
                'pc_webhook_unverified','pc_webhook_signature_expired',
                'pc_webhook_account_ambiguous','pc_webhook_job_invalid'
                    => self::make($operationId,'pc_access_denied'),
                'pc_inbox_persist_failed','pc_inbox_schema_missing'
                    => self::make($operationId,'pc_dependency_unavailable'),
                'pc_inbox_dedup_conflict'
                    => self::make($operationId,'pc_conflict'),
                // IMP-28: checkout MP con retención y dinero diferenciados.
                // No exponer referencia de proveedor, pedido, cookie ni
                // credenciales en errores. Ningún error crea otra order.
                'pc_mp_attempt_unverified','pc_mp_checkout_unresolved',
                'pc_mp_credit_source_not_ready','pc_mp_credit_unverified'
                    => self::make($operationId,'pc_module_not_ready'),
                'pc_mp_attempt_conflict','pc_mp_credit_conflict',
                'pc_mp_attempt_insert_failed'
                    => self::make($operationId,'pc_operation_uncertain'),
                // IMP-27: bóveda/correlación MP. Configurar secretos NO
                // habilita cobro ni autoriza exponerlos en errors REST.
                'pc_mp_credentials_invalid'
                    => self::make($operationId,'pc_field_invalid'),
                'pc_mp_vault_not_ready','pc_mp_credentials_not_ready'
                    => self::make($operationId,'pc_module_not_ready'),
                'pc_mp_version_conflict','pc_mp_rotation_superseded',
                'pc_mp_operation_pending','pc_mp_history_full',
                'pc_mp_vault_conflict','pc_mp_context_invalid'
                    => self::make($operationId,'pc_conflict'),
                'pc_mp_vault_invalid'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-26: formulario anónimo y replay de COD sin
                // exposición de cookie ni de estado financiero no verificado.
                'pc_checkout_form_invalid', 'pc_audit_actor_denied',
                'pc_idempotency_identity_unverified',
                'pc_idempotency_scope_invalid'
                    => self::make($operationId, 'pc_access_denied'),
                'pc_checkout_form_not_ready'
                    => self::make($operationId, 'pc_module_not_ready'),
                'pc_checkout_operation_pending'
                    => self::make($operationId, 'pc_operation_uncertain'),
                // IMP-25: coordinación; nunca exponer stock, franja, token
                // ni estado interno MP. El siguiente consumidor normaliza por
                // los estatus declarados en R8-06/07, sin ruta adicional.
                'pc_checkout_quote_changed', 'pc_checkout_slot_changed',
                'pc_checkout_slot_unavailable', 'pc_checkout_method_ineligible',
                'pc_checkout_hold_missing', 'pc_checkout_hold_invalid',
                'pc_checkout_hold_duplicate', 'pc_checkout_appointment_duplicate',
                'pc_checkout_admission_missing', 'pc_checkout_admission_invalid',
                'pc_checkout_admission_duplicate', 'pc_checkout_attempt_invalid',
                'pc_checkout_mp_context_invalid', 'pc_tracking_resource_incomplete'
                    => self::make($operationId, 'pc_quote_changed'),
                'pc_checkout_admission_expired','pc_checkout_hold_expired'
                    => self::make($operationId, 'pc_expired'),
                'pc_checkout_not_ready', 'pc_checkout_mp_not_ready',
                'pc_checkout_credit_unverified', 'pc_tracking_url_not_ready',
                'pc_tracking_secret_unavailable'
                    => self::make($operationId, 'pc_module_not_ready'),
                'pc_checkout_scope_invalid', 'pc_tracking_operation_invalid'
                    => self::make($operationId, 'pc_access_denied'),
                'pc_checkout_order_invalid','pc_checkout_service_invalid',
                'pc_checkout_admission_failed','pc_checkout_insert_failed',
                'pc_checkout_clock_invalid','pc_checkout_appointment_failed',
                'pc_tracking_issue_failed','pc_tracking_order_invalid'
                    => self::make($operationId, 'pc_dependency_unavailable'),
                // IMP-24: cotización fallida no deja holds ni pedidos,
                // ni revela cantidades de stock o detalles de calendario.
                'pc_quote_unavailable'
                    => self::make($operationId, 'pc_quote_changed'),
                'pc_quote_not_ready'
                    => self::make($operationId, 'pc_module_not_ready'),
                'pc_quote_invariant_invalid'
                    => self::make($operationId, 'pc_dependency_unavailable'),
                // IMP-23: revocación y concesión digitales, siempre
                // sin exponer token, expediente, archivo ni SQL.
                'pc_digital_right_not_found'
                    => self::make($operationId, 'pc_not_found'),
                'pc_digital_right_ineligible'
                    => self::make($operationId, 'pc_field_invalid'),
                'pc_digital_right_case_invalid',
                'pc_digital_right_credit_missing',
                'pc_digital_right_conflict'
                    => self::make($operationId, 'pc_conflict'),
                'pc_digital_right_not_ready'
                    => self::make($operationId, 'pc_module_not_ready'),
                'pc_digital_right_invariant_invalid'
                    => self::make($operationId, 'pc_dependency_unavailable'),
                // IMP-22: no filtrar contactos, casos ni datos SQL a REST.
                'pc_orders_field_invalid'
                    => self::make($operationId, 'pc_field_invalid'),
                'pc_orders_not_found'
                    => self::make($operationId, 'pc_not_found'),
                'pc_orders_quote_changed'
                    => self::make($operationId, 'pc_quote_changed'),
                'pc_orders_not_ready'
                    => self::make($operationId, 'pc_module_not_ready'),
                'pc_orders_invariant_invalid'
                    => self::make($operationId, 'pc_dependency_unavailable'),
                // IMP-21: nunca propagar ruta, hash, MIME ni datos del
                // sistema de archivos hacia respuestas REST.
                'pc_digital_upload_invalid', 'pc_digital_field_invalid'
                    => self::make($operationId, 'pc_field_invalid'),
                'pc_digital_not_found'
                    => self::make($operationId, 'pc_not_found'),
                'pc_digital_access_denied'
                    => self::make($operationId, 'pc_access_denied'),
                'pc_digital_conflict'
                    => self::make($operationId, 'pc_conflict'),
                'pc_digital_not_ready'
                    => self::make($operationId, 'pc_module_not_ready'),
                'pc_private_storage_unavailable'
                    => self::make($operationId, 'pc_dependency_unavailable'),
                // IMP-19: denegar datos no verificados, preservar códigos
                // distritales y distinguir cobertura insuficiente de SQL.
                'pc_ubigeo_invalid'
                    => self::make($operationId, 'pc_field_invalid'),
                'pc_delivery_not_covered'
                    => self::make($operationId, 'pc_field_invalid'),
                'pc_coverage_conflict', 'pc_ubigeo_conflict'
                    => self::make($operationId, 'pc_conflict'),
                'pc_ubigeo_not_ready', 'pc_coverage_not_ready',
                'pc_delivery_not_ready'
                    => self::make($operationId, 'pc_module_not_ready'),
                // IMP-50: ARCO separado de checkout/Libro, sin filtrar
                // contacto, identidad, origen ni motivo de retención.
                'pc_privacy_denied'
                    => self::make($operationId,'pc_access_denied'),
                'pc_privacy_not_found','pc_privacy_export_expired'
                    => self::make($operationId,'pc_not_found'),
                'pc_privacy_conflict','pc_privacy_intake_conflict'
                    => self::make($operationId,'pc_conflict'),
                'pc_privacy_source_unverified',
                'pc_privacy_identity_unverified',
                'pc_privacy_resolution_unverified',
                'pc_privacy_retention_unverified',
                'pc_privacy_delivery_unverified',
                'pc_privacy_scope_unverified'
                    => self::make($operationId,'pc_business_ineligible'),
                'pc_privacy_not_ready','pc_privacy_intake_not_ready',
                'pc_privacy_calendar_unverified'
                    => self::make($operationId,'pc_module_not_ready'),
                'pc_privacy_manifest_invalid','pc_privacy_invariant_invalid',
                'pc_privacy_intake_invalid','pc_privacy_incident_unverified',
                'pc_export_failed'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-49: reclamo ajeno, edición, calendario y storage:
                // no filtrar el ID, el documento o referencias privadas.
                'pc_complaint_denied'
                    => self::make($operationId,'pc_access_denied'),
                'pc_complaint_not_found'
                    => self::make($operationId,'pc_not_found'),
                'pc_complaint_response_conflict'
                    => self::make($operationId,'pc_conflict'),
                'pc_complaint_delivery_unverified'
                    => self::make($operationId,'pc_business_ineligible'),
                'pc_complaint_calendar_unverified'
                    => self::make($operationId,'pc_module_not_ready'),
                'pc_complaint_manifest_invalid'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-48: Libro público, CSRF/legalidad/folio sin filtrar
                // identity, copia privada ni SQL en respuesta de error.
                'pc_complaint_form_denied'
                    => self::make($operationId,'pc_access_denied'),
                'pc_complaint_conflict'
                    => self::make($operationId,'pc_conflict'),
                'pc_complaint_not_ready', 'pc_complaint_form_not_ready',
                'pc_complaint_legal_not_ready'
                    => self::make($operationId,'pc_module_not_ready'),
                'pc_complaint_invariant_invalid'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-47: reenvío/lectura por recurso, sin destinatario ni
                // contenido privado en errores de notificación.
                'pc_notification_denied'
                    => self::make($operationId,'pc_access_denied'),
                'pc_notification_conflict'
                    => self::make($operationId,'pc_conflict'),
                'pc_notification_not_ready'
                    => self::make($operationId,'pc_module_not_ready'),
                // IMP-45: no revelar datos de otra cita, ni razón/evidencia
                // privada de observación o reprogramación en errores REST.
                'pc_appointment_denied'
                    => self::make($operationId,'pc_access_denied'),
                'pc_appointment_conflict'
                    => self::make($operationId,'pc_conflict'),
                'pc_appointment_invalid'
                    => self::make($operationId,'pc_dependency_unavailable'),
                // IMP-18 — no filtrar calendario privado, SQL ni evidencia.
                'pc_agenda_not_found'
                    => self::make($operationId, 'pc_not_found'),
                'pc_agenda_field_invalid', 'pc_agenda_time_ambiguous'
                    => self::make($operationId, 'pc_field_invalid'),
                'pc_agenda_not_ready'
                    => self::make($operationId, 'pc_module_not_ready'),
                'pc_agenda_conflict', 'pc_agenda_overlap'
                    => self::make($operationId, 'pc_conflict'),
                // IMP-17: semántica pública uniforme sin filtrar SQL, stock
                // numérico, pedidos o IDs ajenos al actor.
                'pc_inventory_ineligible'
                    => self::make($operationId, 'pc_field_invalid'),
                'pc_inventory_not_found'
                    => self::make($operationId, 'pc_not_found'),
                'pc_inventory_not_ready'
                    => self::make($operationId, 'pc_module_not_ready'),
                'pc_inventory_unavailable', 'pc_inventory_duplicate',
                'pc_inventory_transition_invalid',
                'pc_inventory_admission_expired', 'pc_inventory_hold_expired'
                    => self::make($operationId, 'pc_conflict'),
                // IMP-15: Transaction::run conserva Transaction_Failure
                // después de rollback; no convertir validación a error 500.
                'pc_catalog_field_invalid'
                    => self::make($operationId, 'pc_field_invalid'),
                'pc_catalog_not_found'
                    => self::make($operationId, 'pc_not_found'),
                'pc_catalog_not_ready'
                    => self::make($operationId, 'pc_module_not_ready'),
                'pc_catalog_access_denied'
                    => self::make($operationId, 'pc_access_denied'),
                'pc_catalog_conflict'
                    => self::make($operationId, 'pc_conflict'),
                'pc_deadlock', 'pc_lock_timeout',
                'pc_version_conflict', 'pc_idempotency_conflict',
                'pc_unique_conflict', 'pc_settings_version_conflict',
                'pc_settings_cas_failed', 'pc_settings_replay_superseded',
                'pc_settings_operation_pending'
                    => self::make($operationId, 'pc_conflict'),
                'pc_commit_outcome_uncertain', 'pc_transaction_outcome_uncertain'
                    => self::make($operationId, 'pc_operation_uncertain'),
                'pc_transaction_unavailable', 'pc_schema_metadata_unavailable'
                    => self::make($operationId, 'pc_dependency_unavailable'),
                default => self::make($operationId, 'pc_internal_error'),
            };
        }
        return self::make($operationId, 'pc_internal_error');
    }
}
