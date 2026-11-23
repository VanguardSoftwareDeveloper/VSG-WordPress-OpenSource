import { Component, computed, inject, input, OnChanges, output, signal, SimpleChanges } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, PcApi } from './api';
import { PC_CONFIG } from './config';
import type { AdminBootstrap } from './dto';
import type { AdminOrderLine } from './admin-orders-contract';
import {
  type FinanceOrder, type AdminCaseSummary, type AdminCaseDetail,
  type ManualVerification, type AdminManualCaseCreate,
  type AdminManualCaseOpened, type AdminManualCaseClose,
  type AdminManualCaseClosed, type FinancialAdjustment,
  type CaseProposalRequest, type CaseResolutionRequest,
  type CaseActionResult, type MutationResult,
  type AdminDigitalRight, type DigitalCaseDecisionCode,
  type DigitalCaseDecisionRequest, type DigitalRightRevokeRequest,
  type DigitalRightRevokeResult,
  validSqlId, checkReason, checkEvidence, checkAuditNote,
  manualPartialDelta, centsAsSoles
} from './admin-finance-contract';

type CaseAction='manual-open'|'manual-adjust'|'manual-reject'
  |'manual-withdrawn'|'logistics-propose'|'logistics-resolve'
  |'digital-decide'|'digital-revoke';
type CaseBody=AdminManualCaseCreate|AdminManualCaseClose
  |FinancialAdjustment|CaseProposalRequest|CaseResolutionRequest
  |DigitalCaseDecisionRequest|DigitalRightRevokeRequest;
interface CaseAttempt {
  action:CaseAction;
  orderId:string;
  caseId?:string;
  method:'POST';
  resource:string;
  body:CaseBody;
  expectedStatus:200|201;
  context:string;
}
function describe(error:unknown):string {
  return error instanceof ApiFailure?error.message
    :error instanceof Error?error.message:'No se pudo confirmar el expediente.';
}
function positiveUnits(raw:string):number {
  if(!/^[1-9][0-9]{0,5}$/.test(raw.trim()))throw new Error('Ingresa unidades enteras positivas.');
  const value=Number(raw.trim());
  if(!Number.isSafeInteger(value)||value>100000)throw new Error('Las unidades superan el límite.');
  return value;
}

@Component({
  selector:'pc-admin-cases',standalone:true,
  imports:[FormsModule],templateUrl:'./admin-cases.html'
})
export class AdminCases implements OnChanges {
  readonly employee=input.required<AdminBootstrap>();
  readonly blocked=input(false);
  readonly order=input.required<FinanceOrder>();
  readonly changed=output<void>();
  readonly pendingChange=output<boolean>();
  private readonly api=inject(PcApi);
  readonly nativeDigitalOpen=inject(PC_CONFIG);

  readonly canRead=computed(()=>this.employee().role_label==='A'
    && this.employee().ui_capabilities.includes('perucommerce_orders_read'));
  readonly canFinancial=computed(()=>this.canRead()
    && this.employee().ui_capabilities.includes('perucommerce_orders_financial_adjust'));
  readonly canLogistics=computed(()=>this.canRead()
    && this.employee().ui_capabilities.includes('perucommerce_orders_fulfill'));
  readonly canResolveLogistics=computed(()=>this.canFinancial()&&this.canLogistics());
  readonly canDigital=computed(()=>this.canRead()
    && this.employee().ui_capabilities.includes('perucommerce_digital_files_manage'));
  readonly digitalRights=computed(()=>this.canDigital()
    ? (this.order().digital_rights??[]) : []);
  readonly canOpenTypedDigitalCase=computed(()=>{
    const d=this.detail();
    return this.canDigital() && !!this.nativeDigitalOpen.digitalOpenUrl
      && !!this.nativeDigitalOpen.digitalOpenNonce
      && d?.case_type==='guest_request_refund'
      && d.case_phase==='open_review'
      && this.digitalRights().some(r=>!r.revoked);
  });
  readonly digitalApprovedRef=computed(()=>{
    const d=this.detail();
    if(!d||d.case_type!=='digital_access_dispute'
      ||d.case_phase!=='closed_revocation_authorized'||d.events_has_more)return null;
    const events=d.events.filter(e=>e.event_code==='digital_case_decided'
      &&e.decision_code==='authorize_revocation');
    const ref=events[events.length-1]?.evidence_ref;
    return typeof ref==='string'&&ref.length>0?ref:null;
  });

  readonly selected=signal<AdminCaseSummary|null>(null);
  readonly detail=signal<AdminCaseDetail|null>(null);
  readonly detailBusy=signal(false);
  readonly busy=signal(false);
  readonly pending=signal<CaseAttempt|null>(null);
  readonly needsRefresh=signal(false);
  readonly error=signal('');
  readonly notice=signal('');
  readonly casePage=signal(1);
  readonly originalUnits=computed(()=>this.detail()?.original_request?.requested_affected_units??null);
  readonly partialLine=computed(()=>{
    const summary=this.selected();
    if(summary?.case_type!=='physical_partial_quantity_adjustment'||!summary.order_line_id)return null;
    return this.order().lines.find(l=>l.line_id===summary.order_line_id)??null;
  });
  readonly canPartiallyAdjust=computed(()=>{
    const d=this.detail(),line=this.partialLine(),units=this.originalUnits();
    return this.canFinancial() && !!d && d.case_phase==='open_review'
      && d.case_type==='physical_partial_quantity_adjustment'
      && typeof units==='number' && Number.isInteger(units) && !!line && line.kind==='physical'
      && !!line.physical_quantities
      && (units??0)>0
      && (units??0)<line.physical_quantities.pending_stock_out_units
      && line.physical_quantities.stock_out_units===0
      && ['PENDIENTE','PREPARANDO','LISTO'].includes(line.fulfillment_state);
  });
  readonly latestProposal=computed(()=>{
    const d=this.detail();
    if(!d)return null;
    const proposals=d.events.filter(e=>e.event_code==='logistics_case_proposed');
    return proposals[proposals.length-1]??null;
  });
  readonly money=centsAsSoles;
  private lastOrderId:string|null=null;

  manualLineId='';
  manualUnits='';
  manualReason='';
  manualContactMethod:ManualVerification['contact_control_method']='outbound_call_to_registered_phone';
  manualFactCategory:ManualVerification['nonpublic_fact_category']='private_order_detail';
  manualPhoneConfirmed=false;
  manualFactConfirmed=false;
  manualNote='';

  closeReason='';
  withdrawalMethod:ManualVerification['contact_control_method']='outbound_call_to_registered_phone';
  withdrawalCategory:ManualVerification['nonpublic_fact_category']='private_order_detail';
  withdrawalPhoneConfirmed=false;
  withdrawalFactConfirmed=false;
  withdrawalNote='';

  adjustReason='';
  logisticsProposal:CaseProposalRequest['resolution_proposal']='continue_investigation';
  logisticsEvidence='';
  logisticsReason='';
  logisticsResolveEvidence='';
  logisticsResolveReason='';
  digitalDecision:DigitalCaseDecisionCode='maintain_access';
  digitalEvidence='';
  digitalReason='';
  digitalRevokeReason='';
  selectedRightId='';
  typedCaseRightId='';
  typedCaseReason='';
  typedCaseKey=crypto.randomUUID();

  ngOnChanges(changes:SimpleChanges):void {
    // Cambio de bloqueo/rol no es una nueva lectura del pedido.
    // Solo una respuesta R8-21 nueva permite liberar needsRefresh.
    if(!changes['order'])return;
    const order=this.order();
    if(this.lastOrderId!==order.id){
      if(!this.pending()){
        this.selected.set(null);
        this.detail.set(null);
        this.casePage.set(1);
        this.needsRefresh.set(false);
      }
      this.lastOrderId=order.id;
    }
    if(!this.pending()){
      const selected=this.selected();
      if(selected){
        const updated=(order.cases??[]).find(c=>c.case_id===selected.case_id);
        if(!updated||updated.row_version!==selected.row_version){
          this.selected.set(updated??null);
          this.detail.set(null);
          this.casePage.set(1);
        }
      }
      if(this.needsRefresh()){
        this.needsRefresh.set(false);
        // Tras una mutación el caso vuelve a leerse explícitamente,
        // jamás resolver desde el snapshot anterior.
        this.detail.set(null);this.selected.set(null);
      }
    }
    if(!order.lines.some(l=>l.line_id===this.manualLineId)){
      this.manualLineId='';
    }
    if(!this.digitalRights().some(r=>r.right_id===this.selectedRightId)){
      this.selectedRightId='';
    }
    if(!this.digitalRights().some(r=>r.right_id===this.typedCaseRightId)){
      this.typedCaseRightId='';
    }
  }

  get eligiblePhysical():AdminOrderLine[] {
    if(!this.canFinancial()||this.order().order_state!=='CONFIRMADO')return [];
    return this.order().lines.filter(l=>l.kind==='physical'
      && ['PENDIENTE','PREPARANDO','LISTO'].includes(l.fulfillment_state)
      && !!l.physical_quantities
      && l.physical_quantities.stock_out_units===0
      && l.physical_quantities.pending_stock_out_units>1
      && !(this.order().cases??[]).some(c=>
        c.order_line_id===l.line_id
        && c.case_type==='physical_partial_quantity_adjustment'
        && c.case_phase==='open_review'));
  }
  get eligibleTypedRights():AdminDigitalRight[] {
    return this.canDigital()?this.digitalRights().filter(r=>!r.revoked):[];
  }
  beginTypedDigitalOpen(event:Event):void {
    const d=this.detail(),caseId=d?.case_id;
    if(!this.canOpenTypedDigitalCase() || this.blocked()
      ||this.busy()||this.detailBusy()||this.pending()!==null||this.needsRefresh()
      ||!caseId||!validSqlId(caseId)
      ||!this.eligibleTypedRights.some(r=>r.right_id===this.typedCaseRightId)) {
      event.preventDefault();
      this.error.set('No hay un expediente invitado y un derecho original autorizados para abrir el caso tipado.');
      return;
    }
    try{
      const url=new URL(this.nativeDigitalOpen.digitalOpenUrl??'');
      if(url.origin!==location.origin || url.protocol!=='https:'
        || !url.pathname.endsWith('/wp-admin/admin-post.php')
        || url.search!=='' || url.hash!=='') {
        throw new Error('La acción administrativa no pertenece a este WordPress.');
      }
      this.typedCaseReason=checkReason(this.typedCaseReason);
    }catch(e){
      event.preventDefault();
      this.error.set(describe(e));
      return;
    }
    // Formulario nativo WP: la navegación, no una respuesta de Angular,
    // determina si se registró la apertura. Mantener la misma clave en
    // doble clic; el dominio conserva su idempotencia.
    this.error.set('');
  }
  get eligibleDigitalRights():AdminDigitalRight[] {
    const summary=this.selected();
    if(!this.canDigital()||summary?.case_type!=='digital_access_dispute'
      ||!summary.order_line_id)return [];
    return this.digitalRights().filter(r=>r.order_line_id===summary.order_line_id
      &&!r.revoked);
  }
  get selectedDigitalRight():AdminDigitalRight|null {
    return this.eligibleDigitalRights.find(r=>r.right_id===this.selectedRightId)??null;
  }
  get partialPreview():string {
    const u=this.originalUnits(),l=this.partialLine();
    if(!l||!u)return 'Sin petición original verificable.';
    try{return this.money(manualPartialDelta(l.unit_price_cents,u));}
    catch{return 'No se pudo calcular el valor histórico exacto.';}
  }

  selectCase(summary:AdminCaseSummary):void {
    if(this.pending()||this.busy()||this.detailBusy()||!this.canRead())return;
    this.selected.set(summary);
    this.selectedRightId='';
    this.typedCaseRightId='';
    this.typedCaseReason='';
    // Una selección de expediente NUEVO usa otra raíz; un doble envío
    // del mismo formulario conserva la misma clave hasta navegación.
    this.typedCaseKey=crypto.randomUUID();
    this.detail.set(null);
    this.casePage.set(1);
    this.error.set('');
    void this.loadCase(summary.case_id,1);
  }

  async loadCase(caseId=this.selected()?.case_id??'',page=this.casePage()):Promise<void>{
    if(!this.canRead()||!validSqlId(caseId)||this.busy()||this.detailBusy())return;
    const orderId=this.order().id;
    this.detailBusy.set(true);this.error.set('');
    try{
      const result=await this.api.json<FinanceOrder>('GET',
        'admin/orders/'+orderId+'?case_id='+caseId+'&case_events_page='+page);
      const d=result.body.case_detail;
      if(this.order().id!==orderId
        ||this.selected()?.case_id!==caseId)return;
      if(result.status!==200||result.body.id!==orderId||!d
        ||d.case_id!==caseId||!validSqlId(d.row_version)
        ||!Array.isArray(d.events)||d.events_page!==page){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.detail.set(d);
      this.casePage.set(page);
      this.notice.set('Expediente leído desde el pedido autorizado. Versión '+d.row_version
        +'. La solicitud original y el último evento son pruebas distintas.');
    }catch(e){this.error.set(describe(e));}
    finally{this.detailBusy.set(false);}
  }
  async casePageMove(direction:-1|1):Promise<void>{
    const d=this.detail();
    if(!d||this.pending()||this.busy()
      ||direction===1&&!d.events_has_more
      ||direction===-1&&this.casePage()<=1)return;
    await this.loadCase(d.case_id,this.casePage()+direction);
  }
  private verification(withdrawn:boolean):ManualVerification {
    const method=withdrawn?this.withdrawalMethod:this.manualContactMethod;
    const category=withdrawn?this.withdrawalCategory:this.manualFactCategory;
    const phone=withdrawn?this.withdrawalPhoneConfirmed:this.manualPhoneConfirmed;
    const fact=withdrawn?this.withdrawalFactConfirmed:this.manualFactConfirmed;
    const note=checkAuditNote(withdrawn?this.withdrawalNote:this.manualNote);
    if(!phone||!fact){
      throw new Error('Solo declara la verificación tras comprobar realmente el teléfono ORIGINAL y un dato privado independiente. No basta marcar una casilla.');
    }
    return {
      contact_control_method:method,
      registered_phone_control_confirmed:true,
      nonpublic_order_fact_confirmed:true,
      nonpublic_fact_category:category,
      safe_audit_note:note
    };
  }
  private prepare(action:CaseAction):CaseAttempt {
    const order=this.order();
    if(!this.canRead()||!validSqlId(order.id)||this.needsRefresh()){
      throw new Error('Falta una lectura autorizada y reciente del pedido.');
    }
    const key=crypto.randomUUID();
    if(action==='manual-open'){
      if(!this.canFinancial()||order.order_state!=='CONFIRMADO')
        throw new Error('La apertura parcial solo corresponde a A con ajuste financiero y pedido confirmado.');
      const line=this.eligiblePhysical.find(l=>l.line_id===this.manualLineId);
      const units=positiveUnits(this.manualUnits);
      if(!line||!line.physical_quantities||units>=line.physical_quantities.pending_stock_out_units){
        throw new Error('La reducción solicitada debe ser inferior al compromiso todavía pendiente de salida.');
      }
      const body:AdminManualCaseCreate={
        line_id:line.line_id,purpose:'physical_partial_quantity_adjustment',
        requested_affected_units:units,reason:checkReason(this.manualReason),
        verification:this.verification(false),
        expected_order_version:order.row_version,
        expected_line_version:line.row_version,
        idempotency_key:key
      };
      return {
        action,orderId:order.id,method:'POST',expectedStatus:201,
        resource:'admin/orders/'+order.id+'/cases',body,
        context:'Apertura de solicitud parcial de línea '+line.line_id+' y '+units+' unidades'
      };
    }
    const summary=this.selected(),d=this.detail();
    if(!summary||!d||summary.case_id!==d.case_id
      ||summary.case_type!==d.case_type
      ||summary.case_phase!==d.case_phase
      ||summary.row_version!==d.row_version){
      throw new Error('Consulta el detalle original y la versión actual del expediente antes de decidir.');
    }
    const base={orderId:order.id,caseId:d.case_id,method:'POST' as const};
    if(action==='digital-decide'){
      if(!this.canDigital()||!['digital_access_dispute','guest_request_refund'].includes(d.case_type)
        ||d.case_phase!=='open_review'){
        throw new Error('Decisión digital requiere expediente legitimado abierto y permiso A.');
      }
      const evidence=checkEvidence(this.digitalEvidence);
      if(evidence.includes('://')){
        throw new Error('La evidencia debe ser una referencia opaca, nunca una URL.');
      }
      const body:DigitalCaseDecisionRequest={
        decision:this.digitalDecision,
        evidence_ref:evidence,
        reason:checkReason(this.digitalReason),
        expected_version:d.row_version,idempotency_key:key
      };
      return {...base,action,expectedStatus:200,
        resource:'admin/digital-cases/'+d.case_id+'/decide',body,
        context:'Decisión documental del expediente digital '+d.case_id
          +'. No revoca derechos ni registra dinero'};
    }
    if(action==='digital-revoke'){
      const right=this.selectedDigitalRight;
      const evidence=this.digitalApprovedRef();
      if(!this.canDigital()||d.case_type!=='digital_access_dispute'
        ||d.case_phase!=='closed_revocation_authorized'
        ||!right||!evidence||!this.selected()?.order_line_id
        ||d.case_id!==this.selected()?.case_id){
        throw new Error('Requiere caso digital legítimo aprobado, evidencia de decisión visible y derecho original del mismo pedido/línea.');
      }
      const body:DigitalRightRevokeRequest={
        case_id:d.case_id,
        legal_reason:checkReason(this.digitalRevokeReason),
        evidence_ref:evidence,
        expected_version:right.row_version,idempotency_key:key
      };
      return {...base,action,expectedStatus:200,
        resource:'admin/digital-rights/'+right.right_id+'/revoke',body,
        context:'Revocación de derecho original '+right.right_id
          +' autorizada por expediente '+d.case_id};
    }
    if(action==='manual-adjust'){
      if(!this.canPartiallyAdjust()){
        throw new Error('Caso no elegible para ajuste parcial. Solicitud original, cantidad, estado o saldo no comprobables.');
      }
      const units=this.originalUnits()!,line=this.partialLine()!;
      const body:FinancialAdjustment={
        line_id:line.line_id,
        case_id:d.case_id,
        affected_units:units,
        unit_effect:'release_unfulfilled_units',
        delta_cents:manualPartialDelta(line.unit_price_cents,units),
        reason:checkReason(this.adjustReason),
        expected_version:order.row_version,
        expected_case_version:d.row_version,
        idempotency_key:key
      };
      return {...base,action,expectedStatus:200,
        resource:'admin/orders/'+order.id+'/adjustments',body,
        context:'Ajuste parcial atómico de '+units+' unidades originales; caso '+d.case_id};
    }
    if(action==='manual-reject'||action==='manual-withdrawn'){
      if(!this.canFinancial()||d.case_type!=='physical_partial_quantity_adjustment'
        ||d.case_phase!=='open_review')throw new Error('Caso parcial no está abierto.');
      const withdrawn=action==='manual-withdrawn';
      const body:AdminManualCaseClose={
        decision:withdrawn?'buyer_withdrawn':'reject',
        reason:checkReason(this.closeReason),
        expected_case_version:d.row_version,idempotency_key:key,
        ...(withdrawn?{withdrawal_verification:this.verification(true)}:{})
      };
      return {...base,action,expectedStatus:200,
        resource:'admin/orders/'+order.id+'/cases/'+d.case_id+'/close',body,
        context:(withdrawn?'Retiro verificado':'Rechazo documentado')+' de caso '+d.case_id};
    }
    if(d.case_type!=='logistics_non_delivery'
      ||!this.canLogistics()||!['open_review','in_review'].includes(d.case_phase)){
      throw new Error('Expediente logístico no habilitado o ya terminal.');
    }
    if(action==='logistics-propose'){
      const body:CaseProposalRequest={
        resolution_proposal:this.logisticsProposal,
        evidence_ref:checkEvidence(this.logisticsEvidence),
        reason:checkReason(this.logisticsReason),
        expected_version:d.row_version,idempotency_key:key
      };
      return {...base,action,expectedStatus:200,
        resource:'admin/logistics-incidents/'+d.case_id+'/proposals',body,
        context:'Propuesta logística documental para caso '+d.case_id};
    }
    const latest=this.latestProposal();
    if(!this.canResolveLogistics()||d.case_phase!=='in_review'
      ||!latest||!['delivery_verified','non_delivery_verified'].includes(latest.decision_code??'')
      ||d.events_has_more){
      throw new Error('Antes de resolver, lee la última página de eventos con una propuesta terminal vigente.');
    }
    const body:CaseResolutionRequest={
      decision:latest.decision_code as CaseResolutionRequest['decision'],
      evidence_ref:checkEvidence(this.logisticsResolveEvidence),
      reason:checkReason(this.logisticsResolveReason),
      expected_version:d.row_version,idempotency_key:key
    };
    return {...base,action:'logistics-resolve',expectedStatus:200,
      resource:'admin/logistics-incidents/'+d.case_id+'/resolve',body,
      context:'Resolución documental del caso '+d.case_id+' según última propuesta'};
  }

  async execute(action:CaseAction):Promise<void> {
    if(this.busy()||this.detailBusy()||this.blocked())return;
    let attempt=this.pending();
    if(attempt&&attempt.action!==action){
      this.error.set('Existe otro intento que debe verificarse antes de iniciar una nueva decisión.');
      return;
    }
    if(!attempt){
      try{attempt=this.prepare(action);this.pending.set(attempt);this.pendingChange.emit(true);}
      catch(e){this.error.set(describe(e));return;}
    }
    this.busy.set(true);this.error.set('');
    try{
      const reply=await this.api.json<MutationResult|CaseActionResult
        |AdminManualCaseOpened|AdminManualCaseClosed|DigitalRightRevokeResult>(
        'POST',attempt.resource,attempt.body
      );
      const isReplayOpen=attempt.action==='manual-open'&&reply.status===200;
      if(reply.status!==attempt.expectedStatus&&!isReplayOpen
        || !reply.body || !('operation_id' in reply.body)
        ||!reply.body.operation_id
        || (['manual-open','manual-reject','manual-withdrawn',
            'logistics-propose','logistics-resolve','digital-decide'].includes(attempt.action)
            && (!('case_id' in reply.body)||!validSqlId(reply.body.case_id??'')))){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      if(attempt.action==='digital-revoke'
        && (!('result' in reply.body)||reply.body.result!=='revoked')){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.pending.set(null);this.needsRefresh.set(true);
      this.notice.set('Operación registrada: '+attempt.context
        +'. Confirma la nueva versión del pedido y del caso. '
        +(attempt.action==='manual-open'
          ?'El expediente está abierto para revisión, SIN aplicar reducción.'
          :attempt.action==='manual-adjust'
          ?'El ajuste parcial fue solicitado como operación atómica; NO equivale a refund externo.'
          : 'La decisión documental no acredita por sí misma entrega ni dinero.'));
      this.selected.set(null);this.detail.set(null);
      this.changed.emit();
    }catch(e){
      if(e instanceof ApiFailure&&[400,401,403,404,410,422,423].includes(e.status)){
        this.pending.set(null);
      }
      this.error.set(describe(e)
        +(e instanceof ApiFailure&&e.status===409
          ?' Conflicto de versión, estado o solicitud original. Relee el expediente y el pedido; no cambies la petición original.'
          :' Conserva la misma clave y cuerpo ante resultado incierto; verifica antes de iniciar otro.'));
    }finally{
      this.busy.set(false);
      if(!this.pending())this.pendingChange.emit(false);
    }
  }
  abandon():void {
    if(this.busy()||this.detailBusy())return;
    this.pending.set(null);this.pendingChange.emit(false);
    this.notice.set('Intento local descartado. Comprueba historial y estado real antes de generar una nueva clave.');
  }
}
