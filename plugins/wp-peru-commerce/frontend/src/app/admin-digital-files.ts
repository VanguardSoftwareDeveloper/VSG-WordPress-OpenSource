import { Component, computed, inject, input, OnChanges, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, PcApi } from './api';
import {
  type AdminProduct, type AdminDigitalSlot, type AdminVariant,
  type DigitalFileMetadata, type DigitalReplaceResult,
} from './admin-catalog-contract';

interface FileAttempt {
  resource:string;
  form:FormData;
  mode:'initial'|'replace';
}

@Component({
  selector:'pc-admin-digital-files',
  standalone:true,
  imports:[FormsModule],
  templateUrl:'./admin-digital-files.html',
})
export class AdminDigitalFiles implements OnChanges {
  readonly product=input.required<AdminProduct>();
  readonly allowed=input.required<boolean>();
  readonly changed=output<void>();
  readonly pendingChange=output<boolean>();
  private readonly api=inject(PcApi);

  readonly variants=computed(()=>this.allowed() && this.product().kind==='digital'
    ? (this.product().variants??[]) : []);
  readonly slots=computed(()=>{
    const result:AdminDigitalSlot[]=[];
    const seen=new Set<string>();
    for(const v of this.variants()){
      for(const slot of v.digital_assignments??[]){
        const key=slot.sellable_id+':'+slot.slot_key;
        if(!seen.has(key)){seen.add(key);result.push(slot);}
      }
    }
    // Compatibilidad con una vista simple que adjunte slots solo al padre.
    for(const slot of this.product().digital_assignments??[]){
      const key=slot.sellable_id+':'+slot.slot_key;
      if(!seen.has(key)){seen.add(key);result.push(slot);}
    }
    return result;
  });
  readonly busy=signal(false);
  readonly error=signal('');
  readonly notice=signal('');

  sellableId='';
  slotKey='';
  filename='';
  reason='';
  selectedSlot='';
  initialFilename='';
  replacementFilename='';
  private initialFile:File|null=null;
  private replacementFile:File|null=null;
  private pending:FileAttempt|null=null;

  ngOnChanges():void {
    if(!this.pending){
      if(!this.variants().some(v=>v.id===this.sellableId)) {
        this.sellableId=this.variants()[0]?.id??'';
      }
      if(!this.slots().some(slot=>this.slotIdentity(slot)===this.selectedSlot)){
        this.selectedSlot=this.slots()[0]?this.slotIdentity(this.slots()[0]):'';
      }
    }
  }
  get hasPending():boolean{return this.pending!==null;}
  get pendingMode():'initial'|'replace'|null{return this.pending?.mode??null;}
  get selectedVariant():AdminVariant|null {
    return this.variants().find(v=>v.id===this.sellableId)??null;
  }
  get selectedAssignment():AdminDigitalSlot|null {
    return this.slots().find(slot=>this.slotIdentity(slot)===this.selectedSlot)??null;
  }
  slotIdentity(slot:AdminDigitalSlot):string {
    return slot.sellable_id+':'+slot.slot_key;
  }
  fileSelected(event:Event,mode:'initial'|'replace'):void {
    if(this.busy()||this.hasPending)return;
    const element=event.target as HTMLInputElement;
    const file=element.files?.item(0)??null;
    if(mode==='initial'){
      this.initialFile=file;
      this.initialFilename=file?.name??'';
      this.filename=file?.name??'';
    }else{
      this.replacementFile=file;
      this.replacementFilename=file?.name??'';
    }
    // El objeto File permanece solo en memoria hasta enviar o salir.
    element.value='';
  }
  private fileReady(file:File|null):file is File {
    return !!file && file.size>0 && file.name.length>0 && file.name.length<=255;
  }
  private newAttempt(mode:'initial'|'replace'):FileAttempt {
    if(!this.allowed()||this.product().kind!=='digital'){
      throw new Error('Solo un administrador autorizado puede gestionar archivos privados.');
    }
    const file=mode==='initial'?this.initialFile:this.replacementFile;
    if(!this.fileReady(file)){
      throw new Error('Selecciona un archivo válido no vacío. El servidor comprobará tamaño, MIME y almacenamiento privado.');
    }
    const form=new FormData();
    // Multipart R8-48/63 exige exactamente un campo binario "file".
    form.set('file',file,file.name);
    const key=crypto.randomUUID();
    if(mode==='initial'){
      if(!this.selectedVariant){
        throw new Error('Selecciona una variante digital del producto.');
      }
      const slot=this.slotKey.trim();
      if(!/^[a-z0-9][a-z0-9_-]{0,99}$/.test(slot)){
        throw new Error('El identificador de slot admite minúsculas, números, guiones y guion bajo.');
      }
      if(this.slots().some(v=>v.sellable_id===this.sellableId&&v.slot_key===slot)){
        throw new Error('El slot ya existe. Para cambiar su archivo activo utiliza Reemplazar.');
      }
      if(!this.filename.trim()||this.filename.length>255){
        throw new Error('Introduce un nombre de archivo válido.');
      }
      form.set('product_id',this.product().id);
      form.set('sellable_id',this.sellableId);
      form.set('slot_key',slot);
      form.set('filename',this.filename.trim());
      form.set('idempotency_key',key);
      return {resource:'admin/digital-files',form,mode};
    }
    const slot=this.selectedAssignment;
    if(!slot)throw new Error('Selecciona un slot existente que deseas reemplazar.');
    if(!this.reason.trim()||this.reason.length>1000) {
      throw new Error('El motivo del reemplazo es obligatorio.');
    }
    form.set('reason',this.reason.trim());
    form.set('expected_version',slot.assignment_row_version);
    form.set('idempotency_key',key);
    return {
      resource:'admin/digital-files/'+slot.active_file_id+'/replacements',
      form,mode,
    };
  }
  async send(mode:'initial'|'replace'):Promise<void> {
    if(this.busy()||!this.allowed())return;
    if(!this.pending){
      try{
        this.pending=this.newAttempt(mode);
        this.pendingChange.emit(true);
      }
      catch(e){this.error.set(e instanceof Error?e.message:'Datos de archivo inválidos.');return;}
    }
    const pending=this.pending;
    this.busy.set(true);this.error.set('');
    this.notice.set('Verificando el archivo privado. Ningún binario se publica en Media Library.');
    try{
      if(pending.mode==='initial'){
        const response=await this.api.form<DigitalFileMetadata>(pending.resource,pending.form);
        if(response.status!==201 || !response.body.protected
          || !response.body.assignment_row_version) {
          throw new ApiFailure(500,'pc_result_invalid',false);
        }
        this.notice.set('Archivo '+response.body.file_id+' asociado al slot '
          +response.body.slot_key+'. Versión '+response.body.assignment_row_version+'.');
        this.initialFile=null;this.initialFilename='';this.filename='';this.slotKey='';
      }else{
        const response=await this.api.form<DigitalReplaceResult>(pending.resource,pending.form);
        if(response.status!==201 || !response.body.new_file_id
          || !response.body.assignment_row_version) {
          throw new ApiFailure(500,'pc_result_invalid',false);
        }
        this.notice.set('Nuevo archivo '+response.body.new_file_id+' para ventas futuras. '
          +'El archivo anterior y los derechos históricos permanecen asociados a las ventas originales. '
          +'Versión del slot '+response.body.assignment_row_version+'.');
        this.replacementFile=null;this.replacementFilename='';this.reason='';
      }
      this.pending=null;
      this.pendingChange.emit(false);
      this.changed.emit();
    }catch(e){
      if(e instanceof ApiFailure && [400,422].includes(e.status)){
        this.pending=null;
        this.pendingChange.emit(false);
      }
      this.error.set((e instanceof ApiFailure?e.message:'No se pudo determinar el resultado.')
        +' Si hay incertidumbre, vuelve a enviar exactamente el mismo intento; no crees otro slot o versión a ciegas.');
    }finally{this.busy.set(false);}
  }
  abandon():void {
    if(this.busy())return;
    this.pending=null;
    this.pendingChange.emit(false);
    this.notice.set('Intento local descartado. Antes de otra carga, comprueba el slot activo y su versión en el servidor.');
  }
  async download(slot:AdminDigitalSlot):Promise<void> {
    if(this.busy()||!this.allowed()||this.hasPending)return;
    this.busy.set(true);this.error.set('');
    try{
      const response=await this.api.privateFile(
        'admin/digital-files/'+slot.active_file_id+'/download?purpose=catalog_file_review'
      );
      const url=URL.createObjectURL(response.blob);
      const anchor=document.createElement('a');
      anchor.href=url;
      anchor.download=response.filename;
      anchor.rel='noreferrer';
      anchor.hidden=true;
      document.body.append(anchor);
      try{anchor.click();}
      finally{
        anchor.remove();
        // La URL temporal no se persiste ni comparte con componentes.
        window.setTimeout(()=>URL.revokeObjectURL(url),60000);
      }
      this.notice.set('Descarga privada preparada para revisión administrativa. '
        +'Su inicio no acredita recepción completa del archivo.');
    }catch(e){
      this.error.set(e instanceof ApiFailure?e.message:'No se pudo abrir el binario protegido.');
    }finally{this.busy.set(false);}
  }
}
