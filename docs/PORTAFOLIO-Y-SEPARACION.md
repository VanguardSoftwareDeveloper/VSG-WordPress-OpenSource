# Portafolio WordPress — política gratuita y separación condicionada por gastos reales

**Decisión del propietario:** 22 de noviembre de 2026. **Política vigente: gratuito por defecto.**

El desarrollador dona voluntariamente su tiempo de programación y mantenimiento. **No se asignará precio por la complejidad, duración de desarrollo, coste de oportunidad, mantenimiento voluntario, competencia, posible demanda ni tamaño del producto.** Solo se contempla un cobro para cubrir un **gasto externo real** de API, infraestructura o servicio que **recaiga en la cuenta del desarrollador**.

## Resultado de evaluación del catálogo

| Grupo | Candidatos | Criterio |
|---|---:|---|
| **Gratuitos** | **131** | Funcionalidad local bajo hosting del usuario, o integración externa con cuenta y facturación a cargo del usuario |
| **De pago obligatorio** | **0 demostrados** | No se ha confirmado ningún servicio/proveedor que obligue a Vanguard a pagar por la categoría descrita |
| **Servicios administrados opcionales** | **Sin productos ni precios aprobados** | Se podrán cobrar únicamente si Vanguard decide financiar un consumo de proveedor/infraestructura en su propia cuenta |

Estas cifras son una **clasificación de diseño**, no una certificación económica de 131 implementaciones. Solo `wp-peru-commerce` contiene fuente; su POS sigue pendiente de implementación y verificación. Los otros 130 nombres son fichas, no plugins disponibles. Los casos de solapamiento y bajo valor pueden mantenerse sin implementar aunque sean gratuitos.

## Reglas de costos para cada plugin

1. **Gratis local:** se ejecuta con WordPress/PHP/base de datos/almacenamiento del sitio del usuario y no utiliza servicios de pago proporcionados por Vanguard.
2. **Gratis con API del usuario (BYOK):** si WhatsApp, Google Maps, Mercado Pago, correo, SMS, almacenamiento de objetos, geocodificación, IA, etc. generan costos, cada comerciante configura y financia **su propia cuenta**. El plugin sigue siendo gratuito. Verificar con el proveedor si esto realmente es posible y qué permisos exige.
3. **Servicio opcional de pago del fabricante:** solo si se presta un servicio centralizado que paga Vanguard (por ejemplo, proxy de API, almacenamiento/entrega de datos, envíos financiados por Vanguard). El costo y el precio deben ser transparentes, medibles, con límites y sin habilitarse por defecto.
4. **Si la API impone costos a Vanguard y no admite facturación por cliente**, buscar primero una integración directa o alternativa sin costo propio. Si eso no resuelve el caso, evaluar un **servicio comercial específico**, no convertir automáticamente en pago todo el plugin.
5. El **tiempo de desarrollo y mantenimiento voluntario nunca es costo de clasificación** conforme a la decisión actual.
6. «Plugin gratis» no equivale a «todos los proveedores gratis»: documentar costos del cliente con transparencia antes de activar una funcionalidad.
7. Nunca compartir una misma API key personal de Vanguard para que terceros generen consumos no limitados. Secrets/tokens del usuario solo en backend seguro, con controles de permisos, límites y desvinculación.

## Arquitectura de distribución

**Publicación segura:** las 131 fichas se pueden presentar como catálogo público documental independiente, pero **no** mediante un cambio de visibilidad de este monorepositorio ni copiando su historial. Ver [puertas y exportación permitida](PUBLICACION-SEGURA.md); PeruCommerce tendrá su propio repositorio de código después de POS/IMP-72. Esta clasificación económica no es una autorización de release.


- Mantener hoy `Vanguard-WordPress-Plugins` **privado** como repositorio de trabajo. Las 131 carpetas siguen ahí sin mover ni duplicar códigos todavía.
- **Futura fuente pública:** publicar únicamente plugins completos, verificados, licenciados y auditados. PeruCommerce conservará su repositorio público propio según [su adenda](../plugins/wp-peru-commerce/docs/47-POS-INTEGRADO-Y-APERTURA-PUBLICA.md), no se mezcla con el resto.
- **Grupo comercial:** sin candidatos reales aún, por tanto no es necesario abrir un repositorio comercial vacío. Si surge un servicio pagado por Vanguard, documentar infraestructura y contrato de cobro antes de separar fuentes o vender servicios.
- Los plugins futuros gratuitos pueden distribuirse en un repositorio colectivo `Vanguard-WordPress-Free` si resulta conveniente; eso **no** autoriza publicar el monorepositorio ni exponer historias importadas, secretos o datos de otros productos.
- La clasificación económica **no modifica** licencias preexistentes, versiones, permisos, alcance del plugin, contratos ni código.

## Inventarios enlazados

- [131 categorías gratuitas y su costo externo potencial](PORTAFOLIO-GRATUITOS.md).
- [Casos que justificarían pagar solo por un servicio propio del fabricante; 0 productos de pago confirmados](PORTAFOLIO-COMERCIALES.md).
- [Catálogo original y estado de implementación](CATALOGO.md).

## Referencias externas para revisar por integración

- [Meta WhatsApp Business — términos de facturación](https://www.whatsapp.com/legal/meta-terms-whatsapp-business).
- [Google Maps — proyectos, claves y facturación](https://developers.google.com/maps/get-started).
- [Cloudflare R2 — tarifas de almacenamiento/operaciones](https://developers.cloudflare.com/r2/pricing/).
- [WordPress — licencia y derechos del código](https://wordpress.org/about/license/).

**Pendiente antes de cualquier integración específica:** verificar con documentación oficial vigente, país y modelo de cuenta si la API permite al negocio ser titular/facturado directamente, sus límites y los requisitos de seguridad. No confundir esta evaluación con pruebas ni contratación real.
