import { Component, inject, OnInit, signal } from '@angular/core';
import { ApiFailure, PcApi } from './api';
import type { AdminBootstrap } from './dto';
import { AdminCatalog } from './admin-catalog';
import { AdminAgenda } from './admin-agenda';
import { AdminOrders } from './admin-orders';
import { AdminFinance } from './admin-finance';
import { AdminLegal } from './admin-legal';
import { AdminOperations } from './admin-operations';

@Component({
  selector:'pc-admin',standalone:true,imports:[AdminCatalog,AdminAgenda,AdminOrders,AdminFinance,AdminLegal,AdminOperations],templateUrl:'./admin-page.html'
})
export class AdminPage implements OnInit {
  readonly api=inject(PcApi);
  readonly state=signal<AdminBootstrap|null>(null);
  readonly busy=signal(false);
  readonly notice=signal('');
  readonly section=signal<'catalog'|'agenda'|'orders'|'finance'|'legal'|'operations'>('catalog');
  readonly agendaVisited=signal(false);
  readonly ordersVisited=signal(false);
  readonly financeVisited=signal(false);
  readonly legalVisited=signal(false);
  readonly operationsVisited=signal(false);
  readonly ordersPending=signal(false);
  readonly financePending=signal(false);
  readonly legalPending=signal(false);
  readonly operationsPending=signal(false);
  showSection(view:'catalog'|'agenda'|'orders'|'finance'|'legal'|'operations'):void {
    if(this.ordersPending()||this.financePending()||this.legalPending()||this.operationsPending())return;
    this.section.set(view);
    if(view==='agenda')this.agendaVisited.set(true);
    if(view==='orders')this.ordersVisited.set(true);
    if(view==='finance')this.financeVisited.set(true);
    if(view==='legal')this.legalVisited.set(true);
    if(view==='operations')this.operationsVisited.set(true);
  }
  ngOnInit():void{void this.load();}
  async load():Promise<void>{
    if(this.busy()||this.ordersPending()||this.financePending()
      ||this.legalPending()||this.operationsPending())return;
    this.busy.set(true);
    this.notice.set('Comprobando sesión y permisos vigentes…');
    try{
      const result=await this.api.json<AdminBootstrap>('GET','admin/bootstrap');
      if(result.body.role_label!=='A' && result.body.role_label!=='O'){
        throw new ApiFailure(403,'pc_access_denied',false);
      }
      this.state.set(result.body);
      if(!result.body.ui_capabilities.includes('perucommerce_catalog_edit')
        && !result.body.ui_capabilities.includes('perucommerce_appointments_manage')
        && result.body.ui_capabilities.includes('perucommerce_orders_read')){
        this.showSection('orders');
      }
      if(result.body.role_label==='A'
        && !result.body.ui_capabilities.includes('perucommerce_catalog_edit')
        && !result.body.ui_capabilities.includes('perucommerce_appointments_manage')
        && !result.body.ui_capabilities.includes('perucommerce_orders_read')
        && result.body.ui_capabilities.includes('perucommerce_reconciliation_manage')){
        this.showSection('finance');
      }
      if(result.body.role_label==='A'
        && !result.body.ui_capabilities.includes('perucommerce_catalog_edit')
        && !result.body.ui_capabilities.includes('perucommerce_appointments_manage')
        && !result.body.ui_capabilities.includes('perucommerce_orders_read')
        && !result.body.ui_capabilities.includes('perucommerce_reconciliation_manage')
        && (result.body.ui_capabilities.includes('perucommerce_complaints_manage')
          ||result.body.ui_capabilities.includes('perucommerce_privacy_manage'))){
        this.showSection('legal');
      }
      if(!result.body.ui_capabilities.includes('perucommerce_catalog_edit')
        &&!result.body.ui_capabilities.includes('perucommerce_appointments_manage')
        &&!result.body.ui_capabilities.includes('perucommerce_orders_read')
        &&!result.body.ui_capabilities.includes('perucommerce_reconciliation_manage')
        &&!result.body.ui_capabilities.includes('perucommerce_complaints_manage')
        &&!result.body.ui_capabilities.includes('perucommerce_privacy_manage')
        &&(result.body.ui_capabilities.includes('perucommerce_notifications_operate')
          ||result.body.role_label==='A'
            &&result.body.ui_capabilities.includes('perucommerce_settings_manage'))){
        this.showSection('operations');
      }
      // Mantener catálogo como primera vista existente; si solo la agenda
      // está habilitada, usar esa sección al iniciar la sesión.
      if(!result.body.ui_capabilities.includes('perucommerce_catalog_edit')
        && result.body.ui_capabilities.includes('perucommerce_appointments_manage')) {
        this.showSection('agenda');
      }
      this.notice.set('Panel cargado. Las acciones se habilitan según el permiso que compruebe el servidor.');
    }catch(error){
      this.state.set(null);
      this.notice.set(error instanceof ApiFailure?error.message:'No se pudo verificar la sesión.');
    }finally{this.busy.set(false);}
  }
}
