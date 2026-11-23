import { InjectionToken } from '@angular/core';

export interface PcConfig {
  readonly scene: 'cart' | 'checkout' | 'return' | 'admin';
  readonly apiBase: string;
  readonly nonce: string;
  readonly checkoutUrl?: string;
  /** Acción WP nativa de apertura digital tipada; nunca REST, solo A. */
  readonly digitalOpenUrl?: string;
  readonly digitalOpenNonce?: string;
}

export const PC_CONFIG = new InjectionToken<PcConfig>('PeruCommerce:config');
