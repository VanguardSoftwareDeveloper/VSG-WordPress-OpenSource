/**
 * IMP-66 — Contratos administrativos R8-39/40/45/46/47/49/51/52.
 * Pistas UI: cada ruta vuelve a comprobar rol, versión, recurso y gates PHP.
 */
import type { SqlId, MoneyCents } from './dto';

export type FiscalRegime='new_rus'|'rer'|'rmt'|'general'|'fourth_category'|'unconfigured';
export type DocumentType='boleta'|'factura'|'rhe'|'ticket';

export interface BusinessSettings {
  business_name:string;
  complaint_mode:'native'|'external';
  business_contact?:string;
  complaint_url?:string;
  privacy_url?:string;
  legal_name?:string;
  tax_id_ruc?:string;
  premises_address?:string;
}
export interface FiscalSettings {
  regime?:FiscalRegime;
  external_document_types?:DocumentType[];
}
export interface MapTilesConfig {
  enabled:boolean;
  tile_url_template?:string;
  attribution_text?:string;
  attribution_url?:string;
}
export interface MailSender {
  from_name?:string;
  from_email?:string;
  signature?:string;
}
export interface AdminSettings {
  business:BusinessSettings;
  fiscal:FiscalSettings;
  row_version:SqlId;
  delivery_enabled?:boolean;
  payment_enabled?:boolean;
  provider_configured?:boolean;
  webhook_connected?:boolean;
  policy_version?:SqlId;
  privacy_version?:SqlId;
  policy_notice?:string;
  privacy_notice?:string;
  digital_download_limit:number|null;
  map_tiles:MapTilesConfig;
  mail_sender?:MailSender;
  routine_buyer_emails_enabled:boolean;
}
export interface SettingsPatch {
  expected_version:SqlId;
  idempotency_key:string;
  business?:BusinessSettings;
  fiscal?:FiscalSettings;
  delivery_enabled?:boolean;
  payment_enabled?:boolean;
  policy_notice?:string;
  privacy_notice?:string;
  digital_download_limit?:number|null;
  map_tiles?:MapTilesConfig;
  mail_sender?:MailSender;
  routine_buyer_emails_enabled?:boolean;
}
export interface PaymentCredentialUpdate {
  environment:'test'|'production';
  access_token:string;
  webhook_secret?:string;
  expected_version:SqlId;
  idempotency_key:string;
}
export interface MutationResult {
  operation_id:string;
  result:string;
  entity_id?:SqlId;
  row_version?:SqlId;
  public_status?:string;
}
export interface CoverageEntry {
  ubigeo_code:string;
  enabled:boolean;
  fee_cents:MoneyCents;
}
export interface CoverageView {
  items:CoverageEntry[];
  row_version:SqlId;
  dataset_version?:string;
}
export interface CoveragePatch {
  entries:CoverageEntry[];
  expected_version:SqlId;
  idempotency_key:string;
}
export interface NotificationEntry {
  id:SqlId;
  event_code:string;
  priority:'high'|'normal'|'low';
  transport_indicator?:string;
  created_at:string;
  can_resend:boolean;
  row_version:SqlId;
  resource_type?:'order'|'appointment'|'case'|'catalog'|'integration';
  resource_id?:SqlId;
}
export interface NotificationPage {
  items:NotificationEntry[];
  page:number;
  per_page:number;
  has_more:boolean;
}
export interface NotificationResend {
  reason:string;
  expected_version:SqlId;
  idempotency_key:string;
}
export interface DiagnosticsSafe {
  https_available:boolean;
  db_engine:string;
  db_transactions_supported:boolean;
  private_storage_status:string;
  email_transport_status?:string;
  pending_jobs?:number;
}

export function validSqlId(id:string):boolean {
  return /^[1-9][0-9]{0,19}$/.test(id);
}
export function reasonText(text:string):string {
  const x=text.trim();
  if(!x||x.length>1000||/[\u0000-\u001f\u007f]/.test(x)){
    throw new Error('El motivo es obligatorio (máximo 1000 caracteres, sin controles ni saltos de línea).');
  }
  return x;
}
export function fieldText(text:string,max:number,label:string):string {
  const x=text.trim();
  if(!x||x.length>max||/[\u0000-\u001f\u007f]/.test(x)){
    throw new Error(label+': indica un valor válido de hasta '+max+' caracteres.');
  }
  return x;
}
export function moneyToSoles(cents:string):string {
  if(!/^(0|[1-9][0-9]{0,18})$/.test(cents))return '';
  const d=cents.padStart(3,'0');
  return d.slice(0,-2)+'.'+d.slice(-2);
}
export function solesToMoney(raw:string,allowZero=true):string {
  const m=/^(0|[1-9][0-9]{0,14})(?:[.,]([0-9]{1,2}))?$/.exec(raw.trim());
  if(!m)throw new Error('Importe en soles inválido: usa hasta dos decimales.');
  const cents=(m[1]+(m[2]??'').padEnd(2,'0')).replace(/^0+(?=\d)/,'');
  if(!allowZero&&cents==='0')throw new Error('El importe debe ser positivo.');
  if(BigInt(cents)>9223372036854775807n)throw new Error('Importe fuera del límite.');
  return cents;
}
export function limitInput(raw:string):number|null {
  const v=raw.trim();
  if(v==='')return null;
  if(!/^[1-9][0-9]{0,5}$/.test(v)||Number(v)>100000){
    throw new Error('La cuota debe ser de 1 a 100000, o dejarse vacía para ilimitada.');
  }
  return Number(v);
}
export function safeUrl(raw:string,max:number,label:string):string {
  const value=fieldText(raw,max,label);
  if(!value.startsWith('https://')||/[\s\u0000-\u001f\u007f]/.test(value)){
    throw new Error(label+': se requiere HTTPS sin espacios ni controles.');
  }
  return value;
}
