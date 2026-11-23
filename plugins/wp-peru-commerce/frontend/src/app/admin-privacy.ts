import { Component, computed, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, PcApi } from './api';
import type { AdminBootstrap } from './dto';
import {
  type PrivacyCaseSummary, type PrivacyPage, type PrivacyRequestDetail,
  type PrivacyResolution, type PrivacyDecision, type PrivacyExportScope,
  type PrivacyExportRequest, type PrivacyExportAck, type MutationResult,
  type PrivacyExportOperation,
  validId, privateRef, responseText, legalBasis, staffDeclaration, exactDate
} from './admin-legal-contract';

interface PendingLegal {
  action:'resolve'|'export';
  id:string;
  resource:string;
  body:PrivacyResolution|PrivacyExportRequest;
  expected:200|202;
}
function describe(error:unknown):string {
  return error instanceof ApiFailure?error.message
    : error instanceof Error?error.message:'No se pudo consultar el expediente ARCO.';
}

@Component({
  selector:'pc-admin-privacy',standalone:true,
  imports:[FormsModule],templateUrl:'./admin-privacy.html'
})
export class AdminPrivacy implements OnInit {
  readonly employee=input.required<AdminBootstrap>();
  readonly blocked=input(false);
  readonly pendingChange=output<boolean>();
  private readonly api=inject(PcApi);
  readonly allowed=computed(()=>this.employee().role_label==='A'
    && this.employee().ui_capabilities.includes('perucommerce_privacy_manage'));
  readonly items=signal<PrivacyCaseSummary[]>([]);
  readonly detail=signal<PrivacyRequestDetail|null>(null);
  readonly page=signal(1);
  readonly more=signal(false);
  readonly loading=signal(false);
  readonly detailBusy=signal(false);
  readonly busy=signal(false);
  readonly downloadBusy=signal(false);
  readonly error=signal('');
  readonly notice=signal('');
  readonly freshRequired=signal(false);
  readonly pending=signal<PendingLegal|null>(null);
  readonly date=exactDate;
  readonly exportOperations=computed(()=>this.detail()?.export_operations??[]);
  readonly ordinaryEvidence=computed(()=>{
    const d=this.detail();
    if(!d)return [];
    const exports=new Set((d.export_operations??[])
      .map(x=>x.evidence_ref).filter((x):x is string=>typeof x==='string'));
    return (d.evidence_refs??[]).filter(ref=>!exports.has(ref));
  });
  isExportReady(op:PrivacyExportOperation):boolean {
    return op.status==='ready'
      &&typeof op.evidence_ref==='string' &&privateRef(op.evidence_ref)
      &&typeof op.expires_at_utc==='string'
      &&Number.isFinite(new Date(op.expires_at_utc).getTime())
      &&new Date(op.expires_at_utc)>new Date();
  }

  decision:PrivacyDecision='fulfilled';
  basis='';
  decisionEvidence='';
  reviewedRetention=false;
  verifiedRealIdentity=false;
  verifiedDelivery=false;

  exportScope:PrivacyExportScope='subject_verified';
  exportReason='';
  verifiedIdentityReference='';
  exportOrderId='';
  exportConfirmed=false;

  ngOnInit():void{if(this.allowed())void this.load(1);}

  async load(page=this.page()):Promise<void>{
    if(!this.allowed()||this.loading()||this.busy())return;
    this.loading.set(true);this.error.set('');
    try {
      const r=await this.api.json<PrivacyPage>('GET',
        'admin/privacy-requests?page='+page+'&per_page=20');
      if(r.status!==200||!Array.isArray(r.body.items)
        ||!Number.isInteger(r.body.page)||typeof r.body.has_more!=='boolean'
        ||r.body.items.some(x=>!validId(x.id))){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.items.set(r.body.items);
      this.page.set(r.body.page);
      this.more.set(r.body.has_more);
      this.notice.set('Lista ARCO resumida consultada; identidad y respuesta requieren lectura particular con finalidad.');
    }catch(e){this.error.set(describe(e));}
    finally{this.loading.set(false);}
  }
  async movePage(offset:-1|1):Promise<void>{
    if(this.pending()||this.loading()||this.busy()||this.detailBusy()
      ||(offset===-1&&this.page()<=1)||(offset===1&&!this.more()))return;
    await this.load(this.page()+offset);
  }
  async select(item:PrivacyCaseSummary):Promise<void>{
    if(!this.allowed()||this.busy()||this.detailBusy()||this.pending()||this.blocked())return;
    await this.loadDetail(item.id);
  }
  async loadDetail(id=this.detail()?.id??''):Promise<void>{
    if(!this.allowed()||!validId(id)||this.detailBusy()||this.busy())return;
    this.detailBusy.set(true);this.error.set('');
    try {
      const r=await this.api.json<PrivacyRequestDetail>('GET',
        'admin/privacy-requests/'+id);
      const x=r.body;
      if(r.status!==200||x.id!==id||!validId(x.row_version)
        ||!['pending','verified','insufficient'].includes(x.verification_status)
        ||(x.export_operations && !Array.isArray(x.export_operations))){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      const old=this.detail();
      this.detail.set(x);
      if(!this.pending()){
        this.freshRequired.set(false);
        if(!old||old.id!==x.id||old.row_version!==x.row_version){
          // Una resolución preparada sin entrega conserva sus términos.
          this.decision=x.decision??'fulfilled';
          this.basis=x.legal_basis??'';
          this.decisionEvidence='';
          this.reviewedRetention=false;
          this.verifiedDelivery=false;
          this.verifiedRealIdentity=false;
          this.exportScope='subject_verified';
          this.exportReason='';
          this.verifiedIdentityReference='';
          this.exportOrderId='';
          this.exportConfirmed=false;
        }
      }
      this.notice.set('Expediente ARCO '+x.id+' consultado. Estado de identidad: '
        +x.verification_status+'. Una declaración del administrador no sustituye la verificación real.');
    }catch(e){this.error.set(describe(e));}
    finally{this.detailBusy.set(false);}
  }
  close():void{
    if(this.busy()||this.detailBusy()||this.pending())return;
    this.detail.set(null);this.error.set('');
  }
  private prepare(action:'resolve'|'export'):PendingLegal{
    const d=this.detail();
    if(!this.allowed()||!d||this.freshRequired()) {
      throw new Error('Consulta el expediente y versión vigente para esta actuación legal.');
    }
    if(d.verification_status!=='verified'||!d.identity_evidence_ref
      ||!privateRef(d.identity_evidence_ref)
      ||!this.verifiedRealIdentity){
      throw new Error('La identidad debe figurar verificada en R8-66 y comprobarse con la evidencia original legítima; una casilla o correo confirmado no bastan.');
    }
    const key=crypto.randomUUID();
    if(action==='resolve'){
      if(d.resolved_at){
        throw new Error('El expediente está resuelto y no puede reabrirse ni reescribirse.');
      }
      if(!this.reviewedRetention || !this.verifiedDelivery){
        throw new Error('Comprueba obligación de conservación y canal de notificación antes de resolver.');
      }
      if((d.decision&&d.decision!==this.decision)
        ||(d.legal_basis&&d.legal_basis!==this.basis.trim())){
        throw new Error('Ya hay una resolución preparada; no cambies su decisión ni fundamento para adjuntar evidencia.');
      }
      const evidence=this.decisionEvidence.trim();
      if(evidence&&!privateRef(evidence))throw new Error('La evidencia de resolución debe ser referencia protegida del mismo caso.');
      const body:PrivacyResolution={
        resolution:this.decision,
        legal_basis:legalBasis(this.basis),
        expected_version:d.row_version,
        idempotency_key:key,
        ...(evidence?{evidence}:{})
      };
      return {
        action,id:d.id,expected:200,body,
        resource:'admin/privacy-requests/'+d.id+'/resolve'
      };
    }
    if(!this.exportConfirmed){
      throw new Error('Revisa finalidad, titular legitimado y alcance exacto de la exportación. No usar como exportación comercial masiva.');
    }
    const body:PrivacyExportRequest={
      privacy_request_id:d.id,
      expected_version:d.row_version,
      scope:this.exportScope,
      reason:responseText(this.exportReason,1000),
      // Esta cadena es declarativa, NO comprueba identidad automáticamente.
      verified_identity:staffDeclaration(this.verifiedIdentityReference),
      idempotency_key:key
    };
    if(body.scope==='order_legal_copy'){
      if(!validId(this.exportOrderId))throw new Error('El alcance de copia legal requiere ID real del pedido del mismo titular.');
      body.order_id=this.exportOrderId;
    }
    return {
      action,id:d.id,expected:202,body,resource:'admin/privacy-exports'
    };
  }
  async execute(action:'resolve'|'export'):Promise<void>{
    if(!this.allowed()||this.busy()||this.detailBusy()||this.blocked())return;
    let attempt=this.pending();
    if(attempt && attempt.action!==action){
      this.error.set('Existe un intento legal anterior. Debe comprobarse antes de iniciar otro.');
      return;
    }
    if(!attempt){
      try {
        attempt=this.prepare(action);
        this.pending.set(attempt);
        this.pendingChange.emit(true);
      }catch(e){this.error.set(describe(e));return;}
    }
    this.busy.set(true);this.error.set('');
    let confirmed=false;
    try{
      if(attempt.action==='export'){
        const r=await this.api.json<PrivacyExportAck>('POST',
          attempt.resource,attempt.body);
        if(r.status!==202||r.body.accepted!==true
          ||r.body.privacy_request_id!==attempt.id||!r.body.operation_id){
          throw new ApiFailure(500,'pc_result_invalid',false);
        }
        confirmed=true;this.pending.set(null);this.freshRequired.set(true);
        this.notice.set('Exportación aceptada como pendiente: operación '
          +r.body.operation_id+'. HTTP 202 NO significa archivo listo; consulta R8-66 para ver pending, ready, failed o expired.');
      }else{
        const r=await this.api.json<MutationResult>('POST',
          attempt.resource,attempt.body);
        if(r.status!==200||!r.body.operation_id||!r.body.result
          ||!['privacy_resolution_prepared','privacy_resolution_evidenced']
            .includes(r.body.result)){
          throw new ApiFailure(500,'pc_result_invalid',false);
        }
        confirmed=true;this.pending.set(null);this.freshRequired.set(true);
        this.notice.set(r.body.result==='privacy_resolution_prepared'
          ?'Se preparó la decisión administrativa. No consta notificación efectiva; sin evidencia legal válida no se declara expediente cerrado.'
          :'El servidor registró la decisión con evidencia verificada, no una eliminación indiscriminada de obligaciones de conservación.');
      }
      this.verifiedRealIdentity=false;
      await this.loadDetail(attempt.id);
    }catch(e){
      if(!confirmed&&e instanceof ApiFailure &&
        [400,401,403,404,410,422,423].includes(e.status)){
        this.pending.set(null);
      }
      this.error.set(confirmed
        ?'Operación confirmada, pero falló la lectura posterior. Consulta el estado antes de otra decisión.'
        :describe(e)+(e instanceof ApiFailure&&e.status===409
          ?' Conflicto de versión, evidencia o estado: relee el expediente, no sobrescribas la resolución.'
          :' Conserva la misma clave y payload si la respuesta es incierta.'));
    }finally {
      this.busy.set(false);
      if(!this.pending())this.pendingChange.emit(false);
    }
  }
  async downloadEvidence(ref:string|undefined,purpose:'privacy_case_audit'|'privacy_export_download'
    ='privacy_case_audit'):Promise<void>{
    const d=this.detail();
    if(!this.allowed()||this.downloadBusy()||this.busy()||this.blocked()
      ||!d||!ref||!privateRef(ref))return;
    if(purpose==='privacy_export_download'){
      const ready=d.export_operations?.some(op=>op.status==='ready'
        &&op.evidence_ref===ref
        &&!!op.expires_at_utc
        &&new Date(op.expires_at_utc)>new Date());
      if(!ready){this.error.set('La exportación no figura lista y vigente en esta lectura.');return;}
    }else if(!(d.evidence_refs??[]).includes(ref)
      &&d.identity_evidence_ref!==ref
      &&d.representation_evidence_ref!==ref
      &&d.response_evidence_ref!==ref){
      this.error.set('La evidencia no pertenece a la proyección de este expediente.');
      return;
    }
    this.downloadBusy.set(true);this.error.set('');
    try{
      const f=await this.api.privateFile('admin/privacy-requests/'+d.id
        +'/evidence/'+ref+'?purpose='+purpose);
      const url=URL.createObjectURL(f.blob);
      const a=document.createElement('a');
      a.href=url;a.download=f.filename;a.rel='noreferrer';a.hidden=true;
      document.body.append(a);
      try{a.click();}finally{
        a.remove();window.setTimeout(()=>URL.revokeObjectURL(url),60000);
      }
      this.notice.set('Descarga privada preparada para revisión, sin exponer URL pública ni afirmar descarga completa. Evita conservar pruebas sensibles en dispositivos compartidos.');
    }catch(e){this.error.set(describe(e));}
    finally{this.downloadBusy.set(false);}
  }
  abandon():void {
    if(this.busy()||this.detailBusy())return;
    this.pending.set(null);this.pendingChange.emit(false);
    this.notice.set('Intento descartado en la interfaz. Consulta estado de expediente/exportación antes de generar una nueva solicitud.');
  }
}
