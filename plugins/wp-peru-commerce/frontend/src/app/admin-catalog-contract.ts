import type { Kind, MoneyCents, SqlId } from './dto';

/** Contratos existentes R8-31…35, R8-48, R8-62/63; sin endpoints nuevos. */
export interface AdminDigitalSlot {
  sellable_id: SqlId;
  slot_key: string;
  active_file_id: SqlId;
  assignment_row_version: SqlId;
  filename?: string;
}

export interface AdminVariant {
  id: SqlId;
  label: string;
  sku?: string;
  price_cents: MoneyCents;
  sale_price_cents?: MoneyCents;
  sale_active: boolean;
  track_stock: boolean;
  service_duration_minutes?: number;
  service_buffer_before_minutes?: number;
  service_buffer_after_minutes?: number;
  active: boolean;
  stock_on_hand: string;
  row_version: SqlId;
  can_order?: boolean;
  /** Exclusivo A; ausente en respuestas para operador O. */
  digital_assignments?: AdminDigitalSlot[];
}

export interface AdminProduct {
  id: SqlId;
  post_id: SqlId;
  title: string;
  kind: Kind;
  visibility: 'draft' | 'hidden' | 'published';
  row_version: SqlId;
  description?: string;
  variants?: AdminVariant[];
  sku?: string;
  price_cents?: MoneyCents;
  sale_price_cents?: MoneyCents;
  sale_active?: boolean;
  track_stock?: boolean;
  service_duration_minutes?: number;
  service_buffer_before_minutes?: number;
  service_buffer_after_minutes?: number;
  stock_on_hand?: string;
  digital_assignments?: AdminDigitalSlot[];
}

export interface AdminCatalogPage {
  items: AdminProduct[];
  page: number;
  per_page: number;
  has_more: boolean;
}

export interface VariantCreate {
  label: string;
  price_cents: MoneyCents;
  sale_active: boolean;
  sku?: string;
  sale_price_cents?: MoneyCents;
  track_stock?: boolean;
  service_duration_minutes?: number;
  service_buffer_before_minutes?: number;
  service_buffer_after_minutes?: number;
  initial_stock_units?: string;
  active?: boolean;
}

export type VariantUpdate = Omit<Partial<VariantCreate>, 'initial_stock_units'> & {id: SqlId};

export interface ProductCreateRequest {
  title: string;
  kind: Kind;
  idempotency_key: string;
  description?: string;
  variants?: VariantCreate[];
  price_cents?: MoneyCents;
  sku?: string;
  sale_price_cents?: MoneyCents;
  sale_active?: boolean;
  track_stock?: boolean;
  initial_stock_units?: string;
  service_duration_minutes?: number;
  service_buffer_before_minutes?: number;
  service_buffer_after_minutes?: number;
}

export interface ProductPatchRequest {
  expected_version: SqlId;
  idempotency_key: string;
  title?: string;
  description?: string;
  variants?: (VariantCreate | VariantUpdate)[];
  price_cents?: MoneyCents;
  sku?: string;
  sale_price_cents?: MoneyCents;
  sale_active?: boolean;
  track_stock?: boolean;
  service_duration_minutes?: number;
  service_buffer_before_minutes?: number;
  service_buffer_after_minutes?: number;
}

export interface VisibilityRequest {
  visibility: 'hidden' | 'published';
  reason: string;
  expected_version: SqlId;
  idempotency_key: string;
}

export interface StockAdjustmentRequest {
  sellable_id: SqlId;
  delta_units: number;
  reason: string;
  expected_version: SqlId;
  idempotency_key: string;
}

export interface MutationResult {
  operation_id: string;
  result: string;
  entity_id?: SqlId;
  row_version?: SqlId;
  public_status?: string;
}

export interface DigitalFileMetadata {
  file_id: SqlId;
  product_id: SqlId;
  filename: string;
  mime_type: string;
  size_bytes: SqlId;
  protected: boolean;
  sellable_id: SqlId;
  slot_key: string;
  assignment_row_version: SqlId;
}

export interface DigitalReplaceResult {
  operation_id: string;
  new_file_id: SqlId;
  old_file_id?: SqlId;
  assignment_row_version: SqlId;
}

/** Formulario expresa importes finales S/ sin operar con float. */
export function centsToSoles(cents: string | undefined): string {
  if (!cents || !/^(0|[1-9][0-9]{0,18})$/.test(cents)) return '0.00';
  const digits = cents.padStart(3, '0');
  return digits.slice(0, -2) + '.' + digits.slice(-2);
}

export function solesToCents(value: string): string {
  const match = /^(0|[1-9][0-9]{0,14})(?:[.,]([0-9]{1,2}))?$/.exec(value.trim());
  if (!match) throw new Error('Importe inválido. Ingresa soles con hasta dos decimales.');
  const cents = (match[1] + (match[2] ?? '').padEnd(2, '0')).replace(/^0+(?=\d)/, '');
  return cents;
}

export function validSqlId(id: string): boolean {
  return /^[1-9][0-9]{0,19}$/.test(id);
}
