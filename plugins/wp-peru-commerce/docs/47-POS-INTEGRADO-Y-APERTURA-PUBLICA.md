# PeruCommerce — POS integrado y preparación de publicación pública

**Decisión del propietario: 2026-11-22. Estado: ampliación de alcance autorizada; POS NO IMPLEMENTADO; publicación pública NO REALIZADA.**

Esta adenda posterior a D-01…D-200 prevalece únicamente respecto de dos decisiones anteriores: el POS deja de estar excluido del producto objetivo y el código de PeruCommerce se destinará a un repositorio público independiente cuando esté preparado. No modifica por inferencia los contratos actuales de compra, dinero, fiscalidad, derechos, roles, datos o integraciones. Los registros y cierres IMP-01…IMP-71 permanecen históricos, exclusivamente estáticos y **no incluyen** un POS.

## 1. Decisiones confirmadas

- **PeruCommerce seguirá siendo gratuito** y su código será público. La licencia declarada en su fuente es **GPL-2.0-or-later**; antes de distribuir se debe verificar la licencia y las atribuciones efectivamente incorporadas al paquete.
- **El POS será una funcionalidad integrada del mismo plugin PeruCommerce**, sin instalar por defecto WooCommerce, otro plugin POS ni utilizar el POS comercial privado VSG como dependencia.
- **No publicar el monorepositorio** `Vanguard-WordPress-Plugins`: contiene otros 130 candidatos independientes. El destino es un repositorio propio y público de PeruCommerce, con un solo origen canónico de futuras modificaciones, no dos copias de código divergentes.
- **Completar, revisar y validar el POS y las pendientes relevantes antes de declarar el primer paquete público apto para uso.** Un repositorio visible para colaboración y una versión estable instalable son estados distintos; la visibilidad no certifica seguridad ni funcionalidad.
- Mantener un comercio y un local principal por instalación, productos/catálogo/stock/pedidos existentes, PEN en céntimos enteros, WordPress/PHP como autoridad y Angular para la interfaz. Preservar las obligaciones legales peruanas condicionales al comerciante.

## 2. Propuesta de POS V1 mínimo (NO ratificada como contrato ni implementada)

Para evitar duplicar los mecanismos de comercio actuales, evaluar este corte funcional:

1. **Caja presencial conectada**, dentro del panel de WordPress, para administrador y operador autorizado. Búsqueda por nombre/SKU y lector de códigos de barras que funcione como teclado; interfaz eficiente en escritorio y móvil. Sin modo offline ni SaaS obligatorio en la primera entrega.
2. **Carrito de venta presencial** de productos físicos y variantes publicadas, con cantidades, descuentos únicamente si ya existen como política y total calculado por servidor. Digitales y citas requieren un diseño específico de entrega/contacto y ocupación; nunca omitir las restricciones del checkout existente.
3. **Cobro en efectivo presencial** registrado de forma verificable y auditada al completar una venta, con importe entregado, cambio y monto neto. No equiparar el importe entregado con ingreso, ni generar crédito en Mercado Pago desde un clic del navegador. Otros medios presenciales requieren contrato y validación propios.
4. **Sesión de caja** con apertura, saldo inicial, ventas, retiros/ingresos justificados si se autorizan, arqueo y cierre por actor/terminal; definir la semántica de turno, concurrencia y ajustes antes del DDL. No mezclar el efectivo ya registrado de pedidos web con un turno de mostrador sin vínculo explícito.
5. **Ticket de operación imprimible** desde navegador, claramente diferenciado de un comprobante tributario. La emisión SUNAT sigue siendo externa y condicional al régimen, salvo futura decisión e integración verificada.
6. **Historial y correcciones** reutilizando pedidos, movimientos de stock, ledger financiero, permisos, evidencia y ajustes existentes. Anulaciones/devoluciones respetan elegibilidad, derechos y trazabilidad originales: no borrar ventas o modificar cobros históricos.

### Invariantes no negociables

- Una venta presencial debe integrar **un único pedido** y un único conjunto coherente de movimientos de stock/dinero/eventos; no construir una base de ventas POS paralela a pedidos de ecommerce.
- Precio y stock del servidor, capacidad por recurso/rol, autenticación WordPress y nonce contra CSRF para staff; aislamiento por instalación y mínimo dato. El navegador no valida pagos ni decide disponibilidad.
- Reintentos deben ser idempotentes, incluso tras timeout/COMMIT ambiguo. Bloqueos InnoDB y control de versiones evitan doble venta de la última unidad frente a checkout online u otra caja.
- La operación de completar una venta debe ser atómica en sus efectos locales. Un error deja evidencia recuperable y no un falso éxito; los informes de caja derivan de eventos durables.
- Sin acceso público a endpoints de caja, ni credenciales, PII, logs, binarios privados o datos reales en la publicación. No habilitar el nuevo módulo por defecto hasta cumplir las puertas P-C/P-D que apliquen.
- El POS debe funcionar sin el plugin candidato independiente `plugins/wp-wordpress-pos`, que hoy solo tiene una ficha documental; no confundir ambos productos.

## 3. Orden de ingeniería antes de la publicación

| Paso | Entregable | Estado al 2026-11-22 |
| --- | --- | --- |
| P1 | Ratificar corte del POS V1, operaciones, roles, caja y efectos sobre pedidos web | PENDIENTE; esta adenda incluye propuesta |
| P2 | Extender contratos REST/OpenAPI, trazabilidad PER, estados, permisos, DDL/migración y criterios de aceptación solo para las operaciones realmente necesarias | PENDIENTE |
| P3 | Implementar backend transaccional POS reutilizando dominio y endpoints existentes donde sea correcto, sin inventar estados ni atajos de caja | PENDIENTE |
| P4 | Implementar superficies Angular POS para escritorio/móvil, impresión y manejo de fallos/reintentos | PENDIENTE |
| P5 | Revisar estáticamente consumidores, esquema, migraciones, permisos, stock, dinero, redirecciones, scripts y documentación directamente afectados | PENDIENTE |
| P6 | **Con autorización específica de ejecución:** compilar, realizar pruebas de integración/concurrencia/caja, verificar WordPress/PHP/Angular, dependencias, UX/accesibilidad, y resolver IMP-72 y puertas P-C/P-D aplicables | NO AUTORIZADO NI EJECUTADO AQUÍ |
| P7 | Auditar fuentes GPL, dependencias, avisos, assets/licencias, fuentes del frontend, secretos e historial, y preparar ZIP + paquete fuente correspondiente | PENDIENTE |
| P8 | Separar un repositorio canónico limpio de PeruCommerce, verificar que contenga solo sus archivos autorizados, hacer pública su fuente y adaptar referencias del catálogo privado sin duplicar el mantenimiento | NO REALIZADO |

### Escenarios mínimos de aceptación POS (definidos, NO ejecutados)

- Dos cajeros o una caja y el checkout online disputan la última unidad: como máximo una venta compromete el stock.
- Se pierde la respuesta después de confirmar el pago en efectivo: el reintento no duplica pedido, dinero, ticket contable ni decremento de stock.
- Fallan COMMIT, cierre/arqueo o permisos: no aparece una caja cerrada falsamente ni cambia saldo sin evento.
- Operador no puede cambiar saldos iniciales ajenos, realizar correcciones financieras reservadas a administrador ni leer datos restringidos.
- Precio, descuento, cantidad, cambio y total se recalculan en servidor; el ticket refleja el pedido confirmado y nunca dice que SUNAT emitió un comprobante sin evidencia.
- Ventas POS y ventas web mantienen existencias y movimientos consistentes tras ajustes y migración.

## 4. Publicación, licencias y seguridad

La fuente actual declara `GPL-2.0-or-later` y contiene `LICENSE`, pero **no consta una build, lockfile resuelto, ZIP ni auditoría ejecutada**. La publicación debe usar un **repositorio aislado con origen e historial revisados**: no cambiar visibilidad del monorepositorio y no copiar automáticamente un historial que incluya materiales de otros productos o secretos. Si existieran credenciales comprometidas, revocarlas/rotarlas antes de exponer nada. La revisión de texto actual no cubre un historial Git completo.

Separar: **(a) fuente pública de desarrollo**; **(b) release instalable**; **(c) habilitación real de caja/cobros por instalación**. La elección de hacer pública la fuente no otorga por sí misma aprobación del programa Claude for Open Source.

## 5. Continuidad

- Fuente actualmente revisada: `VanguardSoftwareDeveloper/Vanguard-WordPress-Plugins`, `plugins/wp-peru-commerce/`, rama `main`.
- Consultar antes de implementar `AGENTS.md`, `docs/01`, `docs/43`, OpenAPI `0.12.0-draft`, `includes/orders-*`, `includes/checkout-*`, `includes/inventory-*`, `includes/cash-payments*`, `includes/financial-events.php`, `includes/transaction*` y las superficies Angular administrativas.
- **No iniciar IMP-72 como cierre final sin incorporar y revisar el alcance POS**, si el objetivo es publicar la V1 con POS. Nunca convertir esta propuesta en un falso cierre de implementación o validación.
