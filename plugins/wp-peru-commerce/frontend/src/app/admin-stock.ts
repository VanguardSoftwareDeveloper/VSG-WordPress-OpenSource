import { Component, computed, inject, input, OnChanges, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, PcApi } from './api';
import type { AdminProduct, AdminVariant, MutationResult, StockAdjustmentRequest } from './admin-catalog-contract';

@Component({
  selector:'pc-admin-stock',
  standalone:true,
  imports:[FormsModule],
  templateUrl:'./admin-stock.html'
})
export class AdminStock implements OnChanges {
  readonly product=input.required<AdminProduct>();
  readonly changed=output<void>();
  readonly pendingChange=output<boolean>();
  private readonly api=inject(PcApi);
  readonly options=computed(()=>(this.product().variants??[])
    .filter(v=>this.product().kind==='physical'&&v.track_stock));
  readonly busy=signal(false);
  readonly error=signal('');
  readonly notice=signal('');

  selectedId='';
  changeText='';
  reason='';
  private pending:StockAdjustmentRequest|null=null;

  ngOnChanges():void {
    if(!this.pending && !this.options().some(x=>x.id===this.selectedId)){
      this.selectedId=this.options()[0]?.id??'';
    }
  }
  get selected():AdminVariant|null {
    return this.options().find(x=>x.id===this.selectedId)??null;
  }
  get hasPending():boolean {return this.pending!==null;}
  async adjust():Promise<void>{
    if(this.busy()||this.options().length===0)return;
    if(!this.pending){
      const sellable=this.selected;
      const trimmed=this.changeText.trim();
      const reason=this.reason.trim();
      if(!sellable){
        this.error.set('Selecciona una variante física con inventario controlado.');
        return;
      }
      if(!/^[+-]?(?:0|[1-9][0-9]{0,9})$/.test(trimmed)
        || !Number.isSafeInteger(Number(trimmed))
        || Number(trimmed)===0
        || Math.abs(Number(trimmed))>2147483647){
        this.error.set('Especifica un ajuste entero distinto de cero entre -2147483647 y +2147483647.');
        return;
      }
      if(!reason||reason.length>1000){
        this.error.set('El motivo del ajuste es obligatorio (máximo 1000 caracteres).');
        return;
      }
      this.pending={
        sellable_id:sellable.id,
        delta_units:Number(trimmed),
        reason,
        expected_version:sellable.row_version,
        idempotency_key:crypto.randomUUID(),
      };
      this.pendingChange.emit(true);
    }
    const attempt=this.pending;
    this.busy.set(true);this.error.set('');this.notice.set('Registrando ajuste con versión y motivo…');
    try {
      const reply=await this.api.json<MutationResult>('POST','admin/stock/adjustments',attempt);
      if(reply.status!==200||!reply.body.operation_id) {
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.pending=null;
      this.pendingChange.emit(false);
      this.changeText='';this.reason='';
      this.notice.set('Resultado registrado: '+reply.body.result+'. Recargando existencias y versiones.');
      this.changed.emit();
    }catch(e) {
      if(e instanceof ApiFailure && [400,422].includes(e.status)){
        this.pending=null;
        this.pendingChange.emit(false);
      }
      this.error.set((e instanceof ApiFailure?e.message:'No se pudo confirmar el ajuste.')
        +' Comprueba el saldo y la versión antes de iniciar otro intento. Si el resultado es incierto, reintenta exactamente el mismo.');
    }finally{this.busy.set(false);}
  }
  abandon():void {
    if(this.busy())return;
    this.pending=null;
    this.pendingChange.emit(false);
    this.notice.set('Intento descartado solo en el navegador. Verifica antes que no exista un ajuste aplicado.');
  }
}
