import { Injectable, signal } from '@angular/core';
import type { CartLine, Kind } from './dto';

export interface SelectedCartLine extends CartLine {
  product_id?: string;
  kind?: Kind;
  title?: string;
}

/**
 * Sólo persistimos IDs/cantidades/franja temporal, jamás identidad,
 * importe, secreto, tracking URL, OTP ni token digital.
 */
@Injectable({providedIn:'root'})
export class CartState {
  private readonly key='pc_cart_v1';
  readonly lines=signal<SelectedCartLine[]>(this.read());
  checkoutLines():CartLine[]{return this.lines().map(l=>({sellable_id:l.sellable_id,quantity:l.quantity,...(l.slot?{slot:l.slot}:{})}));}

  add(id:string,quantity=1,product?:{id:string;kind:Kind;title:string}):void {
    if (!/^[1-9][0-9]{0,19}$/.test(id) || !Number.isSafeInteger(quantity)
        || quantity<1 || quantity>100000) { return; }
    const lines=[...this.lines()];
    const idx=lines.findIndex(l=>l.sellable_id===id);
    if (idx>=0) {
      const next=lines[idx].quantity+quantity;
      if(next>100000) return;
      lines[idx]={...lines[idx],quantity:next,...(product?{product_id:product.id,kind:product.kind,title:product.title}:{})};
    } else if(lines.length<100) {
      lines.push({sellable_id:id,quantity,...(product?{product_id:product.id,kind:product.kind,title:product.title}:{})});
    } else { return; }
    this.assign(lines);
  }
  setQuantity(id:string,quantity:number):void {
    if (!Number.isSafeInteger(quantity)) return;
    if (quantity<=0) {this.remove(id);return;}
    if(quantity>100000 || (this.lines().some(x=>x.sellable_id===id&&x.kind==='service')&&quantity!==1)) return;
    this.assign(this.lines().map(l=>l.sellable_id===id?{...l,quantity}:l));
  }
  chooseSlot(id:string,slot:CartLine['slot']):void {
    const lines=this.lines().map(l=>l.sellable_id===id?
      {...l,...(slot?{slot}:{slot:undefined})}:l);
    // Una sola cita en todo el carrito, como exige el backend.
    let count=0;
    for(const line of lines){ if(line.slot) count++; }
    if(count>1)return;
    this.assign(lines);
  }
  remove(id:string):void {this.assign(this.lines().filter(l=>l.sellable_id!==id));}
  clear():void {this.assign([]);}
  private assign(lines:SelectedCartLine[]):void {
    this.lines.set(lines);
    try {sessionStorage.setItem(this.key,JSON.stringify(lines));}catch { /* memoria solamente */ }
  }
  private read():SelectedCartLine[] {
    try {
      const raw=sessionStorage.getItem(this.key);
      if (!raw || raw.length>20000) return [];
      const entries:unknown=JSON.parse(raw);
      if(!Array.isArray(entries) || entries.length>100) return [];
      const output:SelectedCartLine[]=[];
      for(const item of entries){
        if(!item||typeof item!=='object')return [];
        const l=item as Record<string,unknown>;
        if(typeof l['sellable_id']!=='string'
          || !/^[1-9][0-9]{0,19}$/.test(l['sellable_id'])
          || typeof l['quantity']!=='number'
          || !Number.isInteger(l['quantity']) || l['quantity']<1 || l['quantity']>100000
          || output.some(x=>x.sellable_id===l['sellable_id']))return [];
        let slot:CartLine['slot'];
        if(l['slot']!==undefined){
          if(!l['slot'] || typeof l['slot']!=='object')return [];
          const s=l['slot'] as Record<string,unknown>;
          if(typeof s['starts_at_utc']!=='string'
            || typeof s['ends_at_utc']!=='string'
            || s['starts_at_utc'].length>40 || s['ends_at_utc'].length>40
            || (s['timezone']!==undefined && typeof s['timezone']!=='string'))return [];
          slot={starts_at_utc:s['starts_at_utc'],ends_at_utc:s['ends_at_utc'],
            ...(typeof s['timezone']==='string'?{timezone:s['timezone']}:{})};
        }
        output.push({sellable_id:l['sellable_id'],quantity:l['quantity'] as number,...(slot?{slot}:{}),
          ...(typeof l['product_id']==='string' && /^[1-9][0-9]{0,19}$/.test(l['product_id'])?{product_id:l['product_id']}:{}),
          ...(['physical','digital','service'].includes(String(l['kind']))?{kind:l['kind'] as Kind}:{}),
          ...(typeof l['title']==='string' && l['title'].length<=200?{title:l['title']}:{})});
      }
      if(output.filter(x=>x.slot).length>1)return [];
      return output;
    } catch { return []; }
  }
}
