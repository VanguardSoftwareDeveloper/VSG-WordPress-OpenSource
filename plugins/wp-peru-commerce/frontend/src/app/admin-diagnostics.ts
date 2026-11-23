import { Component, computed, inject, input, OnInit, signal } from '@angular/core';
import { ApiFailure, PcApi } from './api';
import type { AdminBootstrap } from './dto';
import type { DiagnosticsSafe } from './admin-config-contract';

@Component({
  selector:'pc-admin-diagnostics',standalone:true,
  templateUrl:'./admin-diagnostics.html'
})
export class AdminDiagnostics implements OnInit {
  readonly employee=input.required<AdminBootstrap>();
  private readonly api=inject(PcApi);
  readonly allowed=computed(()=>this.employee().role_label==='A'
    &&this.employee().ui_capabilities.includes('perucommerce_settings_manage'));
  readonly data=signal<DiagnosticsSafe|null>(null);
  readonly loading=signal(false);
  readonly error=signal('');
  readonly notice=signal('');

  ngOnInit():void{if(this.allowed())void this.load();}

  async load():Promise<void>{
    if(!this.allowed()||this.loading())return;
    this.loading.set(true);this.error.set('');
    try{
      const reply=await this.api.json<DiagnosticsSafe>('GET','admin/diagnostics');
      const body=reply.body;
      if(reply.status!==200||typeof body.https_available!=='boolean'
        ||typeof body.db_engine!=='string'
        ||typeof body.db_transactions_supported!=='boolean'
        ||typeof body.private_storage_status!=='string'
        ||body.pending_jobs!==undefined
          &&(!Number.isSafeInteger(body.pending_jobs)||body.pending_jobs<0)){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.data.set(body);
      this.notice.set('Señales de diagnóstico actualizadas; ninguna sustituye una prueba de ejecución, integraciones o entrega.');
    }catch(e){
      this.error.set(e instanceof ApiFailure?e.message
        :e instanceof Error?e.message:'No fue posible leer el diagnóstico.');
    }finally{this.loading.set(false);}
  }
}
