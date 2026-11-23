/**
 * IMP-64: DTO administrativos de OpenAPI 0.12.0-draft.
 * Operaciones R8-25/26/27/28/29/30/56/57/64/69/70 (sin rutas nuevas).
 * La proyección R8-21 y los permisos del servidor son autoritativos.
 */
import type { SqlId, MoneyCents } from './dto';
import type { AdminCaseSummary, AdminOrder } from './admin-orders-contract';
export type { AdminOrder, AdminCaseSummary };

export interface AdminFinancialEvent {
  event_id: SqlId;
  kind: string;
  amount_cents: string;
  occurred_at: string;
  source_payment_event_id?: SqlId;
  external_reference?: string;
}
export interface AdminExternalDocumentSummary {
  id: SqlId;
  document_kind: string;
  recorded_at: string;
  series?: string;
  number?: string;
  issued_at?: string;
  amount_cents?: MoneyCents;
  source_document_id?: SqlId;
}
export interface FinanceOrder extends AdminOrder {
  /** R8-21: proyección A con permiso digital; nunca visible a O. */
  digital_rights?: AdminDigitalRight[];
  financial_movements?: AdminFinancialEvent[];
  external_documents?: AdminExternalDocumentSummary[];
  case_detail?: AdminCaseDetail;
}
export interface AdminDigitalRight {
  right_id: SqlId;
  order_line_id: SqlId;
  file_id: SqlId;
  row_version: SqlId;
  revoked: boolean;
  download_limit?: number | null;
  download_count: number;
}
export type DigitalCaseDecisionCode =
  'maintain_access' | 'authorize_revocation' | 'financial_review';
export interface DigitalCaseDecisionRequest {
  decision: DigitalCaseDecisionCode;
  evidence_ref: string;
  reason: string;
  expected_version: SqlId;
  idempotency_key: string;
}
export interface DigitalRightRevokeRequest {
  case_id: SqlId;
  legal_reason: string;
  evidence_ref: string;
  expected_version: SqlId;
  idempotency_key: string;
}
export interface DigitalRightRevokeResult {
  operation_id: string;
  result: string;
  row_version?: SqlId;
}
export interface AdminCaseOriginalRequest {
  source_kind: 'guest' | 'staff' | 'system';
  recorded_at: string;
  purpose?: string;
  reason?: string;
  requested_ubigeo?: string;
  requested_address?: string;
  case_details?: string;
  evidence_ref?: string;
  requested_affected_units?: number;
  manual_verification_basis?: string;
}
export interface AdminCaseEvent {
  event_id: SqlId;
  event_code: string;
  occurred_at: string;
  actor_kind: 'guest' | 'staff' | 'system';
  decision_code?: string;
  safe_summary?: string;
  evidence_ref?: string;
}
export interface AdminCaseDetail {
  case_id: SqlId;
  row_version: SqlId;
  case_type: string;
  case_phase: string;
  reason_code: string;
  opened_at: string;
  original_request?: AdminCaseOriginalRequest;
  events: AdminCaseEvent[];
  events_page: number;
  events_per_page: 50;
  events_has_more: boolean;
}

export interface ManualVerification {
  contact_control_method: 'outbound_call_to_registered_phone' |
    'verified_reply_from_registered_phone';
  registered_phone_control_confirmed: true;
  nonpublic_order_fact_confirmed: true;
  nonpublic_fact_category: 'private_delivery_detail' | 'private_order_detail';
  safe_audit_note: string;
}
export interface AdminManualCaseCreate {
  line_id: SqlId;
  purpose: 'physical_partial_quantity_adjustment';
  requested_affected_units: number;
  reason: string;
  verification: ManualVerification;
  expected_order_version: SqlId;
  expected_line_version: SqlId;
  idempotency_key: string;
}
export interface AdminManualCaseOpened {
  case_id: SqlId;
  order_id: SqlId;
  order_line_id: SqlId;
  case_phase: 'open_review';
  row_version: SqlId;
  operation_id: string;
  opened_at_utc: string;
}
export interface AdminManualCaseClose {
  decision: 'reject' | 'buyer_withdrawn';
  reason: string;
  expected_case_version: SqlId;
  idempotency_key: string;
  withdrawal_verification?: ManualVerification;
}
export interface AdminManualCaseClosed {
  case_id: SqlId;
  order_id: SqlId;
  order_line_id: SqlId;
  case_phase: 'closed_rejected' | 'closed_withdrawn';
  row_version: SqlId;
  operation_id: string;
  resolved_at_utc: string;
}
export interface FinancialAdjustment {
  delta_cents: string;
  reason: string;
  expected_version: SqlId;
  idempotency_key: string;
  line_id?: SqlId;
  case_id?: SqlId;
  affected_units?: number;
  unit_effect?: 'release_unfulfilled_units';
  expected_case_version?: SqlId;
}
export interface MutationResult {
  operation_id: string;
  result: string;
  entity_id?: SqlId;
  row_version?: SqlId;
  public_status?: string;
}
export interface CaseActionResult {
  case_id: SqlId;
  operation_id: string;
  row_version: SqlId;
  case_phase?: string;
}
export interface CaseProposalRequest {
  resolution_proposal: 'delivery_verified'|'non_delivery_verified'|'continue_investigation';
  evidence_ref: string;
  reason: string;
  expected_version: SqlId;
  idempotency_key: string;
}
export interface CaseResolutionRequest {
  decision: 'delivery_verified'|'non_delivery_verified';
  evidence_ref: string;
  reason: string;
  expected_version: SqlId;
  idempotency_key: string;
}
export interface CashCorrectionRequest {
  source_event_id: SqlId;
  delta_cents: string;
  reason: string;
  expected_version: SqlId;
  idempotency_key: string;
  evidence?: string;
}
export interface ExternalRefundRequest {
  source_payment_event_id: SqlId;
  amount_cents: MoneyCents;
  external_reference: string;
  evidence: string;
  expected_version: SqlId;
  idempotency_key: string;
}
export type DocumentKind = 'boleta'|'factura'|'rhe'|'ticket'|'nota_credito'|'guia';
export interface ExternalDocumentRequest {
  document_kind: DocumentKind;
  issued_at: string;
  expected_version: SqlId;
  idempotency_key: string;
  series?: string;
  number?: string;
  external_reference?: string;
  source_document_id?: SqlId;
  amount_cents?: MoneyCents;
  evidence_ref?: string;
}
export interface ExternalDocumentResult {
  operation_id: string;
  record_id: SqlId;
  external_only: true;
}
export interface DeliveryApprovalRequest {
  new_ubigeo: string;
  new_address: string;
  reason: string;
  expected_version: SqlId;
  idempotency_key: string;
}
export interface ReconciliationSummary {
  case_id: SqlId;
  order_id?: SqlId;
  source_operation_id?: SqlId;
  reason_code: string;
  created_at: string;
  has_verified_payment?: boolean;
  row_version: SqlId;
  provider_order_ref?: string;
  // Importante: R8-29 NO incluye case_phase.
}
export interface ReconciliationPage {
  items: ReconciliationSummary[];
  page: number;
  per_page: number;
  has_more: boolean;
}
export type ReconciliationState =
  | 'open_review'|'closed_no_charge'|'closed_reassigned';
export type ReconciliationResolution = 'close_no_charge'|'reassign_resources';
export interface ReconciliationResolve {
  resolution: ReconciliationResolution;
  evidence: string;
  expected_version: SqlId;
  idempotency_key: string;
  order_id?: SqlId;
  new_service_start_at_utc?: string;
  new_service_end_at_utc?: string;
  buyer_agreement_evidence?: string;
}

export function validSqlId(value: string): boolean {
  return /^[1-9][0-9]{0,19}$/.test(value);
}
export function checkReason(value: string): string {
  const v=value.trim();
  if (!v || v.length>1000 || /[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/.test(v)) {
    throw new Error('Indica un motivo válido (hasta 1000 caracteres).');
  }
  return v;
}
export function checkEvidence(value:string, max=500):string {
  const v=value.trim();
  if (!v || v.length>max || /[\u0000-\u001f\u007f]/.test(v) || v.includes('://')) {
    throw new Error('La evidencia debe ser una referencia opaca válida, sin URL ni caracteres de control.');
  }
  return v;
}
export function checkExternalId(value: string,max=200): string {
  const v=value.trim();
  if (v.length<8 || v.length>max || !/^[a-zA-Z0-9._:-]+$/.test(v)) {
    throw new Error('La referencia externa debe tener 8–'+max+' caracteres (letras, números, ., _, :, -).');
  }
  return v;
}
export function checkAuditNote(raw:string):string {
  const value=raw.trim();
  // El backend rechaza números y @ para minimizar PII.
  if (value.length<8 || value.length>400 || /[0-9@\u0000-\u001f\u007f]/.test(value)
    || value.includes('://')) {
    throw new Error('Nota segura de 8–400 caracteres, sin números, @, URL, teléfono, DNI, OTP ni contenido del segundo dato.');
  }
  return value;
}
/** Importes monetarios exactos, con signo opcional y sin float. */
export function solesToSignedCents(raw:string, allowNegative=true):string {
  const v=raw.trim();
  const m=/^(-?)(0|[1-9][0-9]{0,14})(?:[.,]([0-9]{1,2}))?$/.exec(v);
  if (!m || (!allowNegative && m[1]==='-')) {
    throw new Error('Importe inválido en PEN. Usa soles, hasta dos decimales y signo cuando corresponda.');
  }
  const magnitude=(m[2]+(m[3]??'').padEnd(2,'0')).replace(/^0+(?=\d)/,'');
  const signed=BigInt(magnitude)*(m[1]==='-'?-1n:1n);
  if(signed===0n || signed>9223372036854775807n || signed < -9223372036854775808n){
    throw new Error('El importe debe ser distinto de cero y estar dentro del límite monetario.');
  }
  return signed.toString();
}
export function centsAsSoles(value:string):string {
  if(!/^-?(0|[1-9][0-9]*)$/.test(value))return 'Importe no válido';
  const negative=value.startsWith('-'),digits=(negative?value.slice(1):value).padStart(3,'0');
  return (negative?'-':'')+'S/ '+digits.slice(0,-2)+'.'+digits.slice(-2);
}
export function manualPartialDelta(unitPriceCents:string, units:number):string {
  if(!/^[1-9][0-9]*$/.test(unitPriceCents)
    ||!Number.isSafeInteger(units)||units<1) throw new Error('Importe o unidades no válidas.');
  const amount=BigInt(unitPriceCents)*BigInt(units);
  if(amount>9223372036854775807n)throw new Error('Ajuste fuera de límites monetarios.');
  return '-'+amount.toString();
}
