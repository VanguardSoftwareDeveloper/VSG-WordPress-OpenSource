/**
 * IMP-62 — Tipos de AgendaView y mutaciones del OpenAPI 0.12.0-draft.
 * El recurso de agenda es único. Los DTO de lectura no son permisos.
 */
export type SqlId = string;
export type AppointmentOutcome = 'REALIZADA' | 'NO_ASISTIO';

export interface WeeklyWindow {
  weekday: number; // ISO 1=lunes…7=domingo
  window_index: number; // 0..27 por día
  enabled: boolean;
  closes_day_offset: 0 | 1;
  opens_local?: string; // HH:mm; solo si enabled
  closes_local?: string;
}

export interface WeeklyScheduleView {
  resource_id: SqlId;
  row_version: SqlId; // del ancla pc_schedule_revisions
  active_revision_no: SqlId; // versión de la revisión efectiva por fecha
  effective_from: string; // fecha local comercial
  timezone: string; // IANA
  weekly_rules: WeeklyWindow[];
}

export interface AgendaAppointment {
  appointment_id: SqlId;
  starts_at_utc: string;
  ends_at_utc: string;
  row_version: SqlId;
  state: string;
  service_label?: string;
  contact_for_task?: string; // solo proyección autorizada R8-36
}

export interface AgendaClosure {
  closure_id: SqlId;
  starts_at_utc: string;
  ends_at_utc: string;
  row_version: SqlId;
  active: boolean;
  reason_code?: string;
}

export interface AgendaView {
  resource_id: SqlId;
  date: string;
  timezone: string;
  appointments: AgendaAppointment[];
  closures: AgendaClosure[];
  weekly_schedule: WeeklyScheduleView;
}

export interface SlotPage {
  service_id: SqlId;
  date: string;
  slots: {
    starts_at_utc: string;
    ends_at_utc: string;
    timezone?: string;
    available: boolean;
  }[];
}

export interface MutationResult {
  operation_id: string;
  entity_id?: SqlId;
  row_version?: SqlId;
  result: string;
  public_status?: string;
}

export type ClosureRequest =
  | {
    action: 'create'; resource_id: SqlId; starts_at: string; ends_at: string;
    reason: string; idempotency_key: string;
  }
  | {
    action: 'update'; resource_id: SqlId; closure_id: SqlId;
    expected_version: SqlId; starts_at: string; ends_at: string;
    reason: string; idempotency_key: string;
  }
  | {
    action: 'retire'; resource_id: SqlId; closure_id: SqlId;
    expected_version: SqlId; reason: string; idempotency_key: string;
  };

export interface ScheduleRequest {
  resource_id: SqlId;
  effective_from: string;
  weekly_rules: WeeklyWindow[];
  reason: string;
  expected_version: SqlId;
  idempotency_key: string;
}
export interface ScheduleResult {
  resource_id: SqlId;
  row_version: SqlId;
  effective_from: string;
  operation_id: string;
}

export interface RescheduleRequest {
  new_start: string;
  new_end: string;
  reason: string;
  expected_version: SqlId; // versión pc_appointments
  idempotency_key: string;
}
export interface OutcomeRequest {
  outcome: AppointmentOutcome;
  observed_at_utc: string;
  evidence_ref: string;
  expected_version: SqlId;
  idempotency_key: string;
}
export interface OutcomeResult {
  operation_id: string;
  result: string;
  row_version?: SqlId;
}

/** Aceptar únicamente días civiles reales, no fechas normalizadas por JS. */
export function validDate(value: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value) || Number(value.slice(0,4)) < 1000) return false;
  const date = new Date(value + 'T00:00:00.000Z');
  return !Number.isNaN(date.valueOf()) && date.toISOString().slice(0, 10) === value;
}

/**
 * El control datetime-local se usa explícitamente como entrada UTC.
 * NO reinterpretar las horas en zona del navegador ni inventar offset en
 * días con DST: el servidor exige RFC3339 con offset inequívoco.
 */
export function utcMinute(value: string): string {
  if (!/^\d{4}-\d{2}-\d{2}T(?:[01]\d|2[0-3]):[0-5]\d$/.test(value)) {
    throw new Error('Indica fecha y hora UTC completas.');
  }
  if (Number(value.slice(0,4)) < 1000) throw new Error('La fecha UTC debe corresponder a un año válido.');
  const date = new Date(value + ':00.000Z');
  if (Number.isNaN(date.valueOf()) || date.toISOString().slice(0, 16) !== value) {
    throw new Error('La fecha u hora UTC no es válida.');
  }
  return date.toISOString();
}

export function utcMinuteInput(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.valueOf())) return '';
  return date.toISOString().slice(0, 16);
}

export function durationMinutes(start: string, end: string): number {
  return (new Date(end).valueOf() - new Date(start).valueOf()) / 60000;
}

export function labelLocal(value: string, timeZone: string): string {
  try {
    const instant = new Date(value);
    if (Number.isNaN(instant.valueOf())) return 'Fecha no disponible';
    return new Intl.DateTimeFormat('es-PE', {
      dateStyle: 'medium', timeStyle: 'short', timeZone
    }).format(instant);
  } catch {
    return value;
  }
}

export function todayForZone(zone: string): string {
  try {
    const parts = new Intl.DateTimeFormat('en', {
      timeZone: zone, year: 'numeric', month: '2-digit', day: '2-digit'
    }).formatToParts(new Date());
    const part = (name: string) => parts.find(p => p.type === name)?.value ?? '';
    const result = part('year') + '-' + part('month') + '-' + part('day');
    if (validDate(result)) return result;
  } catch { /* La zona IANA procede del servidor; bloquear si falta. */ }
  return '';
}
