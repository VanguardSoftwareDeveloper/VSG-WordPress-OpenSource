import { Component, computed, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, PcApi } from './api';
import { soles, type AdminBootstrap } from './dto';
import {
  type AdminOrder, type AdminOrderLine, type AdminOrdersPage,
  type AdminOrderSummary, type OrderState, type FulfillmentTarget,
  type PendingOrderAction, type OrderAction,
  type FulfillmentChangeRequest, type CashReceiptRequest,
  type CancelLineRequest, type DeliveryChangeRequest,
  type LogisticsIncidentRequest, type MutationResult,
  type LogisticsIncidentAccepted,
  physicalTargets, physicalQuantitiesValid, cashDecimalToCents,
  reasonText, opaqueEvidence,
} from './admin-orders-contract';

const ORDERS_FILTERS: {value: 'all'|OrderState; label: string}[] = [
  {value:'all',label:'Todos los estados'},
  {value:'CONFIRMADO',label:'Confirmados · tareas actuales'},
  {value:'PENDIENTE_PAGO',label:'Pendientes de pago'},
  {value:'VENCIDO',label:'Vencidos · revisar'},
  {value:'CANCELADO',label:'Cancelados'},
  {value:'FINALIZADO',label:'Finalizados'}
];
const READY_FOR_CANCEL = ['PENDIENTE','PREPARANDO','LISTO'];

function canCancelState(line: AdminOrderLine): boolean {
  return line.kind === 'physical'
    ? READY_FOR_CANCEL.includes(line.fulfillment_state)
    : line.kind === 'service' && line.fulfillment_state === 'CONFIRMADA';
}
function isId(value: string): boolean {
  return /^[1-9][0-9]{0,19}$/.test(value);
}
function message(error: unknown): string {
  return error instanceof ApiFailure ? error.message
    : error instanceof Error ? error.message
    : 'No fue posible confirmar la operación.';
}

/** IMP-63: capa de presentación, las decisiones de dinero/stock son del servidor. */
@Component({
  selector: 'pc-admin-orders',
  standalone: true,
  imports: [FormsModule],
  templateUrl: './admin-orders.html',
})
export class AdminOrders implements OnInit {
  readonly employee = input.required<AdminBootstrap>();
  readonly pendingChange = output<boolean>();
  private readonly api = inject(PcApi);

  readonly canRead = computed(() =>
    this.employee().ui_capabilities.includes('perucommerce_orders_read'));
  readonly canFulfill = computed(() => this.canRead()
    && this.employee().ui_capabilities.includes('perucommerce_orders_fulfill'));
  readonly canCash = computed(() => this.canRead()
    && this.employee().ui_capabilities.includes('perucommerce_cash_record'));
  readonly canCancel = computed(() => this.canRead() && (
    this.employee().ui_capabilities.includes('perucommerce_orders_cancel_unpaid')
    || (this.employee().role_label === 'A'
      && this.employee().ui_capabilities.includes('perucommerce_orders_financial_adjust'))));
  readonly canDelivery = computed(() => this.canRead()
    // El backend ejecuta aprobación directa para A con capacidad financiera,
    // incluso créditos por tarifa menor. Esa decisión requiere UI de
    // revisión económica (IMP-64), no el formulario operativo sin cotización.
    && this.employee().role_label === 'O'
    && this.employee().ui_capabilities.includes('perucommerce_orders_fulfill'));
  readonly canIncident = computed(() => this.employee().role_label === 'A'
    && this.canFulfill());

  readonly orders = signal<AdminOrderSummary[]>([]);
  readonly detail = signal<AdminOrder|null>(null);
  readonly selectedLineId = signal<string|null>(null);
  readonly selectedLine = computed(() => this.detail()?.lines.find(
    line => line.line_id === this.selectedLineId()) ?? null);
  readonly listBusy = signal(false);
  readonly detailBusy = signal(false);
  readonly actionBusy = signal(false);
  readonly page = signal(1);
  readonly hasMore = signal(false);
  readonly error = signal('');
  readonly notice = signal('');
  readonly pending = signal<PendingOrderAction|null>(null);
  readonly search = signal('');
  readonly visibleOrders = computed(() => {
    const term=this.search().trim().toLocaleLowerCase('es-PE');
    return term ? this.orders().filter(o =>
      o.id.includes(term) || o.order_number.toLocaleLowerCase('es-PE').includes(term))
      : this.orders();
  });
  readonly possibleFulfillment = computed(() => {
    const line=this.selectedLine(),order=this.detail();
    if (!line || !order || line.kind!=='physical'
      || order.order_state!=='CONFIRMADO' || !physicalQuantitiesValid(line))return [];
    // El DTO usa "pickup" o "delivery:UBIGEO"; no asumir modalidad
    // ni ofrecer despacho a un retiro en local.
    const mode=order.delivery_snapshot === 'pickup'?'pickup'
      : /^delivery:[0-9]{6}$/.test(order.delivery_snapshot??'')?'delivery':null;
    const q=line.physical_quantities!;
    if (['PENDIENTE','PREPARANDO','LISTO'].includes(line.fulfillment_state)
      && (q.pending_stock_out_units<1 || q.stock_out_units!==0)) return [];
    if (line.fulfillment_state==='DESPACHADO'
      && (q.pending_stock_out_units!==0 || q.stock_out_units<1)) return [];
    return physicalTargets(line.fulfillment_state).filter(target => {
      if (target==='DESPACHADO') return mode==='delivery';
      if (target==='ENTREGADO') return (
        (line.fulfillment_state==='DESPACHADO' && mode==='delivery')
        || (line.fulfillment_state==='LISTO' && mode==='pickup')
      );
      return true;
    });
  });
  readonly canCancelSelected = computed(() => {
    const order=this.detail(),line=this.selectedLine();
    return this.canCancel() && !!order && !!line
      && order.order_state === 'CONFIRMADO'
      && order.payment_indicator === 'PENDIENTE_COBRO'
      && canCancelState(line);
  });
  readonly canOpenIncident = computed(() => {
    const order=this.detail(),line=this.selectedLine();
    return this.canIncident() && !!order && !!line
      && order.order_state === 'CONFIRMADO'
      && line.kind === 'physical' && line.fulfillment_state === 'DESPACHADO'
      && physicalQuantitiesValid(line)
      && !(order.cases??[]).some(c => c.case_type==='logistics_non_delivery'
        && c.order_line_id===line.line_id
        && ['open_review','in_review'].includes(c.case_phase));
  });
  readonly canChangeDelivery = computed(() => {
    const order=this.detail();
    return this.canDelivery() && !!order
      && order.order_state === 'CONFIRMADO'
      && /^delivery:[0-9]{6}$/.test(order.delivery_snapshot??'')
      && order.lines.some(line => line.kind === 'physical'
        && physicalQuantitiesValid(line)
        && ['PENDIENTE','PREPARANDO','LISTO'].includes(line.fulfillment_state));
  });
  readonly canRecordCash = computed(() => {
    const order=this.detail();
    return this.canCash() && !!order
      && order.order_state === 'CONFIRMADO'
      && order.payment_indicator === 'PENDIENTE_COBRO';
  });
  readonly money = soles;
  readonly filters = ORDERS_FILTERS;

  filter: 'all'|OrderState = 'CONFIRMADO';
  fulfillmentTarget: FulfillmentTarget = 'PREPARANDO';
  fulfillmentEvidence = '';
  cancelReason = '';
  cashAmount = '';
  cashAttestation = false;
  deliveryUbigeo = '';
  deliveryAddress = '';
  deliveryReason = '';
  incidentReason = '';
  incidentEvidence = '';

  ngOnInit(): void {
    if (this.canRead()) void this.loadOrders(1);
  }

  /** Paginación por filtro de estado del servidor; búsqueda solo local. */
  async loadOrders(page=this.page(), duringMutation=false): Promise<void> {
    if (!this.canRead() || this.listBusy() || (this.actionBusy() && !duringMutation)) return;
    this.listBusy.set(true);
    if(!duringMutation)this.error.set('');
    try {
      const path='admin/orders?page='+page+'&per_page=20'
        +(this.filter==='all'?'':'&state='+this.filter);
      const reply=await this.api.json<AdminOrdersPage>('GET',path);
      const result=reply.body;
      if (reply.status!==200 || !Array.isArray(result.items)
        || result.items.length>100 || !Number.isInteger(result.page)
        || typeof result.has_more!=='boolean') {
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.orders.set(result.items);
      this.page.set(result.page);
      this.hasMore.set(result.has_more);
    } catch(e) {
      this.error.set(message(e));
    } finally {
      this.listBusy.set(false);
    }
  }

  async changeFilter(): Promise<void> {
    if (this.pending() || this.listBusy() || this.detailBusy() || this.actionBusy())return;
    this.detail.set(null);
    this.selectedLineId.set(null);
    await this.loadOrders(1);
  }
  async movePage(direction: -1|1): Promise<void> {
    if (this.pending() || this.listBusy() || this.detailBusy() || this.actionBusy()
      || (direction===-1 && this.page()<=1) || (direction===1 && !this.hasMore()))return;
    this.detail.set(null);
    this.selectedLineId.set(null);
    await this.loadOrders(this.page()+direction);
  }

  async openOrder(id: string): Promise<void> {
    if (this.pending() || !isId(id))return;
    await this.reloadDetail(id);
  }

  async reloadDetail(id=this.detail()?.id??'', duringMutation=false): Promise<void> {
    if (!this.canRead() || !isId(id) || (this.actionBusy() && !duringMutation) || this.detailBusy()) return;
    this.detailBusy.set(true);
    this.error.set('');
    try {
      const reply=await this.api.json<AdminOrder>('GET','admin/orders/'+id);
      const order=reply.body;
      if (reply.status!==200 || order.id!==id || !isId(order.row_version)
        || typeof order.agreed_notices?.policy_notice!=='string'
        || typeof order.agreed_notices?.privacy_notice!=='string'
        || !Array.isArray(order.lines) || order.lines.length>100
        || order.lines.some(line => !isId(line.line_id)
          || !isId(line.row_version) || !physicalQuantitiesValid(line))) {
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      const switched=this.detail()?.id!==id;
      this.detail.set(order);
      if (switched && !this.pending())this.selectedLineId.set(null);
      if (this.selectedLineId() && !order.lines.some(
        line => line.line_id===this.selectedLineId())) {
        // Si hay operación pendiente, su payload conserva la línea vieja:
        // no se modifica ni se "transfiere" a otra fila.
        if (!this.pending())this.selectedLineId.set(null);
      }
      if (!this.pending()) {
        const selected=this.selectedLine();
        if (selected && !physicalTargets(selected.fulfillment_state).includes(
          this.fulfillmentTarget)) {
          this.fulfillmentTarget=physicalTargets(selected.fulfillment_state)[0]??'PREPARANDO';
        }
      }
      this.notice.set('Pedido '+order.order_number+' consultado. Versión de pedido '
        +order.row_version+'. El estado de pago y cada línea son independientes.');
    } catch(e) {
      // Un GET fallido conserva el detalle anterior y el intento de mutación.
      this.error.set(message(e)+' Se conserva la última lectura; verifica antes de actuar.');
    } finally {
      this.detailBusy.set(false);
    }
  }

  selectLine(id: string): void {
    if (this.pending() || this.actionBusy())return;
    const line=this.detail()?.lines.find(l=>l.line_id===id);
    if (!line)return;
    this.selectedLineId.set(id);
    this.fulfillmentTarget=physicalTargets(line.fulfillment_state)[0]??'PREPARANDO';
    this.fulfillmentEvidence='';
    this.cancelReason='';
    this.incidentReason='';
    this.incidentEvidence='';
    this.error.set('');
  }

  closeOrder(): void {
    if (this.pending() || this.actionBusy())return;
    this.detail.set(null);
    this.selectedLineId.set(null);
    this.error.set('');
  }

  private makeAction(action: OrderAction): PendingOrderAction {
    const order=this.detail();
    if (!order || !isId(order.id) || !isId(order.row_version)) {
      throw new Error('Consulta el pedido y su versión actual antes de operar.');
    }
    const key=crypto.randomUUID();
    if (action==='fulfillment') {
      const line=this.selectedLine();
      if (!this.canFulfill() || !line || line.kind!=='physical'
        || !physicalQuantitiesValid(line)
        || !this.possibleFulfillment().includes(this.fulfillmentTarget)) {
        throw new Error('Transición física no disponible. Recarga el pedido y revisa la línea.');
      }
      const outbound=this.fulfillmentTarget==='DESPACHADO'
        || this.fulfillmentTarget==='ENTREGADO';
      const evidence=this.fulfillmentEvidence.trim();
      const payload:FulfillmentChangeRequest={
        target:this.fulfillmentTarget, expected_version:line.row_version,
        idempotency_key:key,
        ...(outbound?{evidence:opaqueEvidence(evidence)}
          :evidence?{evidence:opaqueEvidence(evidence)}:{})
      };
      return {
        action,method:'POST',orderId:order.id,lineId:line.line_id,successStatus:200,
        resource:'admin/orders/'+order.id+'/lines/'+line.line_id+'/fulfillment',
        payload,description:'Transición física a '+payload.target
          +' de línea '+line.line_id+' · versión '+line.row_version
      };
    }
    if (action==='cancel') {
      const line=this.selectedLine();
      if (!line || !this.canCancelSelected()) {
        throw new Error('La cancelación simple no es elegible en esta lectura. Los casos con dinero o ajustes requieren atención administrativa.');
      }
      const payload:CancelLineRequest={
        expected_version:line.row_version,reason:reasonText(this.cancelReason),
        idempotency_key:key
      };
      return {
        action,method:'POST',orderId:order.id,lineId:line.line_id,successStatus:200,
        resource:'admin/orders/'+order.id+'/lines/'+line.line_id+'/cancel',
        payload,description:'Solicitud de cancelación de línea '+line.line_id
          +' · versión '+line.row_version
      };
    }
    if (action==='cash') {
      if (!this.canRecordCash() || !this.cashAttestation) {
        throw new Error('El efectivo solo puede registrarse tras comprobar el pedido contraentrega y la recepción física real del importe neto completo.');
      }
      const payload:CashReceiptRequest={
        amount_cents:cashDecimalToCents(this.cashAmount),
        expected_version:order.row_version,idempotency_key:key
      };
      return {
        action,method:'POST',orderId:order.id,successStatus:200,
        resource:'admin/orders/'+order.id+'/cash-receipts',
        payload,description:'Registro declarado de efectivo en pedido '+order.id
          +' · versión '+order.row_version
      };
    }
    if (action==='delivery') {
      if (!this.canChangeDelivery()) {
        throw new Error('El cambio de entrega no está disponible para este pedido o estado.');
      }
      if (!/^[0-9]{6}$/.test(this.deliveryUbigeo.trim())) {
        throw new Error('El distrito requiere un código UBIGEO de seis dígitos.');
      }
      const address=this.deliveryAddress.trim();
      const reason=reasonText(this.deliveryReason);
      if (!address || address.length>500
        || /[\u0000-\u001f\u007f]/.test(address)
        || /[\u0000-\u001f\u007f]/.test(reason)) {
        throw new Error('Dirección y motivo deben ser textos de una línea, sin caracteres de control.');
      }
      const payload:DeliveryChangeRequest={
        new_ubigeo:this.deliveryUbigeo.trim(),new_address:address,
        reason,
        expected_version:order.row_version,idempotency_key:key
      };
      return {
        action,method:'POST',orderId:order.id,successStatus:200,
        resource:'admin/orders/'+order.id+'/delivery-change',
        payload,description:'Propuesta operativa de dirección para pedido '+order.id
          +' · versión '+order.row_version
      };
    }
    const line=this.selectedLine();
    if (!line || !this.canOpenIncident()) {
      throw new Error('La apertura de incidencia exige administrador y línea física despachada sin entrega confirmada.');
    }
    const payload:LogisticsIncidentRequest={
      reason:reasonText(this.incidentReason),
      evidence_ref:opaqueEvidence(this.incidentEvidence),
      expected_version:line.row_version,idempotency_key:key
    };
    return {
      action:'incident',method:'POST',orderId:order.id,lineId:line.line_id,
      successStatus:201,
      resource:'admin/orders/'+order.id+'/lines/'+line.line_id+'/logistics-incidents',
      payload,description:'Incidencia logística de línea '+line.line_id
        +' · versión '+line.row_version
    };
  }

  /**
   * Un solo intento mutable. En 409/red/5xx se conserva exactamente el
   * mismo body/UUID; el usuario debe consultar antes de iniciar otro.
   */
  async submit(action: OrderAction): Promise<void> {
    if (!this.canRead() || this.actionBusy() || this.detailBusy() || this.listBusy())return;
    let attempt=this.pending();
    if (attempt && attempt.action!==action) {
      this.error.set('Hay un intento anterior pendiente. Comprueba o resuelve su resultado antes de iniciar otro.');
      return;
    }
    if (!attempt) {
      try {
        attempt=this.makeAction(action);
        this.pending.set(attempt);
        this.pendingChange.emit(true);
      }catch(e){
        this.error.set(message(e));
        return;
      }
    }
    this.actionBusy.set(true);
    this.error.set('');
    this.notice.set('Consultando resultado de '+attempt.description+'. No se anticipa éxito.');
    let confirmed=false;
    try {
      const reply=await this.api.json<MutationResult|LogisticsIncidentAccepted>(
        attempt.method,attempt.resource,attempt.payload
      );
      if (reply.status!==attempt.successStatus
        || typeof reply.body.operation_id!=='string'
        || !reply.body.operation_id
        || (attempt.action!=='incident'
          && (typeof (reply.body as MutationResult).result!=='string'
            || !(reply.body as MutationResult).result))) {
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      if (attempt.action==='incident' && !isId(
        (reply.body as LogisticsIncidentAccepted).case_id??'')) {
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      const outcome = attempt.action==='incident'
        ? 'Expediente '+(reply.body as LogisticsIncidentAccepted).case_id
          +' abierto para evaluación administrativa; no equivale a entrega, cancelación ni reembolso.'
        : 'Operación '+reply.body.operation_id
          +' aceptada por el servidor. Revisa la lectura posterior antes de otra acción.';
      confirmed=true;
      this.pending.set(null);
      this.clearForm(attempt.action);
      // Releer: el código 200 es confirmación de la operación REST,
      // no prueba de dinero físico recibido, entrega o estado global.
      await this.reloadAfterSuccess(attempt.orderId);
      this.notice.set(outcome);
    } catch(e) {
      if (confirmed) {
        // No volver a tratar una respuesta 200/201 legítima como un
        // resultado incierto solo porque falló un GET posterior.
        this.error.set('La operación REST fue confirmada, pero no se pudo releer el pedido. Consulta el historial antes de actuar nuevamente.');
        return;
      }
      if (e instanceof ApiFailure
        && [400,401,403,404,410,422,423].includes(e.status)) {
        // Rechazo explícito sin aceptación de efecto. Permitir corrección.
        this.pending.set(null);
        this.pendingChange.emit(false);
      }
      this.error.set(message(e)
        +(e instanceof ApiFailure && e.status===409
          ? ' Conflicto de estado o versión: actualiza la línea/pedido, compara hechos y revisa antes de descartar el intento.'
          : ' Si el resultado es incierto, conserva la misma clave/payload. No dupliques el cobro, salida o expediente.'));
    }finally {
      if (confirmed) this.pendingChange.emit(false);
      this.actionBusy.set(false);
    }
  }

  /** Las lecturas después de un éxito nunca se convierten en otro POST. */
  private async reloadAfterSuccess(id: string): Promise<void> {
    // Mantener actionBusy durante las lecturas para no aceptar otro POST
    // entre la confirmación y la actualización del detalle.
    await this.reloadDetail(id,true);
    await this.loadOrders(this.page(),true);
  }

  private clearForm(action: OrderAction):void {
    switch(action){
      case 'fulfillment': this.fulfillmentEvidence=''; break;
      case 'cancel': this.cancelReason=''; break;
      case 'cash':
        this.cashAmount='';this.cashAttestation=false;break;
      case 'delivery':
        this.deliveryUbigeo='';this.deliveryAddress='';this.deliveryReason='';break;
      case 'incident':
        this.incidentReason='';this.incidentEvidence='';break;
    }
  }

  abandonPending():void {
    if (this.actionBusy() || this.detailBusy() || this.listBusy()) return;
    this.pending.set(null);
    this.pendingChange.emit(false);
    this.notice.set('Intento descartado solo en esta pantalla. Antes de generar otro, verifica en el pedido, sus versiones e historial si el efecto quedó registrado.');
  }
}
