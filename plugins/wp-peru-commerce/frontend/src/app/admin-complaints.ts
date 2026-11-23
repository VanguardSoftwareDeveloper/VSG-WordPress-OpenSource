import { Component, computed, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, PcApi } from './api';
import type { AdminBootstrap } from './dto';
import {
  type ComplaintPage, type ComplaintSummary, type ComplaintDetail,
  type ComplaintRespond, type MutationResult,
  validId, privateRef, responseText, exactDate
} from './admin-legal-contract';

interface PendingReply {
  id:string;
  body:ComplaintRespond;
}
function errorMessage(error:unknown):string {
  return error instanceof ApiFailure ? error.message
    : error instanceof Error ? error.message : 'No fue posible comprobar el expediente.';
}

@Component({
  selector:'pc-admin-complaints',standalone:true,
  imports:[FormsModule],templateUrl:'./admin-complaints.html'
})
export class AdminComplaints implements OnInit {
  readonly employee=input.required<AdminBootstrap>();
  readonly blocked=input(false);
  readonly pendingChange=output<boolean>();
  private readonly api=inject(PcApi);
  readonly allowed=computed(()=>this.employee().role_label==='A'
    && this.employee().ui_capabilities.includes('perucommerce_complaints_manage'));

  readonly entries=signal<ComplaintSummary[]>([]);
  readonly page=signal(1);
  readonly more=signal(false);
  readonly listing=signal(false);
  readonly detailBusy=signal(false);
  readonly busy=signal(false);
  readonly detail=signal<ComplaintDetail|null>(null);
  readonly pending=signal<PendingReply|null>(null);
  readonly freshRequired=signal(false);
  readonly notice=signal('');
  readonly error=signal('');
  readonly downloadBusy=signal(false);
  readonly date=exactDate;

  response='';
  responseEvidence='';
  responseAttested=false;

  ngOnInit():void { if(this.allowed())void this.load(1); }

  async load(page=this.page()):Promise<void> {
    if(!this.allowed()||this.listing()||this.busy())return;
    this.listing.set(true);this.error.set('');
    try {
      const r=await this.api.json<ComplaintPage>('GET',
        'admin/complaints?page='+page+'&per_page=20');
      if(r.status!==200 || !Array.isArray(r.body.items)
        || !Number.isInteger(r.body.page)
        || typeof r.body.has_more!=='boolean'
        || r.body.items.some(x=>!validId(x.id))){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.entries.set(r.body.items);
      this.page.set(r.body.page);
      this.more.set(r.body.has_more);
      this.notice.set('Listado legal resumido actualizado. El folio no concede acceso ni permite consultar el caso públicamente.');
    }catch(e){this.error.set(errorMessage(e));}
    finally{this.listing.set(false);}
  }
  async pageMove(offset:-1|1):Promise<void>{
    if(this.pending()||this.busy()||this.listing()||this.detailBusy()
      ||(offset===-1&&this.page()<=1)||(offset===1&&!this.more()))return;
    await this.load(this.page()+offset);
  }

  async select(item:ComplaintSummary):Promise<void>{
    if(!this.allowed()||this.pending()||this.blocked()||this.busy()||this.detailBusy())return;
    await this.loadDetail(item.id);
  }
  async loadDetail(id=this.detail()?.id??''):Promise<void>{
    if(!this.allowed()||!validId(id)||this.detailBusy()||this.busy())return;
    this.detailBusy.set(true);this.error.set('');
    try {
      const r=await this.api.json<ComplaintDetail>('GET','admin/complaints/'+id);
      const data=r.body;
      if(r.status!==200||data.id!==id||!validId(data.row_version)
        ||!data.claimant||typeof data.description!=='string'
        ||typeof data.request!=='string') {
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      const old=this.detail();
      this.detail.set(data);
      if(!this.pending()){
        this.freshRequired.set(false);
        if(!old ||old.id!==id||old.row_version!==data.row_version){
          this.response=data.response_text??'';
          this.responseEvidence='';
          this.responseAttested=false;
        }
      }
      this.notice.set('Expediente '+data.receipt_number+' leído con versión '
        +data.row_version+'. Responder no acredita notificación al reclamante.');
    }catch(e){
      this.error.set(errorMessage(e)+' Se conserva la última lectura autorizada, sin inventar folio ni estado de entrega.');
    }finally{this.detailBusy.set(false);}
  }
  close():void{
    if(this.pending()||this.busy()||this.detailBusy())return;
    this.detail.set(null);
    this.response='';this.responseEvidence='';this.responseAttested=false;
  }

  async respond():Promise<void>{
    if(!this.allowed()||this.busy()||this.detailBusy()||this.blocked())return;
    let attempt=this.pending();
    if(!attempt){
      try {
        const record=this.detail();
        if(!record||record.responded_at||this.freshRequired()) {
          throw new Error('El caso ya está respondido o debe releerse antes de otra respuesta.');
        }
        if(!this.responseAttested){
          throw new Error('Verifica el contenido jurídico y el canal de notificación antes de registrar la respuesta.');
        }
        const text=responseText(this.response,20000);
        const ref=this.responseEvidence.trim();
        if(record.response_text && record.response_text!==text){
          throw new Error('Ya existe una respuesta preparada; debes conservar su texto exacto al adjuntar evidencia de entrega.');
        }
        if(record.response_text && !ref){
          throw new Error('La respuesta ya estaba preparada. Para confirmar entrega requiere una evidencia privada válida y el verificador legal.');
        }
        if(ref && !privateRef(ref)){
          throw new Error('La evidencia debe ser una referencia privada válida del mismo caso.');
        }
        const body:ComplaintRespond={
          response_text:text,expected_version:record.row_version,
          idempotency_key:crypto.randomUUID(),
          ...(ref?{evidence:ref}:{})
        };
        attempt={id:record.id,body};
        this.pending.set(attempt);
        this.pendingChange.emit(true);
      }catch(e){this.error.set(errorMessage(e));return;}
    }
    this.busy.set(true);this.error.set('');
    let confirmed=false;
    try{
      const r=await this.api.json<MutationResult>('POST',
        'admin/complaints/'+attempt.id+'/respond',attempt.body);
      if(r.status!==200||typeof r.body.operation_id!=='string'
        ||!validId(r.body.row_version??'')
        ||!['complaint_response_prepared','complaint_response_evidenced'].includes(
          r.body.result)) {
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      confirmed=true;
      this.pending.set(null);
      this.freshRequired.set(true);
      this.responseAttested=false;
      this.notice.set(r.body.result==='complaint_response_prepared'
        ?'Se registró un borrador de respuesta, SIN constancia de entrega al reclamante. Para acreditar entrega se requiere evidencia protegida y verificada.'
        :'El backend registró evidencia verificable de respuesta. Revisa detalle/canal, no equipares automáticamente correo enviado con recepción.');
      await this.loadDetail(attempt.id);
    }catch(e){
      if(!confirmed && e instanceof ApiFailure &&
        [400,401,403,404,410,422,423].includes(e.status))this.pending.set(null);
      this.error.set(confirmed
        ?'La escritura fue confirmada, pero no se pudo refrescar el detalle. Consulta el caso antes de otra decisión.'
        :errorMessage(e)+(e instanceof ApiFailure&&e.status===409
          ?' Conflicto de versión o evidencia: consulta la respuesta vigente, no sobrescribas.'
          :' Si el resultado es incierto, conserva el mismo cuerpo y la misma clave.'));
    }finally{
      this.busy.set(false);
      if(!this.pending())this.pendingChange.emit(false);
    }
  }

  async downloadEvidence(ref:string, purpose:'legal_case_review'|'legal_response_evidence'
    ='legal_case_review'):Promise<void>{
    const item=this.detail();
    if(!this.allowed()||this.downloadBusy()||!item||!privateRef(ref)
      ||this.busy()||this.blocked()||(purpose==='legal_case_review'
        && !(item.attachments??[]).includes(ref))
      ||(purpose==='legal_response_evidence'&&item.response_evidence_ref!==ref))return;
    this.downloadBusy.set(true);this.error.set('');
    try{
      const f=await this.api.privateFile('admin/complaints/'+item.id
        +'/evidence/'+ref+'?purpose='+purpose);
      // URL creada solo para descarga local autorizada, nunca almacenada
      // como estado ni como enlace público de expediente.
      const url=URL.createObjectURL(f.blob);
      const link=document.createElement('a');
      link.href=url;link.download=f.filename;link.rel='noreferrer';link.hidden=true;
      document.body.append(link);
      try{link.click();}finally{
        link.remove();
        window.setTimeout(()=>URL.revokeObjectURL(url),60000);
      }
      this.notice.set('El binario privado se preparó para descarga, sin asegurar que la descarga terminó. No almacenes pruebas personales en un equipo compartido.');
    }catch(e){this.error.set(errorMessage(e));}
    finally{this.downloadBusy.set(false);}
  }
  abandon():void{
    if(this.busy()||this.detailBusy())return;
    this.pending.set(null);this.pendingChange.emit(false);
    this.notice.set('Intento descartado solamente del navegador. Consulta la respuesta jurídica registrada antes de enviar una nueva.');
  }
}
