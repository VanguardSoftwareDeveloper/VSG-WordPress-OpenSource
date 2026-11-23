import { Component, computed, input, OnInit, output, signal } from '@angular/core';
import type { AdminBootstrap } from './dto';
import { AdminComplaints } from './admin-complaints';
import { AdminPrivacy } from './admin-privacy';

/** Pantallas legales separadas de pedidos: no existe acceso por folio. */
@Component({
  selector:'pc-admin-legal',standalone:true,
  imports:[AdminComplaints,AdminPrivacy],
  templateUrl:'./admin-legal.html'
})
export class AdminLegal implements OnInit {
  readonly employee=input.required<AdminBootstrap>();
  readonly pendingChange=output<boolean>();
  readonly canComplaints=computed(()=>this.employee().role_label==='A'
    && this.employee().ui_capabilities.includes('perucommerce_complaints_manage'));
  readonly canPrivacy=computed(()=>this.employee().role_label==='A'
    && this.employee().ui_capabilities.includes('perucommerce_privacy_manage'));
  readonly complaintPending=signal(false);
  readonly privacyPending=signal(false);
  readonly section=signal<'complaints'|'privacy'>('complaints');
  readonly privacyVisited=signal(false);

  ngOnInit():void {
    if(!this.canComplaints()&&this.canPrivacy())this.select('privacy');
  }

  select(view:'complaints'|'privacy'):void {
    if(this.complaintPending()||this.privacyPending())return;
    if(view==='complaints'&&!this.canComplaints())return;
    if(view==='privacy'&&!this.canPrivacy())return;
    this.section.set(view);
    if(view==='privacy')this.privacyVisited.set(true);
  }
  onPending(source:'complaints'|'privacy',value:boolean):void {
    if(source==='complaints')this.complaintPending.set(value);
    else this.privacyPending.set(value);
    this.pendingChange.emit(this.complaintPending()||this.privacyPending());
  }
}
