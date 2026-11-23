# E-04 — Contrato monetario de precios, cotización, delivery, ajustes y devoluciones V1

**Estado: E-04 APROBADO COMO POLÍTICA COMERCIAL/NUMÉRICA D-96…D-108.** E-05 aprobado D-109…D-122 y **E-06 aprobado D-123…D-134** (doc.19). **Pendientes** J-03/P0-17/P0-25, E-07/E-08 y SQL/hosting. Revisión documental 2026-11-17. No certifica impuestos, entrega real de email ni integración MP.
**No autoriza código, pruebas, migraciones, conexión de cuentas, pasarela real ni despliegue.**
**Propósito:** evitar que Codex decida representaciones numéricas, redondeos, tarifas de entrega, cobros, conciliaciones, importes de cancelación o tratamiento tributario sin contrato.

Fuentes de producto: decisiones **D-01…D-95** en [01-DECISIONES-Y-ESTADO.md](01-DECISIONES-Y-ESTADO.md), E-01 [14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md](14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md), E-02 [15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md](15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md), E-03 [16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md](16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md). La matriz fiscal de [07-CUMPLIMIENTO-PERU.md](07-CUMPLIMIENTO-PERU.md) permanece parcialmente pendiente.

**Investigación tributaria posterior (2026-11-17):** `docs/26-CONTRATO-TRIBUTARIO-P0-17-J03-V1.md` documenta regímenes, comprobantes externos y tasa conjunta SUNAT, mientras `docs/25-MATRICES-NORMATIVAS-J01-J05-P0-25-V1.md` registra obligaciones por supuesto. **La V1 no calcula bases imponibles, IGV desglosado, redondeos fiscales, retenciones, CPE ni notas de crédito**; conservar dinero comercial en enteros de céntimos y no sumar impuestos universales. No confundir esta investigación con validación por emisor/actividad.

**Nota de precedencia vigente (18-11-2026):** las reglas funcionales E-01…E-07 aprobadas en este documento se conservan. Los textos anteriores que presentan E-08, DTO, capabilities, TTL o cobertura de expedientes como *pendientes de decisión* son **históricos**, no una segunda fuente de contratos: prevalecen `docs/01` D-01…D-198, `docs/41/42`, OpenAPI **0.10.0-draft (67 REST/64 rutas/134 DTO)** y `docs/23/24` (39 tablas, 56 PER). Las comprobaciones de motor, proveedor, hosting, normativa por comercio y release pertenecen a P-C/P-D; no se presentan como verificadas. El usuario autorizó aquí **documentación**, no código ni ejecución.

## 1. Invariantes confirmados que no pueden reinterpretarse

1. **PEN exclusiva** y precio final visible antes de confirmar; nada de multimoneda (D-45/D-46). El comerciante escribe el precio **final** y no un importe neto para que un motor fiscal aplique IGV universalmente (D-49).
2. Artículo simple con precio propio; cada variante vendible con precio/oferta **explícitos**, independientes y vigentes; la oferta es manual, sin cupones ni programación (D-41/D-50/D-52/D-57).
3. **Un cobro inicial por pedido**, no por artículo; contraentrega solo cuando todo puede cobrarse presencialmente **en un mismo evento** (D-21/D-33).
4. El backend calcula y valida totales y disponibilidad; un pedido confirmado conserva precio, línea, cantidad y tarifa **pactados en snapshots inmutables**. Correcciones posteriores son eventos de ajuste, jamás edición destructiva (D-59).
5. Cancelación ordinaria por **línea completa**; petición por algunas unidades se atiende en expediente/ajuste administrativo (D-56/D-95). La operación no cancela líneas restantes.
6. Si se cancela última línea física antes de despacho, revisar cargo de delivery, servicio/costo efectivamente prestado y derechos del comprador (D-63); no fijar descuento, penalidad ni tarifa universal sin contrato.
7. Cambio de dirección confirmada se propone/autoriza según D-65/D-83: no reescribir original; validar cobertura y diferencia de tarifa, administrador ante costo/cobertura cambiados o dinero acreditado.
8. Operador registra efectivo **realmente recibido** con auditoría; solo administrador corrige el cobro mediante ajuste inmutable (D-51/D-53).
9. **El plugin no ejecuta reembolsos** con Mercado Pago ni otros medios. Solo administrador registra un **resultado externo comprobado**, total o parcial, con importe/referencia y evidencia (D-54/D-66).
10. Las comisiones del proveedor, pagos pendientes, devoluciones, contracargos y cumplimiento físico/digital/servicio son hechos **diferentes**; un resultado de devolución no borra el pago inicial (D-55/D-59/D-68).
11. **D-64:** el operador jamás cancela una línea si en el pedido existió dinero acreditado, incluso cuando el saldo neto posterior sea cero; los cálculos no alteran esta prohibición.
12. Las obligaciones fiscales, documentos externos y remedios legales dependen del régimen, operación y sector, no de una tarifa impositiva universal ni de si el comprador aceptó un término inválido.

## 2. Documentación oficial consultada y consecuencias

| Ref | Referencia oficial | Hecho y efecto de diseño | Límite |
| --- | --- | --- | --- |
| M-01 | Mercado Pago Checkout Pro Orders: creación/gestión de order | El campo **total_amount** es string decimal; debe coincidir con **suma de unit_price × quantity** de los ítems enviados; se contemplan cero o dos decimales. Se reportan errores si importes no coinciden o total es cero. | No implica que los cargos de entrega deban modelarse como producto ficticio ni que el proveedor acepte cualquier carrito sin revisar versión de la integración |
| M-02 | Mercado Pago reportes: campos TRANSACTION_AMOUNT, SETTLEMENT_NET_AMOUNT, FEE_AMOUNT, TRANSACTION_CURRENCY | El importe bruto de la compra, el neto recibido por comercio y las comisiones **son magnitudes distintas**; PEN figura como moneda soportada en datos del proveedor. | No asume una comisión fija ni agrega comisión al comprador sin información/obligación aplicable |
| M-03 | SUNAT: regímenes NRUS/RER/RMT/General | Los documentos y reglas tributarias varían por régimen; **NRUS no emite facturas** que dan derecho a crédito fiscal. | No derivar tasa IGV, base imponible ni obligación concreta a partir del nombre comercial del plugin |
| M-04 | SUNAT: nota de crédito electrónica | Puede vincular anulación, descuento y devolución con factura o boleta anterior, según reglas del documento. | No equivale a devolución de dinero ni se emite desde PeruCommerce V1 |
| M-05 | Política tributaria confirmada D-04/D-26/D-46/D-49 | PeruCommerce registra datos/referencias del comprobante externo, por régimen y operación. | El cálculo fiscal y validez del documento no se verifican sin la matriz P0-25 |

Fuentes oficiales:
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/create-order
- https://www.mercadopago.com.pe/developers/es/reference/online-payments/checkout-pro/create-order/post
- https://www.mercadopago.com.pe/developers/es/docs/checkout-api-payments/additional-content/reports/account-money/report-fields
- https://www.mercadopago.com.pe/developers/es/docs/checkout-api-orders/resources/reports/account-money/report-fields
- https://emprender.sunat.gob.pe/ruc/regimenes-tributarios-mype/nuevo-regimen-unico-simplificado-nuevo-rus
- https://www.gob.pe/institucion/sunat/pages/280-regimenes-tributarios
- https://cpe.sunat.gob.pe/tipos_de_comprobantes/nota_de_credito
- https://www.sunat.gob.pe/padronesnotificaciones/padronObligados/m-PadronObligados-010626.html

**Estado documental:** verificado como referencia pública a fecha de corte; la modalidad efectiva de integración y normativa vigente al desplegar deberán confirmarse. La distinción entre precio total al cliente y comprobante SUNAT **no** queda fiscalmente cerrada por estas referencias.

## 3. Unidades, precisión y validación: política comercial APROBADA

**E04-A APROBADO (D-96):** representar todos los importes internos pactados en **céntimos PEN mediante enteros**; el formato S/ 12.34 es de entrada/salida y no un float binario para sumar dinero. El motor de cotización y las sumas operan con enteros y verificación de desbordamientos. La API financiera adaptará valores a decimal string/number únicamente conforme al proveedor y su contrato E-08. Alternativa: tipo decimal exacto con escala 2 end-to-end.

**E04-B APROBADO (D-97):** precios unitarios y ofertas con **máximo dos decimales**; exigir precio efectivo **mayor que cero** para cualquier línea vendible que vaya a pago V1, y precios/cantidades nunca negativos; cantidades de venta **enteras positivas**, sin cantidad fraccionaria por peso/medida. La oferta manual aplicada no puede superar al precio regular; si es inválida, impedir publicación/cobro con esa oferta en vez de corregir silenciosamente. Alternativa: permitir precios cero/fracciones con rutas especiales de cumplimiento y validación de proveedor.

**E04-C APROBADO (D-98):** con precio unitario y tarifa ya expresados en céntimos, **subtotal de línea = cantidad entera × precio final vigente** sin nuevo redondeo. Total de pedido = suma de subtotales de líneas + **un** cargo de entrega aplicable + otros cargos **solo si existen y fueron aprobados/informados**. No añadir IGV genérico a un precio final ya declarado. Si E-04/P0-25 permiten más adelante un desglose de base e impuesto, fijar procedimiento jurídico de redondeo separado, sin afectar total cobrado al comprador.

**PENDIENTES específicos:** valor máximo/precisión de almacenamiento, validación de importes históricos, moneda/decimales que acepta versión de checkout elegida, descuentos fiscales, descuentos negativos, formato por localización y redondeo de importes tributarios externos. No invocar Number/float de JavaScript o PHP para producir resultado monetario vinculante.

## 4. Contrato de cotización y snapshots

**E04-D APROBADO (D-99):** separar tres importes:
- **Cotización actual**: calculada por el backend desde artículos/variantes/precios/oferta vigente, disponibilidad, modalidad de entrega, distrito UBIGEO y cargos permitidos. No compromete precio o stock por solo estar en carrito.
- **Total pactado al iniciar «Pagar»**: revalidar desde servidor y exigir al cliente aceptar total actualizado si ha cambiado desde el carrito. Cuando se crea intento válido, asociar total/cargos/snapshots al pedido y **no modificarlos por cambios posteriores de catálogo**.
- **Movimientos posteriores**: cambios de dirección, cancelaciones, devoluciones o correcciones generan ajustes asociados, no actualizan retroactivamente el precio del intento inicial ni el snapshot.

**Consecuencia:** si el vendedor cambia una oferta mientras el cliente tiene carrito abierto, el carrito se recotiza antes de iniciar el pago; si modifica el precio después de crear el intento válido, **no altera el importe del intento ya emitido**. Si el proveedor intenta cobrar importe diferente al pactado, abrir discrepancia e investigar; no acreditar el pedido por retorno visual ni modificar el snapshot para ocultarlo.

**No decidido aquí:** expiración de cotización previa al clic, recuperación de checkout abandonado, reintento de orden externa con expiración distinta al hold local de 15 minutos, posibilidad de volver a cotizar un pedido aún no confirmado, detalles de integración E-05/E-08.

**Contrato P0-21 documental (2026-11-17):** `docs/33-MAPAS-UBIGEO-PROVEEDOR-P0-21-V1.md` fija la separación entre mapa Leaflet/tiles opcionales y tarifa/cobertura por UBIGEO **calculadas solo en PHP**. No usar polígonos ni km para precio, no pedir geolocalización por defecto; las fuentes INEI 2023 y 2025-12 presentan metadatos/licencias diferentes y el dataset final no está seleccionado. No se efectuó importación ni validación real.

## 5. Delivery, recojo, servicios y cartografía

**E04-E APROBADO (D-100):** usar **una modalidad de entrega física por pedido** V1: recojo en único local o delivery a un distrito UBIGEO/dirección validada; una tarifa de delivery por pedido (no por unidad ni por línea), conforme configuración comercial vigente. Los **servicios presenciales en local y productos digitales no generan tarifa de delivery** por sí solos. Un pedido mixto cobra delivery solo si tiene físicos elegibles a domicilio. Sin físicos a domicilio, no debe aparecer cargo de entrega.

**E04-F APROBADO (D-101):** tarifa de entrega final debe conocerse y mostrarse **antes del pago**; si cambia distrito/modalidad en carrito, recalcular por backend. Tras confirmar, el cargo de entrega pactado permanece histórico. Un cambio posterior de dirección crea propuesta/ajuste, con autorización de administrador cuando cambia costo, cobertura o hubo dinero acreditado (D-65/D-83). No «reemplazar tarifa antigua» ni cobrar diferencia de forma silenciosa.

**Barrera de cobertura:** si distrito no está cubierto, no ofrecer delivery, aunque mapa estime ubicación. Permitir recojo/manual address solo cuando cumpla reglas del tipo de pedido. Proveedor cartográfico no fija el precio ni la elegibilidad (D-23/D-44).

**E04-G APROBADO (D-102):** cuando se cancela **la última línea física antes del despacho**, no mantener la tarifa de entrega automáticamente: evaluar si hubo prestación de entrega o gasto jurídicamente repercutible comprobado. Si **no se inició ni prestó ningún servicio de entrega que legalmente pueda cobrarse**, devolución aplicable de la tarifa completa; si hay gastos/servicio real, **revisión administrativa con prueba y derechos**, nunca porcentaje fijo. E-04 debe distinguir importes propuestos vs importes legalmente confirmados; solo el administrador aprueba ajustes financieros en pedidos pagados.

**Pendiente J-01/P0-25:** qué gastos pueden trasladarse válidamente al consumidor y cómo demostrarlo; no asumir que solo documentar un costo lo hace automáticamente cobrable.

**DOC-QA30/D-199 — entrada manual al expediente, sin modificar su efecto (2026-11-18):** el flujo de cantidad parcial física D-95 ya puede iniciar con **R8-69 POST `/admin/orders/{id}/cases`** solo administrador A con `perucommerce_orders_read` **Y** `perucommerce_orders_financial_adjust`. Es una apertura por solicitud verificada mediante control del teléfono original y segundo dato del pedido no público (E07-E), con `line_id`, unidades **solicitadas**, motivo, versiones de pedido y línea e idempotencia durable. La respuesta devuelve `case_id` y `row_version` en fase `open_review`, recuperables por R8-21 a A. El expediente **no ejecuta ni aprueba** reducción: R8-26 revalida id/caso de misma línea y comercio, versión, cantidades restantes, acreditación/importe y decisión bajo locks; actualiza compromiso/movimiento sin editar snapshot original, reembolso o cancelación ordinaria. Reintentos de apertura idénticos no duplican caso; solicitud diferente con caso sin resolver requiere revisar la original (409), no acumular expedientes por error. El caso no puede originarse mediante R8-55 de logística ni saltarse el OTP del invitado R8-12 fingiendo otro actor. **R8-12 solo contempla solicitudes invitadas tipadas, sin unidades solicitadas, y no satisface directamente el case_id de un ajuste parcial físico con affected_units; este expediente específico se abre mediante R8-69.** `docs/42` documenta el cierre, sin software aún.

**Ciclo de expediente completo D-200 / DOC-QA31 (18-11-2026):** R8-69 abre caso `open_review` sobre solicitud física parcial verificada. Si A **rechaza** la petición o acredita un **retiro del comprador** tras nueva verificación E07-E, R8-70 (`POST /admin/orders/{id}/cases/{case_id}/close`) exige `decision`, `reason`, `expected_case_version`, `idempotency_key` y, para `buyer_withdrawn`, `withdrawal_verification`; registra `closed_rejected` o `closed_withdrawn`, evento, `resolved_at` y auditoría **sin tocar pedido, dinero o inventario**. Cuando A SÍ autoriza la reducción, `FinancialAdjustment` de R8-26 debe incluir `expected_case_version` junto con `case_id/line_id/affected_units/unit_effect` y validar que el caso es `open_review` del mismo pedido/línea; la cantidad ejecutada no se cambia unilateralmente frente a la petición original. El ajuste físico/monetario, evento de caso y transición **`closed_adjusted`** deben confirmarse **atómicamente** o revertirse todos. Reintento idempotente no libera dos veces, y caso terminal no se reutiliza para ajuste. `closed_adjusted` NO acredita reembolso externo; si existe sigue exigiendo R8-27. Ninguna de las tres fases internas afecta estados comerciales E-01. Un nuevo pedido de gestión legítimo tras cierre utiliza nueva petición, identidad comprobada, versiones y clave, no «reabrir» el caso anterior. OpenAPI vigente **0.12.0-draft, 69 REST/66 rutas/138 DTO**; sin código/pruebas.

## 6. Cancelaciones por línea completa y cantidades parciales administrativas

**E04-H APROBADO (D-103):**
- **Cancelación ordinaria de línea completa:** proponer ajuste basado en **importe histórico pactado de esa línea**, no su precio nuevo en catálogo. Otras líneas permanecen.
- **Cantidad parcial dentro de una línea:** D-95 exige expediente/ajuste administrativo, no operación ordinaria. La solicitud identifica unidades afectadas, costo histórico de esas unidades, motivos, evidencia y aprobación. No se emite segunda cotización comercial sustituyendo la original.
- **Delivery:** permanece mientras haya físicos a domicilio pendientes de entregar; cuando desaparece la última línea, revisar según E04-G; si hay despacho/incidencia, no asumir devolución automática.
- **Diferencias de monto:** el sistema propone importe elegible y lo separa del dinero realmente reintegrado. Un administrador no puede introducir arbitrariamente un valor superior al dinero acreditado ni dar por verificado un reembolso no comprobado.
- **Tributos y comprobante:** si un ajuste requiere nota de crédito, descuento u otro documento externo, registrar su referencia/estado, sujeto a matriz fiscal P0-25. El reembolso externo y el comprobante no son el mismo evento.

### Lectura de cantidades después del ajuste — DOC-QA27, 18-11-2026

`AdminOrderLine.quantity` y `GuestOrderLine.quantity` siempre representan la **cantidad original pactada** de `pc_order_lines`; el ajuste R8-26 no las reemplaza por un saldo neto. En líneas físicas, ambas lecturas incluyen `physical_quantities` con `committed_units`, `released_units`, `stock_out_units` y `pending_stock_out_units`, según el mapeo de `docs/23` §4.3. El último valor es el saldo comprometido **pendiente de salida física**: permite preparar/despachar o entregar por recojo únicamente si las guardas E-01 lo autorizan. No es una cantidad pendiente de recepción por el comprador.

La reducción `unit_effect=release_unfulfilled_units` de R8-26 se limita a **líneas físicas con compromiso vigente**; digital/servicio se rechazan con 422 `pc_business_ineligible`, sin cambiar el ajuste exclusivamente monetario existente. Es **estrictamente parcial**: bajo lock, `0 < affected_units < pending_stock_out_units` vigente. Liberar todo el remanente se tramita por R8-23 como cancelación completa de la línea, con sus guardas de estado, autorización, expediente y efectos existentes; no dejar una línea en preparación/lista sin unidades mediante un ajuste parcial. El saldo cero impide una **nueva salida**; no impide F-06 (`DESPACHADO → ENTREGADO`), que solo registra la recepción de unidades ya salidas.

Ejemplo contractual: para `quantity=3`, un compromiso confirmado de tres unidades y la liberación administrativa de una producen `(committed_units=3, released_units=1, stock_out_units=0, pending_stock_out_units=2)`. Después de despachar las dos restantes, el saldo pendiente de salida es cero y `stock_out_units=2`; el estado `DESPACHADO` continúa distinguiéndose de `ENTREGADO`. No recalcular dinero pactado multiplicando precio por ese saldo: los importes originales y ajustes mantienen sus registros separados.

Estos contadores de la propia línea son visibles al operador autorizado y al invitado del pedido propio, sin revelar expediente, motivo interno, evidencia, movimientos financieros ni existencias globales. R8-26 actualiza el compromiso y la versión de la línea en la misma transacción; una acción de cumplimiento con versión anterior debe recargar el pedido tras el conflicto. No se crean cancelaciones parciales ordinarias, despachos parciales ni devoluciones automáticas.

**Caso mixto ilustrativo no vinculante:** físico S/ 35.00 + digital S/ 12.50 + cita S/ 20.00 + delivery S/ 6.00 = S/ 73.50. Si se cancela solo el físico antes del despacho, importe histórico de la línea = S/ 35.00; si además se confirma jurídicamente que corresponde devolver los S/ 6.00 de delivery, el ajuste relacionado es S/ 41.00. Digital y cita conservan sus importes. **La cifra es escenario de ejemplo**, no una regla general de devolución del delivery.

## E-08 — importe MP, `payer` y delivery APROBADOS EN POLÍTICA (D-149…D-166)

El contrato `21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` ratifica checkout tipado MP/COD, `total_amount` exacto desde céntimos, sumas de ítems y un ítem `Entrega` **no inventariable**, sin añadir IGV universal ni modificar históricos. D-157 fija **omitir `payer`** cuando físico no tiene email y D-159 enviar `PT15M` **solo si el proveedor lo permite**. **D-157/D-158/D-159 están aprobados condicionalmente, sin verificación MP real**: si un supuesto falla, bloquear MP y pedir nueva decisión; no exigir correo al físico ni cambiar a alternativa B.

El bruto cobrado y comisiones siguen separados; ningún reembolso se ejecuta desde la API. **Pendientes:** OpenAPI/DTO, J-03/P0-17/P0-25 para fiscalidad por régimen, SQL/hosting y comprobación autorizada de proveedor/credenciales.

## 7. Un cobro inicial: Mercado Pago y contraentrega

**E04-I APROBADO (D-104):** la orden de pago externo se genera por el **total bruto pactado** de ese pedido y una sola operación inicial; todos sus ítems/suma deben coincidir con el importe enviado al proveedor. Cómo se representa delivery en la modalidad concreta (ítem explícito o estructura admitida por API) se resolverá E-08 con documentación del endpoint y **sin inventar un producto de catálogo**. Un error de suma, moneda u orden obliga a **no iniciar un segundo cargo a ciegas** y a conciliar primero (E-05).

- **Monto bruto vendido al cliente** no es **monto neto que recibe el comerciante** tras comisiones de Mercado Pago. Registrar la comisión como movimiento/información de conciliación, no restarla silenciosamente de deuda del comprador ni añadirla por defecto a su total.
- **Contraentrega:** registrar monto efectivamente cobrado neto de vuelto si existió, correspondiente al importe total pactado en un **único evento presencial**; el vuelto, si se controla físicamente en caja, no es una segunda venta ni cobro del plugin. Operador no acredita diferencias como pago completo sin validación; desacuerdos pasan a incidencia/ajuste administrado. **CERRADO D-105/E04-J:** V1 registra solo efectivo neto aplicado al pedido, sin campos de billete entregado y vuelto; las reglas de error/idempotencia de esa operación requieren E-05/E-08.
- **Una cotización puede quedar sin cobro**; CONFIRMADO en contraentrega no significa pagado. Abonos parciales por línea no justifican descargar digitales ni marcar pedido como totalmente abonado.

**E04-J APROBADO (D-105):** registrar en V1 **solo el importe efectivo neto aplicado al pedido** (dinero que queda cobrado tras entregar cualquier vuelto), que debe coincidir con saldo exigible para acreditarlo completamente; la gestión física del billete entregado y vuelto queda fuera del plugin. Si hay entrega de importe distinto, no confirmar pago total y generar incidencia para corrección/admin. Alternativa: modelar en plugin efectivo entregado/vuelto, sin cambiar el neto aplicado.

## 8. Cambio de dirección después de cobro: diferencias positivas/negativas

**E04-K APROBADO (D-106):** si se solicita dirección con tarifa diferente después de acreditar el **único cobro inicial**:
- **Mismo importe:** admin aprueba si D-83 lo exige por cobertura/cobro; ajuste de dirección sin segundo movimiento financiero, con evaluación de viabilidad.
- **Tarifa inferior:** admin aprueba nuevo dato, registra crédito/ajuste si corresponde y después resultado de eventual reembolso externo **comprobado**. No declarar devuelto solo por cambiar dirección.
- **Tarifa superior:** no efectuar cargo adicional automático ni alterar el pago original; bloquear aplicación hasta resolución administrativa. V1 evita cobrar un segundo pago dentro del mismo pedido: se propone otra dirección con tarifa compatible o, si procede legalmente, cancelar/recrear pedido con una nueva obligación pactada, preservando historial y derechos.
- **Servicio físico en ruta:** cambio queda en expediente/incidencia, nunca prometer automáticamente aceptación logística.

**PENDIENTE:** derechos/remedios cuando no se atiende nueva dirección, consentimiento del comprador E-07, norma de cargos y ejecución E-08. **E-06 aprobado (D-123…D-134)** define que una solicitud/cambio se comunica por su estado real, no constituye aceptación de nueva tarifa ni cargo adicional.

## Identidad y confidencialidad del dinero — E-07 APROBADO (D-135…D-148)

El contrato `20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md` **APRUEBA E07-A…N (D-135…D-148)**: datos mínimos por actor y pedido, exportaciones con finalidad/permiso, protección de referencias externas, enlace privado solo lectura, OTP o verificación manual para cambios sensibles y conservación por fundamento legal. El operador no accede a reintegros reservados a administrador ni al Libro; J-04/J-05/P0-25 y E-08 siguen abiertos.

El modelo monetario D-96…D-108 aprobado no autoriza borrar snapshots y movimientos por solicitud ARCO automática ni exponer documentos fiscales al público. Jurídicamente quedan J-03/J-04/J-05/P0-17/P0-25, y técnicamente E-08/P0-18/P0-26.

## 9. Reembolsos y saldo financiero reconstruible

**E04-L APROBADO (D-107):** guardar cada movimiento como evento **inmutable** vinculado al cobro original (referencia de proveedor o recibo de efectivo): cobro acreditado, corrección administrada, reintegro externo total/parcial verificado, contracargo y discrepancia. **Solo administrador** registra/revisa resultados de reintegro externos con referencia, fecha, importe PEN y constancia; no existe endpoint de ejecución de devolución.

**Invariantes financieros propuestos para aprobar y luego implementar:**
- Separar **importe pactado** (snapshot de pedido), **cobros acreditados brutos**, **ajustes comerciales/obligaciones de devolución**, **devoluciones externas verificadas**, **contracargos** y **saldo resultante**. Un campo «pagado=true» no sustituye al libro.
- Para registrar un **nuevo** reintegro externo verificado, su importe no puede superar el **cobro bruto acreditado menos el acumulado de reintegros externos verificados anteriores** del mismo cobro; contracargos/reversiones requieren conciliación separada para evitar contabilizar el mismo dinero dos veces. El acumulado total de reintegros verificados nunca debe superar el cobro bruto acreditado, sin perjuicio de una incidencia por datos externos contradictorios.
- Una solicitud/obligación de devolución puede estar **pendiente** mientras el dinero todavía no ha salido. No disminuir acreditación histórica ni declarar reintegro realizado por tener una solicitud o ajuste.
- No registrar dos veces la misma referencia de reembolso/evento, incluso con reintento, múltiples operadores o cierre/reapertura del navegador (E-05).
- No usar comisiones del proveedor para justificar devolución inferior al derecho del consumidor; el cálculo real lo determina la política y legislación aplicables.
- **Saldo de cobro**, **importe a reintegrar** y **dinero ya reintegrado** deben poder reconstruirse desde eventos y ser visibles con etiquetas distintas, sin borrar historia.

**Ejemplo de conciliación propuesto:** pedido S/ 73.50 cobrado; devolución externa verificada de S/ 20.00 → acreditación original S/ 73.50, reintegro verificado S/ 20.00, neto de movimientos S/ 53.50. Si luego se verifica otro reintegro S/ 10.00, acumulado S/ 30.00, neto S/ 43.50. **El total original de venta permanece S/ 73.50**; no se confunde neto con precio pactado ni con comisión del procesador.

## 10. Fiscalidad contextual: POR APROBAR con matriz legal

**La fiscalidad es un contrato separado de los totales comerciales.** El comerciante informa régimen/sujeto/operación. El sistema no inventa base imponible ni aplica un 18 % universal a todo precio final ni emite SUNAT. Documentos de boleta/factura/recibo por honorarios **solo si aplican al emisor/actividad** (D-26).

| Caso | Estado documental | Contrato faltante |
| --- | --- | --- |
| Nuevo RUS | Fuente SUNAT confirma boletas/tickets, **no facturas** con crédito fiscal | Campos específicos, requisitos formales y si la actividad admite dicho régimen |
| RER, RMT, General | La documentación SUNAT distingue obligaciones/documentos | Matriz por operación gravada/exonerada/inafecta, emisor y tipo de comprobante |
| Servicios independientes de cuarta categoría | Debe diferenciarse de venta empresarial ordinaria | Condición del sujeto, recibo por honorarios y retenciones aplicables cuando corresponda |
| Venta/servicio con devolución | SUNAT regula documento externo que puede corresponder (nota de crédito y otros) | Vínculo al comprobante original, motivo, importe fiscal, fechas y emisor válido |
| Tarifa delivery | Precio visible pactado no determina tratamiento tributario | Gravabilidad, afectación al precio total y desglose según negocio/servicio |
| Oferta manual | Precio de oferta comercial no autoriza cálculo fiscal inventado | Desglose correcto según norma, no motor genérico |

**E04-M APROBADO (D-108):** almacenar cotización **comercial final** y, cuando corresponda, referencia/importe/serie/fecha del comprobante o nota de crédito **emitidos fuera de PeruCommerce**. Si el comerciante aporta desglose fiscal externo válido, registrarlo separado y conciliable; no derivarlo automáticamente desde el precio final ni permitir que cambie retroactivamente la venta. Alternativa: registrar solo referencias sin desglose para simplificar, con menor conciliación interna.

**Pendiente crítico J-03/P0-25:** matriz SUNAT por régimen, clase de bien/servicio, documento, tributo, gravabilidad, redondeo fiscal, devoluciones, guías cuando aplique, retención y tratamiento de cargos de entrega. No aprobar cálculo tributario por seleccionar E04-M. La documentación general oficial no basta para validar supuestos sectoriales.

## 11. Escenarios E-04 (documentales, NO pruebas ejecutadas)

Los ejemplos están expresados en PEN y presuponen precios comerciales ya fijados, sin crear tasas fiscales. **Escenarios de referencia para las políticas E04-A…M APROBADAS (D-96…D-108):** los resultados comerciales descritos son criterios propuestos de verificación documental, NO pruebas ejecutadas ni reglas fiscales completas.

| ID | Dato de entrada / caso | Resultado de referencia / negativa |
| --- | --- | --- |
| M4-X01 | Simple S/ 12.50 × 2 | Subtotal S/ 25.00, sin error de float |
| M4-X02 | Simple S/ 12.50, oferta activa S/ 10.90, cantidad 3 | Se utiliza oferta vigente: S/ 32.70, no 37.50 |
| M4-X03 | Variantes con precio 8.00 y 9.50 | Cada variante produce su propio subtotal, sin herencia de precio |
| M4-X04 | Variante sin precio final explícito | No iniciar pago válido, error de cotización |
| M4-X05 | Precio ingresado 1.239 | Rechazar exceso de decimales, no truncar silenciosamente |
| M4-X06 | Precio efectivo negativo / cantidad fraccionaria | Rechazar según opción E04-B, no vender con monto inválido |
| M4-X07 | 2 físicos de S/ 35.00 y S/ 10.00 + envío S/ 6.00 | 35.00 + 10.00 + 6.00 = S/ 51.00, una entrega |
| M4-X08 | Solo digital 12.50 + servicio 20.00 presencial | Total S/ 32.50, sin tarifa de delivery |
| M4-X09 | Físico en recojo local + servicio presencial | Sin tarifa de delivery al no contratar traslado |
| M4-X10 | Distrito fuera de cobertura | No cotizar delivery aunque pin de mapa esté en otro distrito |
| M4-X11 | Precio cambia en catálogo luego de carrito, antes de Pagar | Recotizar, informar nuevo total y requerir confirmación |
| M4-X12 | Precio cambia luego de crear intento válido por 73.50 | Pedido/intento conservan total histórico 73.50, no mutar por catálogo |
| M4-X13 | Provider envía amount 73.49 para intento de 73.50 | Discrepancia, no marcar cobro completo por diferencia |
| M4-X14 | MP reporta bruto 100.00, fee 3.50, neto 96.50 | Venta pactada/abonada al cliente S/ 100.00; comisión separada S/ 3.50 |
| M4-X15 | MP total_amount 73.50, suma items 73.49 | Bloquear envío hasta reconciliar; no inventar centavo |
| M4-X16 | Pedido mixto físico 35.00, digital 12.50, cita 20.00, envío 6.00 | Total pactado S/ 73.50 |
| M4-X17 | Cancelar físico 35.00 del mixto sin cancelar las otras líneas | Propuesta base de ajuste de línea 35.00, sin mutar digital/cita; delivery revisado aparte |
| M4-X18 | Cancelación última línea física sin despacho ni servicio de entrega prestado y con derecho de devolución del flete | Propuesta total de devolución 35.00 + 6.00 = S/ 41.00, tras validar elegibilidad |
| M4-X19 | Reclamo por parcial de cantidad dentro de línea con 3 unidades | No cancelación ordinaria; expediente/ajuste admin D-95, importe sujeto a E-04 |
| M4-X20 | Pedido ya pagado: cambio dirección con tarifa de 6.00 a 8.00 | No nuevo cargo automático ni sobrescritura; aprobación admin y resolver diferencia por política E04-K |
| M4-X21 | Pedido ya pagado: tarifa de 6.00 baja a 4.00 | Historial 6.00 intacto, crédito 2.00 si corresponde; reintegro externo solo cuando verificado |
| M4-X22 | Contraentrega pedido 51.00, operador registra 51.00 | Registro neto exacto, cobro acreditado con auditoría, sin segunda captura |
| M4-X23 | Contraentrega pedido 51.00, operador recibe menos y trata de marcar pagado | Denegar acreditación total; incidencia sin fingir saldo cero |
| M4-X24 | Reintegro externo comprobado 20.00 sobre cobro 73.50 | Cobro original 73.50, reintegro verificado 20.00, movimientos netos 53.50 |
| M4-X25 | Registrar dos veces mismo reintegro externo de 20.00 | Un solo evento efectivo; repetición no reduce saldo dos veces |
| M4-X26 | Registrar reintegros por acumulado > cobro acreditado | Bloquear/abrir conciliación, no registrar como reembolso comprobado completo |
| M4-X27 | Reembolso solicitado, proveedor sin prueba de ejecución | No marcar reintegro verificado ni alterar pago original |
| M4-X28 | Administrador corrige cobro en efectivo erróneo | Ajuste relacionado, original intacto; sin devolución externa automática |
| M4-X29 | Pedido NRUS solicita factura | No ofrecer emisión de factura como opción válida del emisor; régimen y documento externos |
| M4-X30 | Nota de crédito externa requerida tras devolución | Guardar referencia/estado/documento externo, no crear XML SUNAT |
| M4-X31 | Usuario ingresa importe total S/ 0.00 para Mercado Pago | Rechazo de order con total cero según contrato Checkout Pro; decisión de rutas sin cargo E04-B |
| M4-X32 | Cancelación parcial deja pedido con otras líneas ya entregadas | No convertir pedido global en CANCELADO; mantener original y efectos por línea |

**Ningún escenario M4-X acredita funcionamiento o compatibilidad probados.** Las cifras de ejemplos son cálculos de cotización comercial, no comprobantes tributarios ni montos de derechos automáticamente exigibles.

## 12. Registro de aprobaciones E-04 — POLÍTICA COMERCIAL Y NUMÉRICA CERRADA

**Confirmación expresa:** el propietario aprobó «Todas A en E-04» (13 opciones E04-A…M) el 2026-11-17. Se registraron como **D-96…D-108**. Las alternativas B quedan descartadas. No extender ninguna decisión más allá de lo aprobado o de derechos legales aplicables.

| Origen | Registro | Política A aprobada |
| --- | --- | --- |
| E04-A | D-96 | Montos internos en **enteros de céntimos PEN**, con validación de precisión y serialización decimal exacta hacia sistemas externos |
| E04-B | D-97 | Precios efectivos positivos de hasta 2 decimales, ofertas válidas y cantidades enteras positivas; sin artículos gratuitos ni unidades fraccionarias en V1 |
| E04-C | D-98 | Subtotal de línea = cantidad entera × precio unitario final en céntimos; total = suma de líneas + cargo de entrega aplicable y demás conceptos solo si estuvieran aprobados/informados; sin redondeos intermedios ni IGV universal agregado |
| E04-D | D-99 | Cotización revalidada en servidor al pulsar Pagar; si cambia el total exige nueva aceptación y congela snapshot al crear intento válido |
| E04-E | D-100 | Una modalidad física y una tarifa de entrega por pedido, según distrito UBIGEO; digitales y servicios en local no generan delivery |
| E04-F | D-101 | Tarifa de delivery visible antes del pago; cambios tras confirmar se reflejan en ajustes y eventos sin sobrescribir importe pactado |
| E04-G | D-102 | Al cancelar última línea física antes de despacho, revisar servicio/costo jurídicamente cobrable y derechos: reintegro total de delivery si no existe prestación cobrable; incertidumbre a revisión administrativa |
| E04-H | D-103 | Cancelaciones usan precio **histórico** de la línea; cantidades parciales van a expediente/ajuste administrativo; delivery se evalúa aparte |
| E04-I | D-104 | Cobro inicial bruto de Mercado Pago concuerda con total pactado y suma de ítems; comisiones y liquidación neta son magnitudes separadas y conciliables |
| E04-J | D-105 | Efectivo: registrar únicamente **importe neto aplicado** al pedido en un evento, sin modelar billetes/vuelto; diferencias se tratan como incidencia, no pago total ficticio |
| E04-K | D-106 | Dirección con tarifa mayor **después del pago** no provoca cargo automático adicional en el mismo pedido; resolver administrativamente otra dirección compatible o un nuevo pedido legalmente viable, preservando original |
| E04-L | D-107 | Libro inmutable de cobros, ajustes, contracargos e reintegros externos verificados, con referencias únicas, límites de acumulado y conciliación de parciales; sin API de reembolso |
| E04-M | D-108 | Registrar comprobantes y notas de crédito **emitidos externamente**, con referencia y desglose fiscal solo cuando el comerciante lo aporte y sea válido; sin motor fiscal automático |

**Guardas de interpretación:** D-102 no presupone que todo costo real sea legalmente cobrable: J-01/J-03/P0-25 deben precisar el supuesto. D-103 no autoriza inventar valor fiscal ni aplicar un prorrateo a cantidades parciales por cuenta propia. D-104 **no decide la representación del cargo delivery en el endpoint MP**: corresponde E-08. D-107 no permite registrar devoluciones solicitadas como verificadas ni duplicar reintegros. D-108 no obliga a calcular un impuesto para todos los comerciantes.

## 13. Subcontratos que siguen pendientes tras aprobar las 13 opciones

- **M-10 / E-04 técnico:** estructura definitiva de importes, límites de entero/decimal, versión de cotización, precisión de tarifa, esquema de asientos y conciliación; aprobar modelo físico P0-18.
- **J-03 / P0-17 / P0-25:** matriz SUNAT vigente de gravabilidad, tributos, precios, documento externo, delivery, descuentos, notas de crédito y redondeos fiscales por régimen/operación. No deducir tasa de IGV universal.
- **E-05:** contrato técnico **APROBADO COMO POLÍTICA D-109…D-122** en `18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md` (E05-A…N): atomicidad, idempotencia, administradores simultáneos, locks stock/citas, expiración por tiempo efectivo y conciliación. **PENDIENTES P0-18/P0-19/E-08:** SQL, motor real y API externa. No deducir funcionamiento probado.
- **E-06 APROBADO D-123…D-134:** `19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md` fija comunicación de recotización, delivery, revisión administrativa, discrepancia MP, reintegro solicitado y resultado externo comprobado, sin inventar importes ni modificar originales. Pendientes autorización/identidad E-07 y envío/API E-08.
- **E-07 APROBADO (D-135…D-148):** verificación sensible, lectura restringida de importes/PII por rol y retención por categoría. **Pendientes:** J-04/J-05/P0-25 para plazos/excepciones legales, E-08 para campos REST y resguardo real de evidencias.
- **E-08:** modalidad Checkout Pro concreta, construcción de total/items/shipping conforme API vigente, webhooks, códigos de error, capacidad de backend y validación de credenciales.
- **J-01/J-02:** derechos del consumidor en cancelación/entrega/servicios y sus obligaciones concretas por caso y sector.
- **P0-24/P0-26 y compatibilidad:** licencia, versiones estables, distribución ZIP, upgrades/rollback/retención. No se cierra E-04 funcional suponiendo que estas áreas no existen.

**Estado de salida:** E-04…E-07 aprobados en política D-96…D-148. **PENDIENTES:** J-01…J-05/P0-17/P0-25, E-08, SQL/hosting/ZIP y demás P0; M4-X/C5-X/N6-X/P7-X no son pruebas ejecutadas. No iniciar código.
