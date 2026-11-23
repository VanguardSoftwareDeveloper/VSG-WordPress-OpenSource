/**
 * IMP-65 — DTO vigentes OpenAPI 0.12.0-draft, R8-15/41/42/43/44/50/65…68.
 * Las referencias privadas NO son URL, token ni contraseña; backend autoriza
 * actor A + caso + finalidad para cada lectura.
 */
import type { SqlId } from './dto';

export interface Page<T> {
  items: T[];
  page: number;
  per_page: number;
  has_more: boolean;
}
export type ComplaintKind = 'reclamo' | 'queja';
export interface ComplaintSummary {
  id: SqlId;
  receipt_number: string;
  kind: ComplaintKind;
  submitted_at: string;
  responded_at?: string;
  customer_name?: string; // el backend lo omite por defecto en lista
}
export type ComplaintPage = Page<ComplaintSummary>;
export interface ComplaintParty {
  full_name: string;
  identification_kind?: string;
  identification_value?: string;
  address?: string;
  phone?: string;
  email?: string;
}
export interface ComplaintDetail {
  id: SqlId;
  receipt_number: string;
  kind: ComplaintKind;
  submitted_at: string;
  row_version: SqlId;
  due_at?: string;
  merchant?: {name?: string; tax_id?: string; address?: string};
  claimant: ComplaintParty;
  representative?: ComplaintParty;
  subject: 'producto'|'servicio';
  subject_description?: string;
  claimed_amount_cents?: string;
  description: string;
  request: string;
  response_text?: string;
  responded_at?: string;
  response_channel?: string;
  response_evidence_ref?: string;
  delivery_confirmed_at?: string;
  attachments?: string[];
  privacy_notice_version?: string;
}
export interface ComplaintRespond {
  response_text: string;
  expected_version: SqlId;
  idempotency_key: string;
  evidence?: string;
}
export interface MutationResult {
  operation_id: string;
  result: string;
  entity_id?: SqlId;
  row_version?: SqlId;
  public_status?: string;
}

export type PrivacyRequestType = 'access'|'rectification'|'cancellation'|'opposition';
export type PrivacyVerification = 'pending'|'verified'|'insufficient';
export type PrivacyDecision = 'fulfilled'|'partially_fulfilled'|'rejected'|'retained_due_to_legal_obligation';
export interface PrivacyCaseSummary {
  id: SqlId;
  request_type: PrivacyRequestType;
  received_at: string;
  due_at?: string;
  has_verified_identity?: boolean;
}
export type PrivacyPage = Page<PrivacyCaseSummary>;
export interface PrivacyExportOperation {
  operation_id: string;
  status: 'pending'|'ready'|'failed'|'expired';
  evidence_ref?: string;
  expires_at_utc?: string;
  failure_code?: string;
}
export interface PrivacyRequestDetail {
  id: SqlId;
  request_type: PrivacyRequestType;
  received_at: string;
  due_at?: string;
  row_version: SqlId;
  verification_status: PrivacyVerification;
  verified_at?: string;
  applicant_name?: string;
  request_summary?: string;
  channel?: string;
  identity_evidence_ref?: string;
  representation_evidence_ref?: string;
  legal_basis?: string;
  decision?: PrivacyDecision;
  resolved_at?: string;
  response_channel?: string;
  response_evidence_ref?: string;
  evidence_refs?: string[];
  export_operations?: PrivacyExportOperation[];
}
export interface PrivacyResolution {
  resolution: PrivacyDecision;
  legal_basis: string;
  expected_version: SqlId;
  idempotency_key: string;
  evidence?: string;
}
export type PrivacyExportScope = 'subject_verified'|'order_legal_copy';
export interface PrivacyExportRequest {
  scope: PrivacyExportScope;
  reason: string;
  verified_identity: string;
  privacy_request_id: SqlId;
  expected_version: SqlId;
  idempotency_key: string;
  order_id?: SqlId;
}
export interface PrivacyExportAck {
  operation_id: string;
  accepted: boolean;
  expires_at_utc?: string;
  privacy_request_id: SqlId;
}
export type PrivateEvidencePurpose =
  | 'legal_case_review'|'legal_response_evidence'
  | 'privacy_identity_review'|'privacy_resolution_review'
  | 'privacy_case_audit'|'privacy_export_download';

export function validId(id:string):boolean {
  return /^[1-9][0-9]{0,19}$/.test(id);
}
export function privateRef(ref:string):boolean {
  return /^[A-Za-z0-9_-]{16,128}$/.test(ref);
}
export function responseText(input:string,limit:number):string {
  const value=input.trim();
  if(!value || value.length>limit
      || /[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/.test(value)){
    throw new Error('Texto requerido, sin caracteres de control y con máximo '+limit+' caracteres.');
  }
  return value;
}
export function legalBasis(input:string):string {
  const value=input.trim();
  if(!value || value.length>400 || /[\u0000-\u001f\u007f]/.test(value)) {
    throw new Error('Indica un fundamento jurídico válido de hasta 400 caracteres, en una línea.');
  }
  return value;
}
export function staffDeclaration(input:string):string {
  const value=input.trim();
  if(!value || value.length>200 || /[\u0000-\u001f\u007f]/.test(value)){
    throw new Error('Referencia de identidad declarada de hasta 200 caracteres, sin datos personales crudos ni URL.');
  }
  return value;
}
export function exactDate(value:string):string {
  const d=new Date(value);
  return Number.isNaN(d.valueOf())?'No disponible':
    new Intl.DateTimeFormat('es-PE',{dateStyle:'medium',timeStyle:'short',timeZone:'America/Lima'}).format(d);
}
