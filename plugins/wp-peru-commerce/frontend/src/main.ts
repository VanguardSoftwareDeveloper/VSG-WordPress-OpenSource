import { bootstrapApplication } from '@angular/platform-browser';
import { PC_CONFIG, type PcConfig } from './app/config';
import { PcApp } from './app/pc-app';

const root = document.querySelector<HTMLElement>('pc-root[data-pc-root], pc-root[data-api]');
if (root) {
  const scene = root.dataset['scene'];
  const api = root.dataset['api'];
  if (scene && ['cart','checkout','return','admin'].includes(scene) && api) {
    const url = new URL(api, location.href);
    if (url.origin === location.origin && url.protocol === 'https:') {
      const config: PcConfig = {
        scene: scene as PcConfig['scene'],
        apiBase: url.href.endsWith('/') ? url.href : url.href + '/',
        nonce: root.dataset['nonce'] || '',
        checkoutUrl: root.dataset['checkout'] || undefined,
        digitalOpenUrl: root.dataset['digitalOpenUrl'] || undefined,
        digitalOpenNonce: root.dataset['digitalOpenNonce'] || undefined,
      };
      void bootstrapApplication(PcApp, {
        providers: [
          { provide: PC_CONFIG, useValue: config },
        ],
      }).catch(() => {
        root.textContent = 'No se pudo abrir la interfaz. Vuelve a cargar la página.';
      });
    }
  }
}
