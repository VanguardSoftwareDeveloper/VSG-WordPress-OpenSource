import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, LogicalAttempt, PcApi } from './api';
import { CartState } from './cart-state';
import { soles, type CheckoutCashResponse, type CheckoutMpResponse,
  type CheckoutRequest, type Contact, type Delivery, type PublicBootstrap,
  type QuoteResponse } from './dto';

/** R8-16 entrega catálogo INEI versionado; el importe lo decide R8-05. */
interface DistrictEntry {
  code:string;
  department:string;
  province:string;
  district:string;
  delivery_available:boolean;
}
interface DistrictPage {
  items:DistrictEntry[];
  page:number;
  per_page:number;
  has_more:boolean;
  dataset_version?:string;
}

@Component({
  selector:'pc-checkout',standalone:true,imports:[FormsModule],
  templateUrl:'./checkout-page.html',
})
export class CheckoutPage implements OnInit {
  readonly api=inject(PcApi);
  readonly cart=inject(CartState);
  readonly bootstrap=signal<PublicBootstrap|null>(null);
  readonly quote=signal<QuoteResponse|null>(null);
  readonly busy=signal(false);
  readonly error=signal('');
  readonly info=signal('');
  readonly order=signal('');
  readonly pending=signal(false);
  readonly needsPhone=computed(()=>this.cart.lines().some(x=>x.kind==='physical'));
  readonly needsEmail=computed(()=>this.cart.lines().some(x=>x.kind==='digital'||x.kind==='service'));
  readonly missingKinds=computed(()=>this.cart.lines().some(x=>!x.kind));
  readonly money=soles;
  methods():('mercado_pago'|'cash')[]{
    const methods=this.bootstrap()?.payment_methods??[];
    return methods.filter(m=>m==='mercado_pago'
      || (!this.cart.lines().some(x=>x.kind==='digital')
          && !(this.needsPhone() && this.cart.lines().some(x=>x.kind==='service')
            && this.deliveryMode==='delivery')));
  }
  name='';
  phone='';
  email='';
  deliveryMode:'pickup'|'delivery'='pickup';
  ubigeo='';
  districtQuery='';
  readonly districtBusy=signal(false);
  readonly districtMatches=signal<DistrictEntry[]>([]);
  readonly districtError=signal('');
  readonly selectedDistrict=signal<DistrictEntry|null>(null);
  address='';
  reference='';
  method:'mercado_pago'|'cash'='mercado_pago';
  accepted=false;
  private attempt = new LogicalAttempt();
  private submitted=false;
  private redirectUrl:string|null=null;
  private trackingUrl:string|null=null;

  ngOnInit():void{void this.loadBootstrap();}
  async loadBootstrap():Promise<void>{
    this.busy.set(true);this.error.set('');
    try{
      const r=await this.api.json<PublicBootstrap>('GET','public/bootstrap');
      this.bootstrap.set(r.body);
      if(r.body.payment_methods.includes('mercado_pago'))this.method='mercado_pago';
      else if(r.body.payment_methods.includes('cash'))this.method='cash';
    }catch(error){this.error.set(this.message(error));}
    finally{this.busy.set(false);}
  }
  invalidate():void{
    if(this.submitted || this.pending())return;
    this.quote.set(null);this.accepted=false;
    this.info.set('Los datos cambiaron. Revisa la nueva cotización.');
    this.attempt.resetForNewAction();
  }
  async searchDistricts():Promise<void>{
    if(this.submitted || this.busy() || this.districtBusy() || this.deliveryMode!=='delivery')return;
    const q=this.districtQuery.trim();
    if(q.length<2 || q.length>120){
      this.districtError.set('Escribe al menos dos caracteres del distrito, provincia o código UBIGEO.');
      return;
    }
    this.districtBusy.set(true);
    this.districtError.set('');
    this.districtMatches.set([]);
    try{
      // PcApi permite únicamente URL relatives seguras; encodeURIComponent
      // deja sin escapar !'()* que no están en esa whitelist.
      const encoded=encodeURIComponent(q).replace(/[!'()*~]/g,
        c=>'%'+c.charCodeAt(0).toString(16).toUpperCase());
      const result=await this.api.json<DistrictPage>(
        'GET','public/ubigeo?page=1&per_page=30&q='+encoded
      );
      if(result.status!==200 || !Array.isArray(result.body.items)
        || result.body.items.length>30 || result.body.page!==1
        || result.body.items.some(e=>!e || !/^[0-9]{6}$/.test(e.code)
          || typeof e.department!=='string' || typeof e.province!=='string'
          || typeof e.district!=='string' || typeof e.delivery_available!=='boolean')) {
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.districtMatches.set(result.body.items);
      if(result.body.items.length===0){
        this.districtError.set('No se encontraron distritos. Verifica el nombre o código.');
      }
    }catch(error){
      this.districtError.set(this.message(error)
        +' La búsqueda no modifica el pedido ni sustituye la verificación del servidor.');
    }finally{this.districtBusy.set(false);}
  }
  chooseDistrict(item:DistrictEntry):void{
    if(this.submitted||this.busy()||this.districtBusy()
      ||this.deliveryMode!=='delivery'||!item.delivery_available)return;
    this.ubigeo=item.code;
    this.selectedDistrict.set(item);
    this.invalidate();
  }
  manualDistrictChanged():void{
    this.selectedDistrict.set(null);
    this.invalidate();
  }

  private contact():Contact{
    const value:Contact={};
    if(this.name.trim())value.display_name=this.name.trim();
    if(this.phone.trim())value.phone=this.phone.trim();
    if(this.email.trim())value.email=this.email.trim();
    return value;
  }
  private delivery():Delivery{
    if(!this.needsPhone())return {mode:'none'};
    return this.deliveryMode==='pickup'?{mode:'pickup'}:
      {mode:'delivery',ubigeo_code:this.ubigeo.trim(),address:this.address.trim(),
        ...(this.reference.trim()?{reference:this.reference.trim()}:{})};
  }
  private eligible():boolean{
    if(this.cart.lines().length===0 || this.missingKinds()) {
      this.error.set('Actualiza los productos en el carrito para confirmar su modalidad.');
      return false;
    }
    if(this.needsEmail() && !this.email.trim()){
      this.error.set('El correo es necesario para productos digitales o servicios.');
      return false;
    }
    if(this.needsPhone() && !this.phone.trim()){
      this.error.set('Indica un teléfono para coordinar la entrega.');
      return false;
    }
    if(this.needsPhone() && this.deliveryMode==='delivery'
      && (!this.bootstrap()?.supports_delivery || !/^[0-9]{6}$/.test(this.ubigeo)||!this.address.trim())){
      this.error.set('Indica el código distrital UBIGEO y la dirección.');
      return false;
    }
    return true;
  }
  async recalculate():Promise<void>{
    if(this.busy()||this.submitted||!this.eligible())return;
    this.busy.set(true);this.error.set('');this.info.set('');this.accepted=false;
    this.quote.set(null);this.attempt.resetForNewAction();
    try{
      const result=await this.api.json<QuoteResponse>('POST','public/quotes',{
        lines:this.cart.checkoutLines(),contact:this.contact(),delivery:this.delivery()
      });
      this.quote.set(result.body);
      this.info.set('Revisa importe, condiciones y disponibilidad antes de continuar.');
    }catch(error){this.error.set(this.message(error));}
    finally{this.busy.set(false);}
  }
  chooseMethod(v:string):void{
    if(v==='mercado_pago'||v==='cash'){
      this.method=v;
      if(!this.submitted)this.attempt.resetForNewAction();
    }
  }
  async confirm():Promise<void>{
    if(this.busy()||this.submitted || !this.eligible())return;
    const quote=this.quote();
    if(!quote||!this.accepted || !this.methods().includes(this.method)) {
      this.error.set('Necesitas una cotización vigente, revisar las condiciones y elegir un medio válido.');
      return;
    }
    const payload:CheckoutRequest={
      quote_fingerprint:quote.quote_fingerprint,
      lines:this.cart.checkoutLines(),
      contact:this.contact(),
      delivery:this.delivery(),
      policy_version:quote.policy_version,
      privacy_version:quote.privacy_version,
      idempotency_key:this.attempt.current(),
    };
    this.busy.set(true);this.error.set('');this.info.set('Enviando intento de compra…');
    this.submitted=true;
    try{
      if(this.method==='cash'){
        const r=await this.api.json<CheckoutCashResponse>(
          'POST','public/checkout/contraentrega',payload);
        if(r.status!==201||r.body.order_state!=='CONFIRMADO') {
          throw new ApiFailure(r.status,'pc_result_invalid',false);
        }
        this.order.set(r.body.order_id);
        this.trackingUrl=r.body.tracking_url_once||null;
        this.cart.clear();
        this.info.set('Pedido confirmado. El importe en efectivo todavía está pendiente de cobro.');
      }else{
        const r=await this.api.json<CheckoutMpResponse>(
          'POST','public/checkout/mercado-pago',payload);
        if(r.status===202 || r.body.pending_reconciliation){
          this.pending.set(true);
          this.info.set('El intento está en conciliación. No se ha confirmado un pago. No inicies otra compra.');
        }else if(r.status===200 && r.body.checkout_url && !r.body.pending_reconciliation){
          this.redirectUrl=this.verifiedPaymentUrl(r.body.checkout_url);
          this.trackingUrl=r.body.tracking_url_once||null;
          this.order.set(r.body.order_id||'');
          this.info.set('Tu intento de pago está preparado, pero todavía NO está pagado. Continúa en Mercado Pago.');
        }else{
          this.pending.set(true);
          this.info.set('No se puede confirmar el intento. Revisa el seguimiento antes de volver a comprar.');
        }
      }
    }catch(error){
      if(error instanceof ApiFailure && [400,422].includes(error.status)) {
        // Rechazo de forma/validación: no se aceptó la solicitud.
        // Conservar contacto y carrito, exigir cotización nueva.
        this.submitted=false;
        this.pending.set(false);
        this.quote.set(null);
        this.accepted=false;
        this.attempt.resetForNewAction();
        this.error.set(this.message(error)+' Corrige los datos y vuelve a verificar el importe.');
      }else{
        // Red/409/5xx/resultados ambiguos NO autorizan otro checkout.
        this.pending.set(true);
        this.error.set(this.message(error)+' El estado puede ser incierto: no generes otro intento sin comprobarlo.');
      }
    }finally{this.busy.set(false);}
  }
  async goPay():Promise<void> {
    if(!this.redirectUrl||this.busy())return;
    this.busy.set(true);
    this.error.set('');
    try{
      // Token inicial de seguimiento: solo en memoria y fragmento del enlace
      // devuelto UNA vez por checkout. Canjearlo por cookie HttpOnly antes
      // de salir a MP para que el retorno R8-18 pueda consultar el pedido.
      if(this.trackingUrl){
        const url=new URL(this.trackingUrl);
        if(url.origin!==location.origin || url.protocol!=='https:') {
          throw new ApiFailure(400,'pc_tracking_url_invalid',false);
        }
        const fragment=new URLSearchParams(url.hash.slice(1));
        const secret=fragment.get('tracking');
        if(!secret || !/^pc1\.[1-9][0-9]{0,19}\.[A-Za-z0-9_-]{43}$/.test(secret)) {
          throw new ApiFailure(400,'pc_tracking_url_invalid',false);
        }
        await this.api.json<{scope:'order_read_only';expires_at_utc:string}>(
          'POST','guest/sessions',{tracking_secret:secret}
        );
      }
      location.assign(this.redirectUrl);
    }catch(error){
      this.error.set(this.message(error)+' Conserva el enlace privado y solicita asistencia si no se puede verificar.');
    }finally{this.busy.set(false);}
  }
  goTrack():void {
    if(!this.trackingUrl)return;
    try{
      const url=new URL(this.trackingUrl);
      if(url.origin===location.origin && url.protocol==='https:'
        && /^#tracking=pc1\./.test(url.hash))location.assign(url.href);
    }catch{this.error.set('El enlace privado no es válido. Solicita recuperación verificada.');}
  }
  private verifiedPaymentUrl(raw:string):string {
    const url=new URL(raw);
    if(url.protocol!=='https:'
      || !(/(^|\.)mercadopago\.com(\.pe)?$/i.test(url.hostname))) {
      throw new ApiFailure(409,'pc_payment_url_invalid',false);
    }
    return url.href;
  }
  private message(error:unknown):string {
    return error instanceof ApiFailure?error.message:'No se pudo determinar el resultado de la operación.';
  }
  get finished():boolean{return this.submitted;}
  get payPrepared():boolean{return this.redirectUrl!==null;}
  get canTrack():boolean{return this.trackingUrl!==null;}
}
