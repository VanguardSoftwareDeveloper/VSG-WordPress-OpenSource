# Grupo de pago — sin plugins obligatoriamente comerciales demostrados

**Decisión del propietario, 2026-11-22:** no contabilizar programación, tiempo, dificultad, mantenimiento o soporte voluntario como motivo para cobrar. El plugin base será gratuito incluso si es grande o complejo. Solo cobrar si **Vanguard debe pagar de su bolsillo** un servicio, API, infraestructura o consumo externo que haga funcionar una prestación del cliente.

## Estado del portafolio

**0 de 131 categorías asignadas a precio obligatorio.** Los 76 candidatos que anteriormente aparecieron como «comerciales» por dificultad, riesgo, soporte o competencia **se reclasificaron gratuitamente**. Su clasificación antigua no se debe utilizar para tomar decisiones. Véase el [inventario gratuito de las 131 categorías](PORTAFOLIO-GRATUITOS.md).

Ningún proveedor ha sido contratado o facturado por esta clasificación; no hay gastos reales comprobados que permitan fijar un precio o señalar una categoría como obligatoriamente de pago.

## Cuándo existirá una versión o servicio de pago

| Supuesto | Cómo mantener gratuito el plugin | Qué podría cobrarse si lo paga Vanguard |
|---|---|---|
| WhatsApp, SMS, email masivo, notificaciones | Cuenta del comercio, cuotas abonadas directamente al proveedor | Envíos a través de cuenta financiada por Vanguard |
| Google Maps, geocodificación, traducción, IA | Proyecto/API key y facturación del comercio | Consumo de una API central cuya factura pague Vanguard |
| Copias externas, almacenamiento, CDN, optimización | Bucket, servidor o herramienta contratada por el titular del sitio | GB almacenados/procesados y transferencias pagadas por Vanguard |
| Analítica, heatmaps, monitoreo, chat en tiempo real | Ejecución y retención en el hosting del usuario | Ingesta, procesamiento, almacenamiento y servicio alojado por Vanguard |
| Antivirus/inteligencia de amenazas y otras suscripciones a datos | Reglas locales o licencia con cuenta de usuario cuando el proveedor lo permita | Suscripción a feeds/servicios que deba pagar Vanguard |
| Pasarelas, logística, integraciones de países | Credenciales y contratos del negocio | Infraestructura o tarifa específica si el proveedor obliga a que Vanguard sea intermediario facturado |

**Regla:** si el cliente puede pagar al proveedor directamente, habilitar esa opción antes de crear un cobro por intermediación. No prometer APIs gratuitas: el **plugin** es gratis, pero un proveedor puede cobrar al comercio. Donde la integración no admita cuentas de clientes o requiera facturación central, documentar su evidencia antes de decidir un servicio administrado.

## Condiciones para abrir un producto comercial

1. Confirmar proveedor, servicio concreto, tarifa real, país, quién figura como titular/pagador, límites, condiciones y mínimos mensuales.
2. Demostrar por qué no se puede usar el hosting o cuenta propia del cliente, ni alternativa gratuita viable.
3. Separar **plugin base gratuito** y **servicio opcional** que tú financias: mostrar consumo y gastos, cuota justa y límites previsibles, evitar subsidios inadvertidos.
4. No activar un servicio en una cuenta pagada por Vanguard sin aceptación, límites y control de consumo; considerar cuotas, desconexión y medidas antifraude.
5. Documentar licencias y derechos GPL del código incluso cuando se cobre un servicio; no usar cargos de API como justificación para bloquear código abierto distribuido.

## Organización de repositorios

- **Futuro repositorio público `Vanguard-WordPress-Free`**: fuente de plugins preparados para distribución pública, en los que cada componente conserva licencia y releases propios.
- **`Vanguard-WordPress-Premium`**: **sin productos asignados**; no es necesario crear repo vacío ni mover 131 fichas de planificación a dos destinos.
- Mantener `Vanguard-WordPress-Plugins` **privado como entorno de desarrollo** hasta que la fuente de cada plugin esté revisada, sin credenciales, datos ni historial ajeno. No publicar el monorepositorio íntegro.
- PeruCommerce se publicará en repo propio cuando esté preparado, de acuerdo con su adenda POS; no mezclar su código con otros 130 candidatos por comodidad.

**Esto es clasificación económica, no publicación.** No se modificaron pasarelas, licencias de productos, código ejecutable ni instalaciones.
