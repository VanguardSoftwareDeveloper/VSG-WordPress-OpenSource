**PRECEDENCIA VIGENTE D-210 (27-11-2026):** el contrato vigente es OpenAPI **0.13.0-draft (82 REST/78 rutas/162 esquemas)**, con 83 entradas R8, 62 PER, 41 tablas declarativas y 210 decisiones; el POS V1 consta en [docs/48](48-CONTRATO-POS-V1-P2.md) como **DOCUMENTADO/NO IMPLEMENTADO**. Las cifras D-200/0.12.0-draft o anteriores de este archivo quedan como corte histórico; el código y las pruebas siguen alineados con 0.12.0-draft hasta P3.

**PRECEDENCIA REST VIGENTE D-200 (18-11-2026):** OpenAPI `0.12.0-draft` con 69 REST/66 rutas/138 esquemas. La comunicación de solicitudes R8-69/70 y su resolución es parte del caso/auditoría, **no** una autorización automática a enviar un correo rutinario ni una notificación de devolución acreditada; `closed_adjusted` no acredita que Mercado Pago haya reintegrado fondos. El estado real se consulta por R8-21 a A; cualquier aviso externo exige destinatario y canal verificado conforme E-06, sin inventar email para comprador físico solo teléfono. Las políticas de avisos DOC-QA29 se conservan sin retroactividad ni cambios de alcance. Sin envíos probados.

# E-06 — Comunicaciones, notificaciones y recuperación V1

**Estado: E-06 APROBADO EN POLÍTICA FUNCIONAL Y OPERATIVA (D-123…D-134), con contrato de preferencias DOC-QA29 incorporado el 2026-11-18.** El propietario confirmó expresamente «Todas A en E-06» el 2026-11-17. Corte de referencias oficiales: 2026-11-17. Los contratos E-07/E-08 están documentados; quedan comprobaciones reales de transporte, evidencia legal, hosting y conservación aplicable en P-C/P-D. Esta aprobación documental **no autoriza** código, envíos reales, configuración SMTP, pruebas o despliegue.

**Objetivo:** fijar qué hecho confirmado genera cada aviso, destinatario, canal, contenido mínimo, permisos, prioridad y comportamiento ante fallo, sin trasladar a Codex decisiones comerciales. Separa **hecho de negocio**, **evento de comunicación**, **aceptación del transporte** y **recepción real por una persona**; no son equivalentes.

**Precedencia documental:** decisiones D-01…D-134 en 01-DECISIONES-Y-ESTADO.md; E-01 máquinas en doc.14; E-02 roles en doc.15; E-03 elegibilidad/reclamos en doc.16; E-04 importes en doc.17 y E-05 outbox/concurrencia en doc.18. **E06-A…L aprobadas como D-123…D-134**: definen política de canales/eventos y comunicación, no autorizan datos personales, endpoints, mecanismos SMTP ni prueba de recepción fuera de su alcance.

**Nota de precedencia vigente tras DOC-QA27…29 (18-11-2026):** las reglas funcionales E-01…E-07 aprobadas en este documento se conservan. Los textos anteriores que presentan E-08, DTO, capabilities, TTL o cobertura de expedientes como *pendientes de decisión* son **históricos**, no una segunda fuente de contratos: prevalecen `docs/01` D-01…D-198, `docs/41/42`, OpenAPI **0.10.0-draft (67 REST/64 rutas/134 DTO)** y `docs/23/24` (39 tablas, 56 PER). El corte D-198 fue 0.5.0-draft/117 DTO; no es la fuente vigente. Las comprobaciones de motor, proveedor, hosting, normativa por comercio y release pertenecen a P-C/P-D y no se presentan como verificadas. El usuario autorizó aquí **documentación**, no código ni ejecución.

## 1. Fuentes oficiales y hechos verificables

| Referencia | Hecho documentado | Consecuencia para PeruCommerce V1 |
| --- | --- | --- |
| WordPress wp_mail() | Un resultado TRUE significa que el medio de envío procesó la solicitud, **no** que el destinatario haya recibido el correo | No llamar ENTREGADO al estado de un email aceptado por wp_mail; el envío y la venta tienen estados diferentes |
| WordPress wp_mail_failed / wp_mail_succeeded | WordPress expone eventos para error y aceptación del mecanismo de correo | Registrar intentos y fallos sin guardar cuerpos, tokens, adjuntos o datos personales en logs |
| WordPress wp_schedule_event y Plugin Handbook Cron | Los eventos WP-Cron se despachan oportunistamente al haber visitas; no garantizan puntualidad exacta | Las alertas de pago, stock y plazos legales requieren panel persistente y recuperación; el cron es apoyo, no única defensa |
| Indecopi Libro de Reclamaciones | Respuesta escrita en hasta **15 días hábiles improrrogables**; el proveedor conserva constancias de respuesta/envío | Distinguir ingreso/constancia del reclamo de respuesta formal; avisar al administrador y conservar evidencia |
| Decreto Legislativo 1729/2026 y Ley 32495 | Reclamos digitales accesibles; canal de Libro visible conforme a norma; no imponer cargas manipulativas al consumidor | La comunicación de reclamos no puede requerir registro de cuenta ni sujetarse a marketing o pago |
| Reglamento Ley de Protección de Datos Personales, DS 016-2024-JUS | Hay límites al tratamiento publicitario/prospección y al uso del contacto del cliente | Evitar convertir correo/teléfono de venta en lista de promociones; detalle de bases legales/consentimientos en E-07 |
| Mercado Pago Checkout Pro Orders Webhooks | Las notificaciones notifican hechos para que el servidor consulte estado autoritativo; pueden reiterarse | Nunca mandar «pago aprobado» solo por retorno del navegador o cuerpo webhook no verificado |

Fuentes oficiales:
- https://developer.wordpress.org/reference/functions/wp_mail/
- https://developer.wordpress.org/reference/hooks/wp_mail_failed/
- https://developer.wordpress.org/reference/hooks/wp_mail_succeeded/
- https://developer.wordpress.org/plugins/cron/
- https://developer.wordpress.org/reference/functions/wp_schedule_event/
- https://consumidor.gob.pe/libro-de-reclamaciones/
- https://www.gob.pe/institucion/indecopi/campanas/119031-guia-como-resolver-tus-problemas-de-consumo
- https://busquedas.elperuano.pe/dispositivo/NL/2486266-3
- https://busquedas.elperuano.pe/dispositivo/NL/2457203-1
- https://www.gob.pe/institucion/minjus/noticias/1133500-entrara-en-vigencia-la-nueva-regulacion-sobre-el-consentimiento-expreso-de-las-personas-para-recibir-llamadas-publicitarias
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/notifications

**Alcance limitado:** verificar al cerrar P0-25 el reglamento definitivo aplicable, los campos/medios válidos de respuesta del Libro, calendario oficial de días inhábiles, conservación y evidencia. La entrega de un email no se prueba por la aceptación de wp_mail. Una notificación de Mercado Pago **no** es un mensaje destinado al comprador.

## 2. Invariantes ya aprobados (no reabrir)

1. **D-24:** correo exigido en carrito con digital o servicio; teléfono exigido si contiene físicos; si es mixto, ambos. Un pedido físico exclusivamente puede carecer de email: no añadir el correo como requisito oculto.
2. **D-34/D-85:** al finalizar se muestra el enlace privado de seguimiento; se envía por correo cuando existe; si solo hay teléfono, comerciante puede comunicarlo manualmente **tras identificar al comprador**. El enlace es para consultar, no para ejecutar cambios sensibles sin verificación E-07.
3. **D-09/D-40:** los digitales no se descargan antes de acreditación; enlace individual 24 h, derecho independiente de token y reemisión segura. No enviar un archivo protegido en adjuntos de correo.
4. **D-94:** cada reprogramación conserva historial/cupo nuevo verificado y **constancia de comunicación**. E-06 determinará canal y evidencia, no autorización para reprogramar unilateralmente suprimiendo derechos.
5. **D-51/D-54/D-66:** efectivo recibido y reembolso externo comprobado son hechos diferentes; no comunicar «dinero devuelto» ante solicitud pendiente o promesa.
6. **D-55/D-67…D-77:** pedido administrativo, cobro, cumplimiento por línea, citas, incidencias y reembolsos son ejes distintos. No fabricar estado PAID/DELIVERED para simplificar plantilla de correo.
7. **D-78…D-85:** operador solo ve datos mínimos y avisos operativos; administración controla Libro de Reclamaciones, credenciales, políticas y acceso masivo. Las notificaciones no pueden filtrar expedientes/saldos/archivos fuera de capacidades.
8. **D-96…D-108:** moneda PEN y precio pactado histórico; nuevas tarifas, ajustes y reembolsos se comunican como eventos separados, sin modificar el original ni enviar importes no verificados.
9. **D-115/D-121:** outbox local durable e idempotencia de eventos; no se garantiza entrega física de email/WhatsApp exactamente una vez ni se presupone broker/Redis.
10. **D-114/D-122:** WP-Cron no es requisito de integridad ni reloj de 15 minutos; notificación tardía no crea ni revive reservas.

## E-08 — API y diagnóstico de correo APROBADOS EN POLÍTICA (D-149…D-166)

El contrato `21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` ratifica rutas de bandeja administrativa filtrada, reenvío idempotente, `wp_mail()`, Libro/ARCO segregados y avisos derivados de eventos **verificados** de pago. **No** dispara correo por simple retorno de MP ni convierte éxito técnico `wp_mail()` en recepción probada. Los eventos N6 siguen siendo hechos derivados, no estados de negocio.

**Contrato vigente:** los DTO de mensajes, errores y configuración se encuentran en el OpenAPI principal; DOC-QA29 precisa la preferencia de avisos rutinarios en §11.1. **Pendientes de comprobación por instalación:** plazos legales J-04/J-05/P0-25, conservación aplicable, entrega real y compatibilidad de correo del hosting P0-19. E-08 está documentado, **no implementado ni probado**.

## 3. Canales: E06-A/E06-B/E06-C APROBADOS

**E06-A APROBADO (D-123):** la **confirmación en pantalla** y el **seguimiento privado de lectura** son canales de referencia para todo comprador, incluso si no tiene correo. Se envía email transaccional cuando el correo existe y su uso es pertinente. Para pedidos solo físicos con teléfono, permitir que el comercio informe manualmente tras verificar identidad, sin forzar correo ni aceptar que el teléfono demuestre entrega de un mensaje.

**E06-B APROBADO (D-124):** email transaccional con el **mecanismo WordPress wp_mail** como base, remitente comercial configurado por administrador y diagnóstico de disponibilidad, sin plugin SMTP de terceros ni servicio externo obligatorio por defecto. Registrar aceptación/fallo de transporte, **no entrega al buzón**. El administrador debe conocer si faltan parámetros o hay fallos, y el hosting compatible deberá verificarse en P0-19/E-08. Configurar servidor SMTP externo es una opción posterior documentada, no un requisito inventado.

**E06-C APROBADO (D-125):** V1 se limita a **mensajes transaccionales/operativos**, sin campañas de marketing, carrito abandonado, publicidad, listas de difusión, email promocional ni uso automático del teléfono para prospección. Si alguna modalidad futura introduce marketing, su consentimiento y revocación deben contratarse separadamente conforme a Ley 29733/E-07. Un aviso imprescindible de pedido o reclamo no debe depender de aceptar publicidad.

**Separación de orígenes:** un webhook MP va a backend/inbox E-05 y nunca se reenvía sin verificación directamente a un destinatario. La confirmación comercial nace de un evento local durable.

## 4. Matriz de eventos → destinatarios y mensaje (E06-D APROBADO)

**Leyenda:** B = comprador; A = administrador; O = operador (solo información operacional permitida); WEB = pantalla/seguimiento; EMAIL = cuando existe email y está disponible; PANEL = bandeja interna del comerciante; MANUAL = contacto del comercio, tras verificación. «Evento» es un disparador de notificación, NO un nuevo estado E-01.

| ID | Hecho real y condición | A quién y vía sugerida | Contenido permitido / prohibiciones |
| --- | --- | --- | --- |
| N6-01 | Pedido registrado válidamente; pago MP todavía sin acreditar | B: WEB+EMAIL si existe; A/O: PANEL | Número, resumen, total PEN y estado «pendiente de pago»; no «pagado» |
| N6-02 | Contraentrega confirmada | B: WEB+EMAIL si existe; A/O: PANEL | Pedido confirmado con **cobro pendiente**, nunca «efectivo recibido» |
| N6-03 | MP acredita pago tras verificación autoritativa | B: EMAIL si existe+WEB; A/O: PANEL | Confirmación de cobro validado, sin declarar artículos entregados |
| N6-04 | MP rechaza/cancela orden confirmado por proveedor | B: WEB+EMAIL si existe; A/O: PANEL si requiere atención | Estado verificado; no confundir con hold vencido local |
| N6-05 | Retención local vencida sin cobro verificado | B: WEB, no campaña de correo por cada carrito abandonado | Revalidar recursos; no declarar orden externa cancelada |
| N6-06 | Pago tardío acreditado pero recurso agotado | A: alerta PANEL; B: WEB+EMAIL si existe, MANUAL si no | «Pago verificado, disponibilidad en revisión»; no prometer cita/entrega ni reembolso realizado |
| N6-07 | Efectivo efectivamente recibido y registrado | B: WEB+EMAIL si existe; A/O: PANEL | Recibo interno del cobro real, sin confundir con comprobante SUNAT emitido |
| N6-08 | Línea física LISTO para recojo | B: WEB; EMAIL si existe y la preferencia rutinaria de §11.1 lo permite; O: PANEL | Recogida disponible si realmente lista; no marcar ENTREGADO |
| N6-09 | Línea física DESPACHADO o ENTREGADO | B: WEB; EMAIL si existe, tiene valor operativo y la preferencia rutinaria de §11.1 lo permite; O: PANEL | Estado real por línea y fecha; sin exponer ubicación detallada no necesaria |
| N6-10 | Incidencia logística por despacho/no entrega | A: PANEL; B: WEB+EMAIL si existe o MANUAL | Caso en atención; no cancelar automáticamente ni asegurar reintegro |
| N6-11 | Cita de servicio CONFIRMADA | B: EMAIL propuesto (correo exigido como dato por D-24), WEB; O: PANEL | Fecha, horario local, servicio y lugar; no suponer pago si contraentrega |
| N6-12 | Cita REPROGRAMADA válidamente | B: EMAIL+WEB; O/A: PANEL | Antes/después, hora local, constancia de comunicación; aprobación de nueva franja y derechos según E-03 |
| N6-13 | Cita CANCELADA conforme E-01/E-03 | B: EMAIL+WEB; O/A: PANEL | Motivo/estado real; reembolso solo si verificado |
| N6-14 | Servicio REALIZADO o NO_ASISTIO registrado | B: WEB y email solo si útil; O/A: PANEL | Hechos comprobados; no imponer penalidad automática |
| N6-15 | Digital HABILITADO tras dinero acreditado | B: EMAIL+WEB; A: PANEL si incidencia | Explicar acceso protegido y seguimiento; **sin adjuntar el archivo** ni pegar token sin modelo de seguridad E-07 |
| N6-16 | Token de descarga individual vence | B: WEB al consultarlo; sin email masivo automático | Reemisión segura disponible si derecho vigente; no decir «perdió compra» |
| N6-17 | Pedido/una línea cancelada o ajustada | B: WEB+EMAIL si existe; A/O: PANEL según permiso | Líneas afectadas, nuevo efecto/estado; histórico original conservado |
| N6-18 | Cambio de dirección propuesto o aprobado | A: PANEL si necesita autorización; B: WEB+EMAIL si existe | Diferenciar propuesta de aplicada, tarifa y aceptación si corresponde; no cobrar extra |
| N6-19 | Devolución solicitada o en tramitación externa | B: WEB+EMAIL si existe; A: PANEL | «Solicitud recibida/en trámite»; **nunca** «dinero devuelto» |
| N6-20 | Resultado externo de reintegro total/parcial **comprobado** | B: EMAIL si existe+WEB; A: PANEL | Importe PEN probado y referencia apropiada, con origen histórico; sin duplicar mensaje |
| N6-21 | Reclamo/queja ingresado en Libro nativo | B: constancia visible/copiar o descargar conforme formato; EMAIL si existe; A: PANEL | Número/fecha/constancia válida; no condicionar reclamo a pedido/email |
| N6-22 | Reclamo respondido formalmente por administrador | B: canal escrito válido y constancia; A: PANEL | Respuesta motivada y evidencia de remisión; no marcar atendido solo porque SMTP aceptó |
| N6-23 | Reclamo cercano al límite o sin respuesta | A: PANEL destacado y email admin opcional | Plazo legal a verificar con calendario hábil; no cerrar por cron |
| N6-24 | Discrepancia financiera, webhook inválido, trabajo fallido | A: PANEL prioritario y email admin opcional | ID de operación y tipo de incidente sin secretos, tokens o datos excesivos |
| N6-25 | Falló un correo transaccional | A: PANEL; B: WEB/seguimiento si aún puede consultarlo | Pedido y dinero NO retroceden; reintento controlado o contacto manual |
| N6-26 | Módulo desactivado/reactivado con avisos pendientes | A: PANEL; procesamiento posterior cuando sea seguro | Mantener outbox y expedientes; no reenviar todos sin filtros ni duplicar |

**E06-D APROBADO (D-126):** la matriz N6-01…N6-26 establece catálogo funcional de hechos relevantes; **no exige enviar correo por cada evento** ni crea estados E-01. El payload, plantilla legal, selección de canal por condición, reintentos y campos expuestos requieren E-07/E-08 y normativa P0-25.

**Hito de preparación ya previsto en N6-X32:** el aviso informativo de una línea física que entra en `PREPARANDO` pertenece al mismo seguimiento operativo y a la preferencia de §11.1. No introduce un estado E-01 ni otro grupo N6; el hecho y su consulta WEB/PANEL se conservan aunque no corresponda crear EMAIL.

## 5. Entrega, reintentos, deduplicación y trazabilidad — E06-E/E06-F APROBADOS

**E06-E APROBADO (D-127):** para cada evento de negocio confirmado registrar en outbox E-05 **una intención de comunicación por (evento, destinatario, canal, versión de plantilla)**, con idempotencia durable. Procesamiento diferido y reintentos **acotados** con espera progresiva; fallos definitivos o ambiguos pasan a bandeja administrativa. Un reenvío manual autorizado produce un nuevo intento trazable del **mismo hecho**, nunca un segundo pedido, cobro o devolución. Evitar tormentas de correo por webhooks repetidos.

**E06-F APROBADO (D-128):** distinguir funcionalmente los estados técnicos siguientes; los identificadores persistidos, transiciones y API definitivos se fijan en E-07/E-08:
- PENDIENTE: mensaje aún no tramitado.
- EN_PROCESO: intento en curso, sujeto a lease/recuperación de caídas E-05.
- ACEPTADO_POR_TRANSPORTE: wp_mail/medio afirma haber procesado el mensaje; **no** implica recepción confirmada por el destinatario.
- REINTENTO_PROGRAMADO: error que admite recuperación.
- FALLO_FINAL: se agotaron intentos o error permanente; administrador debe revisar.
- OMITIDO_SIN_DESTINO: no hay correo válido/medio permitido; la compra sigue accesible en WEB y puede requerir contacto manual.
- REQUIERE_EVIDENCIA: constancia legal de reclamación/aviso aún no acreditada y no se puede dar por cumplida.

**Garantía realista:** email aceptado puede perderse y correo entregado puede ir a spam. No prometer exactly-once ni lectura/entrega si el transporte no aporta acuse fiable. El correo fallido no revierte un pedido pagado ni prorroga un hold. La duración de leases, intentos, ventana y retención pertenece a E-07/E-08, sin números inventados.

## 6. Panel interno, prioridad y quién puede actuar — E06-G APROBADO

**E06-G APROBADO (D-129):** bandeja interna duradera y **fuente operativa principal** de tareas/alertas, visible según política E-02. Priorizar:
- CRÍTICA: pago acreditado sin recurso, discordancia de importe/moneda, devolución externa dudosa, falla persistente de conciliación, riesgo de entregar sin cobro, acceso a reclamación o evidencia legal faltante.
- REQUIERE_ACCION: nueva cita/cambio, producto pendiente de despacho, incidencia logística, reclamo sin respuesta, correo obligatorio fallido.
- INFORMATIVA: pedido confirmado, pago verificado sin problema, entrega normal, mensaje procesado.

**Permisos:** el administrador ve y resuelve incidencias sensibles y reclamos; el operador ve solo avisos necesarios para pedidos, entrega, stock y agenda, sin expedientes completos del Libro, credenciales o documentos privados. Leer aviso no concede permiso de mutar pedido ni de marcar una devolución como realizada. **No archivar automáticamente** una obligación legal al leerla.

**Ausencia de envío:** un correo administrativo opcional que falla nunca oculta el incidente de la bandeja. La entrega de notificaciones externas es auxiliar respecto del panel y de la fuente de verdad del pedido.

## Identidad de destinatario y exposición de enlaces — E-07 APROBADO (D-135…D-148)

El contrato `20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md` **APRUEBA E07-A…N (D-135…D-148)**: seguimiento de 256 bits y 90 días/solo lectura, OTP de 8 dígitos/10 min/5 intentos para acciones sensibles con correo, verificación manual de teléfono y descarga independiente de 24 h. El email **es un dato de contacto, no prueba por sí solo identidad ni recepción**; no adjuntar archivos protegidos ni secretos. E-08 debe concretar transporte/REST.

E-06 D-123…D-134 y E-07 D-135…D-148 fijan mensajes mínimos, alcance de enlaces y minimización de datos; **pendientes E-08/J-04/J-05/P0-25:** campos finales, URLs/cookies, retención y finalidad/consentimiento legal por caso, prueba de destinatario y transporte real.

## 7. Mensajes al comprador, enlaces privados y seguridad — E06-H APROBADO

**E06-H APROBADO (D-130):** mensajes transaccionales breves en español de Perú, importe original/ajustes en PEN y horarios en la zona local configurada, con fecha y hora absolutas. Usar datos estrictamente necesarios:
- Número público no correlativo si se justifica, nombre del comercio, hecho y estado verdadero, canal de ayuda/Libro.
- No enviar credenciales MP, claves internas, ID de orden privado, tokens administrativos, notas internas, datos fiscales de terceros, documentos protegidos, direcciones completas o datos médicos.
- Para seguimiento, mostrar/enviar enlace privado D-34 con **vencimiento inicial 90 días D-137**, rotación/revocación y recuperación segura. No reutilizarlo como token digital de 24 horas ni en canales de terceros. E-08 fijará cabeceras, URL y sesión.
- Los mensajes no contienen adjuntos de productos digitales ni comprobantes SUNAT que no hayan sido emitidos realmente.
- El remitente/dominio y branding pertenecen al comercio autorizado, sin utilizar identidad de Mercado Pago o SUNAT como si el mensaje proviniera de ellos.

**E-07 APROBADO EN POLÍTICA:** privacidad por finalidad, minimización, destinatarios y verificación. **PENDIENTE J-04/J-05/P0-25/E-08:** base jurídica por mensaje, conservación, entrega real, derechos y evidencia de respuesta del Libro. El email no sustituye historial ni documento original.

## 8. Reservas y servicios — E06-I APROBADO

**E06-I APROBADO (D-131):** avisos esenciales para líneas de servicio: CONFIRMADA, REPROGRAMADA, CANCELADA y cambios relevantes de hora/local, con correo al comprador por D-24 (salvo error de correo que abre alerta) y consulta WEB. Reprogramación debe conservar **constancia del aviso** D-94 y reflejar hora anterior/nueva; nunca cambiar la cita en silencio. Recordatorios automáticos de cita **fuera del alcance V1 recomendado** para reducir ruido; su incorporación posterior exige una decisión adicional, sin inferir envío puntual de WP-Cron ni cobro/penalidad por inasistencia.

Para servicios presenciales, indicar dirección del local y la hora local del comercio; evitar invitaciones externas obligatorias (Google Calendar/WhatsApp) y no confundir reserva confirmada contraentrega con dinero recibido. Un email no confirma por sí solo disponibilidad: el cupo lo decide la transacción E-05.

## 9. Libro de Reclamaciones y plazos — E06-J APROBADO EN POLÍTICA

**E06-J APROBADO (D-132):** el Libro nativo debe mostrar al consumidor **constancia inmediata** de recepción y número de seguimiento, y facilitar copia/descarga de la constancia según la forma legal aplicable. Enviar copia por correo solo cuando haya dirección; nunca exigir compra, cuenta, marketing o correo no indispensable para que el consumidor use el canal legal. Si se usa **enlace externo** del Libro, el proveedor externo responde por su propio formato y acuse; PeruCommerce solo presenta el acceso configurado y no afirma haber registrado un expediente que reside fuera.

- Plazo de respuesta informado por Indecopi: **15 días hábiles improrrogables**. Registrar momento real de ingreso, fecha objetivo calculada por calendario válido y evidencia de respuesta, con pendiente legal J-01/P0-25 sobre días no laborables, forma de responder y plazos especiales.
- Bandeja del administrador muestra pendientes, próximos al plazo y vencidos por consulta a la fecha actual, **no solo por WP-Cron**. Alertas por email admin son opcionales y no prueban cumplimiento.
- Una respuesta formal debe ser escrita y tener evidencia de remisión por canal legal válido. **La aceptación técnica de wp_mail no demuestra que el consumidor la recibió**; conservar constancias adicionales cuando correspondan. No marcar «respondido en plazo» únicamente por hacer clic en «enviar» si no hay evidencia adecuada.
- Fallo del envío/copia no debe hacer desaparecer el reclamo. Abrir incidente de comunicación y gestión manual prioritaria.
- Si falta calendario normativo verificado, no inventar una fecha de vencimiento exacta ni afirmar «dentro del plazo»; mostrar alerta al administrador para revisión.

## 10. Compras solo físicas, teléfono y WhatsApp — E06-K APROBADO

**E06-K APROBADO (D-133):** compras físicas solo con teléfono: confirmación y seguimiento WEB, con contacto manual del comercio **después de verificar identidad**, datos mínimos y constancia auditable cuando corresponda. No imponer email adicional. V1 **no** automatiza WhatsApp Business API, SMS, llamadas ni enlaces masivos, y no incorpora el atajo manual de WhatsApp de la alternativa B. Integraciones futuras requerirían aprobación específica de costos, permisos y tratamiento de datos.

No afirmar que un contacto por teléfono prueba aceptación de nuevas condiciones, entrega del reclamo ni recepción de un correo. La canalización legal del Libro se respeta aunque el pedido original solo tenga teléfono.

## 11. Configuración, idioma y edición de plantillas — E06-L APROBADO

**E06-L APROBADO (D-134):** plantillas transaccionales V1 **integradas y versionadas**, en español de Perú, con personalización limitada por administrador de nombre comercial, remitente válido, datos de contacto y firma/branding simple. No ofrecer editor HTML libre, reglas de automatización programables ni plantillas por plugin de terceros en V1. Las comunicaciones estrictamente legales tienen cuerpo/formato sujeto a normativa y no pueden ser desactivadas arbitrariamente.

- Configuración opcional de notificaciones de hitos rutinarios al comprador mediante el único interruptor `routine_buyer_emails_enabled` definido en §11.1, pero **no desactivar** el registro histórico, confirmación en pantalla, seguimiento, constancia del Libro ni alertas críticas para el administrador.
- Si email no está configurado o falla, avisar en panel; no impedir una compra físicamente válida solo porque su mensaje informativo falló. En venta digital/servicio, el correo sigue siendo dato obligatorio del comprador, pero su caída técnica no equivale automáticamente a venta cancelada.
- Registrar versión de plantilla y del destinatario efectivamente usado, sin retener el cuerpo completo ni secretos más allá de la finalidad legal.
- No prometer envío con horario exacto sin infraestructura comprobada ni imponer dependencias de email/SMS/WhatsApp externas no aceptadas.
- E-07 determina datos, consentimiento, retención y acceso al historial de comunicaciones; E-08 define entrada/salida, fallos, personalización y validación concreta.

### 11.1 Preferencia de avisos rutinarios — DOC-QA29, OpenAPI 0.10.0-draft

`routine_buyer_emails_enabled` es **un booleano por comercio**, con valor inicial `true`. Se lee siempre en `AdminSettings` mediante R8-45 y en la respuesta de R8-46. En `SettingsPatch` es opcional: omitirlo conserva el valor; `false` desactiva los nuevos correos rutinarios y `true` vuelve a permitirlos bajo las condiciones de canal/destinatario existentes. No acepta `null`, cadenas ni un objeto de reglas. Solo A con `perucommerce_settings_manage` puede consultarlo o cambiarlo; no se publica en R8-01 ni se expone a O.

**Alcance cerrado del interruptor:** únicamente emails informativos al comprador por hitos de línea física `PREPARANDO` (N6-X32), `LISTO` (N6-08) y `DESPACHADO`/`ENTREGADO` (N6-09). No modifica la elegibilidad del pedido, las cantidades, el estado de cumplimiento ni la obligación de registrar esos hechos. Los hitos de pago, confirmación inicial, incidencias, cancelaciones/ajustes, devoluciones, citas, acceso digital, Libro/ARCO y comunicaciones legales quedan fuera del interruptor; conservan sus reglas originales. WEB, PANEL, historial y alertas críticas permanecen disponibles. `true` tampoco obliga a enviar un email irrelevante o sin destino válido.

**Persistencia y concurrencia:** guardar el booleano dentro de la opción comercial versionada de WordPress, no autoload y con el mismo CAS, `expected_version`, idempotencia y auditoría de R8-46. La respuesta devuelve el valor efectivo y la nueva `row_version`. Cambiar solo esta preferencia no crea versiones de condiciones/privacidad en `pc_policy_versions` ni invalida el consentimiento histórico; esas versiones cambian únicamente cuando se modifican los textos correspondientes. No se añade tabla, endpoint, capability ni editor de automatizaciones.

**Efecto prospectivo y decisión durable por evento:** evaluar la preferencia una sola vez al decidir, en la transacción del hecho de negocio, si se crea la primera intención EMAIL rutinaria. Registrar el valor y `row_version` de configuración efectivamente leídos junto con la identidad del evento. Para una intención EMAIL creada, persistir `pc_outbox.safe_payload.routine_email_decision` con `enabled` (booleano) y `settings_row_version` (versión CAS); si el valor es `false`, no crear esa intención EMAIL y conservar la misma decisión de omisión en el `safe_payload` de la intención PANEL del mismo evento o en la auditoría correlacionada existente, de forma recuperable e idempotente. El registro no contiene cuerpo de correo, PII adicional ni secretos. Un replay recupera la decisión original por evento y canal; no vuelve a consultar la preferencia para crear un correo que antes se omitió. La omisión por preferencia no es un fallo de `wp_mail`, no equivale a `OMITIDO_SIN_DESTINO` y no genera una alerta de transporte fallido.

**Cola, reintentos y reenvío:** las intenciones creadas antes del cambio conservan su decisión y siguen sus reglas de destino, autorización, vigencia, deduplicación y reintento. El worker no las cancela ni duplica por reinterpretar el nuevo booleano. Los intentos ya aceptados por transporte mantienen su resultado histórico. R8-40 solo reenvía una intención existente y elegible, bajo su versión y permisos, con la misma decisión original; no crea una intención EMAIL para un evento omitido. Si una entrada PANEL representa esa omisión, su lectura indica «Correo rutinario no generado por configuración» y `can_resend=false` para ese email. Reactivar la preferencia afecta eventos nuevos y **no reconstruye ni envía en lote** los avisos anteriores omitidos. No se agregan estados de negocio o estados técnicos de notificación.

**Texto de ayuda del panel:** «Se aplica a los nuevos avisos de preparación, recojo, despacho y entrega. No cancela correos ya en cola. Las confirmaciones, pagos, incidencias y comunicaciones obligatorias se mantienen». El control no promete detener un envío en curso, revocar un correo entregado ni acreditar recepción.

## 12. Escenarios E-06 — especificación documental, NINGUNO EJECUTADO

| ID | Escenario | Resultado aprobado existente o expectativa propuesta |
| --- | --- | --- |
| N6-X01 | Físico puro: teléfono sin correo | Compra válida; confirmación WEB y enlace privado; no exigir email (D-24/D-34) |
| N6-X02 | Mixto con digital y físico | Correo y teléfono obligatorios según líneas; no duplicar confirmación por carrito |
| N6-X03 | Pedido MP creado, dinero todavía pendiente | Mensaje dice pendiente; no «pagado» |
| N6-X04 | Retorno visual MP parece aprobado | No mandar confirmación de cobro hasta verificación backend |
| N6-X05 | Webhook MP llega dos veces | Una intención por evento/destinatario/canal; duplicado no genera aviso distinto |
| N6-X06 | Pago verificado tardío sin stock | Panel crítico, comprador recibe estado de incidencia sin promesa de entrega/reembolso |
| N6-X07 | Retención venció sin cron | WEB muestra vencido efectivo, no depende de recordatorio ni afirma cancelación MP |
| N6-X08 | Contraentrega confirmada y aún no cobrada | No comunicar «pago recibido» ni habilitar archivo digital |
| N6-X09 | Operador registra efectivo completo | Notificar únicamente pago real acreditado con evento idempotente |
| N6-X10 | Intento de cobro falló y reintentó sin saber estado | No enviar mensaje de dos cobros ni nuevo enlace externo a ciegas |
| N6-X11 | Se habilita producto digital pagado | Correo con instrucciones de acceso, sin archivo adjunto ni credencial administrativa |
| N6-X12 | Token de descarga de 24 h vence | No enviar «su compra expiró»; derecho se verifica para reemisión |
| N6-X13 | Servicio CONFIRMADO con pago contraentrega pendiente | Informar cita confirmada y cobro pendiente, ambos correctamente separados |
| N6-X14 | Servicio se reprograma a otra hora | Historial, nuevo horario válido y constancia de aviso; email si existe |
| N6-X15 | Cambio de cita falla por cupo ocupado | No enviar confirmación de nueva hora; original sigue válido |
| N6-X16 | Se registra NO_ASISTIO | No mandar multa/penalidad automática |
| N6-X17 | Solo físico, artículo LISTO para recojo | Seguimiento WEB disponible; email solo si existe y la preferencia rutinaria lo permite al crear la intención, contacto manual si es necesario |
| N6-X18 | Dirección nueva aumenta tarifa después del pago | No solicitar segundo cargo automático; comunicar solicitud/revisión/admin |
| N6-X19 | Se cancela una línea de un mixto | Aviso solo de líneas afectadas; no afirmar cancelación total |
| N6-X20 | Solicitud de reembolso parcial registrada pero no ejecutada | Mensaje «en trámite», no «S/ X devueltos» |
| N6-X21 | Resultado de reintegro externo parcial verificado | Informar monto PEN comprobado, no mutar total original |
| N6-X22 | Administrador reenvía email que falló | Reintento auditable del mismo evento, sin repetir cobro ni orden |
| N6-X23 | wp_mail devuelve TRUE pero cliente no recibe correo | Estado ACEPTADO_POR_TRANSPORTE, nunca ENTREGADO sin prueba |
| N6-X24 | wp_mail devuelve FALSE | Outbox registra error recuperable/definitivo y panel de alerta, pedido sigue intacto |
| N6-X25 | Sin tráfico WordPress durante horas | Alertas panel se calculan al consultar, cron retrasado no libera ni inventa pago |
| N6-X26 | Operador intenta leer respuesta completa del Libro | Denegado conforme D-84, aunque haya alerta de reclamo |
| N6-X27 | Consumidor reclama sin pedido ni correo | Canal Libro accesible; constancia visible, canal alternativo de respuesta conforme ley |
| N6-X28 | Reclamo creado y email de copia falla | Reclamo persiste, constancia disponible, alerta admin |
| N6-X29 | Reclamo próximo a 15 días hábiles | Panel alerta incluso sin WP-Cron; fecha se valida con calendario legal |
| N6-X30 | Se envía respuesta formal con wp_mail TRUE | No equiparar éxito técnico con entrega evidenciada de la respuesta legal |
| N6-X31 | Libro externo configurado | PeruCommerce enlaza al proveedor, no inventa reclamo local ni número propio |
| N6-X32 | Comercio desactiva emails rutinarios de preparación, recojo, despacho o entrega | R8-46 guarda `routine_buyer_emails_enabled=false`; R8-45 recupera el valor. Los nuevos eventos no crean EMAIL rutinario, pero preservan decisión idempotente, WEB/PANEL e historia. La cola anterior mantiene su decisión; no se desactivan confirmaciones, pagos, citas, digitales, constancias ni alertas críticas |
| N6-X33 | Email de administrador incluye expediente completo por defecto | Denegar/omitir: enlace al panel con autorización, solo resumen mínimo |
| N6-X34 | Comprador no aceptó marketing | Avisos transaccionales pertinentes no se convierten en promociones; no campaña |
| N6-X35 | Se reactivó el plugin con outbox acumulada o se volvió a habilitar el correo rutinario | Recuperación de intenciones existentes con claves, límites y decisión original; ningún replay ni reactivación crea emails de eventos omitidos por preferencia |
| N6-X36 | Un correo contiene enlace de seguimiento de invitado | Consulta limitada; reemisión/cambio/devolución requieren verificación E-07 |
| N6-X37 | El comprador cambia de dirección de email luego del pedido | No reenviar historia a destino nuevo sin verificación de identidad y autorización |
| N6-X38 | Falla del servidor de correo con pago acreditado y digital habilitado | No revertir pago ni derecho; mostrar acceso autorizado WEB y alerta de email para resolución |

**Los 38 N6-X son escenarios documentales NO EJECUTADOS**: no hubo envío, recepción real, pruebas de hosting ni comprobación legal. Las decisiones E06-A…L generan criterios A-139…A-150 en doc.08; N6-X no se convierten automáticamente en pruebas ni resultados.

## 13. Registro de aprobación E-06 — POLÍTICA DE COMUNICACIONES CERRADA

**Confirmación expresa:** el propietario aprobó **«Todas A en E-06»** el 2026-11-17. Las 12 decisiones se registraron como **D-123…D-134**, con criterios de aceptación **A-139…A-150**. Las alternativas B quedan descartadas.

| Origen | Decisión | Política A aprobada |
| --- | --- | --- |
| E06-A | D-123 | Confirmación/seguimiento WEB para todos, correo transaccional cuando exista, sin imponerlo a compras solo físicas |
| E06-B | D-124 | Correo mediante WordPress `wp_mail()`, remitente configurado por administrador, sin SMTP ni plugins de terceros obligatorios por defecto |
| E06-C | D-125 | Solo notificaciones transaccionales/operativas en V1; excluir marketing, campañas y carrito abandonado |
| E06-D | D-126 | Catálogo N6-01…N6-26 activado por hechos persistidos, sin correos por cada paso irrelevante |
| E06-E | D-127 | Outbox E-05 con intención idempotente por evento/destinatario/canal/plantilla, reintentos acotados y reenvío manual auditado |
| E06-F | D-128 | Diferenciar aceptación del transporte de recepción acreditada; fallos no revierten pagos/pedidos y se informan en panel |
| E06-G | D-129 | Bandeja interna persistente con alertas por prioridad y visibilidad mínima por rol, independiente del email |
| E06-H | D-130 | Mensajes con información mínima y enlaces protegidos, sin adjuntos digitales ni secretos/expedientes protegidos |
| E06-I | D-131 | Avisos de cita confirmada/reprogramada/cancelada con constancia, sin recordatorios automáticos adicionales V1 |
| E06-J | D-132 | Libro nativo con constancia accesible, evidencia de respuesta y alertas de plazo legal, sin depender solo de correo/cron |
| E06-K | D-133 | Pedidos solo físicos: contacto telefónico manual previa verificación; sin SMS ni WhatsApp automatizado o atajo WhatsApp en V1 |
| E06-L | D-134 | Plantillas transaccionales versionadas es-PE con personalización administrativa limitada; preferencia rutinaria prospectiva de §11.1; requisitos/evidencias no desactivables arbitrariamente |

**Límites de aprobación:** E06-D no exige enviar email por todos los eventos. E06-F no garantiza entrega al buzón por `wp_mail()` exitoso. E06-J **no** demuestra conformidad legal del Libro. E06-I excluye recordatorios automáticos de citas V1. Las comunicaciones no cambian estados de pago/pedido ni autorizaciones E-02.

**Estado contractual del corte histórico DOC-QA29:** E-08 y OpenAPI 0.10.0-draft documentaban API, errores y preferencia rutinaria; DOC-QA29 concreta D-134 sin otra decisión comercial. **Pendientes de implementación y comprobación:** aplicación J-01/J-02/J-04/J-05/P0-25 del Libro y datos, correo del hosting P0-19, outbox SQL/migración/retención/backup P0-18/P0-26 y funcionamiento de los consumidores. **E-07 ya aprobó identidad/contactos y retención por categoría**, no plazos jurídicos exactos. Los 38 N6-X no se ejecutaron.

**Estado de salida: E-06 CERRADO EN POLÍTICA FUNCIONAL/OPERATIVA (D-123…D-134).** No se autorizó enviar correos, contactar compradores, activar SMTP, compilar, probar ni implementar.
