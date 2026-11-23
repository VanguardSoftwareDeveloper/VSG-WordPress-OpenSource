import { Component, computed, input, OnInit, output, signal } from '@angular/core';
import type { AdminBootstrap } from './dto';
import { AdminSettingsPage } from './admin-settings';
import { AdminCoverage } from './admin-coverage';
import { AdminNotifications } from './admin-notifications';
import { AdminDiagnostics } from './admin-diagnostics';

type Section='notifications'|'settings'|'coverage'|'diagnostics';

@Component({
  selector:'pc-admin-operations',standalone:true,
  imports:[AdminSettingsPage,AdminCoverage,AdminNotifications,AdminDiagnostics],
  templateUrl:'./admin-operations.html'
})
export class AdminOperations implements OnInit {
  readonly employee=input.required<AdminBootstrap>();
  readonly pendingChange=output<boolean>();
  readonly canNotifications=computed(()=>['A','O'].includes(this.employee().role_label??'')
    &&this.employee().ui_capabilities.includes('perucommerce_notifications_operate'));
  readonly canSettings=computed(()=>this.employee().role_label==='A'
    &&this.employee().ui_capabilities.includes('perucommerce_settings_manage'));
  readonly canAny=computed(()=>this.canSettings()||this.canNotifications());
  readonly section=signal<Section>('notifications');
  readonly visited=signal<Section[]>([]);
  readonly settingsPending=signal(false);
  readonly coveragePending=signal(false);
  readonly notificationsPending=signal(false);
  readonly hasPending=computed(()=>this.settingsPending()
    ||this.coveragePending()||this.notificationsPending());

  ngOnInit():void {
    this.show(this.canNotifications()?'notifications':'settings');
  }
  show(view:Section):void {
    if(this.hasPending())return;
    if(view==='notifications'&&!this.canNotifications())return;
    if(view!=='notifications'&&!this.canSettings())return;
    this.section.set(view);
    if(!this.visited().includes(view))this.visited.update(old=>[...old,view]);
  }
  onPending(from:'settings'|'coverage'|'notifications',value:boolean):void {
    if(from==='settings')this.settingsPending.set(value);
    else if(from==='coverage')this.coveragePending.set(value);
    else this.notificationsPending.set(value);
    this.pendingChange.emit(this.hasPending());
  }
}
