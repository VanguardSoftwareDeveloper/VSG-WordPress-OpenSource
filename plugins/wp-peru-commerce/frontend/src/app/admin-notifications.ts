import { Component, computed, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, PcApi } from './api';
import type { AdminBootstrap } from './dto';
import {
  type NotificationPage, type NotificationEntry, type NotificationResend,
  type MutationResult, validSqlId, reasonText
} from './admin-config-contract';

interface PendingResend {
  id:string;
  body:NotificationResend;
}
function describe(e:unknown):string {
  return e instanceof ApiFailure?e.message
    :e instanceof Error?e.message:'No se pudo comprobar la intención de aviso.';
}

@Component({
  selector:'pc-admin-notifications',standalone:true,imports:[FormsModule],
  templateUrl:'./admin-notifications.html'
})
export class AdminNotifications implements OnInit {
  readonly employee=input.required<AdminBootstrap>();
  readonly blocked=input(false);
  readonly pendingChange=output<boolean>();
  private readonly api=inject(PcApi);
  readonly allowed=computed(()=>['A','O'].includes(this.employee().role_label??'')
    &&this.employee().ui_capabilities.includes('perucommerce_notifications_operate'));
  readonly entries=signal<NotificationEntry[]>([]);
  readonly page=signal(1);
  readonly more=signal(false);
  readonly search=signal('');
  readonly visible=computed(()=>{
    const term=this.search().trim().toLocaleLowerCase('es-PE');
    return term?this.entries().filter(x=>x.event_code.toLocaleLowerCase('es-PE').includes(term)
      ||x.id.includes(term)):this.entries();
  });
  readonly selected=signal<NotificationEntry|null>(null);
  readonly loading=signal(false);
  readonly busy=signal(false);
  readonly pending=signal<PendingResend|null>(null);
  readonly freshRequired=signal(false);
  readonly error=signal('');
  readonly notice=signal('');
  reason='';

  ngOnInit():void{if(this.allowed())void this.load(1);}

  async load(page=this.page()):Promise<void>{
    if(!this.allowed()||this.loading()||this.busy())return;
    this.loading.set(true);this.error.set('');
    try{
      const reply=await this.api.json<NotificationPage>('GET',
        'admin/notifications?page='+page+'&per_page=20');
      const result=reply.body;
      if(reply.status!==200||!Array.isArray(result.items)
        ||result.items.length>100||!Number.isInteger(result.page)
        ||typeof result.has_more!=='boolean'
        ||result.items.some(x=>!validSqlId(x.id)
          ||!validSqlId(x.row_version)||typeof x.can_resend!=='boolean')){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.entries.set(result.items);
      this.page.set(result.page);this.more.set(result.has_more);
      if(!this.pending()){
        this.freshRequired.set(false);
        const sel=this.selected();
        this.selected.set(sel?result.items.find(x=>x.id===sel.id)??null:null);
      }
      this.notice.set('Bandeja consultada. Los indicadores son de transporte/intención, no de lectura o recepción comprobada.');
    }catch(e){this.error.set(describe(e));}
    finally{this.loading.set(false);}
  }
  async movePage(offset:-1|1):Promise<void>{
    if(this.pending()||this.busy()||this.loading()
      ||(offset===-1&&this.page()<=1)||(offset===1&&!this.more()))return;
    this.selected.set(null);
    await this.load(this.page()+offset);
  }
  choose(x:NotificationEntry):void {
    if(this.busy()||this.blocked()||this.pending()||this.freshRequired()||!x.can_resend)return;
    this.selected.set(x);this.reason='';this.error.set('');
  }
  private makeAttempt():PendingResend {
    const x=this.selected();
    if(!this.allowed()||!x||!x.can_resend||this.freshRequired()
      ||!this.entries().some(e=>e.id===x.id&&e.row_version===x.row_version)){
      throw new Error('Selecciona una intención EMAIL existente y autorizada para reenvío. Nunca recrear un aviso omitido por preferencia.');
    }
    return {
      id:x.id,
      body:{reason:reasonText(this.reason),expected_version:x.row_version,
        idempotency_key:crypto.randomUUID()}
    };
  }
  async resend():Promise<void>{
    if(!this.allowed()||this.busy()||this.loading()||this.blocked())return;
    let attempt=this.pending();
    if(!attempt){
      try{attempt=this.makeAttempt();this.pending.set(attempt);this.pendingChange.emit(true);}
      catch(e){this.error.set(describe(e));return;}
    }
    this.busy.set(true);this.error.set('');
    try{
      const reply=await this.api.json<MutationResult>('POST',
        'admin/notifications/'+attempt.id+'/resend',attempt.body);
      if(reply.status!==200||!reply.body.operation_id
        ||reply.body.result!=='notification_requeued'
        ||!validSqlId(reply.body.row_version??'')){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.pending.set(null);this.freshRequired.set(true);
      this.selected.set(null);this.reason='';
      this.notice.set('La intención EMAIL existente fue reencolada para un nuevo intento. No significa que se entregó ni que el comprador la leyó.');
    }catch(e){
      if(e instanceof ApiFailure&&[400,401,403,404,410,422,423].includes(e.status)){
        this.pending.set(null);
      }
      this.error.set(describe(e)+(e instanceof ApiFailure&&e.status===409
        ?' Cambió versión o elegibilidad: consulta R8-39 y revisa antes de otra operación.'
        :' Si no hay resultado definitivo, reintenta el mismo cuerpo e idempotencia.'));
    }finally{
      this.busy.set(false);
      if(!this.pending())this.pendingChange.emit(false);
    }
  }
  abandon():void{
    if(this.busy()||this.loading())return;
    this.pending.set(null);this.pendingChange.emit(false);
    this.freshRequired.set(true);
    this.notice.set('Intento descartado localmente. Consulta versión y transporte antes de volver a solicitar reenvío.');
  }
}
