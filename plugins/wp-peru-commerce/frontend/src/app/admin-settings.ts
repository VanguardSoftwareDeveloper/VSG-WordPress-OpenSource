import { Component, computed, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, PcApi } from './api';
import type { AdminBootstrap } from './dto';
import {
  type AdminSettings, type SettingsPatch, type BusinessSettings,
  type FiscalSettings, type FiscalRegime, type DocumentType,
  type MailSender, type MapTilesConfig, type PaymentCredentialUpdate,
  type MutationResult, validSqlId, fieldText, safeUrl, limitInput,
} from './admin-config-contract';

type Action='business'|'fiscal'|'policies'|'options'|'mail'|'mail-reset'
  |'map'|'credentials';
interface PendingSettings {
  action:Action;
  resource:'admin/settings'|'admin/payment-credentials';
  method:'PATCH'|'PUT';
  body:SettingsPatch|PaymentCredentialUpdate;
  expected:200;
  secret:boolean;
}
const REGIMES:{value:FiscalRegime;label:string}[]=[
  {value:'unconfigured',label:'Sin declarar'},
  {value:'new_rus',label:'Nuevo RUS'},
  {value:'rer',label:'RER'},
  {value:'rmt',label:'RMT'},
  {value:'general',label:'Régimen General'},
  {value:'fourth_category',label:'Cuarta categoría'},
];
const DOCS:{value:DocumentType;label:string}[]=[
  {value:'boleta',label:'Boleta'}, {value:'factura',label:'Factura'},
  {value:'rhe',label:'Recibo por honorarios'}, {value:'ticket',label:'Ticket'},
];
function errorText(error:unknown):string{
  return error instanceof ApiFailure?error.message
    :error instanceof Error?error.message:'No se pudo completar la configuración.';
}
const same=(a:unknown,b:unknown):boolean=>JSON.stringify(a)===JSON.stringify(b);

@Component({
  selector:'pc-admin-settings',standalone:true,imports:[FormsModule],
  templateUrl:'./admin-settings.html'
})
export class AdminSettingsPage implements OnInit {
  readonly employee=input.required<AdminBootstrap>();
  readonly blocked=input(false);
  readonly pendingChange=output<boolean>();
  private readonly api=inject(PcApi);
  readonly canSettings=computed(()=>this.employee().role_label==='A'
    &&this.employee().ui_capabilities.includes('perucommerce_settings_manage'));
  readonly canCredentials=computed(()=>this.canSettings()
    &&this.employee().ui_capabilities.includes('perucommerce_integrations_manage'));
  readonly data=signal<AdminSettings|null>(null);
  readonly loading=signal(false);
  readonly busy=signal(false);
  readonly pending=signal<PendingSettings|null>(null);
  readonly freshRequired=signal(false);
  readonly error=signal('');
  readonly notice=signal('');
  readonly regimes=REGIMES;
  readonly documentTypes=DOCS;
  readonly configured=computed(()=>this.data()?.provider_configured===true);
  readonly webhook=computed(()=>this.data()?.webhook_connected===true);

  businessName='';
  businessContact='';
  legalName='';
  ruc='';
  address='';
  complaintMode:'native'|'external'='native';
  complaintUrl='';
  privacyUrl='';
  fiscalRegime:FiscalRegime='unconfigured';
  selectedDocs:DocumentType[]=[];
  policyNotice='';
  privacyNotice='';
  deliveryEnabled=false;
  paymentEnabled=false;
  routineEmails=true;
  quotaText='';
  mailName='';
  mailEmail='';
  mailSignature='';
  mapEnabled=false;
  tileTemplate='';
  attributionText='';
  attributionUrl='';
  mapVerified=false;
  environment:'test'|'production'='test';
  accessToken='';
  webhookSecret='';
  credentialAcknowledged=false;

  ngOnInit():void {if(this.canSettings())void this.load();}

  async load():Promise<void>{
    if(!this.canSettings()||this.loading()||this.busy())return;
    this.loading.set(true);this.error.set('');
    try{
      const reply=await this.api.json<AdminSettings>('GET','admin/settings');
      const data=reply.body;
      if(reply.status!==200||!data.business||!data.fiscal
        ||!data.map_tiles||!validSqlId(data.row_version)
        ||typeof data.routine_buyer_emails_enabled!=='boolean'
        ||data.digital_download_limit===undefined){
        throw new ApiFailure(500,'pc_result_invalid',false);
      }
      this.data.set(data);
      if(!this.pending()){
        this.sync(data);
        this.freshRequired.set(false);
      }
      this.notice.set('Configuración del sitio leída. Versión '+data.row_version
        +'. Los campos omitidos en PATCH conservan su valor y la credencial nunca se retorna.');
    }catch(e){this.error.set(errorText(e));}
    finally{this.loading.set(false);}
  }

  private sync(d:AdminSettings):void {
    const b=d.business,f=d.fiscal,m=d.mail_sender??{},t=d.map_tiles;
    this.businessName=b.business_name;this.businessContact=b.business_contact??'';
    this.legalName=b.legal_name??'';this.ruc=b.tax_id_ruc??'';
    this.address=b.premises_address??'';
    this.complaintMode=b.complaint_mode;
    this.complaintUrl=b.complaint_url??'';this.privacyUrl=b.privacy_url??'';
    this.fiscalRegime=f.regime??'unconfigured';
    this.selectedDocs=[...(f.external_document_types??[])];
    this.policyNotice=d.policy_notice??'';
    this.privacyNotice=d.privacy_notice??'';
    this.deliveryEnabled=d.delivery_enabled??false;
    this.paymentEnabled=d.payment_enabled??false;
    this.routineEmails=d.routine_buyer_emails_enabled;
    this.quotaText=d.digital_download_limit===null?'':String(d.digital_download_limit);
    this.mailName=m.from_name??'';this.mailEmail=m.from_email??'';
    this.mailSignature=m.signature??'';
    this.mapEnabled=t.enabled;
    this.tileTemplate=t.tile_url_template??'';
    this.attributionText=t.attribution_text??'';
    this.attributionUrl=t.attribution_url??'';
    this.mapVerified=false;
    // Nunca completar inputs de secretos desde GET ni de un estado viejo.
    this.accessToken='';this.webhookSecret='';this.credentialAcknowledged=false;
  }
  hasDoc(kind:DocumentType):boolean {return this.selectedDocs.includes(kind);}
  toggleDoc(kind:DocumentType,enabled:boolean):void {
    if(this.pending()||this.busy())return;
    this.selectedDocs=enabled
      ? [...new Set([...this.selectedDocs,kind])]
      : this.selectedDocs.filter(x=>x!==kind);
  }

  private request(action:Action):PendingSettings {
    const d=this.data();
    if(!d||!this.canSettings()||this.freshRequired())throw new Error('Consulta nuevamente R8-45 antes de iniciar otra escritura; una operación previa puede haber alterado la versión.');
    const base:SettingsPatch={
      expected_version:d.row_version,idempotency_key:crypto.randomUUID()
    };
    if(action==='credentials'){
      if(!this.canCredentials())throw new Error('Se requiere permiso A de settings E integraciones para girar credenciales.');
      if(!this.credentialAcknowledged){
        throw new Error('Confirma que la credencial fue obtenida del canal seguro legítimo y que no implica habilitación de pagos.');
      }
      const token=this.accessToken;
      const webhook=this.webhookSecret;
      if(token.length<16||token.length>4096
        ||!/^[A-Za-z0-9._~+/=-]+$/.test(token)
        ||webhook && (webhook.length<16||webhook.length>4096
          ||/[\u0000-\u0020\u007f]/.test(webhook))){
        throw new Error('Credencial inválida. No incluyas espacios, controles ni tokens inventados.');
      }
      const body:PaymentCredentialUpdate={
        environment:this.environment,access_token:token,
        expected_version:d.row_version,idempotency_key:base.idempotency_key,
        ...(webhook?{webhook_secret:webhook}:{})
      };
      return {action,resource:'admin/payment-credentials',
        method:'PUT',body,expected:200,secret:true};
    }
    if(action==='business'){
      const b:BusinessSettings={
        business_name:fieldText(this.businessName,200,'Nombre comercial'),
        complaint_mode:this.complaintMode
      };
      const optional=[
        ['business_contact',this.businessContact,200],
        ['legal_name',this.legalName,240],
        ['tax_id_ruc',this.ruc,11],
        ['premises_address',this.address,500],
      ] as const;
      for(const [key,value,max] of optional){
        if(value.trim()){
          const text=fieldText(value,max,key);
          if(key==='tax_id_ruc'&&!/^[0-9]{11}$/.test(text))
            throw new Error('RUC declarado debe tener 11 dígitos.');
          b[key]=text;
        }
      }
      if(this.complaintMode==='external'){
        if(!this.complaintUrl.trim()){
          throw new Error('Libro externo requiere URL real de una alternativa legal verificada por el servidor.');
        }
        b.complaint_url=safeUrl(this.complaintUrl,600,'URL del Libro externo');
      }
      if(this.privacyUrl.trim())b.privacy_url=safeUrl(this.privacyUrl,600,'URL de privacidad complementaria');
      for(const [key,value] of optional){
        if(!value.trim() && typeof d.business[key]==='string' && d.business[key]){
          throw new Error('Para quitar un campo legal existente no se admite enviar vacío: la omisión conserva el valor. Mantén el original o usa un procedimiento específico autorizado.');
        }
      }
      if(same({...d.business,...b},d.business))throw new Error('No hay cambios efectivos en los campos de identidad.');
      base.business=b;
    }else if(action==='fiscal'){
      const fiscal:FiscalSettings={
        regime:this.fiscalRegime,
        external_document_types:[...this.selectedDocs]
      };
      if(same(fiscal,{...d.fiscal,...fiscal}))throw new Error('La declaración fiscal no cambió.');
      base.fiscal=fiscal;
    }else if(action==='policies'){
      const policy=this.policyNotice;
      const privacy=this.privacyNotice;
      if(policy!==d.policy_notice){
        if(!policy.trim()||policy.length>30000)throw new Error('Las condiciones requieren texto válido hasta 30000 caracteres.');
        base.policy_notice=policy;
      }
      if(privacy!==d.privacy_notice){
        if(!privacy.trim()||privacy.length>30000)throw new Error('Privacidad requiere texto válido hasta 30000 caracteres.');
        base.privacy_notice=privacy;
      }
      if(!base.policy_notice&&!base.privacy_notice)throw new Error('No hay textos legales nuevos que versionar.');
    }else if(action==='options'){
      const quota=limitInput(this.quotaText);
      if(this.deliveryEnabled!==d.delivery_enabled)base.delivery_enabled=this.deliveryEnabled;
      if(this.paymentEnabled!==d.payment_enabled)base.payment_enabled=this.paymentEnabled;
      if(this.routineEmails!==d.routine_buyer_emails_enabled)
        base.routine_buyer_emails_enabled=this.routineEmails;
      if(quota!==d.digital_download_limit)base.digital_download_limit=quota;
      if(Object.keys(base).length===2)throw new Error('No hay opciones modificadas.');
    }else if(action==='mail'){
      const name=this.mailName.trim(),email=this.mailEmail.trim(),signature=this.mailSignature;
      const before=d.mail_sender??{};
      const mail:MailSender={};
      if(name!==(before.from_name??'')){
        if(!name)throw new Error('Para quitar toda la configuración usa Restaurar remitente predeterminado.');
        mail.from_name=fieldText(name,160,'Remitente');
      }
      if(email!==(before.from_email??'')){
        if(!email||email.length>254||!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)){
          throw new Error('Indica un correo remitente válido. Para quitarlo usa Restaurar remitente predeterminado.');
        }
        mail.from_email=email;
      }
      if(signature!==(before.signature??'')){
        if(signature.length>1000)throw new Error('La firma supera 1000 caracteres.');
        mail.signature=signature;
      }
      if(Object.keys(mail).length===0)throw new Error('No hay cambios de remitente.');
      base.mail_sender=mail;
    }else if(action==='mail-reset'){
      if(!d.mail_sender)throw new Error('El remitente opcional ya está predeterminado.');
      base.mail_sender={};
    }else if(action==='map'){
      let map:MapTilesConfig={enabled:false};
      if(this.mapEnabled){
        if(!this.mapVerified)throw new Error('Revisa origen, licencia, atribución y CSP antes de solicitar habilitar mapa.');
        const template=safeUrl(this.tileTemplate,600,'Plantilla de tiles');
        if(!['{z}','{x}','{y}'].every(part=>template.includes(part))
          ||template.includes('@')||/[?&](?:key|token|api_key)=/i.test(template)){
          throw new Error('Plantilla sin credenciales y con {z}/{x}/{y} obligatorios.');
        }
        map={
          enabled:true,tile_url_template:template,
          attribution_text:fieldText(this.attributionText,500,'Atribución'),
          attribution_url:safeUrl(this.attributionUrl,500,'Licencia')
        };
      }
      if(same(map,d.map_tiles))throw new Error('No hay cambios en el mapa.');
      base.map_tiles=map;
    }
    return {
      action,resource:'admin/settings',method:'PATCH',
      body:base,expected:200,secret:false
    };
  }

  async save(action:Action):Promise<void>{
    if(this.busy()||this.loading()||this.blocked()||!this.canSettings())return;
    let attempt=this.pending();
    if(attempt&&attempt.action!==action){
      this.error.set('Hay una operación anterior sin resultado confirmado; no puedes enviar otra configuración.');
      return;
    }
    if(!attempt){
      try{attempt=this.request(action);this.pending.set(attempt);this.pendingChange.emit(true);}
      catch(e){this.error.set(errorText(e));return;}
    }
    this.busy.set(true);this.error.set('');
    let confirmed=false;
    try{
      if(attempt.secret){
        const reply=await this.api.json<MutationResult>('PUT',
          attempt.resource,attempt.body);
        if(reply.status!==200||!reply.body.operation_id
          ||!validSqlId(reply.body.row_version??'')){
          throw new ApiFailure(500,'pc_result_invalid',false);
        }
        confirmed=true;
        this.freshRequired.set(true);
        // Borrar referencias del secreto cuanto antes tras un 200 real.
        this.pending.set(null);
        this.accessToken='';this.webhookSecret='';this.credentialAcknowledged=false;
        this.notice.set('La bóveda registró el cambio con versión '
          +reply.body.row_version+'. Guardado NO significa pasarela probada ni webhook conectado.');
        await this.loadAfterMutation();
      }else{
        const reply=await this.api.json<AdminSettings>('PATCH',
          attempt.resource,attempt.body);
        if(reply.status!==200||!validSqlId(reply.body.row_version??'')
          ||!reply.body.business||!reply.body.fiscal){
          throw new ApiFailure(500,'pc_result_invalid',false);
        }
        confirmed=true;this.pending.set(null);
        this.data.set(reply.body);this.sync(reply.body);
        this.freshRequired.set(false);
        this.notice.set('Configuración guardada, versión '+reply.body.row_version
          +'. Solo campos enviados cambian. Textos legales crean versiones históricas si cambiaron.');
      }
    }catch(e){
      if(!confirmed && e instanceof ApiFailure
        &&[400,401,403,404,410,422,423].includes(e.status)){
        this.pending.set(null);
        // En rechazo explícito de credencial, no conservar secreto por inercia.
        if(attempt.secret){this.accessToken='';this.webhookSecret='';}
      }
      this.error.set(confirmed
        ?'La escritura fue confirmada, pero no se pudo releer. Consulta la versión actual antes de otra operación.'
        :errorText(e)+(e instanceof ApiFailure&&e.status===409
          ?' Conflicto de versión/identidad: comprueba qué cambió; no sobreescribas un ajuste simultáneo.'
          :' Si el resultado es incierto, conserva el mismo body y clave para reintentar.'));
    }finally{
      this.busy.set(false);
      if(!this.pending())this.pendingChange.emit(false);
    }
  }

  private async loadAfterMutation():Promise<void>{
    const reply=await this.api.json<AdminSettings>('GET','admin/settings');
    if(reply.status!==200||!validSqlId(reply.body.row_version??'')
      ||!reply.body.business||!reply.body.fiscal||!reply.body.map_tiles)
      throw new ApiFailure(500,'pc_result_invalid',false);
    this.data.set(reply.body);this.sync(reply.body);
    this.freshRequired.set(false);
  }
  abandon():void{
    if(this.busy()||this.loading())return;
    this.pending.set(null);this.pendingChange.emit(false);
    this.freshRequired.set(true);
    this.accessToken='';this.webhookSecret='';this.credentialAcknowledged=false;
    this.notice.set('Intento local descartado y secreto borrado de campos. Antes de otra operación, comprueba la versión efectiva y si el cambio realmente ocurrió.');
  }
}
