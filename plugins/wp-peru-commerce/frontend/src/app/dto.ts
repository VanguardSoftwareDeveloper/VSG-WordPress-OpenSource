/** Tipos de transporte E-08 / OpenAPI 0.12.0-draft. No son permisos. */
export type SqlId = string;
export type MoneyCents = string;
export type Kind = 'physical' | 'digital' | 'service';

export interface Variant {
  id: SqlId;
  label: string;
  price_cents: MoneyCents;
  sale_price_cents?: MoneyCents;
  sale_active: boolean;
  can_order: boolean;
}
export interface Product {
  id: SqlId;
  title: string;
  kind: Kind;
  description?: string;
  price_cents: MoneyCents;
  sale_price_cents?: MoneyCents;
  sale_active: boolean;
  can_order: boolean;
  variants?: Variant[];
}
export interface ProductPage {
  items: Product[];
  page: number;
  per_page: number;
  has_more: boolean;
}
export interface Slot {
  starts_at_utc: string;
  ends_at_utc: string;
  timezone?: string;
  available: boolean;
}
export interface SlotPage {
  service_id: SqlId;
  date: string;
  slots: Slot[];
}
export interface CartLine {
  sellable_id: SqlId;
  quantity: number;
  slot?: {
    starts_at_utc: string;
    ends_at_utc: string;
    timezone?: string;
  };
}
export interface Contact {
  email?: string;
  phone?: string;
  display_name?: string;
}
export type Delivery =
  | {mode: 'none'}
  | {mode: 'pickup'}
  | {mode: 'delivery'; ubigeo_code: string; address: string; reference?: string};
export interface QuoteLine {
  sellable_id: SqlId;
  quantity: number;
  title?: string;
  unit_price_cents: MoneyCents;
  subtotal_cents: MoneyCents;
}
export interface QuoteResponse {
  quote_fingerprint: string;
  currency: 'PEN';
  lines: QuoteLine[];
  subtotal_cents: MoneyCents;
  delivery_cents: MoneyCents;
  total_cents: MoneyCents;
  requires_confirmation: boolean;
  warnings?: string[];
  policy_version: SqlId;
  privacy_version: SqlId;
  policy_notice: string;
  privacy_notice: string;
}
export interface PublicBootstrap {
  business_name: string;
  locale: 'es-PE';
  currency: 'PEN';
  supports_pickup: boolean;
  supports_delivery: boolean;
  payment_methods: ('mercado_pago'|'cash')[];
  policy_version?: SqlId;
  privacy_version?: SqlId;
  policy_notice?: string;
  privacy_notice?: string;
}
export interface CheckoutRequest {
  quote_fingerprint: string;
  lines: CartLine[];
  contact: Contact;
  delivery?: Delivery;
  idempotency_key: string;
  policy_version: SqlId;
  privacy_version: SqlId;
}
export interface CheckoutMpResponse {
  operation_id: string;
  pending_reconciliation: boolean;
  tracking_link_issued: boolean;
  order_id?: SqlId;
  order_state?: string;
  payment_state?: string;
  checkout_url?: string;
  tracking_url_once?: string;
}
export interface CheckoutCashResponse {
  operation_id: string;
  order_id: SqlId;
  order_state: 'CONFIRMADO';
  payment_due_cents: MoneyCents;
  tracking_link_issued: boolean;
  tracking_url_once?: string;
}
export interface GuestOrderLine {
  line_id: SqlId;
  title: string;
  kind: Kind;
  quantity: number;
  unit_price_cents: MoneyCents;
  fulfillment_state: string;
  physical_quantities?: {
    committed_units: number;
    released_units: number;
    stock_out_units: number;
    pending_stock_out_units: number;
  };
}
export interface GuestOrder {
  order_number: string;
  created_at: string;
  order_state: string;
  currency: 'PEN';
  lines: GuestOrderLine[];
  total_cents: MoneyCents;
  payment_summary: {
    status_label: string;
    amount_paid_cents?: MoneyCents;
    amount_outstanding_cents?: MoneyCents;
  };
  delivery_summary?: string;
  appointment_summary?: string;
  agreed_notices: {
    policy_version: SqlId;
    privacy_version: SqlId;
    policy_notice: string;
    privacy_notice: string;
  };
}
export type GuestPurpose = 'reissue_tracking' | 'delivery_change'
  | 'request_refund' | 'reissue_download' | 'resend_notification';
export interface ChallengeAccepted {accepted: boolean; delivery_hint?: string;}
export interface ActionGrantResult {
  accepted: boolean; scope?: string; grant_expires_at_utc?: string;
}
export interface CaseAccepted {case_id: SqlId; accepted: true; review_required: boolean;}
export interface DigitalSessionAck {right_id: SqlId; expires_at_utc: string;}
export interface AdminBootstrap {
  user_id: SqlId;
  role_label?: 'A'|'O';
  business_name?: string;
  ui_capabilities: string[];
}
export interface DiagnosticsSafe {
  https_available: boolean;
  db_engine: string;
  db_transactions_supported: boolean;
  private_storage_status: string;
  email_transport_status?: string;
  pending_jobs?: number;
}

export function soles(value: MoneyCents): string {
  if (!/^(0|[1-9][0-9]{0,18})$/.test(value)) {
    return 'Importe no disponible';
  }
  const digits=value.padStart(3,'0');
  return 'S/ '+digits.slice(0,-2).replace(/\B(?=(\d{3})+(?!\d))/g,',')
    +'.'+digits.slice(-2);
}
