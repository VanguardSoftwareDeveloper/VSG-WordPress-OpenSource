import { Component, computed, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, PcApi } from './api';
import type { AdminBootstrap } from './dto';
import type { AdminOrdersPage, AdminOrderSummary } from './admin-orders-contract';
import { utcMinute } from './admin-agenda-contract';
import {
  type FinanceOrder, type DocumentKind,
  type CashCorrectionRequest, type FinancialAdjustment,
  type ExternalRefundRequest, type ExternalDocumentRequest,
  type ExternalDocumentResult, type DeliveryApprovalRequest,
  type MutationResult,
  validSqlId, checkReason, checkEvidence, checkExternalId,
  solesToSignedCents, centsAsSoles,
} from './admin-finance-contract';
import { AdminCases } from './admin-cases';
import { AdminReconciliation } from './admin-reconciliation';

type FinanceKind = 'cash-correction'|'adjustment'|'refund'|'document'|'delivery-approval';
type FinanceBody = CashCorrectionRequest|FinancialAdjustment
  |ExternalRefundRequest|ExternalDocumentRequest|DeliveryApprovalRequest;
interface FinanceAttempt {
  kind:FinanceKind;
  orderId:string;
  method:'POST';
  resource:string;
  payload:FinanceBody;
  expectedStatus:200|201;
  context:string;
}
const DOCUMENT_KINDS:{value:DocumentKind;label:string}[]=[
  {value:'boleta',label:'Boleta emitida externamente'},
  {value:'factura',label:'Factura emitida externamente'},
  {value:'rhe',label:'Recibo por honorarios externo'},
  {value:'ticket',label:'Ticket externo'},
  {value:'nota_credito',label:'Nota de crédito externa (exige fuente)'},
  {value:'guia',label:'Guía externa'}
];

function describe(error:unknown):string {
  return error instanceof ApiFailure?error.message
    :error instanceof Error?error.message:'No fue posible confirmar la operación.';
}

/**
 * IMP-64 — registros administrativos, nunca ejecutor de refunds MP/SUNAT.
 * Una sola operación mutable en vuelo; GET no concede permisos adicionales.
 */
@Component({
  selector:'pc-admin-finance',standalone:true,
  imports:[FormsModule,AdminCases,AdminReconciliation],
  templateUrl:'./admin-finance.html'
})
export class AdminFinance implements OnInit {
  readonly employee=input.required<AdminBootstrap>();
  readonly pendingChange=output<boolean>();
  private readonly api=inject(PcApi);
  readonly isAdmin=computed(()=>this.employee().role_label==='A');
  readonly canRead=computed(()=>this.isAdmin()
    && this.employee().ui_capabilities.includes('perucommerce_orders_read'));
  readonly canReconcile=computed(()=>this.isAdmin()
    && this.employee().ui_capabilities.includes('perucommerce_reconciliation_manage'));
  readonly canFinancial=computed(()=>this.canRead()
    && this.employee().ui_capabilities.includes('perucommerce_orders_financial_adjust'));
  readonly canCashCorrect=computed(()=>this.canRead()
    && this.employee().ui_capabilities.includes('perucommerce_cash_correct'));
  readonly canRefund=computed(()=>this.canRead()
    && this.employee().ui_capabilities.includes('perucommerce_refunds_record_external'));
  readonly canDocument=computed(()=>this.canRead()
    && this.employee().ui_capabilities.includes('perucommerce_settings_manage'));
  readonly canApproveDelivery=computed(()=>this.canFinancial());
  readonly canCases=computed(()=>this.canRead()
    && (this.canFinancial()
      ||this.employee().ui_capabilities.includes('perucommerce_orders_fulfill')
      ||this.employee().ui_capabilities.includes('perucommerce_digital_files_manage')));
  readonly hasAny=computed(()=>this.isAdmin()&&(this.canRead()||this.canReconcile()));

  readonly order=signal<FinanceOrder|null>(null);
  readonly recent=signal<AdminOrderSummary[]>([]);
  readonly recentPage=signal(1);
  readonly recentMore=signal(false);
  readonly pending=signal<FinanceAttempt|null>(null);
  readonly casesPending=signal(false);
  readonly reconPending=signal(false);
  readonly busy=signal(false);
  readonly reading=signal(false);
  readonly listing=signal(false);
  readonly needsRefresh=signal(false);
  readonly error=signal('');
  readonly notice=signal('');
  readonly money=centsAsSoles;
  readonly documentKinds=DOCUMENT_KINDS;
  readonly sources=computed(()=>this.order()?.financial_movements?.filter(
    e=>e.kind==='initial_charge'&&validSqlId(e.event_id))??[]);
  readonly originalDocuments=computed(()=>this.order()?.external_documents?.filter(
    e=>['boleta','factura','ticket'].includes(e.document_kind))??[]);
  readonly eligibleDelivery=computed(()=>{
    const order=this.order();
    return this.canApproveDelivery()&&!!order&&order.order_state==='CONFIRMADO'
      &&/^delivery:[0-9]{6}$/.test(order.delivery_snapshot??'')
      &&order.lines.some(x=>x.kind==='physical'
        &&['PENDIENTE','PREPARANDO','LISTO'].includes(x.fulfillment_state));
  });
  readonly guestRefundCases=computed(()=>this.order()?.cases?.filter(x=>
    x.case_type==='guest_request_refund'&&x.case_phase==='open_review')??[]);

  orderId='';
  cashSourceId='';
  cashDelta='';
  cashReason='';
  cashEvidence='';
  cashConfirmed=false;

  adjustDelta='';
  adjustReason='';
  adjustLineId='';
  adjustCaseId='';
  adjustConfirmed=false;

  refundSourceId='';
  refundAmount='';
  refundExternalRef='';
  refundEvidence='';
  refundConfirmed=false;

  documentKind:DocumentKind='boleta';
  documentIssuedUtc=new Date().toISOString().slice(0,16);
  documentSeries='';
  documentNumber='';
  documentExternalRef='';
  documentSourceId='';
  documentAmount='';
  documentEvidence='';
  documentChecked=false;

  deliveryUbigeo='';
  deliveryAddress='';
  deliveryReason='';
  deliveryCoverageChecked=false;
  deliveryMoneyChecked=false;

  ngOnInit():void {
    if(this.canRead())void this.loadRecent(1);
  }
  get hasPending():boolean {
    return this.pending()!==null||this.casesPending()||this.reconPending();
  }
  onChildPending(kind:'cases'|'reconciliation',active:boolean):void {
    if(kind==='cases')this.casesPending.set(active);
    else this.reconPending.set(active);
    this.pendingChange.emit(this.hasPending);
  }
  private emitPending():void {this.pendingChange.emit(this.hasPending);}

  async loadRecent(page=this.recentPage()):Promise<void>{
    if(!this.canRead()||this.listing()||this.busy())return;
    this.listing.set(true);this.error.set('');
    try{
      const reply=await this.api.json<AdminOrdersPage>('GET',
        'admin/orders?page='+page+'&per_page=12');
      if(reply.status!==200||!Array.isArray(reply.body.items)
        ||!Number.isInteger(reply.body.page)
        ||typeof reply.body.has_more!=='boolean'){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.recent.set(reply.body.items);
      this.recentPage.set(reply.body.page);
      this.recentMore.set(reply.body.has_more);
    }catch(e){this.error.set(describe(e));}
    finally{this.listing.set(false);}
  }
  async pageRecent(delta:-1|1):Promise<void>{
    if(this.hasPending||this.listing()||this.reading()
      ||(delta===-1&&this.recentPage()<=1)||(delta===1&&!this.recentMore()))return;
    await this.loadRecent(this.recentPage()+delta);
  }
  async chooseOrder(id:string):Promise<void>{
    if(this.hasPending||this.reading()||this.busy()||!validSqlId(id))return;
    this.orderId=id;
    await this.loadOrder(id);
  }
  async loadOrder(id=this.order()?.id??this.orderId,fromConfirmed=false):Promise<void>{
    if(!this.canRead()||!validSqlId(id)||this.reading()||this.busy()&&!fromConfirmed)return;
    this.reading.set(true);
    if(!fromConfirmed)this.error.set('');
    try{
      const reply=await this.api.json<FinanceOrder>('GET','admin/orders/'+id);
      const data=reply.body;
      if(reply.status!==200||data.id!==id||!validSqlId(data.row_version)
        ||!Array.isArray(data.lines)
        ||!data.agreed_notices
        ||data.financial_movements!==undefined
          &&!Array.isArray(data.financial_movements)
        ||data.external_documents!==undefined
          &&!Array.isArray(data.external_documents)){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.order.set(data);
      this.orderId=data.id;
      if(!this.pending())this.needsRefresh.set(false);
      this.notice.set('Pedido '+data.order_number+' leído. Versión '+data.row_version
        +'. Los eventos financieros son asientos históricos y no botones de pago.');
    }catch(e){
      this.error.set(describe(e)+' No se alteró la lectura anterior; consulta antes de otra decisión.');
    }finally{this.reading.set(false);}
  }

  private prepare(kind:FinanceKind):FinanceAttempt {
    const o=this.order();
    if(!o||!this.canRead()||this.needsRefresh()||!validSqlId(o.row_version)){
      throw new Error('Consulta primero el pedido y su versión real para esta decisión.');
    }
    const idempotency_key=crypto.randomUUID();
    const base={kind,orderId:o.id,method:'POST' as const};
    if(kind==='cash-correction'){
      if(!this.canCashCorrect()||!this.cashConfirmed
        ||!this.sources().some(e=>e.event_id===this.cashSourceId)){
        throw new Error('La corrección exige A, evento de cargo original visible y comprobación de caja. No se modifica un registro anterior.');
      }
      const delta=solesToSignedCents(this.cashDelta);
      const body:CashCorrectionRequest={
        source_event_id:this.cashSourceId,delta_cents:delta,
        reason:checkReason(this.cashReason),expected_version:o.row_version,
        idempotency_key,
        ...(this.cashEvidence.trim()?{evidence:checkEvidence(this.cashEvidence)}:{})
      };
      return {...base,expectedStatus:200,
        resource:'admin/orders/'+o.id+'/cash-corrections',payload:body,
        context:'Corrección inmutable de caja con evento '+this.cashSourceId+' y delta '+this.money(delta)};
    }
    if(kind==='adjustment'){
      if(!this.canFinancial()||!this.adjustConfirmed){
        throw new Error('El ajuste monetario requiere autorización financiera y decisión documentada. No equivale a dinero reintegrado.');
      }
      if(this.adjustCaseId&&!this.guestRefundCases().some(c=>c.case_id===this.adjustCaseId)){
        throw new Error('Solo un caso original guest_request_refund abierto admite vinculación para ajuste exclusivamente monetario.');
      }
      if(this.adjustLineId&&!o.lines.some(l=>l.line_id===this.adjustLineId)){
        throw new Error('La línea no pertenece al pedido consultado.');
      }
      const delta=solesToSignedCents(this.adjustDelta);
      const body:FinancialAdjustment={
        delta_cents:delta,reason:checkReason(this.adjustReason),
        expected_version:o.row_version,idempotency_key,
        ...(this.adjustLineId?{line_id:this.adjustLineId}:{}),
        ...(this.adjustCaseId?{case_id:this.adjustCaseId}:{})
      };
      return {...base,expectedStatus:200,
        resource:'admin/orders/'+o.id+'/adjustments',payload:body,
        context:'Ajuste comercial monetario '+this.money(delta)+' del pedido '+o.id};
    }
    if(kind==='refund'){
      if(!this.canRefund()||!this.refundConfirmed
        ||!this.sources().some(e=>e.event_id===this.refundSourceId)){
        throw new Error('Debe comprobarse la devolución ya realizada externamente y seleccionar el cargo real de origen.');
      }
      const amount=solesToSignedCents(this.refundAmount,false);
      const reference=checkExternalId(this.refundExternalRef,200);
      const evidence=checkExternalId(this.refundEvidence,500);
      if(reference.toLowerCase()===evidence.toLowerCase()){
        throw new Error('La constancia y la referencia de devolución deben ser evidencias distintas.');
      }
      const body:ExternalRefundRequest={
        source_payment_event_id:this.refundSourceId,amount_cents:amount,
        external_reference:reference,evidence,expected_version:o.row_version,
        idempotency_key
      };
      return {...base,expectedStatus:200,
        resource:'admin/orders/'+o.id+'/external-refunds',payload:body,
        context:'Registro de reembolso EXTERNO ya verificado de '+this.money(amount)};
    }
    if(kind==='document'){
      if(!this.canDocument()||!this.documentChecked){
        throw new Error('La referencia requiere A y verificación real de emisión por el canal externo autorizado.');
      }
      const issued_at=utcMinute(this.documentIssuedUtc);
      if(new Date(issued_at)>new Date())throw new Error('No se puede registrar fecha de emisión futura.');
      const series=this.documentSeries.trim(),number=this.documentNumber.trim();
      const reference=this.documentExternalRef.trim();
      if(!reference&&(!series||!number)
        ||series.length>30||number.length>64||reference.length>255
        ||/[\u0000-\u001f\u007f]/.test(series+number+reference)){
        throw new Error('Indica serie y número, o referencia externa, con sus límites de longitud.');
      }
      const body:ExternalDocumentRequest={
        document_kind:this.documentKind,issued_at,expected_version:o.row_version,
        idempotency_key,
        ...(series?{series}:{}),...(number?{number}:{}),
        ...(reference?{external_reference:reference}:{})
      };
      if(this.documentKind==='nota_credito'){
        if(!this.originalDocuments().some(x=>x.id===this.documentSourceId)){
          throw new Error('La nota de crédito debe referenciar un documento anterior real del mismo pedido.');
        }
        body.source_document_id=this.documentSourceId;
        body.evidence_ref=checkEvidence(this.documentEvidence);
        body.amount_cents=solesToSignedCents(this.documentAmount,false);
      }else{
        if(this.documentEvidence.trim())body.evidence_ref=checkEvidence(this.documentEvidence);
        if(this.documentAmount.trim())body.amount_cents=solesToSignedCents(this.documentAmount,false);
      }
      return {...base,expectedStatus:201,
        resource:'admin/orders/'+o.id+'/external-documents',payload:body,
        context:'Referencia '+this.documentKind+' de comprobante emitido fuera del plugin'};
    }
    if(!this.eligibleDelivery()||!this.deliveryCoverageChecked||!this.deliveryMoneyChecked){
      throw new Error('La aprobación A exige revisar cobertura/tarifa actual y posibles créditos con evidencia comercial independiente.');
    }
    const ubigeo=this.deliveryUbigeo.trim(),address=this.deliveryAddress.trim();
    if(!/^[0-9]{6}$/.test(ubigeo)||!address||address.length>500
      ||/[\u0000-\u001f\u007f]/.test(address)){
      throw new Error('UBIGEO de seis dígitos y dirección válida obligatorios.');
    }
    const reason=checkReason(this.deliveryReason);
    if(/[\u0000-\u001f\u007f]/.test(reason)){
      throw new Error('El motivo de dirección debe ser de una sola línea y sin caracteres de control.');
    }
    const body:DeliveryApprovalRequest={
      new_ubigeo:ubigeo,new_address:address,reason,
      expected_version:o.row_version,idempotency_key
    };
    return {...base,expectedStatus:200,
      resource:'admin/orders/'+o.id+'/delivery-change',payload:body,
      context:'Decisión administrativa de dirección con posible impacto en tarifa del pedido '+o.id};
  }

  async submit(kind:FinanceKind):Promise<void>{
    if(!this.canRead()||this.busy()||this.reading()||this.listing()
      ||this.casesPending()||this.reconPending())return;
    let attempt=this.pending();
    if(attempt&&attempt.kind!==kind){
      this.error.set('Hay otra operación pendiente con resultado incierto. No se iniciará una segunda.');
      return;
    }
    if(!attempt){
      try{attempt=this.prepare(kind);this.pending.set(attempt);this.emitPending();}
      catch(e){this.error.set(describe(e));return;}
    }
    this.busy.set(true);this.error.set('');
    let confirmed=false;
    try{
      const reply=await this.api.json<MutationResult|ExternalDocumentResult>(
        'POST',attempt.resource,attempt.payload
      );
      const body=reply.body;
      if(reply.status!==attempt.expectedStatus||!body.operation_id
        ||attempt.kind==='document'
          &&(!('record_id' in body)||!validSqlId(body.record_id??'')
            ||body.external_only!==true)
        ||attempt.kind!=='document'
          &&(!('result' in body)||typeof body.result!=='string'||!body.result)){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      confirmed=true;
      this.pending.set(null);this.needsRefresh.set(true);
      this.notice.set('El servidor registró: '+attempt.context
        +'. Esto NO ejecutó refund externo, envío fiscal ni otro cobro.');
      // El GET posterior no debe reinterpretar un POST ya confirmado.
      await this.loadOrder(attempt.orderId,true);
    }catch(e){
      if(confirmed){
        this.error.set('La escritura fue confirmada, pero la lectura posterior falló. Consulta el pedido y sus eventos antes de iniciar otra decisión.');
      }else{
        if(e instanceof ApiFailure&&[400,401,403,404,410,422,423].includes(e.status)){
          this.pending.set(null);
        }
        this.error.set(describe(e)
          +(e instanceof ApiFailure&&e.status===409
            ?' La versión o estado cambió; compara los hechos y el expediente antes de otro intento.'
            :' El resultado puede ser incierto; conserva la misma clave y evita registrar dos veces.'));
      }
    }finally{
      this.busy.set(false);
      this.emitPending();
    }
    if(confirmed){
      await this.loadRecent();
    }
  }
  abandon():void{
    if(this.busy()||this.reading()||this.listing())return;
    this.pending.set(null);this.emitPending();
    this.notice.set('Se descartó únicamente el intento local. Verifica el movimiento/evento real antes de generar otra operación.');
  }
}
