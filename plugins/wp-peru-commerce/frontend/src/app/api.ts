import { Injectable, inject } from '@angular/core';
import { PC_CONFIG } from './config';

/**
 * Cliente same-origin: jamás almacena OTP, token, cookie, nonce o PII en
 * localStorage/sessionStorage ni interpreta un 202 como venta confirmada.
 */
export class ApiFailure extends Error {
  constructor(
    readonly status: number,
    readonly code: string,
    readonly retryable: boolean,
  ) { super(ApiFailure.describe(status)); }

  static describe(status: number): string {
    switch (status) {
      case 0: return 'Sin conexión o respuesta incierta. Comprueba el estado antes de repetir.';
      case 400: case 422: return 'Revisa los datos señalados.';
      case 401: return 'La sesión terminó. Ingresa nuevamente por el canal autorizado.';
      case 403: return 'No tienes permiso para esta operación.';
      case 404: return 'El recurso no está disponible.';
      case 409: return 'Los datos cambiaron. Actualiza y revisa antes de continuar.';
      case 410: return 'El acceso venció o fue revocado.';
      case 423: return 'Esta función aún no está habilitada para el comercio.';
      case 429: return 'Demasiadas solicitudes. Intenta más tarde.';
      case 503: return 'Servicio temporalmente no disponible.';
      default: return 'La operación no se pudo completar.';
    }
  }
}
export interface ApiReply<T> { readonly status: number; readonly body: T; }

@Injectable({providedIn:'root'})
export class PcApi {
  private readonly config = inject(PC_CONFIG);

  async json<T>(
    method: 'GET'|'POST'|'PUT'|'PATCH',
    resource: string,
    payload?: object,
  ): Promise<ApiReply<T>> {
    return this.send<T>(method,resource,payload);
  }

  async form<T>(resource:string, body:FormData):Promise<ApiReply<T>> {
    return this.send<T>('POST',resource,body);
  }

  /**
   * R8-62: descarga administrativa A-only auditada con nonce en cabecera.
   * Nunca usar href directo sin autorización, ni persistir blob o URL.
   */
  async privateFile(resource:string):Promise<{blob:Blob;filename:string}> {
    if (!this.config.nonce
      || !/^[a-z0-9/?=&._%-]+$/i.test(resource)
      || resource.includes('..') || resource.startsWith('/')) {
      throw new ApiFailure(403,'pc_access_denied',false);
    }
    const url=new URL(resource,this.config.apiBase);
    if (url.origin!==location.origin || url.protocol!=='https:') {
      throw new ApiFailure(403,'pc_access_denied',false);
    }
    let response:Response;
    try {
      response=await fetch(url.href,{
        method:'GET',credentials:'same-origin',redirect:'error',
        cache:'no-store',headers:{
          'Accept':'application/octet-stream',
          'X-WP-Nonce':this.config.nonce,
        }
      });
    }catch {
      throw new ApiFailure(0,'pc_network_uncertain',true);
    }
    if(!response.ok) {
      throw new ApiFailure(response.status,'pc_digital_file_unavailable',
        response.status===429||response.status===503);
    }
    const mime=(response.headers.get('content-type')??'').split(';')[0].trim().toLowerCase();
    if (mime!=='application/octet-stream') {
      throw new ApiFailure(500,'pc_result_invalid',false);
    }
    const disposition=response.headers.get('content-disposition')??'';
    const match=/filename="([A-Za-z0-9._-]{1,255})"/.exec(disposition);
    const filename=match?.[1] && match[1]!=='.' && match[1]!=='..'
      ?match[1]:'archivo-protegido.bin';
    const blob=await response.blob();
    if (blob.size===0) throw new ApiFailure(500,'pc_result_invalid',false);
    return {blob,filename};
  }

  private async send<T>(
    method:string, resource:string, payload?: object | FormData,
  ):Promise<ApiReply<T>> {
    if (!/^[a-z0-9/?=&._%-]+$/i.test(resource)
        || resource.includes('..') || resource.startsWith('/')) {
      throw new ApiFailure(400,'pc_invalid_resource',false);
    }
    const url = new URL(resource,this.config.apiBase);
    if (url.origin !== location.origin || url.protocol!=='https:') {
      throw new ApiFailure(400,'pc_invalid_origin',false);
    }
    const headers:Record<string,string>={'Accept':'application/json'};
    if (this.config.nonce) { headers['X-WP-Nonce']=this.config.nonce; }
    if (payload && !(payload instanceof FormData)) {
      headers['Content-Type']='application/json';
    }
    let response:Response;
    try {
      response=await fetch(url.href,{
        method,
        credentials:'same-origin',
        redirect:'error',
        cache:'no-store',
        headers,
        body: payload instanceof FormData ? payload
          : payload ? JSON.stringify(payload) : undefined,
      });
    } catch {
      throw new ApiFailure(0,'pc_network_uncertain',true);
    }
    let data:unknown;
    try { data=await response.json(); }
    catch { throw new ApiFailure(response.status,'pc_result_invalid',false); }
    if (!response.ok) {
      const obj = data && typeof data==='object' ? data as Record<string,unknown> : {};
      throw new ApiFailure(response.status,
        typeof obj['code']==='string' ? obj['code'] : 'pc_request_failed',
        response.status===429 || response.status===503 || response.status===423);
    }
    if (!data || typeof data!=='object' || Array.isArray(data)) {
      throw new ApiFailure(response.status,'pc_result_invalid',false);
    }
    return {status:response.status,body:data as T};
  }
}

/** Mismo intento lógico + misma clave hasta resultado definitivo. */
export class LogicalAttempt {
  private key: string | null = null;
  current(): string {
    return this.key ??= crypto.randomUUID();
  }
  resetForNewAction(): void { this.key=null; }
}
