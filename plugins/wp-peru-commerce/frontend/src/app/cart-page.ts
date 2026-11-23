import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiFailure, PcApi } from './api';
import { CartState } from './cart-state';
import { PC_CONFIG } from './config';
import { soles, type Product, type ProductPage, type Slot, type SlotPage } from './dto';

@Component({
  selector:'pc-cart',
  standalone:true,
  imports:[FormsModule],
  templateUrl:'./cart-page.html'
})
export class CartPage implements OnInit {
  readonly cart=inject(CartState);
  readonly config=inject(PC_CONFIG);
  readonly api=inject(PcApi);
  readonly products=signal<Product[]>([]);
  readonly selected=signal<Record<string,string>>({});
  readonly amounts=signal<Record<string,number>>({});
  readonly error=signal('');
  readonly busy=signal(false);
  readonly slots=signal<Slot[]>([]);
  readonly service=signal<Product|null>(null);
  readonly slotError=signal('');
  page=1;
  hasMore=false;
  serviceDate='';
  private slotSellable='';
  readonly money=soles;

  ngOnInit():void {void this.load();}

  async load():Promise<void>{
    if(this.busy())return;
    this.busy.set(true);this.error.set('');
    try{
      const result=await this.api.json<ProductPage>('GET','public/products?page='+this.page+'&per_page=20');
      this.products.set(result.body.items);
      this.hasMore=result.body.has_more;
      this.selected.update(old=>{
        const next={...old};
        for(const p of result.body.items) {
          const variant=p.variants?.find(v=>v.can_order);
          if(variant&&!next[p.id])next[p.id]=variant.id;
        }
        return next;
      });
      const query=new URL(location.href).searchParams;
      const p=query.get('product'), v=query.get('variant');
      if(this.page===1 && p && v && /^[1-9][0-9]*$/.test(p)
          && /^[1-9][0-9]*$/.test(v)) {
        const found=result.body.items.find(x=>x.id===p);
        if(found?.variants?.some(x=>x.id===v&&x.can_order)){
          this.selected.update(x=>({...x,[p]:v}));
          this.add(found);
        }else {
          const fetched=await this.api.json<Product>('GET','public/products/'+p);
          if(fetched.body.variants?.some(x=>x.id===v&&x.can_order)){
            this.selected.update(x=>({...x,[p]:v}));
            this.add(fetched.body);
          }
        }
        const url=new URL(location.href);
        url.searchParams.delete('product');url.searchParams.delete('variant');
        history.replaceState(null,'',url.pathname+url.search);
      }
    }catch(error){this.error.set(this.message(error));}
    finally{this.busy.set(false);}
  }

  async changePage(next:number):Promise<void>{
    if(next<1||this.busy())return;
    this.page=next;
    await this.load();
  }
  chooseVariant(productId:string,id:string):void{
    this.selected.update(old=>({...old,[productId]:id}));
  }
  setAmount(productId:string,text:string):void{
    const number=Number(text);
    if(Number.isInteger(number)&&number>=1&&number<=100000) {
      this.amounts.update(old=>({...old,[productId]:number}));
    }
  }
  add(product:Product):void{
    const id=this.selected()[product.id];
    const variant=product.variants?.find(v=>v.id===id&&v.can_order);
    if(!variant||!product.can_order){
      this.error.set('La presentación ya no está disponible. Actualiza el catálogo.');
      return;
    }
    const amount=this.amounts()[product.id]??1;
    if(product.kind==='service') {
      this.slotSellable=id;
      this.service.set(product);
      this.slots.set([]);
      this.slotError.set('Elige una fecha y una franja disponible; no se reserva hasta confirmar.');
      return;
    }
    this.cart.add(id,amount,{id:product.id,kind:product.kind,title:product.title});
    this.error.set('');
  }

  async lookupSlots():Promise<void>{
    const product=this.service();
    if(!product||!this.serviceDate||this.busy())return;
    this.busy.set(true);this.slotError.set('');this.slots.set([]);
    try{
      const page=await this.api.json<SlotPage>('GET',
        'public/services/'+product.id+'/slots?date='+encodeURIComponent(this.serviceDate));
      this.slots.set(page.body.slots.filter(x=>x.available));
      if(!page.body.slots.some(x=>x.available)){
        this.slotError.set('No hay franjas disponibles en esa fecha.');
      }
    }catch(error){this.slotError.set(this.message(error));}
    finally{this.busy.set(false);}
  }
  chooseSlot(slot:Slot):void{
    const product=this.service();
    if(!product||!slot.available)return;
    const id=this.slotSellable;
    if(this.cart.lines().some(x=>x.slot&&x.sellable_id!==id)){
      this.slotError.set('Solo se permite una cita por pedido. Retira la cita actual para seleccionar otra.');
      return;
    }
    if(this.cart.lines().some(x=>x.sellable_id===id)){
      this.cart.chooseSlot(id,{starts_at_utc:slot.starts_at_utc,
        ends_at_utc:slot.ends_at_utc,...(slot.timezone?{timezone:slot.timezone}:{})});
    }else{
      this.cart.add(id,1,{id:product.id,kind:product.kind,title:product.title});
      this.cart.chooseSlot(id,{starts_at_utc:slot.starts_at_utc,
        ends_at_utc:slot.ends_at_utc,...(slot.timezone?{timezone:slot.timezone}:{})});
    }
    this.service.set(null);this.slots.set([]);this.slotError.set('');
  }
  setCartAmount(id:string,text:string):void{
    const quantity=Number(text);
    if(Number.isInteger(quantity)&&quantity>=0&&quantity<=100000){
      this.cart.setQuantity(id,quantity);
    }
  }
  async openCheckout():Promise<void>{
    const target=this.config.checkoutUrl;
    if(!target){this.error.set('La página de checkout no está configurada.');return;}
    const url=new URL(target,location.href);
    if(url.protocol!=='https:'||url.origin!==location.origin){
      this.error.set('La página de checkout no pertenece a esta instalación.');
      return;
    }
    location.assign(url.href);
  }
  private message(error:unknown):string{
    return error instanceof ApiFailure?error.message:'No se pudo consultar el catálogo.';
  }
}
