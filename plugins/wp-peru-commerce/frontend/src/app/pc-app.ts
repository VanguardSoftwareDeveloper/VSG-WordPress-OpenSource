import { Component, inject } from '@angular/core';
import { PC_CONFIG } from './config';
import { CartPage } from './cart-page';
import { CheckoutPage } from './checkout-page';
import { ReturnPage } from './return-page';
import { AdminPage } from './admin-page';

@Component({
  selector:'pc-root',
  standalone:true,
  imports:[CartPage,CheckoutPage,ReturnPage,AdminPage],
  templateUrl:'./pc-app.html',
})
export class PcApp { readonly config=inject(PC_CONFIG); }
