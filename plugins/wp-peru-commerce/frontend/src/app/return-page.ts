import { Component, inject, OnInit, signal } from '@angular/core';
import { ApiFailure, PcApi } from './api';
import { soles, type GuestOrder } from './dto';

/**
 * IMP-59: el retorno de Mercado Pago no tiene autoridad para acreditar.
 * Solo la cookie guest de un pedido permite leer la fuente WordPress.
 */
@Component({
  selector:'pc-return',standalone:true,templateUrl:'./return-page.html'
})
export class ReturnPage implements OnInit {
  readonly api=inject(PcApi);
  readonly order=signal<GuestOrder|null>(null);
  readonly busy=signal(false);
  readonly status=signal('El retorno del proveedor no demuestra que el pedido esté pagado.');
  readonly money=soles;
  ngOnInit():void {void this.refresh();}
  async refresh():Promise<void>{
    if(this.busy())return;
    this.busy.set(true);
    this.status.set('Consultando el estado desde el servidor del comercio…');
    try{
      const result=await this.api.json<GuestOrder>('GET','guest/order');
      this.order.set(result.body);
      this.status.set('Consulta privada del pedido. Los cambios de pago pueden tardar en conciliarse.');
    }catch(e){
      this.order.set(null);
      this.status.set((e instanceof ApiFailure?e.message:'No se puede leer el pedido.')
        +' Abre tu enlace de seguimiento privado vigente o solicita recuperación verificada.');
    }finally{this.busy.set(false);}
  }
}
