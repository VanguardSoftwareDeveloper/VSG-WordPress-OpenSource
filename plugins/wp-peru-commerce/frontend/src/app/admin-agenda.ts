import { Component, computed, inject, input, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, PcApi } from './api';
import type { AdminBootstrap } from './dto';
import {
  type AgendaAppointment, type AgendaClosure, type AgendaView,
  type WeeklyWindow, type ClosureRequest, type ScheduleRequest,
  type RescheduleRequest, type OutcomeRequest, type MutationResult,
  type ScheduleResult, type OutcomeResult, type SlotPage,
  validDate, utcMinute, utcMinuteInput, durationMinutes, labelLocal, todayForZone
} from './admin-agenda-contract';

type Action = 'closure-create'|'closure-update'|'closure-retire'
  |'schedule'|'reschedule'|'outcome';

type RequestBody = ClosureRequest|ScheduleRequest|RescheduleRequest|OutcomeRequest;
interface PendingAction {
  kind:Action;
  resource:string;
  method:'POST'|'PATCH';
  body:RequestBody;
  /** La misma clave/body se reutiliza al reintentar; no mutar este objeto. */
  context:string;
}

const WEEKDAYS = ['','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'] as const;

function requiredReason(text:string):string {
  const cleaned=text.trim();
  if(!cleaned||cleaned.length>1000) {
    throw new Error('Indica un motivo de 1 a 1000 caracteres.');
  }
  return cleaned;
}
function resourceId(id:string):boolean { return /^[1-9][0-9]{0,19}$/.test(id); }
function safeRef(text:string):string {
  const ref=text.trim();
  if(!ref||ref.length>500||ref.includes('://')
    ||/[\x00-\x1f\x7f]/.test(ref)) {
    throw new Error('La referencia de evidencia debe ser opaca y no una URL (máximo 500 caracteres).');
  }
  return ref;
}
function interval(from:string,to:string):[string,string] {
  const start=utcMinute(from),end=utcMinute(to);
  if(new Date(start)>=new Date(end))throw new Error('La hora final UTC debe ser posterior al inicio.');
  return [start,end];
}
function cloneRules(source:WeeklyWindow[]):WeeklyWindow[] {
  return source.map(v=>({...v}));
}

@Component({
  selector:'pc-admin-agenda',
  standalone:true,
  imports:[FormsModule],
  templateUrl:'./admin-agenda.html',
})
export class AdminAgenda implements OnInit {
  readonly employee=input.required<AdminBootstrap>();
  private readonly api=inject(PcApi);
  readonly allowed=computed(()=>['A','O'].includes(this.employee().role_label??'')
    && this.employee().ui_capabilities.includes('perucommerce_appointments_manage'));
  readonly adminOutcome=computed(()=>this.allowed()&&this.employee().role_label==='A');

  readonly view=signal<AgendaView|null>(null);
  readonly selectedAppointment=signal<AgendaAppointment|null>(null);
  readonly selectedClosure=signal<AgendaClosure|null>(null);
  readonly pending=signal<PendingAction|null>(null);
  readonly busy=signal(false);
  readonly loading=signal(false);
  readonly error=signal('');
  readonly notice=signal('');
  readonly availability=signal<SlotPage|null>(null);
  readonly availabilityBusy=signal(false);
  readonly availabilityError=signal('');

  readonly weekdays=WEEKDAYS;
  readonly activeClosures=computed(()=>this.view()?.closures.filter(c=>c.active)??[]);
  readonly local=labelLocal;
  readonly todayForZone=todayForZone;
  readonly zone=computed(()=>this.view()?.timezone??'UTC');

  previewLocal(value:string):string {
    try {return labelLocal(utcMinute(value),this.zone());}
    catch {return 'Completa fecha y hora UTC para ver la conversión.';}
  }

  // Fecha inicial de interfaz para el comercio peruano; la zona IANA
  // real se obtiene de R8-36 y prevalece en todos los cambios posteriores.
  date=todayForZone('America/Lima')||new Date().toISOString().slice(0,10);
  serviceId='';
  closureAction:'create'|'update'|'retire'='create';
  closureStartUtc='';
  closureEndUtc='';
  closureReason='';
  scheduleRules:WeeklyWindow[]=[];
  scheduleEffectiveFrom='';
  scheduleReason='';
  rescheduleStartUtc='';
  rescheduleEndUtc='';
  rescheduleReason='';
  outcome:'REALIZADA'|'NO_ASISTIO'='REALIZADA';
  observedUtc='';
  outcomeEvidence='';

  ngOnInit():void {
    if(this.allowed())void this.refresh();
  }

  async refresh():Promise<void> {
    if(!this.allowed()||this.loading())return;
    if(!validDate(this.date)) {
      this.error.set('Selecciona una fecha válida para consultar la agenda.');
      return;
    }
    this.loading.set(true);this.error.set('');
    try {
      const reply=await this.api.json<AgendaView>('GET','admin/agenda?date='+this.date);
      const result=reply.body;
      if(reply.status!==200||result.resource_id!=='1'
        || !validDate(result.date)||!Array.isArray(result.appointments)
        || !Array.isArray(result.closures)
        || !Array.isArray(result.weekly_schedule?.weekly_rules)
        || result.weekly_schedule.resource_id!==result.resource_id) {
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.view.set(result);
      this.availability.set(null);
      // Una operación incierta conserva su objeto, body, versión y clave.
      // La lectura no interpreta que la operación fue aplicada.
      if(!this.pending()){
        this.scheduleRules=cloneRules(result.weekly_schedule.weekly_rules);
        const today=todayForZone(result.timezone);
        this.scheduleEffectiveFrom =
          result.weekly_schedule.effective_from>today
            ?result.weekly_schedule.effective_from:today;
        this.selectUpdatedItems(result);
      }
      this.notice.set('Agenda consultada para '+result.date+' ('+result.timezone
        +'). Revisa las versiones antes de cambiar horario, cierre o cita.');
    } catch(e){
      // Un GET fallido nunca borra la cita ni la revisión que se consultó.
      this.error.set(this.describe(e));
    }finally{this.loading.set(false);}
  }

  async selectDate(next:string):Promise<void> {
    if(this.pending() || this.busy() || this.loading() || !validDate(next))return;
    this.date=next;
    this.selectedAppointment.set(null);
    this.selectedClosure.set(null);
    await this.refresh();
  }

  private selectUpdatedItems(data:AgendaView):void {
    const appointment=this.selectedAppointment();
    if(appointment){
      const updated=data.appointments.find(a=>a.appointment_id===appointment.appointment_id);
      if(updated){
        if(updated.row_version!==appointment.row_version){
          this.rescheduleStartUtc=utcMinuteInput(updated.starts_at_utc);
          this.rescheduleEndUtc=utcMinuteInput(updated.ends_at_utc);
          this.rescheduleReason='';
          this.observedUtc=new Date().toISOString().slice(0,16);
          this.outcomeEvidence='';
        }
        this.selectedAppointment.set(updated);
      }
    }
    const closure=this.selectedClosure();
    if(closure){
      const updated=data.closures.find(c=>c.closure_id===closure.closure_id);
      if(updated){
        if(updated.row_version!==closure.row_version){
          this.closureStartUtc=utcMinuteInput(updated.starts_at_utc);
          this.closureEndUtc=utcMinuteInput(updated.ends_at_utc);
          this.closureReason='';
        }
        this.selectedClosure.set(updated);
      }
    }
  }

  selectAppointment(value:AgendaAppointment):void {
    if(this.pending()||this.busy())return;
    this.selectedAppointment.set(value);
    this.rescheduleStartUtc=utcMinuteInput(value.starts_at_utc);
    this.rescheduleEndUtc=utcMinuteInput(value.ends_at_utc);
    this.rescheduleReason='';
    this.outcome='REALIZADA';
    this.observedUtc=new Date().toISOString().slice(0,16);
    this.outcomeEvidence='';
    this.error.set('');
  }

  selectClosure(value:AgendaClosure):void {
    if(this.pending()||this.busy()||!value.active)return;
    this.selectedClosure.set(value);
    this.closureAction='update';
    this.closureStartUtc=utcMinuteInput(value.starts_at_utc);
    this.closureEndUtc=utcMinuteInput(value.ends_at_utc);
    this.closureReason='';
    this.error.set('');
  }

  createClosure():void {
    if(this.pending()||this.busy())return;
    this.selectedClosure.set(null);
    this.closureAction='create';
    this.closureStartUtc=this.date+'T09:00';
    this.closureEndUtc=this.date+'T10:00';
    this.closureReason='';
    this.error.set('');
  }

  chooseClosureAction(action:'update'|'retire'):void {
    if(!this.selectedClosure()||this.pending())return;
    this.closureAction=action;
    this.closureReason='';
  }

  addWindow(weekday:number):void {
    if(this.pending()||this.busy()||this.scheduleRules.length>=28
      ||weekday<1||weekday>7)return;
    const existing=this.scheduleRules.filter(x=>x.weekday===weekday);
    const used=new Set(existing.map(x=>x.window_index));
    const index=Array.from({length:28},(_,i)=>i).find(i=>!used.has(i));
    if(index===undefined)return;
    this.scheduleRules.push({
      weekday,window_index:index,enabled:true,
      opens_local:'09:00',closes_local:'17:00',closes_day_offset:0
    });
  }

  executeClosure():Promise<void> {
    const kind:Action= this.closureAction==='create'?'closure-create'
      :this.closureAction==='update'?'closure-update':'closure-retire';
    return this.execute(kind);
  }

  removeWindow(weekday:number,index:number):void {
    if(this.pending()||this.busy()||this.scheduleRules.length<=1)return;
    this.scheduleRules=this.scheduleRules.filter(
      r=>!(r.weekday===weekday&&r.window_index===index)
    );
  }

  private weeklyPayload():WeeklyWindow[] {
    if(this.scheduleRules.length<1||this.scheduleRules.length>28) {
      throw new Error('El horario debe tener entre 1 y 28 ventanas.');
    }
    const keys=new Set<string>();
    return this.scheduleRules.map(v=>{
      if(!Number.isInteger(v.weekday)||v.weekday<1||v.weekday>7
        ||!Number.isInteger(v.window_index)||v.window_index<0||v.window_index>27) {
        throw new Error('Día o índice de ventana inválido.');
      }
      const key=v.weekday+':'+v.window_index;
      if(keys.has(key))throw new Error('Hay dos ventanas con el mismo identificador.');
      keys.add(key);
      if(!v.enabled)return {
        weekday:v.weekday,window_index:v.window_index,
        enabled:false,closes_day_offset:0 as const
      };
      if(!/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(v.opens_local??'')
        ||!/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(v.closes_local??'')
        ||(v.closes_day_offset!==0&&v.closes_day_offset!==1)) {
        throw new Error('Cada ventana activa requiere dos horas locales válidas y un desplazamiento 0/1.');
      }
      const opens_local=v.opens_local!;
      const closes_local=v.closes_local!;
      const minutes=(s:string)=>Number(s.slice(0,2))*60+Number(s.slice(3,5));
      const length=minutes(closes_local)+v.closes_day_offset*1440-minutes(opens_local);
      if(length<=0||length>1440) {
        throw new Error('La ventana debe durar entre 1 minuto y 24 horas. Usa día siguiente para cruzar medianoche.');
      }
      return {
        weekday:v.weekday,window_index:v.window_index,
        enabled:true,closes_day_offset:v.closes_day_offset,
        opens_local,closes_local
      };
    });
  }

  private prepare(kind:Action):PendingAction {
    const view=this.view();
    if(!view||!this.allowed()||view.resource_id!=='1') {
      throw new Error('La agenda no está lista o la sesión carece de permisos.');
    }
    if(view.date!==this.date){
      throw new Error('La fecha solicitada ya no coincide con la última agenda verificada. Consulta la fecha antes de modificar recursos o citas.');
    }
    const idempotency_key=crypto.randomUUID();
    if(kind==='schedule'){
      if(!validDate(this.scheduleEffectiveFrom)) {
        throw new Error('Elige fecha local de inicio válida.');
      }
      const today=todayForZone(view.timezone);
      if(!today||this.scheduleEffectiveFrom<today) {
        throw new Error('La vigencia no puede empezar antes de hoy en el horario del comercio.');
      }
      const body:ScheduleRequest={
        resource_id:view.resource_id,
        effective_from:this.scheduleEffectiveFrom,
        weekly_rules:this.weeklyPayload(),
        expected_version:view.weekly_schedule.row_version,
        reason:requiredReason(this.scheduleReason),
        idempotency_key
      };
      return {kind,resource:'admin/agenda/schedule',method:'PATCH',body,
        context:'Horario semanal del recurso 1, versión '+body.expected_version};
    }
    if(kind.startsWith('closure-')){
      const action=kind.slice('closure-'.length) as 'create'|'update'|'retire';
      const base={
        resource_id:view.resource_id,
        reason:requiredReason(this.closureReason),idempotency_key
      };
      let body:ClosureRequest;
      if(action==='create'){
        const [starts_at,ends_at]=interval(this.closureStartUtc,this.closureEndUtc);
        body={...base,action,starts_at,ends_at};
      }else{
        const closure=this.selectedClosure();
        if(!closure||!closure.active
          || !view.closures.some(c=>c.closure_id===closure.closure_id&&c.active)) {
          throw new Error('El cierre ya no aparece como activo en esta fecha. Consulta el intervalo actualizado.');
        }
        if(action==='retire'){
          body={...base,action,closure_id:closure.closure_id,
            expected_version:closure.row_version};
        }else{
          const [starts_at,ends_at]=interval(this.closureStartUtc,this.closureEndUtc);
          body={...base,action,closure_id:closure.closure_id,
            expected_version:closure.row_version,starts_at,ends_at};
        }
      }
      return {kind,resource:'admin/agenda/closures',method:'POST',body,
        context:'Cierre '+(this.selectedClosure()?.closure_id??'nuevo')
          +' · '+(this.selectedClosure()?.row_version??'sin versión previa')};
    }
    const appointment=this.selectedAppointment();
    if(!appointment || appointment.state!=='CONFIRMADA'
      || !view.appointments.some(a=>a.appointment_id===appointment.appointment_id&&a.state==='CONFIRMADA')) {
      throw new Error('La cita no figura confirmada en esta fecha. Consulta la fecha nueva y la versión real antes de actuar.');
    }
    const route='admin/appointments/'+appointment.appointment_id
      + (kind==='reschedule'?'/reschedule':'/outcome');
    if(kind==='reschedule'){
      const [new_start,new_end]=interval(this.rescheduleStartUtc,this.rescheduleEndUtc);
      const original=durationMinutes(appointment.starts_at_utc,appointment.ends_at_utc);
      if(original!==durationMinutes(new_start,new_end)
        ||(new_start===new Date(appointment.starts_at_utc).toISOString()
           &&new_end===new Date(appointment.ends_at_utc).toISOString())) {
        throw new Error('Cambia la franja sin alterar la duración original de la cita.');
      }
      const body:RescheduleRequest={
        new_start,new_end,reason:requiredReason(this.rescheduleReason),
        expected_version:appointment.row_version,idempotency_key
      };
      return {kind,resource:route,method:'POST',body,
        context:'Cita '+appointment.appointment_id+' versión '+appointment.row_version};
    }
    if(!this.adminOutcome())throw new Error('Solo administrador A puede constatar un resultado.');
    const body:OutcomeRequest={
      outcome:this.outcome,
      observed_at_utc:utcMinute(this.observedUtc),
      evidence_ref:safeRef(this.outcomeEvidence),
      expected_version:appointment.row_version,idempotency_key
    };
    if(new Date(body.observed_at_utc)>new Date()
      || new Date(body.observed_at_utc)<new Date(appointment.ends_at_utc)) {
      throw new Error('La observación debe ser posterior al fin de la cita y no puede estar en el futuro.');
    }
    return {kind,resource:route,method:'POST',body,
      context:'Resultado '+appointment.appointment_id+' versión '+appointment.row_version};
  }

  async execute(kind:Action):Promise<void> {
    if(!this.allowed()||this.busy()||this.loading())return;
    let pending=this.pending();
    if(pending && pending.kind!==kind) {
      this.error.set('Existe otro intento sin resultado definitivo. Verifícalo o descártalo expresamente antes de otra acción.');
      return;
    }
    try{
      if(!pending){
        pending=this.prepare(kind);
        this.pending.set(pending);
      }
    }catch(e){
      this.error.set(this.describe(e));
      return;
    }
    this.busy.set(true);
    this.error.set('');
    this.notice.set('Procesando '+pending.context+'. No se considera éxito hasta respuesta 200.');
    try{
      const reply=await this.api.json<MutationResult|ScheduleResult|OutcomeResult>(
        pending.method,pending.resource,pending.body
      );
      if(reply.status!==200||typeof reply.body.operation_id!=='string'
        ||reply.body.operation_id.length===0){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      const completed=pending.kind;
      const details=pending.context;
      this.pending.set(null);
      // No deducir que las citas cambiaron por un 200 genérico: el
      // servidor confirma la operación, luego se relee R8-36.
      if(completed==='reschedule'||completed==='outcome'){
        this.selectedAppointment.set(null);
      }
      if(completed.startsWith('closure-')){
        this.selectedClosure.set(null);
        this.closureReason='';
        this.closureAction='create';
      }
      if(completed==='schedule')this.scheduleReason='';
      await this.refresh();
      this.notice.set('Operación confirmada por el servidor: '+details
        +'. Consulta la agenda actualizada. No se han modificado citas automáticamente por una revisión semanal.');
    }catch(e){
      if(e instanceof ApiFailure && [400,401,403,404,410,422,423].includes(e.status)){
        // Rechazo explícito de la API: permitir corregir los campos.
        this.pending.set(null);
      }
      this.error.set(this.describe(e)
        +(e instanceof ApiFailure&&e.status===409
          ?' Conflicto de franja o versión: consulta la agenda, compara la fila y decide un nuevo intento. No se reemplazó silenciosamente.'
          :' Si la respuesta es incierta, conserva el mismo intento o consulta la agenda antes de descartarlo.'));
    }finally{this.busy.set(false);}
  }

  abandon():void {
    if(this.busy()||this.loading())return;
    this.pending.set(null);
    this.notice.set('Descartaste el intento solo en esta pantalla. Revisa historial, recurso y versiones antes de generar otra clave.');
  }

  async loadAvailability():Promise<void> {
    if(!this.allowed()||this.availabilityBusy())return;
    if(!resourceId(this.serviceId) || !validDate(this.date)){
      this.availabilityError.set('Ingresa ID real de una variante de servicio publicada y fecha válida.');
      return;
    }
    this.availabilityBusy.set(true);
    this.availabilityError.set('');
    this.availability.set(null);
    const requestedDate=this.date;
    const requestedService=this.serviceId;
    try{
      const reply=await this.api.json<SlotPage>('GET',
        'public/services/'+requestedService+'/slots?date='+requestedDate);
      if(requestedDate!==this.date||requestedService!==this.serviceId)return;
      if(reply.status!==200||reply.body.service_id!==requestedService
        ||reply.body.date!==requestedDate||!Array.isArray(reply.body.slots)){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.availability.set(reply.body);
    }catch(e){
      this.availabilityError.set(this.describe(e)
        +' La consulta de franjas no reserva ni garantiza disponibilidad.');
    }finally{this.availabilityBusy.set(false);}
  }

  private describe(e:unknown):string {
    return e instanceof ApiFailure?e.message
      :e instanceof Error?e.message:'No se pudo verificar la operación.';
  }
}
