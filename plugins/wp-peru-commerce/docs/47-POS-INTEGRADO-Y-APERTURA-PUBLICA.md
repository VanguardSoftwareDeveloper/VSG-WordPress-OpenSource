# PeruCommerce — POS integrado y preparación de publicación pública

**Decisión del propietario: 2026-11-22. Estado: ampliación de alcance autorizada; POS NO IMPLEMENTADO; publicación pública NO REALIZADA.**

**Ratificación P1 (2026-11-27):** el propietario ratificó el alcance POS V1 y las decisiones D1–D14 con precisiones obligatorias, registradas como D-201…D-210. El contrato P2 está **DOCUMENTADO/NO IMPLEMENTADO** en [docs/48](48-CONTRATO-POS-V1-P2.md) y en OpenAPI `0.13.0-draft`. Donde esta adenda y docs/48 difieran, prevalece docs/48.

**Nota de ubicación (2026-11-26):** la fuente se consolidó en el repositorio público canónico `VSG-WordPress-OpenSource` ([registro](../../../docs/SOURCE-CONSOLIDATION.md)). Las referencias a `Vanguard-WordPress-Plugins` y a un repositorio propio de PeruCommerce describen la decisión de 2026-11-22 y quedan superadas solo en cuanto a ubicación. «Publicación pública NO REALIZADA» se refiere al paquete distribuible: no existe release, build ni ZIP verificados.

Esta adenda posterior a D-01…D-200 prevalece únicamente respecto de dos decisiones anteriores: el POS deja de estar excluido del producto objetivo y el código de PeruCommerce se destinará a un repositorio público independiente cuando esté preparado. No modifica por inferencia los contratos actuales de compra, dinero, fiscalidad, derechos, roles, datos o integraciones. Los registros y cierres IMP-01…IMP-71 permanecen históricos, exclusivamente estáticos y **no incluyen** un POS. Las decisiones D-201…D-210 (docs/48) concretan el POS sin alterar los contratos del canal web.

## 1. Decisiones confirmadas

- **PeruCommerce seguirá siendo gratuito** y su código será público. La licencia declarada en su fuente es **GPL-2.0-or-later**; antes de distribuir se debe verificar la licencia y las atribuciones efectivamente incorporadas al paquete.
- **El POS será una funcionalidad integrada del mismo plugin PeruCommerce**, sin instalar por defecto WooCommerce, otro plugin POS ni utilizar el POS comercial privado VSG como dependencia.
- **No publicar el monorepositorio** `Vanguard-WordPress-Plugins`: contiene otros 130 candidatos independientes. El destino es un repositorio propio y público de PeruCommerce, con un solo origen canónico de futuras modificaciones, no dos copias de código divergentes.
- **Completar, revisar y validar el POS y las pendientes relevantes antes de declarar el primer paquete público apto para uso.** Un repositorio visible para colaboración y una versión estable instalable son estados distintos; la visibilidad no certifica seguridad ni funcionalidad.
- Mantener un comercio y un local principal por instalación, productos/catálogo/stock/pedidos existentes, PEN en céntimos enteros, WordPress/PHP como autoridad y Angular para la interfaz. Preservar las obligaciones legales peruanas condicionales al comerciante.

## 2. POS V1 mínimo (ratificado el 2026-11-27; NO implementado)

Corte funcional ratificado (D-201…D-207). El contrato detallado está en [docs/48](48-CONTRATO-POS-V1-P2.md); este resumen conserva la propuesta original con las precisiones ratificadas:

1. **Caja presencial conectada**, dentro del panel de WordPress, para administrador y operador autorizado. Búsqueda por nombre/SKU y lector de códigos de barras que funcione como teclado; interfaz eficiente en escritorio y móvil. Sin modo offline ni SaaS obligatorio en la primera entrega.
2. **Carrito de venta presencial** de productos físicos y variantes publicadas, con cantidades y total calculado por servidor. **Sin descuentos en V1.** Digitales, servicios y citas quedan excluidos del POS V1 (D-205); nunca omitir las restricciones del checkout existente.
3. **Cobro en efectivo presencial** registrado de forma verificable y auditada al completar una venta. El importe entregado y el vuelto son evidencia, no ingreso; el redondeo a favor del consumidor separa importe original, ajuste y efectivo recibido (D-207). No generar crédito en Mercado Pago desde un clic del navegador. Otros medios presenciales quedan excluidos de V1 y requieren contrato y validación propios.
4. **Sesión de caja** con apertura, saldo inicial, ventas, ingresos y retiros justificados, arqueo y cierre por actor y caja (D-203). El efectivo de pedidos web solo entra en una sesión con vínculo explícito y real; nunca se asigna automáticamente.
5. **Constancia de operación imprimible** desde navegador, con la leyenda «Este documento no es un comprobante de pago». La emisión SUNAT sigue siendo externa y condicional al régimen; el POS guía su emisión externa y registra su referencia (D-206).
6. **Historial y correcciones** reutilizando pedidos, movimientos de stock, ledger financiero, permisos, evidencia y ajustes existentes. Errores, anulaciones, reembolsos y devoluciones siguen los procedimientos de D-205, incluida la devolución posterior a la entrega R8-83: no se borran ventas ni se modifican cobros históricos.

### Invariantes no negociables

- Una venta presencial debe integrar **un único pedido** y un único conjunto coherente de movimientos de stock/dinero/eventos; no construir una base de ventas POS paralela a pedidos de ecommerce.
- Precio y stock del servidor, capacidad por recurso/rol, autenticación WordPress y nonce contra CSRF para staff; aislamiento por instalación y mínimo dato. El navegador no valida pagos ni decide disponibilidad.
- Reintentos deben ser idempotentes, incluso tras timeout/COMMIT ambiguo. Bloqueos InnoDB y control de versiones evitan doble venta de la última unidad frente a checkout online u otra caja.
- La operación de completar una venta debe ser atómica en sus efectos locales. Un error deja evidencia recuperable y no un falso éxito; los informes de caja derivan de eventos durables.
- Sin acceso público a endpoints de caja, ni credenciales, PII, logs, binarios privados o datos reales en la publicación. No habilitar el nuevo módulo por defecto hasta cumplir las puertas P-C/P-D que apliquen.
- El POS debe funcionar sin el plugin candidato independiente `plugins/wp-wordpress-pos`, que hoy solo tiene una ficha documental; no confundir ambos productos.

## 3. Orden de ingeniería antes de la publicación

| Paso | Entregable | Estado al 2026-11-27 |
| --- | --- | --- |
| P1 | Ratificar corte del POS V1, operaciones, roles, caja y efectos sobre pedidos web | RATIFICADO el 2026-11-27 (D-201), con precisiones del propietario |
| P1b | Resolver contractualmente el redondeo del efectivo | RESUELTO CONTRACTUALMENTE (D-207); validación contable antes de P-D |
| P2 | Extender contratos REST/OpenAPI, trazabilidad PER, estados, permisos, DDL/migración y criterios de aceptación solo para las operaciones realmente necesarias | DOCUMENTADO/NO IMPLEMENTADO ([docs/48](48-CONTRATO-POS-V1-P2.md), OpenAPI `0.13.0-draft`) |
| P3 | Implementar backend transaccional POS reutilizando dominio y endpoints existentes donde sea correcto, sin inventar estados ni atajos de caja | PENDIENTE |
| P4 | Implementar superficies Angular POS para escritorio/móvil, impresión y manejo de fallos/reintentos | PENDIENTE |
| P5 | Revisar estáticamente consumidores, esquema, migraciones, permisos, stock, dinero, redirecciones, scripts y documentación directamente afectados | PENDIENTE |
| P6 | **Con autorización específica de ejecución:** compilar, realizar pruebas de integración/concurrencia/caja, verificar WordPress/PHP/Angular, dependencias, UX/accesibilidad, y resolver IMP-72 y puertas P-C/P-D aplicables | NO AUTORIZADO NI EJECUTADO AQUÍ |
| P7 | Auditar fuentes GPL, dependencias, avisos, assets/licencias, fuentes del frontend, secretos e historial, y preparar ZIP + paquete fuente correspondiente | PENDIENTE |
| P8 | Separar un repositorio canónico limpio de PeruCommerce, verificar que contenga solo sus archivos autorizados, hacer pública su fuente y adaptar referencias del catálogo privado sin duplicar el mantenimiento | NO REALIZADO |

### Escenarios mínimos de aceptación POS (definidos, NO ejecutados)

- Dos cajeros o una caja y el checkout online disputan la última unidad: como máximo una venta compromete el stock.
- Se pierde la respuesta después de confirmar el pago en efectivo: el reintento no duplica pedido, dinero, constancia de operación ni decremento de stock.
- Fallan COMMIT, cierre/arqueo o permisos: no aparece una caja cerrada falsamente ni cambia saldo sin evento.
- Operador no puede cambiar saldos iniciales ajenos, realizar correcciones financieras reservadas a administrador ni leer datos restringidos.
- Precio, cantidad, redondeo, vuelto y total se recalculan en servidor; la constancia refleja el pedido confirmado y nunca dice que SUNAT emitió un comprobante sin evidencia.
- Ventas POS y ventas web mantienen existencias y movimientos consistentes tras ajustes y migración.

## 4. Publicación, licencias y seguridad

La fuente actual declara `GPL-2.0-or-later` y contiene `LICENSE`, pero **no consta una build, lockfile resuelto, ZIP ni auditoría ejecutada**. La publicación debe usar un **repositorio aislado con origen e historial revisados**: no cambiar visibilidad del monorepositorio y no copiar automáticamente un historial que incluya materiales de otros productos o secretos. Si existieran credenciales comprometidas, revocarlas/rotarlas antes de exponer nada. La revisión de texto actual no cubre un historial Git completo.

Separar: **(a) fuente pública de desarrollo**; **(b) release instalable**; **(c) habilitación real de caja/cobros por instalación**. La elección de hacer pública la fuente no otorga por sí misma aprobación del programa Claude for Open Source.

## 5. Continuidad

- Fuente actualmente revisada: `VanguardSoftwareDeveloper/VSG-WordPress-OpenSource`, `plugins/wp-peru-commerce/`, rama `main`.
- Consultar antes de implementar `AGENTS.md`, `docs/01`, `docs/43`, `docs/48`, OpenAPI `0.13.0-draft`, `includes/orders-*`, `includes/checkout-*`, `includes/inventory-*`, `includes/cash-payments*`, `includes/financial-events.php`, `includes/transaction*` y las superficies Angular administrativas.
- **No iniciar IMP-72 como cierre final sin incorporar y revisar el alcance POS**, si el objetivo es publicar la V1 con POS. Nunca convertir esta propuesta en un falso cierre de implementación o validación.
