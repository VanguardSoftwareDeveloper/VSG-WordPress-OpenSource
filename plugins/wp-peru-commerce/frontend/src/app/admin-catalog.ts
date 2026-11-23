import { Component, computed, inject, input, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, PcApi } from './api';
import type { AdminBootstrap, Kind } from './dto';
import { soles } from './dto';
import {
  type AdminCatalogPage, type AdminProduct, type AdminVariant,
  type ProductCreateRequest, type ProductPatchRequest,
  type VariantCreate, type VariantUpdate, type VisibilityRequest,
  centsToSoles, solesToCents,
} from './admin-catalog-contract';
import { AdminStock } from './admin-stock';
import { AdminDigitalFiles } from './admin-digital-files';

type StateFilter = 'all'|'draft'|'hidden'|'published';
type FormMode = 'create'|'edit'|null;
type SavePayload = ProductCreateRequest|ProductPatchRequest;

interface VariantDraft {
  key:string;
  id?:string;
  label:string;
  sku:string;
  price:string;
  saleActive:boolean;
  salePrice:string;
  trackStock:boolean;
  initialStock:string;
  duration:string;
  bufferBefore:string;
  bufferAfter:string;
  active:boolean;
}
interface ProductDraft {
  title:string;
  description:string;
  kind:Kind;
  variable:boolean;
  sku:string;
  price:string;
  saleActive:boolean;
  salePrice:string;
  trackStock:boolean;
  initialStock:string;
  duration:string;
  bufferBefore:string;
  bufferAfter:string;
  variants:VariantDraft[];
}
interface PendingSave {
  method:'POST'|'PATCH';
  resource:string;
  body:SavePayload;
  httpSuccess:200|201;
}
interface PendingVisibility {
  resource:string;
  body:VisibilityRequest;
}

function blankVariant():VariantDraft {
  return {
    key:crypto.randomUUID(),label:'',sku:'',price:'0.00',saleActive:false,
    salePrice:'',trackStock:false,initialStock:'0',duration:'30',
    bufferBefore:'0',bufferAfter:'0',active:true,
  };
}
function blankProduct():ProductDraft {
  return {
    title:'',description:'',kind:'physical',variable:false,sku:'',
    price:'0.00',saleActive:false,salePrice:'',trackStock:false,
    initialStock:'0',duration:'30',bufferBefore:'0',bufferAfter:'0',variants:[],
  };
}
function variantDraft(v:AdminVariant):VariantDraft {
  return {
    key:v.id,id:v.id,label:v.label,sku:v.sku??'',
    price:centsToSoles(v.price_cents),saleActive:v.sale_active,
    salePrice:v.sale_price_cents?centsToSoles(v.sale_price_cents):'',
    trackStock:v.track_stock,initialStock:'0',
    duration:String(v.service_duration_minutes??30),
    bufferBefore:String(v.service_buffer_before_minutes??0),
    bufferAfter:String(v.service_buffer_after_minutes??0),active:v.active,
  };
}

@Component({
  selector:'pc-admin-catalog',
  standalone:true,
  imports:[FormsModule,AdminStock,AdminDigitalFiles],
  templateUrl:'./admin-catalog.html',
})
export class AdminCatalog implements OnInit {
  readonly employee=input.required<AdminBootstrap>();
  private readonly api=inject(PcApi);
  readonly canEdit=computed(()=>this.employee().ui_capabilities.includes('perucommerce_catalog_edit'));
  readonly canHide=computed(()=>this.employee().ui_capabilities.includes('perucommerce_catalog_hide'));
  readonly canPublish=computed(()=>this.employee().role_label==='A'
    && this.employee().ui_capabilities.includes('perucommerce_catalog_publish'));
  readonly canStock=computed(()=>this.employee().ui_capabilities.includes('perucommerce_stock_manage'));
  readonly canFiles=computed(()=>this.employee().role_label==='A'
    && this.canEdit()
    && this.employee().ui_capabilities.includes('perucommerce_digital_files_manage'));
  readonly items=signal<AdminProduct[]>([]);
  readonly chosenId=signal<string|null>(null);
  readonly chosen=computed(()=>this.items().find(p=>p.id===this.chosenId())??null);
  readonly busy=signal(false);
  readonly loading=signal(false);
  readonly error=signal('');
  readonly notice=signal('');
  readonly page=signal(1);
  readonly hasMore=signal(false);
  readonly childPending=signal(false);
  readonly search=signal('');
  readonly visibleItems=computed(()=>{
    const term=this.search().trim().toLocaleLowerCase('es-PE');
    return term?this.items().filter(p=>p.title.toLocaleLowerCase('es-PE').includes(term)
      || p.id.includes(term)):this.items();
  });
  readonly money=soles;

  filter:StateFilter='all';
  form:ProductDraft=blankProduct();
  mode:FormMode=null;
  editing:AdminProduct|null=null;
  visibilityReason='';
  private savePending:PendingSave|null=null;
  private visibilityPending:PendingVisibility|null=null;

  ngOnInit():void {
    if(this.canEdit())void this.refresh(1);
  }

  async refresh(page=this.page()):Promise<void> {
    if(!this.canEdit() || this.loading())return;
    this.loading.set(true);
    this.error.set('');
    try{
      const query='admin/products?page='+page+'&per_page=20'
        +(this.filter==='all'?'':'&state='+this.filter);
      const reply=await this.api.json<AdminCatalogPage>('GET',query);
      if(reply.status!==200 || !Array.isArray(reply.body.items)) {
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      const previouslySelected=this.chosen();
      const keepSelected=this.childPending() && previouslySelected
        && !reply.body.items.some(p=>p.id===previouslySelected.id);
      this.items.set(keepSelected
        ?[previouslySelected,...reply.body.items]:reply.body.items);
      this.page.set(reply.body.page);
      this.hasMore.set(reply.body.has_more);
      if(!this.childPending() && this.chosenId() && !reply.body.items.some(p=>p.id===this.chosenId())){
        this.chosenId.set(null);
      }
      this.notice.set('Catálogo actualizado. Precios y versiones corresponden a esta lectura.');
    }catch(e){this.error.set(this.message(e));}
    finally{this.loading.set(false);}
  }
  async filterChanged():Promise<void> {
    if(this.hasPending())return;
    this.chosenId.set(null);this.mode=null;this.editing=null;
    await this.refresh(1);
  }
  async pageChanged(offset:number):Promise<void> {
    if(this.hasPending() || (offset>0 && !this.hasMore())
      || (offset<0 && this.page()<=1))return;
    this.chosenId.set(null);this.mode=null;this.editing=null;
    await this.refresh(this.page()+offset);
  }
  select(p:AdminProduct):void {
    if(this.busy()||this.hasPending())return;
    this.chosenId.set(p.id);
    this.editing=p;this.mode=null;
    this.error.set('');
    this.notice.set('Producto seleccionado. Versión de catálogo '+p.row_version+'.');
  }
  startCreate():void {
    if(!this.canEdit()||this.busy()||this.hasPending())return;
    this.mode='create';this.editing=null;this.chosenId.set(null);
    this.form=blankProduct();this.error.set('');this.notice.set('');
  }
  edit(p:AdminProduct):void {
    if(!this.canEdit()||this.busy()||this.hasPending())return;
    this.select(p);this.mode='edit';this.editing=p;
    const variable=p.price_cents===undefined;
    this.form={
      title:p.title,description:p.description??'',kind:p.kind,
      variable,sku:p.sku??'',price:centsToSoles(p.price_cents),
      saleActive:p.sale_active??false,
      salePrice:p.sale_price_cents?centsToSoles(p.sale_price_cents):'',
      trackStock:p.track_stock??false,initialStock:'0',
      duration:String(p.service_duration_minutes??30),
      bufferBefore:String(p.service_buffer_before_minutes??0),
      bufferAfter:String(p.service_buffer_after_minutes??0),
      variants:variable?(p.variants??[]).map(variantDraft):[],
    };
    this.notice.set('Editando versión '+p.row_version+'. Las variantes omitidas no se eliminan.');
  }
  cancelForm():void {
    if(this.hasPending()||this.busy())return;
    this.mode=null;this.editing=null;
  }
  switchVariable():void {
    if(this.mode!=='create')return;
    this.form.variants=this.form.variable?[blankVariant()]:[];
  }
  addVariant():void {
    if(!this.form.variable || this.form.variants.length>=100 || this.hasPending())return;
    this.form.variants.push(blankVariant());
  }
  removeVariant(index:number):void {
    if(this.form.variants[index]?.id || this.hasPending())return;
    this.form.variants.splice(index,1);
  }
  hasPending():boolean {
    return this.savePending!==null||this.visibilityPending!==null||this.childPending();
  }
  pendingVisibilityTarget(): 'hidden'|'published'|null {
    return this.visibilityPending?.body.visibility??null;
  }

  private integer(value:string,minimum:number,maximum:number,label:string):number {
    if(!/^(0|[1-9][0-9]{0,4})$/.test(value.trim())){
      throw new Error(label+': ingresa un entero válido.');
    }
    const number=Number(value);
    if(!Number.isInteger(number)||number<minimum||number>maximum){
      throw new Error(label+': valor fuera de rango.');
    }
    return number;
  }
  private units(value:string):string {
    const v=value.trim();
    if(!/^(0|[1-9][0-9]{0,18})$/.test(v)) {
      throw new Error('Las unidades iniciales deben ser enteros no negativos.');
    }
    return v;
  }
  private requireDraft():void {
    if(!this.form.title.trim() || this.form.title.trim().length>240){
      throw new Error('El título es obligatorio (máximo 240 caracteres).');
    }
    if(this.form.description.length>20000){
      throw new Error('La descripción supera el límite del contrato.');
    }
    if(this.form.variable && this.form.variants.length===0){
      throw new Error('Agrega al menos una variante.');
    }
  }
  private sellableFields(source:VariantDraft|ProductDraft, kind:Kind,
    created:boolean):Omit<VariantCreate,'label'> {
    const price=solesToCents(source.price);
    const salePrice=source.salePrice.trim()?solesToCents(source.salePrice):undefined;
    if(source.saleActive && (!salePrice || salePrice==='0'
      || BigInt(salePrice)>BigInt(price) || price==='0')){
      throw new Error('La oferta activa necesita un precio menor o igual al precio base.');
    }
    if(source.sku.length>100)throw new Error('SKU demasiado largo.');
    const out:Omit<VariantCreate,'label'>={
      price_cents:price,
      sale_active:source.saleActive,
      sku:source.sku.trim(),
      ...(salePrice?{sale_price_cents:salePrice}:{}),
      ...(kind==='physical'?{track_stock:source.trackStock}:{}),
    };
    if(kind==='physical' && created && source.trackStock){
      out.initial_stock_units=this.units(source.initialStock);
    }
    if(kind==='service'){
      out.service_duration_minutes=this.integer(source.duration,1,1440,'Duración');
      out.service_buffer_before_minutes=this.integer(source.bufferBefore,0,1440,'Margen previo');
      out.service_buffer_after_minutes=this.integer(source.bufferAfter,0,1440,'Margen posterior');
    }
    return out;
  }
  private variantFields(v:VariantDraft,kind:Kind):VariantCreate|VariantUpdate {
    if(!v.label.trim()||v.label.length>180){
      throw new Error('Cada variante necesita un nombre de máximo 180 caracteres.');
    }
    const basic=this.sellableFields(v,kind,!v.id);
    if(v.id){
      return {id:v.id,label:v.label.trim(),active:v.active,...basic};
    }
    return {label:v.label.trim(),active:v.active,...basic};
  }
  private request():PendingSave {
    this.requireDraft();
    const mode=this.mode;
    if(!mode)throw new Error('No hay un formulario activo.');
    const f=this.form;
    const isNew=mode==='create';
    const path=isNew?'admin/products':'admin/products/'+this.editing?.id;
    const base={
      title:f.title.trim(),
      description:f.description,
      idempotency_key:crypto.randomUUID(),
    };
    if(isNew){
      const result:ProductCreateRequest={...base,kind:f.kind};
      if(f.variable){
        if(f.variants.length>100)throw new Error('Máximo 100 variantes.');
        result.variants=f.variants.map(x=>this.variantFields(x,f.kind) as VariantCreate);
      }else{
        Object.assign(result,this.sellableFields(f,f.kind,true));
      }
      return {method:'POST',resource:path,body:result,httpSuccess:201};
    }
    if(!this.editing)throw new Error('Falta la versión del producto.');
    const result:ProductPatchRequest={
      ...base,expected_version:this.editing.row_version,
    };
    if(f.variable){
      // PATCH: no destruir variantes. Mantener IDs, sin stock directo.
      // No actualizar 100 variantes si solo se editó el título.
      // Variantes existentes omitidas conservan versión e identidad.
      const changed:(VariantCreate|VariantUpdate)[]=[];
      for(const variant of f.variants){
        const next=this.variantFields(variant,f.kind);
        if(!variant.id){
          changed.push(next);
          continue;
        }
        const original=this.editing.variants?.find(v=>v.id===variant.id);
        if(!original){
          throw new Error('La variante cambió en el servidor. Recarga y revisa antes de continuar.');
        }
        const prior=this.variantFields(variantDraft(original),f.kind);
        if(JSON.stringify(next)!==JSON.stringify(prior)){
          changed.push(next);
        }
      }
      if(changed.length>0)result.variants=changed;
    }else{
      Object.assign(result,this.sellableFields(f,f.kind,false));
    }
    return {method:'PATCH',resource:path,body:result,httpSuccess:200};
  }
  async save():Promise<void>{
    if(this.busy()||!this.canEdit()||this.visibilityPending)return;
    if(!this.savePending) {
      try {this.savePending=this.request();}
      catch(e){this.error.set(e instanceof Error?e.message:'Datos inválidos.');return;}
    }
    const pending=this.savePending;
    this.busy.set(true);this.error.set('');this.notice.set('Guardando con versión e idempotencia…');
    try{
      const reply=await this.api.json<AdminProduct>(
        pending.method,pending.resource,pending.body
      );
      if(reply.status!==pending.httpSuccess || !reply.body.id
        || !reply.body.row_version)throw new ApiFailure(500,'pc_result_invalid',false);
      this.savePending=null;
      this.upsert(reply.body);
      this.mode=null;this.editing=null;
      this.notice.set('Producto guardado. Versión actual '+reply.body.row_version+'.');
    }catch(e){
      const known=e instanceof ApiFailure;
      if(known && [400,422].includes(e.status)){
        this.savePending=null;
        this.error.set(this.message(e)+' Corrige el formulario antes de guardar.');
      }else{
        this.error.set(this.message(e)
          +' El intento se conserva exactamente; no se enviará una clave nueva al reintentar.');
      }
    }finally{this.busy.set(false);}
  }
  private upsert(p:AdminProduct):void {
    this.items.update(old=>{
      const position=old.findIndex(x=>x.id===p.id);
      if(position>=0)return old.map(x=>x.id===p.id?p:x);
      return [p,...old].slice(0,20);
    });
    this.chosenId.set(p.id);
  }

  async changeVisibility(target:'hidden'|'published'):Promise<void>{
    const product=this.chosen();
    if(!product || this.busy() || this.savePending || this.childPending()
      || (this.visibilityPending && this.visibilityPending.body.visibility!==target)
      || (target==='hidden'&&!this.canHide())
      || (target==='published'&&!this.canPublish()))return;
    if(!this.visibilityPending){
      const reason=this.visibilityReason.trim();
      if(!reason||reason.length>1000){
        this.error.set('Explica el motivo del cambio de visibilidad (máximo 1000 caracteres).');
        return;
      }
      this.visibilityPending={
        resource:'admin/products/'+product.id+'/visibility',
        body:{
          visibility:target,reason,
          expected_version:product.row_version,
          idempotency_key:crypto.randomUUID()
        }
      };
    }
    const pending=this.visibilityPending;
    this.busy.set(true);this.error.set('');
    try{
      const reply=await this.api.json<AdminProduct>('POST',pending.resource,pending.body);
      if(reply.status!==200||!reply.body.row_version)
        throw new ApiFailure(500,'pc_result_invalid',false);
      this.visibilityPending=null;this.visibilityReason='';
      this.upsert(reply.body);this.notice.set(
        'Visibilidad actualizada: '+reply.body.visibility+'. Versión '+reply.body.row_version+'.'
      );
    }catch(e){
      if(e instanceof ApiFailure && [400,422].includes(e.status)){
        this.visibilityPending=null;
      }
      this.error.set(this.message(e)+' Revisa el estado del producto antes de decidir otra operación.');
    }finally{this.busy.set(false);}
  }

  /**
   * Abandono EXPLÍCITO, nunca al refrescar ni al fallar la red.
   * Ante resultado ambiguo, primero revisar catálogo y auditoría por canal A.
   */
  abandonPending():void {
    if(this.busy())return;
    this.savePending=null;
    this.visibilityPending=null;
    this.mode=null;
    this.editing=null;
    this.notice.set('Intento descartado en esta pantalla. Verifica el efecto real en el comercio antes de repetir la operación.');
  }
  async reloadAfterChild():Promise<void>{
    await this.refresh();
    this.notice.set('Listado consultado nuevamente. Comprueba los IDs y versiones antes de otra acción.');
  }
  private message(e:unknown):string{
    return e instanceof ApiFailure?e.message
      :e instanceof Error?e.message:'No se pudo completar la operación.';
  }
}
