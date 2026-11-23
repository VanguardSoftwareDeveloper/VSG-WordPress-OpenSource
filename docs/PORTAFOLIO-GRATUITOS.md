# Portafolio gratuito — 131 categorías candidatas

**Criterio expreso del propietario, 22/11/2026:** tiempo de diseño, programación, revisión, mantenimiento propio y soporte voluntario **NO cuentan como gasto para decidir el precio**. El software será gratuito por defecto. Cobrar solo cuando el desarrollador **asuma una factura real** de API, infraestructura o servicio de terceros que no se pueda evitar o trasladar directamente a la cuenta del usuario. Los costos de hosting/servicios del usuario no son costos del autor.

**Estado de evidencias:** 131 carpetas del catálogo; solo `wp-peru-commerce` contiene implementación en fuente y todavía no se ha validado ni distribuido. Esta evaluación es por **arquitectura técnicamente plausible**, NO por auditoría individual de 131 implementaciones ni garantía de tarifas futuras. En algunas categorías, la funcionalidad requiere una cuenta del usuario con costos externos o límites; publicar el **código del plugin** gratuitamente no garantiza que todos los terceros sean gratuitos.

## Resultado

- **131/131 categorías aptas para diseñarse como plugins gratuitos**, localmente o con proveedor facturado directamente al titular de cada sitio.
- **0/131 categorías** con obligación demostrada hoy de facturar un servicio/API a la cuenta de Vanguard.
- **0 productos premium decididos** por gasto externo inevitable; ningún plugin cambia a pago solo por complejidad o soporte.
- No significa construir 131 plugins: evaluar utilidad, duplicación y seguridad antes de cada desarrollo. `wp-wordpress-pos` (#82) sigue siendo una ficha sin implementación: la funcionalidad POS aprobada se integra en PeruCommerce (#60), no se duplica.
- La clasificación se cambia por producto cuando se demuestre un **gasto real obligatorio para el desarrollador**, no por conjetura de que una API o un proyecto de seguridad «debe ser de pago».

## Condiciones de distribución gratuita

1. Backend, base de datos, tareas y archivos en el WordPress/hosting del usuario, salvo integración opcional expresamente autorizada.
2. Cuando un proveedor requiera dinero o tarjeta (Mercado Pago, Meta, Google Maps, almacenamiento, correo, SMS, IA, etc.), que el **usuario sea titular de la cuenta, API key, proyecto, cuota y facturación**. No se deben incrustar credenciales de Vanguard ni enrutar consumos por su cuenta por defecto.
3. El componente gratuito debe operar sin cuotas del fabricante; cuando una función dependa de servicio externo, explicar cuenta, permisos, riesgos y costo para el usuario **antes** de activarla, y fallar de forma segura si está sin configurar.
4. Seguridad y privacidad se mantienen: secretos del comerciante exclusivamente del lado servidor, permisos, capacidad de revocación, minimización de datos, rate limits y licencias de terceros vigentes.
5. Las funciones administradas y financiadas por Vanguard serían un **servicio opcional independiente**, nunca razón retroactiva para convertir a pago el plugin base.
6. La disponibilidad, licencias, términos y tarifas de terceros se verifican con cada integración y versión; «sin gasto del fabricante» requiere comprobar **quién es titular de la cuenta facturada**.

## Inventario integral

En «Costo externo potencial», «cuenta del usuario» identifica una **arquitectura recomendada**, no que el proveedor ya haya aprobado esa modalidad para el producto concreto.

| Nº | Categoría | Modalidad propuesta | Costo externo potencial / condición |
|---:|---|---|---|
| 1 | [`wp-security-suite`](../plugins/wp-security-suite/) | Gratis; servicio del usuario si aplica | Inteligencia de amenazas externa opcional; instalar reglas locales si no hay cuenta. |
| 2 | [`wp-login-protection`](../plugins/wp-login-protection/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 3 | [`wp-antispam`](../plugins/wp-antispam/) | Gratis; servicio del usuario si aplica | Antispam remoto opcional; clave/consumo del sitio del usuario. |
| 4 | [`wp-site-backups`](../plugins/wp-site-backups/) | Gratis; servicio del usuario si aplica | Backups remotos opcionales; almacenamiento en cuenta del usuario. |
| 5 | [`wp-site-migration`](../plugins/wp-site-migration/) | Gratis; servicio del usuario si aplica | Almacenamiento temporal externo opcional; que lo contrate el usuario. |
| 6 | [`wp-cache-performance`](../plugins/wp-cache-performance/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 7 | [`wp-image-optimizer`](../plugins/wp-image-optimizer/) | Gratis; servicio del usuario si aplica | Procesamiento remoto opcional; procesamiento local cuando sea viable. |
| 8 | [`wp-database-optimizer`](../plugins/wp-database-optimizer/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 9 | [`wp-mail-smtp`](../plugins/wp-mail-smtp/) | Gratis; servicio del usuario si aplica | Servicio SMTP de correo: cuenta y facturación del usuario. |
| 10 | [`wp-mail-logging`](../plugins/wp-mail-logging/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 11 | [`wp-redirections`](../plugins/wp-redirections/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 12 | [`wp-broken-links`](../plugins/wp-broken-links/) | Gratis; servicio del usuario si aplica | Verificación externa de URL opcional; evitar financiar rastreos masivos. |
| 13 | [`wp-security-audit`](../plugins/wp-security-audit/) | Gratis; servicio del usuario si aplica | Almacenamiento externo de eventos opcional; instancia local predeterminada. |
| 14 | [`wp-technical-diagnostics`](../plugins/wp-technical-diagnostics/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 15 | [`wp-multisite-management`](../plugins/wp-multisite-management/) | Gratis; servicio del usuario si aplica | Monitoreo remoto opcional; instalaciones y servidores bajo control del usuario. |
| 16 | [`wp-error-recovery`](../plugins/wp-error-recovery/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 17 | [`wp-seo-manager`](../plugins/wp-seo-manager/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 18 | [`wp-structured-data`](../plugins/wp-structured-data/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 19 | [`wp-local-seo`](../plugins/wp-local-seo/) | Gratis; servicio del usuario si aplica | Mapas/geocodificación opcional: clave y proyecto del usuario. |
| 20 | [`wp-web-analytics`](../plugins/wp-web-analytics/) | Gratis; servicio del usuario si aplica | Analítica externa opcional; procesamiento local como base. |
| 21 | [`wp-conversion-tracking`](../plugins/wp-conversion-tracking/) | Gratis; servicio del usuario si aplica | Publicidad y conversiones: cuentas de las plataformas del usuario. |
| 22 | [`wp-cookie-consent`](../plugins/wp-cookie-consent/) | Gratis; servicio del usuario si aplica | Consentimiento local, sin financiar una CMP centralizada. |
| 23 | [`wp-accessibility-checker`](../plugins/wp-accessibility-checker/) | Gratis; servicio del usuario si aplica | Auditorías SaaS opcionales; el usuario contrata el servicio. |
| 24 | [`wp-legal-policies`](../plugins/wp-legal-policies/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 25 | [`wp-ad-management`](../plugins/wp-ad-management/) | Gratis; servicio del usuario si aplica | Plataformas de anuncios: cuentas y cargos del anunciante. |
| 26 | [`wp-contact-forms`](../plugins/wp-contact-forms/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 27 | [`wp-surveys-quizzes`](../plugins/wp-surveys-quizzes/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 28 | [`wp-custom-fields`](../plugins/wp-custom-fields/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 29 | [`wp-custom-post-types`](../plugins/wp-custom-post-types/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 30 | [`wp-data-tables`](../plugins/wp-data-tables/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 31 | [`wp-advanced-search`](../plugins/wp-advanced-search/) | Gratis; servicio del usuario si aplica | Motor de búsqueda externo opcional; clave del cliente. |
| 32 | [`wp-content-filters`](../plugins/wp-content-filters/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 33 | [`wp-multilingual`](../plugins/wp-multilingual/) | Gratis; servicio del usuario si aplica | Traducción automática opcional; API y cuota del cliente. |
| 34 | [`wp-translation-manager`](../plugins/wp-translation-manager/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 35 | [`wp-import-export`](../plugins/wp-import-export/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 36 | [`wp-media-library`](../plugins/wp-media-library/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 37 | [`wp-photo-galleries`](../plugins/wp-photo-galleries/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 38 | [`wp-pdf-viewer`](../plugins/wp-pdf-viewer/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 39 | [`wp-podcasts`](../plugins/wp-podcasts/) | Gratis; servicio del usuario si aplica | Hospedaje de audio/CDN opcional a cargo del creador. |
| 40 | [`wp-headless-api`](../plugins/wp-headless-api/) | Gratis; servicio del usuario si aplica | Servicios headless externos opcionales; WordPress sigue siendo servidor. |
| 41 | [`wp-automations-webhooks`](../plugins/wp-automations-webhooks/) | Gratis; servicio del usuario si aplica | Conexiones externas por tokens/cuentas del usuario. |
| 42 | [`wp-editorial-workflows`](../plugins/wp-editorial-workflows/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 43 | [`wp-user-registration`](../plugins/wp-user-registration/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 44 | [`wp-roles-permissions`](../plugins/wp-roles-permissions/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 45 | [`wp-download-manager`](../plugins/wp-download-manager/) | Gratis; servicio del usuario si aplica | Almacenamiento privado o de objetos, del usuario. |
| 46 | [`wp-knowledge-base`](../plugins/wp-knowledge-base/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 47 | [`wp-documentation`](../plugins/wp-documentation/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 48 | [`wp-crm`](../plugins/wp-crm/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 49 | [`wp-email-subscribers`](../plugins/wp-email-subscribers/) | Gratis; servicio del usuario si aplica | Proveedor de correo masivo y envíos a nombre del comercio. |
| 50 | [`wp-lead-capture`](../plugins/wp-lead-capture/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 51 | [`wp-whatsapp-business`](../plugins/wp-whatsapp-business/) | Gratis; servicio del usuario si aplica | WhatsApp Business API y mensajes facturados al negocio, nunca a tu cuenta común. |
| 52 | [`wp-live-chat`](../plugins/wp-live-chat/) | Gratis; servicio del usuario si aplica | Chat SaaS opcional; versión alojada en el WordPress del usuario. |
| 53 | [`wp-helpdesk-tickets`](../plugins/wp-helpdesk-tickets/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 54 | [`wp-web-push`](../plugins/wp-web-push/) | Gratis; servicio del usuario si aplica | Proveedor push externo opcional; cuenta del usuario. |
| 55 | [`wp-social-feeds`](../plugins/wp-social-feeds/) | Gratis; servicio del usuario si aplica | APIs de redes sociales: acceso/tokens del usuario; no financiar su consumo. |
| 56 | [`wp-reviews-reputation`](../plugins/wp-reviews-reputation/) | Gratis; servicio del usuario si aplica | Plataformas de reputación externas opcionales; cuenta del usuario. |
| 57 | [`wp-affiliate-program`](../plugins/wp-affiliate-program/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 58 | [`wp-business-locations`](../plugins/wp-business-locations/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 59 | [`wp-ab-testing`](../plugins/wp-ab-testing/) | Gratis; servicio del usuario si aplica | Analítica/A-B SaaS opcional; experimentos en hosting del usuario. |
| 60 | [`wp-peru-commerce`](../plugins/wp-peru-commerce/) | Gratis; servicio del usuario si aplica | Mercado Pago y mapas con cuenta del comercio; POS integrado sin servidor tuyo. |
| 61 | [`wp-digital-products`](../plugins/wp-digital-products/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 62 | [`wp-checkout-fields`](../plugins/wp-checkout-fields/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 63 | [`wp-payment-gateways`](../plugins/wp-payment-gateways/) | Gratis; servicio del usuario si aplica | Pasarela de pago: comisiones y cuenta del comerciante. |
| 64 | [`wp-latam-payments`](../plugins/wp-latam-payments/) | Gratis; servicio del usuario si aplica | Pasarelas regionales: cuenta, tarifas y conciliación del comerciante. |
| 65 | [`wp-b2b-wholesale`](../plugins/wp-b2b-wholesale/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 66 | [`wp-marketplaces`](../plugins/wp-marketplaces/) | Gratis; servicio del usuario si aplica | Pago de vendedores/marketplace: cuentas y comisiones de titulares. |
| 67 | [`wp-product-variations`](../plugins/wp-product-variations/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 68 | [`wp-commerce-subscriptions`](../plugins/wp-commerce-subscriptions/) | Gratis; servicio del usuario si aplica | Cobro recurrente procesado por pasarela del comercio. |
| 69 | [`wp-inventory`](../plugins/wp-inventory/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 70 | [`wp-shipping-rates`](../plugins/wp-shipping-rates/) | Gratis; servicio del usuario si aplica | Cálculo de envío externo opcional, cuenta del comercio. |
| 71 | [`wp-logistics-integrations`](../plugins/wp-logistics-integrations/) | Gratis; servicio del usuario si aplica | Transportistas y agregadores: consumo del usuario. |
| 72 | [`wp-dropshipping`](../plugins/wp-dropshipping/) | Gratis; servicio del usuario si aplica | Proveedor de dropshipping: planes y cuotas del usuario. |
| 73 | [`wp-coupons-discounts`](../plugins/wp-coupons-discounts/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 74 | [`wp-loyalty-points`](../plugins/wp-loyalty-points/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 75 | [`wp-cart-recovery`](../plugins/wp-cart-recovery/) | Gratis; servicio del usuario si aplica | Email/SMS para recuperación: cuenta y costos del negocio. |
| 76 | [`wp-commercial-pdfs`](../plugins/wp-commercial-pdfs/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 77 | [`wp-fiscal-taxes`](../plugins/wp-fiscal-taxes/) | Gratis; servicio del usuario si aplica | Servicios fiscales o SUNAT: si hay terceros pagos, los contrata el usuario. |
| 78 | [`wp-product-reviews`](../plugins/wp-product-reviews/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 79 | [`wp-quote-requests`](../plugins/wp-quote-requests/) | Gratis; servicio del usuario si aplica | Canales de envío de presupuestos opcionales: proveedor del usuario. |
| 80 | [`wp-product-search`](../plugins/wp-product-search/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 81 | [`wp-product-bundles`](../plugins/wp-product-bundles/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 82 | [`wp-wordpress-pos`](../plugins/wp-wordpress-pos/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 83 | [`wp-appointment-bookings`](../plugins/wp-appointment-bookings/) | Gratis; servicio del usuario si aplica | Avisos SMS/correo y pasarelas: cuentas del comercio. |
| 84 | [`wp-events-calendar`](../plugins/wp-events-calendar/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 85 | [`wp-event-tickets`](../plugins/wp-event-tickets/) | Gratis; servicio del usuario si aplica | Entradas y cobros mediante pasarela del organizador. |
| 86 | [`wp-lms`](../plugins/wp-lms/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 87 | [`wp-memberships`](../plugins/wp-memberships/) | Gratis; servicio del usuario si aplica | Pasarela y mensajería de membresías: cuenta del propietario del sitio. |
| 88 | [`wp-private-communities`](../plugins/wp-private-communities/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 89 | [`wp-forums`](../plugins/wp-forums/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 90 | [`wp-donations`](../plugins/wp-donations/) | Gratis; servicio del usuario si aplica | Procesamiento de donaciones en cuenta de la organización. |
| 91 | [`wp-business-directories`](../plugins/wp-business-directories/) | Gratis; servicio del usuario si aplica | Mapas/servicios de directorio opcionales: cuenta del operador. |
| 92 | [`wp-classifieds`](../plugins/wp-classifieds/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 93 | [`wp-job-board`](../plugins/wp-job-board/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 94 | [`wp-real-estate`](../plugins/wp-real-estate/) | Gratis; servicio del usuario si aplica | Mapas, firma digital, CRM externos opcionales: cuenta de la inmobiliaria. |
| 95 | [`wp-hotel-booking`](../plugins/wp-hotel-booking/) | Gratis; servicio del usuario si aplica | Canales hoteleros y pasarelas externas: contratos del hotel. |
| 96 | [`wp-tours`](../plugins/wp-tours/) | Gratis; servicio del usuario si aplica | Reservas/pagos/mapas externos: cuenta del operador de tours. |
| 97 | [`wp-restaurants`](../plugins/wp-restaurants/) | Gratis; servicio del usuario si aplica | Pagos, delivery y mensajería: cuenta del restaurante. |
| 98 | [`wp-vehicle-sales`](../plugins/wp-vehicle-sales/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 99 | [`wp-project-management`](../plugins/wp-project-management/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 100 | [`wp-sports-leagues`](../plugins/wp-sports-leagues/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 101 | [`wp-associations`](../plugins/wp-associations/) | Gratis; servicio del usuario si aplica | Pasarela/mensajería de cuotas: cuenta de la asociación. |
| 102 | [`wp-safe-svg`](../plugins/wp-safe-svg/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 103 | [`wp-database-search-replace`](../plugins/wp-database-search-replace/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 104 | [`wp-cron-manager`](../plugins/wp-cron-manager/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 105 | [`wp-update-control`](../plugins/wp-update-control/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 106 | [`wp-admin-menu`](../plugins/wp-admin-menu/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 107 | [`wp-admin-columns`](../plugins/wp-admin-columns/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 108 | [`wp-file-manager`](../plugins/wp-file-manager/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 109 | [`wp-media-offload`](../plugins/wp-media-offload/) | Gratis; servicio del usuario si aplica | S3/R2 u objetos: bucket y cobro del propietario del sitio. |
| 110 | [`wp-bulk-editor`](../plugins/wp-bulk-editor/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 111 | [`wp-permalinks`](../plugins/wp-permalinks/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 112 | [`wp-code-snippets`](../plugins/wp-code-snippets/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 113 | [`wp-user-switching`](../plugins/wp-user-switching/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 114 | [`wp-social-login`](../plugins/wp-social-login/) | Gratis; servicio del usuario si aplica | OAuth en proyecto del sitio; confirmar términos y permisos de proveedor. |
| 115 | [`wp-mfa`](../plugins/wp-mfa/) | Gratis; servicio del usuario si aplica | Segundo factor SMS opcional pagado por el sitio; TOTP local posible. |
| 116 | [`wp-captcha`](../plugins/wp-captcha/) | Gratis; servicio del usuario si aplica | CAPTCHA remoto opcional: cuenta y términos del sitio. |
| 117 | [`wp-maintenance-mode`](../plugins/wp-maintenance-mode/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 118 | [`wp-affiliate-links`](../plugins/wp-affiliate-links/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 119 | [`wp-rss-aggregator`](../plugins/wp-rss-aggregator/) | Gratis; servicio del usuario si aplica | Feeds/procesamiento externo opcional: cuenta del usuario. |
| 120 | [`wp-heatmaps`](../plugins/wp-heatmaps/) | Gratis; servicio del usuario si aplica | Analítica masiva en BD/infra del usuario; ningún servidor de telemetría tuyo. |
| 121 | [`wp-video-player`](../plugins/wp-video-player/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 122 | [`wp-frontend-submissions`](../plugins/wp-frontend-submissions/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 123 | [`wp-wishlist`](../plugins/wp-wishlist/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 124 | [`wp-product-compare`](../plugins/wp-product-compare/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 125 | [`wp-product-feeds`](../plugins/wp-product-feeds/) | Gratis; servicio del usuario si aplica | Feeds de productos para plataformas publicitarias: cuentas del comercio. |
| 126 | [`wp-order-export`](../plugins/wp-order-export/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 127 | [`wp-multi-currency`](../plugins/wp-multi-currency/) | Gratis; servicio del usuario si aplica | API de tipos de cambio: proyecto/clave y cobro del usuario. |
| 128 | [`wp-gift-cards`](../plugins/wp-gift-cards/) | Gratis; local | Función local en WordPress/hosting del usuario; no se identifica API externa necesaria en la ficha. |
| 129 | [`wp-shipment-tracking`](../plugins/wp-shipment-tracking/) | Gratis; servicio del usuario si aplica | Transportistas: cuenta del comercio y tarifas vigentes. |
| 130 | [`wp-google-shopping`](../plugins/wp-google-shopping/) | Gratis; servicio del usuario si aplica | Google Merchant API: proyecto/credenciales del comercio. |
| 131 | [`wp-age-gate`](../plugins/wp-age-gate/) | Gratis; servicio del usuario si aplica | Verificación de identidad/edad avanzada, si se contrata, paga el sitio. |

## Fuentes oficiales de referencia (solo modelos de facturación, no auditoría exhaustiva)

- Meta WhatsApp Business: https://www.whatsapp.com/legal/meta-terms-whatsapp-business — cargos por uso y cuenta de negocio.
- Google Maps: https://developers.google.com/maps/get-started — el proyecto de cliente requiere facturación y credenciales.
- Cloudflare R2: https://developers.cloudflare.com/r2/pricing/ — almacenamiento/operaciones se cobran a la cuenta titular.
- WordPress GPL: https://wordpress.org/about/license/ — derechos sobre código y distribución separados de facturación de servicios.

**Conteo de fichas con posible integración externa explícita en esta evaluación:** 60. Las otras 71 no tienen en su finalidad un servicio remoto que haya sido identificado como requisito; esto no sustituye revisión de implementación y dependencias.
