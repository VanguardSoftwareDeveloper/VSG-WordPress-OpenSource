/**
 * IMP-63. Contratos existentes R8-20/21/22/23/24/28/55,
 * derivados de OpenAPI 0.12.0-draft. No hay endpoints nuevos.
 * Nunca inferir permiso de cancelación, cobro o entrega desde un DTO.
 */
import type { Kind, MoneyCents, SqlId } from './dto';

export type OrderState =
  | 'PENDIENTE_PAGO' | 'CONFIRMADO' | 'FINALIZADO'
  | 'CANCELADO' | 'VENCIDO';
export type PhysicalState =
  | 'PENDIENTE' | 'PREPARANDO' | 'LISTO'
  | 'DESPACHADO' | 'ENTREGADO' | 'CANCELADO';
export type FulfillmentTarget =
  | 'PREPARANDO' | 'LISTO' | 'DESPACHADO' | 'ENTREGADO';
export type OrderPaymentIndicator =
  | 'PENDIENTE_COBRO' | 'ACREDITADO' | 'SIN_COBRO'
  | 'CONCILIACION_PENDIENTE';

export interface TaskContact {
  phone?: string;
  email?: string;
  recipient?: string;
  address?: string;
}

export interface AdminOrderSummary {
  id: SqlId;
  order_number: string;
  order_state: string;
  created_at: string;
  total_cents: MoneyCents;
  payment_indicator: string;
  fulfillment_summary?: string;
  /** El backend omite el contacto en listados masivos para O. */
  contact_for_task?: TaskContact;
}

export interface AdminOrdersPage {
  items: AdminOrderSummary[];
  page: number;
  per_page: number;
  has_more: boolean;
}

export interface PhysicalQuantities {
  committed_units: number;
  released_units: number;
  stock_out_units: number;
  pending_stock_out_units: number;
}

export interface AdminOrderLine {
  line_id: SqlId;
  title: string;
  kind: Kind;
  /** Cantidad histórica pactada, jamás saldo pendiente de despacho. */
  quantity: number;
  unit_price_cents: MoneyCents;
  fulfillment_state: string;
  row_version: SqlId;
  /** Solo líneas físicas: salidas y liberaciones históricas. */
  physical_quantities?: PhysicalQuantities;
}

export interface OrderAgreedNotices {
  policy_version: SqlId;
  privacy_version: SqlId;
  policy_notice: string;
  privacy_notice: string;
}

export interface OperationalEvent {
  event: string;
  at: string;
}

export interface AdminCaseSummary {
  case_id: SqlId;
  order_line_id?: SqlId;
  case_type: string;
  case_phase: string;
  reason_code: string;
  row_version: SqlId;
  opened_at: string;
  last_decision_code?: string;
}

export interface AdminOrder {
  id: SqlId;
  order_number: string;
  order_state: string;
  row_version: SqlId;
  lines: AdminOrderLine[];
  total_cents: MoneyCents;
  payment_indicator: string;
  agreed_notices: OrderAgreedNotices;
  contact_for_task?: TaskContact;
  delivery_snapshot?: string;
  operational_events?: OperationalEvent[];
  /** Expedientes privados proyectados exclusivamente cuando corresponde a A. */
  cases?: AdminCaseSummary[];
  // Los campos financieros, fiscales y de derechos no se necesitan aquí;
  // la API los filtra por finalidad/capacidad y se gestionan en IMP-64.
}

export interface MutationResult {
  operation_id: string;
  entity_id?: SqlId;
  row_version?: SqlId;
  result: string;
  public_status?: string;
}

export interface FulfillmentChangeRequest {
  target: FulfillmentTarget;
  expected_version: SqlId; // versión de pc_order_lines, no del pedido
  idempotency_key: string;
  evidence?: string; // obligatorio para DESPACHADO/ENTREGADO
}

export interface CancelLineRequest {
  expected_version: SqlId; // versión de la línea
  reason: string;
  idempotency_key: string;
  case_id?: SqlId; // casos financieros requieren expedientes IMP-64
}

export interface CashReceiptRequest {
  amount_cents: MoneyCents;
  expected_version: SqlId; // versión de pc_orders
  idempotency_key: string;
}

export interface DeliveryChangeRequest {
  new_ubigeo: string;
  new_address: string;
  reason: string;
  expected_version: SqlId; // versión de pc_orders
  idempotency_key: string;
}

export interface LogisticsIncidentRequest {
  reason: string;
  evidence_ref: string;
  expected_version: SqlId; // versión de pc_order_lines
  idempotency_key: string;
}

export interface LogisticsIncidentAccepted {
  case_id: SqlId;
  operation_id: string;
  row_version: SqlId;
  case_phase?: string;
}

export type OrderAction =
  | 'fulfillment' | 'cancel' | 'cash' | 'delivery' | 'incident';

export type OrderActionPayload =
  | FulfillmentChangeRequest | CancelLineRequest
  | CashReceiptRequest | DeliveryChangeRequest
  | LogisticsIncidentRequest;

export interface PendingOrderAction {
  action: OrderAction;
  method: 'POST';
  resource: string;
  payload: OrderActionPayload;
  successStatus: 200 | 201;
  orderId: SqlId;
  lineId?: SqlId;
  description: string;
}

/** Las transiciones son sugerencias de UI, no verificaciones de recurso. */
export function physicalTargets(state: string): FulfillmentTarget[] {
  switch (state) {
    case 'PENDIENTE': return ['PREPARANDO','LISTO'];
    case 'PREPARANDO': return ['LISTO'];
    case 'LISTO': return ['DESPACHADO','ENTREGADO'];
    case 'DESPACHADO': return ['ENTREGADO'];
    default: return [];
  }
}

/**
 * Invariante contractual:
 * committed >= released + out; pending = committed - released - out.
 * Nunca usar quantity como "por preparar/despachar".
 */
export function physicalQuantitiesValid(line: AdminOrderLine): boolean {
  const q = line.physical_quantities;
  if (line.kind !== 'physical') return q === undefined;
  if (!q || !Number.isInteger(line.quantity) || line.quantity < 1) return false;
  const values = [
    q.committed_units, q.released_units,
    q.stock_out_units, q.pending_stock_out_units
  ];
  if (!values.every(n => Number.isSafeInteger(n) && n >= 0)) return false;
  return q.committed_units <= line.quantity
    && q.released_units + q.stock_out_units <= q.committed_units
    && q.pending_stock_out_units
      === q.committed_units - q.released_units - q.stock_out_units;
}

/**
 * El importe manual es exacto en PEN, sin floats, formateo de miles,
 * cambios de moneda ni redondeo. No se calcula el saldo del pedido aquí.
 */
export function cashDecimalToCents(display: string): string {
  const m = /^(0|[1-9][0-9]{0,14})(?:[.,]([0-9]{1,2}))?$/.exec(display.trim());
  if (!m) throw new Error('Ingresa un importe válido en soles con hasta dos decimales.');
  const value = (m[1] + (m[2] ?? '').padEnd(2,'0')).replace(/^0+(?=[0-9])/,'');
  if (value === '0') throw new Error('El importe recibido debe ser positivo.');
  if (BigInt(value) > 9223372036854775807n) {
    throw new Error('El importe supera el límite admitido.');
  }
  return value;
}

export function reasonText(raw: string): string {
  const text = raw.trim();
  if (!text || text.length > 1000 || /[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/.test(text)) {
    throw new Error('Es obligatorio indicar un motivo válido de hasta 1000 caracteres.');
  }
  return text;
}

export function opaqueEvidence(raw: string): string {
  const text = raw.trim();
  if (!text || text.length > 500 || /[\u0000-\u001f\u007f]/.test(text)
    || text.includes('://')) {
    throw new Error('La evidencia debe ser una referencia privada opaca de hasta 500 caracteres, no una URL.');
  }
  return text;
}
