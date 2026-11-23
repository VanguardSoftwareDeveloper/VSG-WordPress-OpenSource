import { Component, computed, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, PcApi } from './api';
import type { AdminBootstrap } from './dto';
import {
  type CoverageEntry, type CoveragePatch, type CoverageView,
  validSqlId, moneyToSoles, solesToMoney
} from './admin-config-contract';

function message(e:unknown):string {
  return e instanceof ApiFailure?e.message
    : e instanceof Error?e.message:'No se pudo comprobar la cobertura.';
}

@Component({
  selector:'pc-admin-coverage',standalone:true,imports:[FormsModule],
  templateUrl:'./admin-coverage.html'
})
export class AdminCoverage implements OnInit {
  readonly employee=input.required<AdminBootstrap>();
  readonly blocked=input(false);
  readonly pendingChange=output<boolean>();
  private readonly api=inject(PcApi);
  readonly allowed=computed(()=>this.employee().role_label==='A'
    &&this.employee().ui_capabilities.includes('perucommerce_settings_manage'));

  readonly view=signal<CoverageView|null>(null);
  readonly search=signal('');
  readonly filtered=computed(()=>{
    const term=this.search().trim();
    return this.view()?.items.filter(i=>i.ubigeo_code.includes(term))??[];
  });
  readonly shown=computed(()=>this.filtered().slice(0,30));
  readonly selected=signal<string|null>(null);
  readonly loading=signal(false);
  readonly busy=signal(false);
  readonly pending=signal<CoveragePatch|null>(null);
  readonly freshRequired=signal(false);
  readonly error=signal('');
  readonly notice=signal('');
  readonly centsToSoles=moneyToSoles;

  code='';
  fee='0.00';
  enabled=false;
  reviewed=false;

  ngOnInit():void {if(this.allowed())void this.load();}

  async load():Promise<void>{
    if(!this.allowed()||this.loading()||this.busy())return;
    this.loading.set(true);this.error.set('');
    try{
      const reply=await this.api.json<CoverageView>('GET','admin/ubigeo-coverage');
      const d=reply.body;
      if(reply.status!==200||!Array.isArray(d.items)
        ||d.items.length>2500||!validSqlId(d.row_version)
        ||d.items.some(i=>!/^[0-9]{6}$/.test(i.ubigeo_code)
          ||typeof i.enabled!=='boolean'
          ||!/^(0|[1-9][0-9]*)$/.test(i.fee_cents))){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.view.set(d);
      if(!this.pending()){
        this.freshRequired.set(false);
        const choice=d.items.find(x=>x.ubigeo_code===this.selected());
        if(choice)this.choose(choice);
      }
      this.notice.set('Cobertura consultada, versión '+d.row_version
        +'. Dataset '+(d.dataset_version??'no anunciado')+'. Solo una tarifa elegida se envía en el PATCH.');
    }catch(e){this.error.set(message(e));}
    finally{this.loading.set(false);}
  }

  choose(entry:CoverageEntry):void{
    if(this.pending()||this.busy()||this.blocked()||this.freshRequired())return;
    this.selected.set(entry.ubigeo_code);
    this.code=entry.ubigeo_code;
    this.fee=moneyToSoles(entry.fee_cents);
    this.enabled=entry.enabled;
    this.reviewed=false;
  }
  newDistrict():void{
    if(this.pending()||this.busy()||this.blocked()||this.freshRequired())return;
    this.selected.set(null);this.code='';this.fee='0.00';
    this.enabled=false;this.reviewed=false;
  }

  private makePatch():CoveragePatch {
    const d=this.view();
    if(!this.allowed()||!d||this.freshRequired()){
      throw new Error('Se requiere una versión R8-51 actual para modificar cobertura.');
    }
    const code=this.code.trim();
    if(!/^[0-9]{6}$/.test(code))throw new Error('UBIGEO distrital de 6 dígitos obligatorio.');
    if(!this.reviewed)throw new Error('Revisa la cobertura/catálogo INEI legítimo antes de aprobar un distrito.');
    const fee=solesToMoney(this.fee);
    const existing=d.items.find(i=>i.ubigeo_code===code);
    if(existing&&existing.enabled===this.enabled&&existing.fee_cents===fee){
      throw new Error('La regla no presenta cambios.');
    }
    return {entries:[{ubigeo_code:code,enabled:this.enabled,fee_cents:fee}],
      expected_version:d.row_version,idempotency_key:crypto.randomUUID()};
  }

  async save():Promise<void>{
    if(this.busy()||this.loading()||this.blocked()||!this.allowed())return;
    let attempt=this.pending();
    if(!attempt){
      try{attempt=this.makePatch();this.pending.set(attempt);this.pendingChange.emit(true);}
      catch(e){this.error.set(message(e));return;}
    }
    this.busy.set(true);this.error.set('');
    let confirmed=false;
    try{
      const reply=await this.api.json<CoverageView>('PATCH','admin/ubigeo-coverage',attempt);
      if(reply.status!==200||!validSqlId(reply.body.row_version)
        ||!Array.isArray(reply.body.items)){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      confirmed=true;
      this.pending.set(null);
      this.freshRequired.set(true);
      this.view.set(reply.body);
      this.reviewed=false;
      this.notice.set('Regla de distrito registrada por el servidor, versión '+reply.body.row_version
        +'. No recalcula tarifas pactadas en pedidos anteriores. Relee R8-51 antes de otra edición.');
    }catch(e){
      if(!confirmed&&e instanceof ApiFailure
        &&[400,401,403,404,410,422,423].includes(e.status)){
        this.pending.set(null);
      }
      this.error.set(message(e)
        +(e instanceof ApiFailure&&e.status===409
          ?' Conflicto de dataset o versión. Consulta y revisa antes de aprobar.'
          :' Ante resultado incierto conserva cuerpo/idempotencia; no dupliques el cambio.'));
    }finally{
      this.busy.set(false);
      if(!this.pending())this.pendingChange.emit(false);
    }
  }
  abandon():void{
    if(this.busy()||this.loading())return;
    this.pending.set(null);this.pendingChange.emit(false);
    this.freshRequired.set(true);
    this.notice.set('Intento descartado localmente; relee dataset, cobertura y versión antes de repetir.');
  }
}
