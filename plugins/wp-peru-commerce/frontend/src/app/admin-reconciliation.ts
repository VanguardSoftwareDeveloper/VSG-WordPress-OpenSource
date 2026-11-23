import { Component, computed, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, PcApi } from './api';
import type { AdminBootstrap } from './dto';
import { utcMinute } from './admin-agenda-contract';
import {
  type ReconciliationPage, type ReconciliationSummary,
  type ReconciliationResolution, type ReconciliationResolve,
  type ReconciliationState, type MutationResult,
  validSqlId, checkEvidence,
} from './admin-finance-contract';

interface PendingReconciliation {
  caseId: string;
  resource: string;
  body: ReconciliationResolve;
}

/** La proyección R8-29 no incluye case_phase. Solamente el filtro OPEN permite decidir. */
@Component({
  selector:'pc-admin-reconciliation',standalone:true,
  imports:[FormsModule],templateUrl:'./admin-reconciliation.html'
})
export class AdminReconciliation implements OnInit {
  readonly employee=input.required<AdminBootstrap>();
  readonly blocked=input(false);
  readonly pendingChange=output<boolean>();
  private readonly api=inject(PcApi);
  readonly allowed=computed(()=>this.employee().role_label==='A'
    && this.employee().ui_capabilities.includes('perucommerce_reconciliation_manage'));
  readonly cases=signal<ReconciliationSummary[]>([]);
  readonly selected=signal<ReconciliationSummary|null>(null);
  readonly page=signal(1);
  readonly hasMore=signal(false);
  readonly busy=signal(false);
  readonly loading=signal(false);
  readonly notice=signal('');
  readonly error=signal('');
  readonly pending=signal<PendingReconciliation|null>(null);
  readonly needsRefresh=signal(false);

  state:ReconciliationState='open_review';
  resolution:ReconciliationResolution='close_no_charge';
  evidence='';
  serviceStartUtc='';
  serviceEndUtc='';
  buyerAgreement='';
  serviceRequired=false;
  validatedExternal=false;
  readonly states=[
    {value:'open_review',label:'Abiertos a revisión'},
    {value:'closed_no_charge',label:'Cerrados sin cobro'},
    {value:'closed_reassigned',label:'Cerrados por reasignación'}
  ];

  ngOnInit():void{if(this.allowed())void this.load(1);}

  async load(page=this.page()):Promise<void>{
    if(!this.allowed()||this.loading()||this.busy())return;
    this.loading.set(true);this.error.set('');
    try{
      const reply=await this.api.json<ReconciliationPage>('GET',
        'admin/reconciliation?page='+page+'&per_page=20&state='+this.state);
      if(reply.status!==200||!Array.isArray(reply.body.items)
        ||!Number.isInteger(reply.body.page)
        ||typeof reply.body.has_more!=='boolean'
        ||reply.body.items.some(c=>!validSqlId(c.case_id)
          ||!validSqlId(c.row_version)
          ||(!c.order_id&&!c.source_operation_id))){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.cases.set(reply.body.items);
      if(!this.pending())this.needsRefresh.set(false);
      this.page.set(reply.body.page);
      this.hasMore.set(reply.body.has_more);
      const selected=this.selected();
      if(selected&&!this.pending()){
        this.selected.set(reply.body.items.find(x=>x.case_id===selected.case_id)??null);
      }
      this.notice.set('Expedientes MP consultados. El estado procede del filtro de consulta, no del texto del caso.');
    }catch(e){this.error.set(this.describe(e));}
    finally{this.loading.set(false);}
  }
  async filterChanged():Promise<void>{
    if(this.pending()||this.busy()||this.loading())return;
    this.selected.set(null);await this.load(1);
  }
  async pageMove(delta:-1|1):Promise<void>{
    if(this.pending()||this.busy()||this.loading()
      ||(delta===-1&&this.page()<=1)||(delta===1&&!this.hasMore()))return;
    this.selected.set(null);await this.load(this.page()+delta);
  }
  select(c:ReconciliationSummary):void{
    if(this.pending()||this.busy()||this.loading()||this.needsRefresh())return;
    this.selected.set(c);this.error.set('');
    this.resolution='close_no_charge';
    this.evidence='';this.serviceRequired=false;this.validatedExternal=false;
    this.serviceStartUtc='';this.serviceEndUtc='';this.buyerAgreement='';
  }
  private prepare():PendingReconciliation {
    const row=this.selected();
    if(!this.allowed()||this.needsRefresh()||this.state!=='open_review'||!row
      ||!this.cases().some(x=>x.case_id===row.case_id)){
      throw new Error('Selecciona un expediente abierto y vigente. Los casos cerrados no se reabren.');
    }
    if(!row.order_id||!validSqlId(row.order_id)){
      throw new Error('Caso MP prepedido sin order_id: no se puede fabricar vínculo, saldo ni pedido. Requiere recuperación autorizada.');
    }
    if(!this.validatedExternal){
      throw new Error('Debes haber revisado evidencia externa y la relación con el pedido. El navegador no autentica dinero.');
    }
    const body:ReconciliationResolve={
      resolution:this.resolution,expected_version:row.row_version,
      evidence:checkEvidence(this.evidence),idempotency_key:crypto.randomUUID()
    };
    if(this.resolution==='reassign_resources'){
      if(row.has_verified_payment!==true){
        throw new Error('No existe indicador de crédito verificado en la lectura. El backend exige ledger y GET oficial antes de reasignar.');
      }
      body.order_id=row.order_id;
      if(this.serviceRequired){
        body.new_service_start_at_utc=utcMinute(this.serviceStartUtc);
        body.new_service_end_at_utc=utcMinute(this.serviceEndUtc);
        if(new Date(body.new_service_end_at_utc)<=new Date(body.new_service_start_at_utc)){
          throw new Error('El fin UTC debe ser posterior al inicio de la cita.');
        }
        body.buyer_agreement_evidence=checkEvidence(this.buyerAgreement);
      }
    }
    return {
      caseId:row.case_id,
      resource:'admin/reconciliation/'+row.case_id+'/resolve',body
    };
  }
  async submit():Promise<void>{
    if(!this.allowed()||this.busy()||this.loading()||this.blocked())return;
    let attempt=this.pending();
    if(!attempt){
      try{attempt=this.prepare();this.pending.set(attempt);this.pendingChange.emit(true);}
      catch(e){this.error.set(this.describe(e));return;}
    }
    this.busy.set(true);this.error.set('');
    let completed=false;
    try{
      const reply=await this.api.json<MutationResult>('POST',attempt.resource,attempt.body);
      if(reply.status!==200||!reply.body.operation_id||!reply.body.result){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      completed=true;
      this.pending.set(null);
      this.needsRefresh.set(true);
      this.selected.set(null);
      this.validatedExternal=false;
      this.notice.set('Resolución registrada por el servidor: '+reply.body.result
        +'. No implica refund ejecutado ni un segundo cobro.');
    }catch(e){
      if(e instanceof ApiFailure&&[400,401,403,404,410,422,423].includes(e.status)){
        this.pending.set(null);
      }
      this.error.set(this.describe(e)
        +(e instanceof ApiFailure&&e.status===409
          ?' Conflicto: el expediente o el proveedor cambió. Consulta y verifica antes de iniciar otra resolución.'
          :' Conserva la misma clave ante resultado incierto; no generes otro pago ni expediente.'));
    }finally{
      this.busy.set(false);
      if(!this.pending())this.pendingChange.emit(false);
    }
    if(completed)await this.load();
  }
  abandon():void{
    if(this.busy()||this.loading())return;
    this.pending.set(null);this.pendingChange.emit(false);
    this.notice.set('Intento descartado en el navegador. Confirma resultado del caso antes de intentar otra resolución.');
  }
  private describe(error:unknown):string{
    return error instanceof ApiFailure?error.message
      :error instanceof Error?error.message:'No se pudo confirmar la conciliación.';
  }
}
