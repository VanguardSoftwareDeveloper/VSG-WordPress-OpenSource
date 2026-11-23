# E-05 — Concurrencia, transacciones, idempotencia y recuperación V1

**Progreso posterior P0-18 (2026-11-17):** `docs/23-MODELO-FISICO-DE-DATOS-E-INDICES-V1.md` propone columnas, tipos, índices y locks; **no es SQL aprobado ni sustituye E-05**. Requiere revisión DDL/hosting y autorización específica para ejecutar.

**Estado: E-05 APROBADO EN POLÍTICA TÉCNICA (D-109…D-122).** El propietario aprobó expresamente las catorce opciones A E05-A…N el 2026-11-17. **No están cerrados el esquema SQL P0-18, motor/hosting P0-19, el contrato Mercado Pago E-08, la recuperación ZIP P0-26 ni la verificación de funcionamiento**. Revisión de referencias: 2026-11-17. Fase 0 documental. **No autoriza código, compilación, pruebas, SQL, instalación, credenciales ni despliegue.**
**Objetivo:** permitir que Codex implemente una única interpretación consistente de disponibilidad, pagos, movimientos, fallos y recuperación, sin inventar decisiones sobre cuándo se reserva un recurso, qué hacer ante datos contradictorios o cómo conciliar un cargo externo.
**Precedencia:** decisiones D-01…D-122 en 01-DECISIONES-Y-ESTADO.md; E-01 aprobado en 14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md; E-02 en 15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md; E-03 en 16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md; E-04 en 17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md. **No crear estados, cobros, permisos ni cálculos de negocio distintos a los aprobados.**

**Nota de precedencia vigente tras D-198 (18-11-2026):** las reglas funcionales E-01…E-07 aprobadas en este documento se conservan. Los textos anteriores que presentan E-08, DTO, capabilities, TTL o cobertura de expedientes como *pendientes de decisión* son **históricos**, no una segunda fuente de contratos: prevalecen `docs/01` D-01…D-198, `docs/41/42`, OpenAPI **0.8.0-draft (67 REST/64 rutas/134 DTO)** y `docs/23/24` (39 tablas, 56 PER). Solo quedan pruebas/compatibilidad de motor, proveedor, hosting, normativa por comercio y release en P-C/P-D; no se presentan como verificadas. El usuario autorizó aquí **documentación**, no código ni ejecución.

**D-199 / R8-69, atomicidad de apertura manual (18-11-2026):** para caso de reducción estrictamente parcial de unidades físicas solicitado por teléfono, el backend usa `pc_idempotency` con scope (**instalación, actor WP A, pedido, línea, finalidad, clave**) y hash de contenido. Bajo transacción y orden de locks `pc_orders → pc_order_lines` verificar `expected_order_version`, `expected_line_version`, línea física del pedido, `0 < requested_affected_units < pending_stock_out_units`, y ausencia de otro `pc_cases` `open_review` del mismo pedido/línea/finalidad. Registrar `pc_cases` (solicitud original/verificación categorizada), primer `pc_case_events` (actor_kind staff/actor_user_id real), idempotencia y auditoría **atómicamente**; actor y fecha provienen del servidor, no del cuerpo. Si el primer commit ya ocurrió, reintento de la misma clave/payload recupera `case_id` sin otro evento (200); clave idéntica con cuerpo distinto o solicitud concurrente duplicada se rechaza con 409 y se recarga desde R8-21. **El caso no modifica dinero, stock, estados E-01 ni genera refund**. Su aprobación futura es R8-26, que toma sus locks de dinero/variante y comprueba de nuevo caso/identidad/estado/importe/versión bajo idempotencia separada. Verificación manual con teléfono registrado y segundo dato privado conforme E07-E; no registrar secretos ni declarar comprobada la identidad solo por flags. Esto es diseño sin SQL ni pruebas ejecutadas.

**D-200 / DOC-QA31 — carrera R8-26 vs R8-70 (18-11-2026):** el expediente físico parcial abierto por R8-69 pasa de `open_review` **a exactamente uno de** `closed_adjusted` (R8-26 con ajuste aplicado), `closed_rejected` o `closed_withdrawn` (R8-70 sin efecto financiero). Las dos mutaciones toman locks en orden **`pc_orders → pc_order_lines → pc_cases`**; R8-26 continúa a anclas stock/ledger según E05 y R8-70 no toca stock ni dinero. Ambas comparan `expected_case_version` contra `pc_cases.row_version`, exigen fase `open_review` y caso legítimo mismo comercio/pedido/línea/tipo; un cierre concurrente provoca 409 y **ninguna** liberación parcial. R8-26 agrega ajuste a `pc_order_adjustments`, variación de compromiso, incrementos de versiones, `manual_case_adjusted` en `pc_case_events`, `pc_cases.resolved_at/case_phase=closed_adjusted` y auditoría **en la misma transacción**; rollback si falla un paso. R8-70 solo escribe `manual_case_rejected/manual_case_withdrawn`, cambio de caso a fase terminal/fecha, evento/idempotencia/auditoría en una transacción; para retiro exige verificación telefónica nueva. Hash de solicitud y clave idempotente reutilizados devuelven resultado original sin repetir evento, pero claves nuevas con caso terminal no reabren. No ejecutar refund externo, no inventar estado comercial E-01 ni tabla 40. La exactitud de CAS/locks y bloqueo de doble evento son criterios para pruebas autorizadas futuras, no verificación ejecutada ahora.

## 1. Fuentes oficiales y restricciones constatadas

| Referencia | Hecho respaldado | Consecuencia para E-05 |
| --- | --- | --- |
| WordPress Plugin Handbook — Cron | **WP-Cron** se desencadena por visitas y no ejecuta tareas continuamente | Nunca depender de un cron puntual para caducar los holds de 15 minutos ni impedir una segunda reserva; limpieza/reconciliación pueden ser diferidas |
| MySQL 8.4 — InnoDB locking reads | Una lectura ordinaria seguida de escritura puede sufrir cambios concurrentes; **SELECT ... FOR UPDATE** bloquea registros dentro de transacciones | El carrito o consulta de calendario por sí solos no protegen stock/cupo. Requerir bloqueos/atomicidad en persistencia, con motor compatible |
| WordPress.org — Requirements | WordPress recomienda PHP moderno y **MySQL o MariaDB**; no garantiza que cualquier hosting o tabla propia permita transacciones InnoDB | Verificar versión, motor real, índices y permisos del hosting antes de dar soporte comercial; P0-19 sigue abierto |
| Mercado Pago — Checkout Pro Orders: Create order | La creación de órdenes exige **X-Idempotency-Key** y admite consulta por ID; un error o reintento puede ser ambiguo | Persistir correlación y clave para no crear órdenes/cobros duplicados tras fallo de red |
| Mercado Pago — Checkout Pro Orders: errores | Hay errores 409 por clave utilizada, 423 por recurso bloqueado, 429 por cuota y 5xx | No interpretar cualquier error como ausencia de orden. Consultar/conciliar antes de rotar claves o generar un intento nuevo |
| Mercado Pago — Checkout Pro Orders Webhooks | La notificación usa firma (x-signature, x-request-id, data.id) y recurso remoto; pueden existir reenvíos | Autenticar origen, comprobar ID y estado autoritativo; no confirmar pago por retorno al navegador ni procesar un mismo efecto dos veces |

Fuentes oficiales:
- https://developer.wordpress.org/plugins/cron/
- https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html
- https://wordpress.org/about/requirements/
- https://www.mercadopago.com.pe/developers/es/reference/online-payments/checkout-pro/create-order/post
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/create-order
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/payment-management/integration-errors
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/resources/notifications/webhooks
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/notifications

**Límite de documentación:** la modalidad exacta Checkout Pro Orders frente a otra API, las versiones compatibles y las condiciones de hosting se deben aprobar en P0-19/E-08. La existencia de estas páginas oficiales no demuestra una transacción ejecutada, una webhooks real ni compatibilidad del servidor instalado.

## 2. Invariantes ya aprobados (no someter a nueva elección)

1. El intento válido al pulsar **Pagar** inicia la retención **por 15 minutos**, sin renovación automática; seleccionar turno en ficha/carrito **no reserva** (D-27/D-35/D-36).
2. Contraentrega confirmada compromete stock/cita **inmediatamente**, sin fingir dinero acreditado, y exige un único evento de cobro presencial de todo el pedido (D-33/D-37).
3. Un recurso no se asigna a dos pedidos: stock controlado por variante, un único recurso de agenda, sin solapamiento de duración o buffers (D-31/D-38).
4. Una retención vencida **no cancela** Mercado Pago; pago acreditado tardíamente sin recursos abre incidencia, no sobreventa; la recuperación de pedido VENCIDO exige nueva asignación comprobada y resolución auditada (D-28/D-69/D-74).
5. Máquina de estados E-01 aprobada, sin transiciones extra de pedido/pago/línea por conveniencia técnica (D-67…D-77).
6. Pedido confirmado/snapshots originales inmutables; cancelar línea completa, ajustar precio histórico y conservar demás líneas/recursos; cantidad parcial de línea por expediente/ajuste, no cancelación ordinaria automática (D-56/D-59/D-95/D-103).
7. Dinero comercial en céntimos PEN y total histórico validado, un cobro inicial por pedido; comisión MP distinta de importe bruto y neto recibido; no introducir segundo cobro automático por dirección más cara (D-96…D-108).
8. El operador solo cancela sin **haber existido** dinero acreditado y sin efecto financiero, con permiso; administrador autoriza efectos financieros, correcciones de efectivo y registro de resultado de reembolsos externos comprobados (D-53/D-54/D-60/D-64).
9. PeruCommerce **no ejecuta reembolsos por API** V1; un reintegro externo parcial verificable no puede contabilizarse dos veces ni superar el cobro realmente acreditado (D-66/D-107).
10. Devolución de físico ENTREGADO no restituye stock vendible por la mera solicitud: exige inspección/recepción o resolución autorizada; cita reprogramada mantiene historial y una sola cita efectiva (D-89/D-94).
11. Descarga digital bloqueada antes de pago acreditado, token individual de 24 h, derecho independiente del token, acceso habilitado sin descarga requiere evaluación (D-09/D-40/D-61/D-71).
12. Una respuesta visual de retorno/cambio de URL del comprador **no** constituye verificación de dinero; el proveedor se comprueba desde backend y el resultado se registra en el eje correcto (D-55/D-68).

## 3. Límites transaccionales: E05-A/E05-B APROBADOS

**E05-A APROBADO (D-109):** tablas de dominio que cambian juntas (pedidos, líneas, reservas, disponibilidad, pagos/movimientos, operaciones idempotentes, auditoría y trabajos persistentes) utilizan almacenamiento local **transaccional InnoDB** compatible con el hosting WordPress. Cada operación crítica tiene una transacción corta y se rechaza la ejecución cuando no pueda obtenerse la garantía (motor sin transacciones, índices no creados, migración parcial). Evitar que un fallo se simule como éxito. **No** declarar soporte MyISAM u otras formas de almacenamiento sin un contrato equivalente verificado.

**E05-B APROBADO (D-110):** proteger stock por **registro estable de variante vendible** bajo lock transaccional (incluso cuando no existe todavía una reserva), comprobar suma de comprometidos + retenciones activas + permisos de transición, y actualizar de forma atómica. Nunca consultar disponibilidad fuera de lock y luego insertar una reserva asumiendo que sigue libre. Cuando el comercio desactiva control de stock, no inventar una restricción ficticia, pero la cita sigue protegida.

**Invariante de disponibilidad aprobado en política (D-109/D-110):** disponibles = existencias vendibles controladas − unidades comprometidas por pedidos − unidades con hold VIGENTE no vencido − admisiones breves de creación todavía activas, sin contar dos veces unidades convertidas de admisión a hold o de hold a compromiso. Ajustes administrativos de stock también toman el lock de la variante. Modelo físico y campos exactos pendientes P0-18.

**Bloqueo ordenado:** si un pedido afecta varias variantes y un turno, bloquear primero variantes en orden estable de identidad, luego agenda y fechas en orden estable, y luego registros financieros/pedido conforme a un orden documentado. Manejar timeout/deadlock con rollback, reintento acotado y nueva validación; nunca admitir negativa de stock o cita duplicada.

## 4. Agenda: E05-C APROBADO

**E05-C APROBADO (D-111):** para un recurso único, serializar operaciones de calendario mediante una fila estable de **recurso/día** que pueda bloquearse incluso si aún no hay citas; para servicios que cruzan medianoche, bloquear todos los días implicados en orden estable. Bajo ese lock, buscar solapamientos de intervalos **[inicio, fin)** incluyendo duración y buffers configurados, holds activos, compromisos y admisiones breves antes de insertar o mover. Guardar instantes UTC y zona del comercio para presentación. Un simple SELECT FOR UPDATE sobre las citas existentes **no protege bien un día vacío** si no hay fila que bloquear.

Invariantes: un solo recurso; máximo una cita por pedido; una reprogramación no deja dos citas activas; intervalos con buffers no se superponen; un no-show y cita ya prestada no se convierten en cancelaciones ordinarias. Diferentes tipos de operación comparten la misma exclusión mutua.

**PENDIENTE E-03/J-02:** duración, buffers, fecha máxima, hora del comercio, inicio efectivo del servicio y derechos de modificación, que aquí no se infieren.

## E-08 — política APROBADA de integración MP, firma y errores (D-149…D-166)

El documento `21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` confirma rutas de checkout MP/COD, clave idempotente, `POST /webhooks/mercado-pago` firmado, inbox durable y GET de order antes de acreditar, con catálogo de errores `pc_*`. **No cambia E-05**: admisión breve, llamada externa fuera de SQL, hold local de 15 minutos solo tras intento válido, y recuperación ante fallo MP/BD. Los 60 escenarios I8-X están **sin ejecutar**.

La vigencia `PT15M`, `payer` omitido y el ítem `Entrega` son políticas **condicionadas a compatibilidad real de cuenta peruana**. Un evento de order `processed` no basta para acreditar sin verificar fondos; no ejecutar refunds automáticos ni crear un intento nuevo ante timeout sin conciliar. **PENDIENTES:** OpenAPI físico, P0-18/P0-19, validaciones MP autorizadas y recuperación ZIP.

## 5. Crear intento Mercado Pago con recursos escasos: E05-D/E05-E APROBADOS

**Problema estructural:** una base de datos local no puede confirmar atómicamente un cambio SQL y una petición HTTP a Mercado Pago. Tampoco es válido retener un lock SQL mientras un servidor externo responde. D-36 obliga a iniciar el **hold comercial de 15 minutos solo después del intento de pago válido**.

**E05-D APROBADO (D-112):** coordinación en dos etapas, con admisión temporal interna y verificación final:

1. Backend recotiza y valida condiciones; crea un **registro de operación idempotente** y una **admisión corta de creación**, que protege provisionalmente el stock/cupo mediante locks transaccionales. Esta admisión **no es** el estado E-01 VIGENTE del hold de 15 minutos, no da derecho al cliente ni debe anunciarse como reserva confirmada.
2. Se confirma transacción local y **se libera el lock SQL** antes de llamar al proveedor. Correlación, hash de solicitud y clave externa idempotente quedan guardados.
3. Se crea/consulta la order externa con clave idempotente persistente. Si existe una respuesta válida, se recupera el recurso local bajo otro lock: solo si la admisión/recursos siguen siendo válidos, se convierte a hold **VIGENTE de 15 minutos** desde el instante válido de creación de intento registrado en servidor y se persiste el checkout correspondiente. Nunca prometer 15 minutos de más por retraso del proceso o del navegador.
4. Solo después de la confirmación local del hold válido se entrega la URL de Checkout Pro al comprador. Si el intento externo existe pero el recurso ya no está disponible, **no exponer URL** y dejar expediente técnico para reconciliar/cancelar una orden no pagada **solo si un contrato futuro E-08 lo autoriza**. Nunca emitir reembolso automático.
5. Respuesta perdida, reinicio PHP, respuesta externa ambigua o webhook previo al retorno: consultar correlación, procesar idempotentemente y evitar una segunda order por conjetura. La admisión breve tampoco prolonga automáticamente el hold de quince minutos.

**Alcance aprobado (D-112):** la coordinación en fases con admisión interna breve es política técnica vinculante, **sin introducir un estado E-01 ni ampliar los 15 minutos del hold**. **Pendientes de contrato físico/P0-18/P0-19/E-08:** vida máxima de admisión, tiempos de espera, recuperación de órdenes externas huérfanas y permisos del hosting; no codificar antes de cerrarlos. Jamás enviar al comprador a pagar sin hold comercial válido confirmado localmente.

**E05-E APROBADO (D-113):** no efectuar **llamadas HTTP externas dentro** de una transacción SQL abierta. Separar fases locales y externas con referencias y resultados auditables; compensar/reconciliar estado externo cuando no pueda deshacerse el pago. Una transacción SQL nunca garantiza rollback de dinero ya acreditado externamente.

## 6. Holds, expiración y cron: E05-F APROBADO

**E05-F APROBADO (D-114):** todas las consultas que asignan disponibilidad usan la **fecha efectiva de expiración guardada en servidor/BD**. Un hold vencido no consume disponibilidad aunque WP-Cron no haya ejecutado limpieza, y no renueva su plazo. El proceso diferido marca VENCIDA/limpia índices y notifica cuando proceda; no es el guardián de exclusión mutua.

- Determinar hora UTC desde fuente coherente del servidor/BD para generar fecha de inicio/fin y validar expiración; registrar las fechas, sin confiar en reloj Angular.
- Tras vencimiento lógico, reusar un recurso requiere obtener el lock de nuevo y pasar comprobación; webhook tardío no resucita hold: solo registro de acreditación e incidencia/reasignación administrada conforme D-74.
- La ejecución de WP-Cron es oportunista: si no llegan visitas ni webhooks durante horas, limpieza y notificación pueden retrasarse. La corrección de stock/cita debe mantenerse cuando se reanude el tráfico.
- Programar recuperación opcional según hosting (cron externo no obligatorio). **E-06 APROBADO (D-123…D-134)**: avisos pueden retrasarse por WP-Cron, bandeja administrativa persistente e independiente de email. **Pendientes:** intervalos, retención/privacidad E-07, llamadas/enlaces y transportes E-08 y comprobación del hosting P0-19.

## 7. Idempotencia y concurrencia humana: E05-G APROBADO

**E05-G APROBADO (D-115):** todas las mutaciones económicas y de disponibilidad poseen **clave de operación durable con ámbito de comercio/entidad/actor + tipo de operación**, hash canónico de los parámetros relevantes, estado de procesamiento y resultado recuperable.

- Mismo identificador y **mismo payload** → retornar el resultado persistido o indicar que sigue procesándose, **sin repetir efectos**.
- Misma clave y **payload distinto** → conflicto, no reutilizar operación para otro importe, otro pedido, otro horario o distinta cantidad.
- Doble clic, reintento REST, timeout, reconexión y dos pestañas convergen en operación única cuando representan el mismo intento.
- Para Mercado Pago, guardar clave enviada como X-Idempotency-Key **antes** de llamar al proveedor. Ante timeout/409/423/5xx, consultar/reconciliar la orden antes de generar clave nueva; nunca rotar una clave solo para intentar cobrar de nuevo sin saber qué pasó.
- Separar clave de operación comercial, identificador de intento, ID de orden MP, ID de movimiento y entrega de webhook: son identificadores con alcances diferentes. No reutilizar el mismo idempotency key para operaciones comerciales distintas.
- La deduplicación persiste el plazo exigido por la obligación/retención y por el sistema de pagos; **periodos exactos de conservación aún pendientes E-07/P0-26**. No dependen de sesión PHP, memoria de proceso ni navegador.

**La garantía buscada es exactamente una vez en efectos persistidos, NO exactamente una vez en la entrega física de un webhook.** Los webhooks pueden llegar repetidos o fuera de orden.

## 8. Webhooks confiables, orden y discrepancias: E05-H/E05-I APROBADOS

**E05-H APROBADO (D-116):** aceptar una notificación Checkout Pro Orders solo tras autenticar **x-signature** según el contrato oficial y comprobar correlación con cuenta/ambiente y recurso esperado. Registrar **inbox durable** (ID del proveedor, ID de recurso, acción/versión y deduplicación) y responder recepción exitosa solo si se guardó de manera segura. Si no se puede guardar, responder fallo para permitir reintento del emisor; el código y límites exactos son E-08. El reenvío de una misma notificación se considera normal, no evento financiero adicional.

**E05-I APROBADO (D-117):** al procesar inbox, obtener estado de **order/pago autoritativo** por servidor, validar cuenta/comercio, moneda PEN, importe pactado, ID de intento y evento de proveedor; reconciliar contra libro propio bajo transacción local. Retorno de navegador y cuerpo del webhook son señales para consultar, nunca acreditación definitiva.

- Un evento anterior **RECHAZADO/PENDIENTE** no degrada **APROBADO** previamente verificado; una reversión/contracargo se registra por su movimiento independiente y puede requerir revisión, no edición del cobro original.
- Duplicados y versiones fuera de orden se comparan contra estado fuente actual. Dos workers compitiendo por la misma notificación no vuelven a comprometer stock/cupo ni duplican asiento.
- Pago externo acreditado con hold vencido: si recursos perdidos, incidencia D-28; si quedan recursos, resolución auditada con **nueva asignación** D-74, nunca reanimar hold previo ni confirmar todo automáticamente.
- Incoherencia de importe/comercio/ambiente/referencia: no acreditar pedido como completamente pagado; registrar discrepancia para conciliar, sin emitir otra orden por reflejo.

**PENDIENTE E-08:** webhook definitivo de Orders, parámetros, esquema de firma, codificación exacta, eventos, consulta GET, límites, códigos HTTP, cuotas y mapeo proveedor→E-01. Ningún evento inventado se usa como prueba de dinero.

## 9. Confirmación contraentrega y caja: E05-J APROBADO

**E05-J APROBADO (D-118):** una transacción local confirma **pedido contraentrega + snapshots + compromiso de stock/cita** y obligación financiera pendiente, siempre después de validar toda la compra, elegibilidad de efectivo y disponibilidad. Sin intento MP ficticio ni hold MP de 15 minutos.

- Si falla la asignación de stock o cita, el pedido no queda confirmado como si tuviera recursos.
- Un operador registra efectivo **neto realmente aplicado** y que coincide con importe exigible D-105; comprobación de monto, permiso y versión dentro de transacción con asiento inmutable y clave de idempotencia.
- Un reintento del registro de efectivo no suma dos veces. Un monto insuficiente no crea acreditación total; conservar incidencia y permitir corrección exclusiva de administrador D-53.
- Contraentrega mixta con digital: acceso digital permanece BLOQUEADO hasta dinero efectivamente acreditado, aunque pedido CONFIRMADO y stock/cita comprometidos.

## 10. Cancelaciones, restock, cita y devoluciones: E05-K/E05-L APROBADOS

**E05-K APROBADO (D-119):** cancelación ordinaria de **línea completa** y sus efectos directos (estado, liberación de stock/cupo, reserva, ajuste comercial, auditoría y entrada de evento posterior) se realizan con **exclusión mutua sobre pedido/línea y recursos** o transacción equivalente. Impedir una segunda cancelación de la misma línea desde otro operador, administrador, pestaña o callback.

- No tocar precio pactado ni otras líneas; calcular el ajuste desde snapshot D-103, con delivery separado D-102.
- Si dinero fue acreditado, operador nunca cancela D-64; autorización del administrador y efecto monetario quedan auditados.
- **Restock de PENDIENTE/PREPARANDO/LISTO elegible:** liberar la reserva/compromiso una sola vez después de cancelación efectiva. **ENTREGADO:** nunca restock vendible por una simple solicitud: esperar recepción/inspección/resolución D-89.
- **DESPACHADO:** registrar incidencia D-62; cancelación excepcional D-75 solo tras no entrega comprobada y autorización del administrador, sin reponer stock hasta determinar disponibilidad física real.
- **Digital:** revocar acceso solo con decisión válida D-92, sin borrado del derecho/evidencia histórico por token vencido.
- **Servicio confirmado:** liberar cita solo al confirmar cancelación válida; reprogramación debe **asegurar la nueva franja antes de liberar la anterior** dentro de exclusión mutua E-01/D-94. Un fallo preserva el horario original.

**E05-L APROBADO (D-120):** reembolso externo total/parcial comprobado registrado por **administrador** con ID único de proveedor+cuenta+cobro+reembolso, importe en céntimos PEN, fecha y evidencia, sin sobrescribir cobro original. Controlar acumulado, contracargos y doble entrada dentro de una transacción; si el proveedor no entrega ID inequívoco, crear **expediente de conciliación manual** con referencia/evidencia y no asumir automáticamente unicidad por monto. Nunca usar API de ejecución de devolución.

**Contrato documental de ciclo de vida posterior (2026-11-17):** `docs/30-CICLO-DE-VIDA-ZIP-RESPALDO-RECUPERACION-P0-26-V1.md` especifica restauración de **BD + almacenamiento digital privado + configuración + versión ZIP** y conciliación de MP tras el punto de recuperación, **antes de crear nuevos cobros**. DDL y backups no son rollback transaccional ni sustituyen E-05; sin pruebas de Hostinger, migración ni pago autorizadas.

## 11. Trabajos diferidos y recuperación: E05-M/E05-N APROBADOS

**E05-M APROBADO (D-121):** persistir en la misma transacción una **entrada de trabajo pendiente** ligada al evento de negocio (outbox sencillo/local para emails/avisos y recuperación), con identificador de evento y estado de envío. Una tarea ejecutada dos veces no envía dos decisiones comerciales; si envía dos correos por un fallo entre proveedor externo y confirmación local, recuperar y deduplicar cuando el canal lo permita. **No afirmar exactly-once de correo/WhatsApp**. No agregar Redis, broker de mensajes ni servidor externo por defecto.

**E05-N APROBADO (D-122):** combinar **conciliación oportunista** al recibir webhook/consulta de pedido/operación administrativa con tarea diferida en WP-Cron y controles manuales de recuperación para administrador. La consistencia de cobro/stock/agenda no depende del cron. Backoff acotado, clasificación de errores y prioridad a pagos/retenciones ambiguas; no crear un segundo intento por timeout hasta resolver el anterior.

| Fallo | Resultado requerido según políticas aprobadas, con detalles técnicos pendientes |
| --- | --- |
| Se cae PHP antes de commit SQL | Rollback de efectos locales; si no hubo llamada externa, reintento idempotente; nunca suponer venta completada |
| Se cae PHP después de commit y antes de responder | Reintento con misma clave devuelve resultado local y no duplica pedido/cita/cobro |
| MP crea order y se pierde la respuesta | Correlación guardada → consultar/reconciliar; no generar otra order a ciegas |
| MP acredita pago y se cae antes de persistir | Webhook repetido/consulta de servidor recupera; sin doble cumplimiento |
| No hay tráfico WordPress por horas | Hold vence **lógicamente** y libera disponibilidad al siguiente acceso; cron puede estar retrasado |
| Llega webhook después de expiración | Acreditar/registrar dinero verificable; incidencia o resolución D-74, no sobreventa |
| Se desconecta proveedor de notificaciones | Evento local pendiente, reintentos acotados e indicador visible; no revertir compra |
| Falla migración SQL | Bloquear mutaciones que requieran esquema correcto y preservar datos para reparación, no seguir operando en modo inseguro |
| Tabla/engine no transaccional | **No** ejecutar checkout/reservas/cobros bajo garantías falsas; informar administración |
| Solicitud de devolución ya confirmada | No emitir reembolso desde plugin; preservar resultado externo y registrar solo una vez |

### Contrato mínimo de observabilidad propuesto

Registrar por evento: operación, pedido/línea, recurso, actor autorizado, hora UTC, versión esperada/efectiva, clave idempotente con protección, tipo de bloqueo/fallo, ID de intento/proveedor, identificador de correlación y resultado; **no** copiar claves API, dirección completa innecesaria, tokens privados ni adjuntos sensibles. Categoría del problema, recuperabilidad y próxima acción deben ser visibles para administrador (E-06/E-07). La retención del log es pendiente E-07/P0-26.

## Protección de claves y evidencias — E-07 APROBADO (D-135…D-148)

El contrato `20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md` **APRUEBA E07-A…N D-135…D-148**: verificadores protegidos de token/OTP, sin secretos en inbox/outbox/logs, mínimos eventos de descarga, backups/exports no públicos y autorización por recurso/campo. La retención de claves idempotentes, referencias MP, eventos y auditoría sigue subordinada a normativa J-04/J-05/P0-25/P0-26 y definición física E-08/P0-18.

Las políticas **D-121/D-122 y D-135…D-148** están aprobadas: el enlace de seguimiento inicial vence a 90 días y el OTP sensible a 10 minutos; esto **no** determina la retención legal de eventos ni garantiza controles de seguridad implementados. Pendientes E-08, J-04/J-05/P0-25 y comprobación de hosting.

## 12. Matriz de invariantes e índices candidatos (NO es esquema autorizado)

| Identificador lógico candidato | Invariante | Efecto requerido |
| --- | --- | --- |
| Identidad operación | (comercio, tipo de operación, clave de idempotencia) única | Un solo resultado por intento lógico/payload |
| Order externa MP | (cuenta/ambiente, ID order MP) único | No vincular misma order a dos pedidos |
| Movimiento externo MP | (cuenta/ambiente, ID pago, ID evento financiero) único | No registrar dos veces acreditación |
| Reintegro comprobado | (cuenta/ambiente, cobro fuente, ID devolución proveedor) único | No duplicar devolución parcial |
| Variante y stock | Registro estable bloqueable por variante vendible | No admitir sobreventa con consultas concurrentes |
| Recurso de agenda por día | Registro estable bloqueable aunque no haya citas | No admitir citas solapadas ni phantoms en día vacío |
| Hold / admisión | ID, recurso, cantidad/intervalo, inicio y caducidad server UTC | TTL efectivo incluso si WP-Cron falla |
| Pedido/línea | ID, versión, estado E-01, snapshot histórico y eventos | Cancelación/ajuste idempotente y auditado |
| Asiento comercial | Pago fuente, tipo, importe céntimos, referencia y causal | Saldo reconstruible, movimientos inmutables |
| Entrada inbox/outbox | Correlación, estado, intentos y próximo reintento | Recuperación tras caída o envío duplicado |

**PENDIENTE P0-18:** nombres y número de tablas, tipos SQL, migraciones, transacciones, índices, tiempos de bloqueo, versión/aislamiento, interacciones con tablas de WordPress, motor real disponible y verificación de instalación. No materializar este listado en SQL antes de cerrar el diseño físico.

## 13. Escenarios de aceptación E-05 (documentales, NO ejecutados)

| ID | Escenario | Resultado esperado / pregunta abierta |
| --- | --- | --- |
| C5-X01 | Dos compradores intentan última unidad controlada | Una sola admisión/hold válido o confirmación; otro ve agotado sin sobreventa |
| C5-X02 | Dos carritos con variante y cantidad > stock remanente | Ninguno confirma exceso; transacción decide con validación actual |
| C5-X03 | Cambia stock operador mientras otro compra | Mismo recurso bloqueado; no existencia negativa ni compromiso duplicado |
| C5-X04 | Dos compradores eligen último turno con buffers | Una sola reserva vigente/compromiso, sin solapamiento |
| C5-X05 | Calendario vacío recibe reservas simultáneas solapadas | Lock de recurso/día evita carreras sin fila de cita preexistente |
| C5-X06 | Cita cruza medianoche | Bloquear días afectados en orden estable, sin doble intervalo |
| C5-X07 | Servicio reprogramado a franja ocupada | Mantiene franja anterior; no queda cita doble |
| C5-X08 | Pagar se pulsa dos veces con misma solicitud | Un pedido/intento/clave/hold, no dos órdenes |
| C5-X09 | Se reutiliza idempotency key con otro importe | Rechazar conflicto; no usar respuesta vieja para la nueva compra |
| C5-X10 | Proveedor acepta order pero PHP pierde respuesta | Recuperar por correlación/consulta; no nuevo cargo/checkout a ciegas |
| C5-X11 | Proveedor tarda más que admisión interna corta | No exponer checkout sin hold final válido; reconciliar orden externa pendiente |
| C5-X12 | MP notifica antes de que el endpoint Pagar responda | Resolver contra ID/correlación persistente, sin saltar disponibilidad |
| C5-X13 | Falla red antes de orden externa | No afirmar hold; liberar admisión interna de manera segura |
| C5-X14 | Respuesta MP indica éxito pero hold ya no puede asignarse | Sin URL de cobro al cliente, expediente conciliable; no sobreventa ni pago ficticio |
| C5-X15 | Webhook legítimo se entrega tres veces | Un único asiento/acreditación/compromiso, inbox deduplicado |
| C5-X16 | Webhook firmado llega con datos de otra cuenta | Rechazo o cuarentena; nunca acreditar un pedido ajeno |
| C5-X17 | Webhook sin firma válida o ID manipulado | Denegar y no procesar ni revelar datos privados |
| C5-X18 | Webhook de rechazo atrasado tras aprobación verificada | APROBADO preservado; revisar contradicción sin degradar por evento viejo |
| C5-X19 | Página de retorno afirma pagado sin webhook/verificación | No acreditar hasta consulta/validación servidor |
| C5-X20 | MP confirma monto/moneda distinto al pactado | Discrepancia e incidencia, no marcar todo pagado |
| C5-X21 | Hold de 15 min vence sin visitas ni cron | Disponibilidad efectiva recuperada al siguiente acceso, sin renovación |
| C5-X22 | Pago tardío con último stock ya vendido | Dinero verificado + incidencia; no comprometer stock inexistente |
| C5-X23 | Pago tardío con recurso recuperable | Solo resolución auditada y **nueva** asignación D-74; no revivir hold anterior |
| C5-X24 | Contraentrega reserva último stock y última cita | Pedido/stock/cita comprometidos juntos o fallo coherente, dinero pendiente |
| C5-X25 | Doble clic al registrar efectivo neto recibido | Un solo cobro aplicado con auditoría |
| C5-X26 | Operador registra menos efectivo e intenta marcar completo | No acreditar saldo completo; incidencia |
| C5-X27 | Dos administradores cancelan misma línea simultáneamente | Un solo cambio y un único ajuste/liberación de recurso |
| C5-X28 | Se solicita cancelar una unidad de línea con cantidad tres | Expediente/ajuste D-95, no evento de cancelación ordinaria de cantidad |
| C5-X29 | Cancelación de última línea física con delivery | Ajuste histórico y evaluación legal de tarifa sin doble restock |
| C5-X30 | Solicitud devolución de físico ENTREGADO | No reponer vendible sin recepción/inspección/resolución D-89 |
| C5-X31 | Físico DESPACHADO no entregado | Incidencia, prueba D-75 y autorización antes de cancelar; no stock inventado |
| C5-X32 | Reprogramar con nueva franja libre tras bloqueo | Nueva asignación y liberación anterior en una operación coherente; auditoría |
| C5-X33 | Un correo transaccional falla después de confirmar pedido | Pedido permanece confirmado; aviso pendiente y recuperable, no repetir cobro |
| C5-X34 | WP-Cron se retrasa y reaparece tráfico | Limpieza/comunicación se retoma, no reviven holds vencidos |
| C5-X35 | Admin registra mismo reintegro externo dos veces | Un único evento financiero; importe/ID comprobados y auditados |
| C5-X36 | Admin registra dos reintegros parciales distintos | Se permite acumulado válido sin superar cobro original |
| C5-X37 | Contracargo se recibe después de devolución externa parcial | Conciliar sin duplicar salida de dinero ni borrar pago original |
| C5-X38 | Dos administradores intentan ajustes distintos sobre mismo cobro | Exclusión/versionado, no balance inconsistente |
| C5-X39 | Crash después de commit, antes de respuesta REST | Repetir solicitud recupera el mismo resultado, cero efectos nuevos |
| C5-X40 | Crash durante transacción de cancelación | Estado y ajuste se confirman juntos o no; sin línea cancelada y stock aún vendido por error |
| C5-X41 | Hosting usa tabla no transaccional | Operación crítica bloqueada, diagnóstico claro y ningún éxito aparente |
| C5-X42 | Migración dejó índices parciales | No aceptar checkout/citas/cobros hasta corregir esquema |
| C5-X43 | Dos pedidos mixtos compiten por misma variante y cita | Orden total de locks, sin deadlock no recuperado ni sobreventa |
| C5-X44 | Administración intenta refund externo solo por solicitud | No marca dinero devuelto ni llama API de reembolso |
| C5-X45 | Retry MP con 409/423/429/5xx | Se consulta/correlaciona orden existente y se limita reintento; no segunda order a ciegas |
| C5-X46 | Comprador pierde conexión mientras proveedor cobra | Conciliar por order y pedido con token privado, sin inventar resultado al cliente |
| C5-X47 | Token digital vence pero compra sigue pagada | Token viejo no descarga; derecho subsiste salvo revocación aprobada; reemisión E-07 |
| C5-X48 | Se deshabilita plugin con trabajos pendientes | Historial se preserva; reinicio reanuda reconciliación cuando sea seguro, sin ejecución dos veces |

**No hay simulación, test, benchmark, integración de proveedor ni pruebas de hosting ejecutadas**. Cada caso necesita operaciones de entrada, versiones, tiempo, estado anterior/posterior, auditoría, errores y evidencia cuando se autorice diseñar y comprobar el sistema.

## 14. Registro de aprobaciones E-05 — POLÍTICA TÉCNICA CERRADA

**Aprobación expresa (2026-11-17):** el propietario confirmó **«Todas A en E-05»**. Las catorce opciones recomendadas E05-A…E05-N se registran como **D-109…D-122**; las alternativas B fueron descartadas. La política técnica vinculante es la expuesta en las secciones 3…12, sin crear estados E-01 adicionales.

| Opción | Decisión | Política técnica A aprobada |
| --- | --- | --- |
| E05-A | D-109 | Tablas transaccionales críticas **InnoDB**, verificar motor/índices y bloquear operaciones críticas ante soporte insuficiente |
| E05-B | D-110 | Stock controlado protegido por fila estable de variante, con bloqueo y orden definido de adquisición |
| E05-C | D-111 | Agenda protegida por recurso/día bloqueable, incluso vacío, y revalidación de intervalos/buffers |
| E05-D | D-112 | Order MP en fases con admisión interna breve e idempotente, llamada externa sin lock SQL y hold comercial de 15 minutos solo tras intento válido y asignación confirmada |
| E05-E | D-113 | **Nunca** mantener una transacción SQL abierta durante HTTP externo; usar fases, correlación y recuperación/compensación auditable |
| E05-F | D-114 | Hold caduca por hora efectiva backend/BD, sin depender de WP-Cron ni renovación automática |
| E05-G | D-115 | Registro durable de idempotencia por operación/payload, clave MP persistida y misma respuesta para reintento idéntico; conflicto si payload diferente |
| E05-H | D-116 | Autenticar webhooks MP, registrar inbox durable deduplicada antes de confirmar recepción y consultar fuente autoritativa |
| E05-I | D-117 | Verificar pago/order y correlación contra proveedor antes de acreditar; eventos anteriores no degradan un pago verificado |
| E05-J | D-118 | Contraentrega confirma pedido, stock y cita **en una sola transacción**, efectivo posterior registrado idempotentemente |
| E05-K | D-119 | Cancelación de línea, ajuste y liberación de stock/cupo son atómicos; reprogramación asegura franja nueva antes de liberar la anterior |
| E05-L | D-120 | Reembolso externo comprobado se registra una vez por referencia/cobro con acumulado atómico; sin ID inequívoco requiere conciliación manual |
| E05-M | D-121 | Outbox local durable para avisos/trabajos, sin Redis ni broker obligatorio ni promesa de entrega exactamente una vez en canales externos |
| E05-N | D-122 | Conciliación por webhook/consulta/WP-Cron y recuperación manual administrada; cron puntual no es requisito de integridad |

**Aprobado = política de diseño, no evidencia operativa.** Continúan abiertos límites y persistencia física de admisión, orden de locks SQL, versión del motor/hosting, índices, transacciones/aislamiento, firmas y contratos exactos MP, retención de llaves/evidencia, comunicaciones e instalación/migraciones. No transformar las 48 filas C5-X en pruebas aprobadas o ejecutadas por el mero registro de estas decisiones.

## 15. Subcontratos y evidencias todavía pendientes

- **E5-T01 / P0-18:** modelo ER físico con nombres reales, tipos SQL, restricciones, índices, migración incremental, checksum/versionado, lock order completo, aislamiento y estrategia de recuperación antes/durante upgrade. Revisar límites de hosting y engine.
- **E5-T02 / P0-19:** versiones objetivo estables de WordPress/PHP/MariaDB/MySQL, motor InnoDB real, límites de conexión/timeouts y compatibilidad del hosting, con fuentes oficiales. El manual WordPress recomienda versiones modernas, pero no equivale a haberlas comprobado en el hosting.
- **E5-T03 / E-08:** contrato exacto de modalidad MP Checkout Pro Orders, firma, GET de confirmación, status/status_detail, errores 409/423/429/5xx, reintentos y representación del delivery en total/ítems. No inventar endpoints ni identidad de órdenes.
- **E5-T04 / E-06:** política **APROBADA D-123…D-134** en `19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md`: eventos, correo wp_mail, destinatarios, outbox/reintentos, bandeja, reclamaciones y estados verdaderos. D-121/D-122 siguen como fundamento técnico; **PENDIENTES** campos y retención E-07, API/transportes E-08 y verificación legal P0-25.
- **E5-T05 / E-07 APROBADO EN POLÍTICA D-135…D-148:** mínimos de auditoría, identidades invitadas, acceso admin y tokens protegidos; pendientes modelo físico, retención jurídica de idempotency keys/inbox/outbox, control REST y evidencia real E-08/J-04/J-05/P0-25.
- **E5-T06 / J-01/J-02/J-03/P0-17/P0-25:** derechos/permisos jurídicos para cancelar, reembolsar, restock, cambios de servicio, fiscalidad y referencia de documentos externos; no cerrar por precisión de SQL.
- **E5-T07 / P0-26:** instalación/activación, migraciones que fallan, recuperación, backup, reactivación, actualización ZIP, desinstalación sin pérdida de evidencia obligatoria.
- **E5-T08 / prueba futura autorizada:** casos C5-X01…C5-X48 de concurrencia, caída, proveedor, reloj, stock/agenda, reintegros, compatibilidad y restauración; definir fixtures/evidencia, pero **no ejecutar sin permiso**.

**Estado de salida:** E-05…E-07 aprobados en sus políticas D-109…D-148. **Pendientes:** P0-18/P0-19/P0-26, subcontratos E5-T01…T08, E-08, J-01…J-05/P0-17/P0-25 y verificación futura autorizada. Los C5-X/N6-X/P7-X no son pruebas ejecutadas. Codex sigue sin autorización.


## Rectificación P-A de persistencia pre-HTTP (2026-11-18)

La fase A de E05-D debe guardar **antes** del HTTP a Mercado Pago una operación técnica idempotente `pc_idempotency` con cuenta/ambiente referenciados sin credenciales, `X-Idempotency-Key`, `external_reference`, fingerprint, cotización comercial exacta, líneas, datos mínimos de contacto y versiones de condiciones informadas. `checkout_context_snapshot` se protege por finalidad E-07; no se almacenan claves de acceso ni secretos de seguimiento. La transacción local confirma esta raíz y la admisión de 60 segundos, sin crear todavía el intento E-01 CREADO o pedido E-01 PENDIENTE_PAGO: O-01/MP-01 ocurren solo cuando una order válida fue confirmada y se asigna hold local de 15 min. La fase B usa el mismo contexto congelado y nunca el carrito mutable o el precio vigente para reconstruir un cobro ambiguo. Si MP pudo crear order pero falta su ID recuperable, se bloquea nuevo cobro y se concilia por medios verificados; la existencia de la clave idempotente NO demuestra que el proveedor permita buscar por ella. Sin HTTP dentro de transacción, sin doble POST a ciegas, sin nuevo estado comercial. Ver columnas en docs/23 y errores R8-06/17 en OpenAPI vigente.

## Idempotencia de R8-12 y coherencia de expediente invitado (18-11-2026)

La solicitud `POST /guest/requests` R8-12 queda vinculada a `GuestActionRequest.idempotency_key` (16…128 caracteres), además de cookie de sesión invitada, propósito y grant OTP/identificación de **un uso, 5 minutos**. Antes de una mutación, comprobar scope y payload en `pc_idempotency` bajo lock; un resultado previo de la MISMA sesión/pedido/propósito/clave/payload puede recuperar `CaseAccepted.case_id` aunque el grant de esa operación se consumiera. A otra sesión/propósito/payload o con sesión revocada, no se expone caso ni revive el permiso; misma clave con otro payload →409. Si la clave es nueva y el grant fue consumido → denegar y exigir nueva verificación legítima.

La PRIMERA ejecución usa una transacción corta única para insertar `pc_cases` con snapshot original, primer `pc_case_events` (con `actor_kind='guest'`, actor_user_id NULL), persistir resultado de `pc_idempotency` y marcar `pc_action_grants.consumed_at`; commit todo o nada, nunca correo/webhook fuera de SQL. Un 202 contiene case_id pero significa solicitud en revisión, **no** cancelación, cambio de dirección ni reembolso ejecutado. Ante pérdida de respuesta o caída PHP, repetir exactamente el intento lógico; no crear caso adicional, no duplicar alertas outbox. Si el contenido no puede reconstruirse o se revocó la sesión, aplicar recuperación autorizada sin exponer PII.
