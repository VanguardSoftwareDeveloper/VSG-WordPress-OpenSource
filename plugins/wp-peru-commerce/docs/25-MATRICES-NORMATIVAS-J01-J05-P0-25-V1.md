# P0-25 — Matrices normativas peruanas J-01…J-05 para PeruCommerce V1

**Corte de consulta:** 2026-11-17 (Perú).  
**Estado:** INVESTIGACIÓN DOCUMENTAL CON FUENTES OFICIALES, **no dictamen jurídico, no prueba de cumplimiento, no contrato de campos definitivo ni implementación**. La opción D-174 aprobó el MÉTODO de matrices; esta selección de supuestos/validaciones se propone para revisión. No se ejecutaron trámites, SQL, pruebas, correos, servicios ni pagos.

**Aplicación documental en P0-23:** `docs/openapi/peru-commerce-v1.openapi.draft.json` (0.2.2-draft, 90 esquemas DTO) propone campos en `ComplaintCreate`, `PrivacyResolution`, `FiscalSettings`, etc.; **NO** establece por sí mismo los datos legalmente obligatorios, validación de identidad ni requisitos finales de formulario. Deben cotejarse con J-01…J-05 aplicables a cada comerciante y con fuente vigente antes de implementación.

**Estado posterior D-192 (2026-11-17):** **CERRADA EN POLÍTICA DE APLICABILIDAD CONDICIONAL**, no en certificación jurídica universal. El formulario del Libro (reclamo/queja y datos del Anexo I) no impone DNI, RUC, correo o compra previa al checkout general. ARCO, consumidor, documentos externos e incidentes se evalúan por sujeto, responsable, régimen y actividad concreta; se preservan evidencias y plazos legales según norma vigente. RM 244-2026-PCM publicada en julio 2026 es **proyecto**, no prueba de reforma definitiva. La ficha pública consultada no demuestra un decreto posterior; antes de liberar el Libro nativo debe verificarse el texto consolidado aplicable. La distinción LEG-04/05 quedó especificada por consultas protegidas R8-65/66 con D-189. OpenAPI 0.4.0-draft cuenta con 65 REST/62 rutas/115 DTO. No se simula SUNAT ni se declara cumplimiento de una instalación.

## 1. Frontera del producto y reglas vinculantes

PeruCommerce V1 es un plugin WordPress para **un comercio y local**, que vende físicos, digitales y servicios presenciales en un mismo carrito. WordPress/PHP calcula precio final en PEN y controla stock/cita, mientras Angular monta las interfaces; el plugin no es emisor SUNAT, ni asesor tributario, ni plataforma de cumplimiento jurídico certificada. Emisión de boletas/facturas/RHE, notas de crédito y reembolsos se hace por el titular **fuera del plugin**, guardando evidencia/verificación; Mercado Pago sólo gestiona un cobro inicial cuando el medio sea compatible.

Mantener sin reinterpretar E-01…E-08 (docs 14…21), D-167 monetario y D-174 legal (docs/22). P0-18 documentado en docs/23 y R8↔PER/OpenAPI **parcial** en docs/24 y docs/openapi. Este documento no crea permisos, endpoints ni nuevas máquinas de estados.

**Clasificación de filas:** BASE VERIFICADA = respaldada por fuentes oficiales para el supuesto expresado; CONDICIONAL = requiere conocer actividad/emisor/circunstancias; POLÍTICA APROBADA = decisión de producto, no certificación; PARCIAL = exige más concreción normativa/operativa; PROYECTO = no norma vigente; PENDIENTE = no se completó la comprobación del caso.

## 2. Registro de fuentes oficiales (acceso documental 2026-11-17)

| Ref | Fuente/alcance | Fecha documental | URL oficial |
| --- | --- | --- | --- |
| F01 | Ley 32495, arts. 150-151, plataformas digitales y Libro | 11-11-2025 | https://busquedas.elperuano.pe/dispositivo/NL/2457203-1 |
| F02 | DS 011-2011-PCM, Reglamento del Libro y modificatorias, arts. 6 y 12 (copia consolidada SAT) | texto con modificatorias, confirmar posterior | https://www.sat.gob.pe/Transparenciav3/Portals/0/Docs/NormasOtrasEntidades/DS_011-2011-PCM_v3.pdf |
| F03 | Indecopi, guía del Libro, requisitos, plazo 15 días y SIREC | consultada 17-11-2026 | https://consumidor.gob.pe/libro-de-reclamaciones/ |
| F04 | RM 244-2026-PCM: publicación de PROYECTO de modificación reglamentaria | 17-07-2026 | https://www.gob.pe/institucion/pcm/normas-legales/8406634-244-2026-pcm |
| F05 | DL 1729: atención de reclamos digitales art.24.4/24.5 y prácticas coercitivas art.56.1.h | 12-02-2026 | https://busquedas.elperuano.pe/dispositivo/NL/2486266-3 |
| F06 | Indecopi: promociones/outlets no excluyen remedios por falta de idoneidad | 16-06-2026 | https://www.gob.pe/institucion/indecopi/noticias/1407060-tiendas-por-departamento-no-pueden-impedir-cambios-o-devoluciones-de-productos-adquiridos-en-sus-outlets |
| F07 | SUNAT: regímenes NRUS/RER/RMT/Régimen General | consultada 17-11-2026 | https://emprender.sunat.gob.pe/ruc/regimenes-tributarios-mype/regimenes-tributarios |
| F08 | SUNAT: tasa conjunta 18%, composición IPM/IGV por año | consultada 17-11-2026 | https://emprender.sunat.gob.pe/principales-impuestos/impuesto-general-las-ventas-igv/impuesto-general-las-ventas |
| F09 | SUNAT: Nuevo RUS, boletas/tickets y alcance | consultada 17-11-2026 | https://emprender.sunat.gob.pe/ruc/regimenes-tributarios-mype/nuevo-regimen-unico-simplificado-nuevo-rus |
| F10 | SUNAT: RER, boletas y facturas electrónicas | consultada 17-11-2026 | https://emprender.sunat.gob.pe/ruc/regimenes-tributarios-mype/regimen-especial-renta-rer |
| F11 | SUNAT: RMT, comprobantes y guías cuando corresponden | consultada 17-11-2026 | https://emprender.sunat.gob.pe/ruc/regimenes-tributarios-mype/regimen-mype-tributario |
| F12 | SUNAT Personas: recibos por honorarios de independientes, cuarta categoría | consultada 17-11-2026 | https://personas.sunat.gob.pe/trabajador-independiente/comprobantes-pago-registro |
| F13 | SUNAT: notas de crédito externas para anulaciones/ajustes/devoluciones | consultada 17-11-2026 | https://www.gob.pe/17414-emitir-nota-de-credito-electronica |
| F14 | Ley 29733 y Reglamento DS 016-2024-JUS (ANPD) | vigente desde 31-03-2025 | https://www.gob.pe/institucion/anpd/normas-legales/6554453-16-2024-jus |
| F15 | ANPD: inscripción gratuita de bancos de datos por titular responsable | actualización 22-05-2026 | https://www.gob.pe/8060-inscribir-banco-de-datos-en-el-registro-nacional-de-proteccion-de-datos-personales |
| F16 | ANPD: derechos ARCO, 20 días acceso y 10 días rectificación/cancelación/oposición | consultada 17-11-2026 | https://www.gob.pe/9269-iniciar-procedimiento-para-el-ejercicio-de-derechos-de-acceso-rectificacion-cancelacion-y-oposicion |
| F17 | ANPD: comunicación de flujos transfronterizos, requisitos | actualización 2026 | https://www.gob.pe/9253 |
| F18 | PCM/MINJUSDH: reporte de incidentes de seguridad de información | 19-09-2025 | https://www.gob.pe/institucion/pcm/noticias/1249567-pcm-y-ministerio-de-justicia-presentan-herramienta-digital-para-reportar-incidentes-de-seguridad-y-proteger-datos-personales |
| F19 | SUNAT: TUO Código Tributario, art. 87.7, conservar 5 años o prescripción mayor | actualización del PDF hasta 16-05-2025 | https://www.sunat.gob.pe/legislacion/codigo/textoCompleto-TUO-CT.pdf |
| F20 | MINJUSDH: regla de reporte de incidentes de datos a ANPD cuando corresponde | orientación oficial | https://www.gob.pe/institucion/minjus/noticias/1099347-minjusdh-realizo-conferencia-virtual-para-explicar-aspectos-de-la-ley-de-proteccion-de-datos-personales-y-su-nuevo-reglamento |
| F21 | Indecopi: Tu Libro de Reclamaciones, herramienta gratuita | 24-11-2025 | https://www.gob.pe/institucion/indecopi/noticias/1296574-los-emprendedores-ya-pueden-generar-su-libro-de-reclamaciones-de-manera-rapida-y-sencilla-sin-costo-alguno |
| F22 | SUNAT: cálculo del IGV, cargos complementarios y afectación de base imponible | consultada 17-11-2026 | https://orientacion.sunat.gob.pe/3109-05-calculo-del-impuesto |
| F23 | Indecopi: campaña sobre reclamos digitales | 2026 | https://www.gob.pe/institucion/indecopi/campa%C3%B1as/119031-no-estas-solo-resolvemos-tus-problemas-de-consumo |

**Advertencia normativa 2026:** la RM 244-2026-PCM (F04) publica **un proyecto** de reforma del Reglamento del Libro, no demuestra que exista un decreto definitivo posterior. La búsqueda realizada no acreditó un texto definitivo posterior a la consulta; por ello la verificación de vigencia y transición sigue siendo requisito previo a V1. La reforma por **Ley 32495** y el **DL 1729** sí son normas publicadas.

## 3. Matrices de aplicabilidad, responsabilidad, campo y evidencia

Columnas: ID único; condición que activa obligación; norma/guía; obligación verificable; obligado y rol del plugin; consumidor/UI/persistencia; fuentes; estado de evidencia. **No todas las filas aplican a cualquier comerciante**.

### J-01 — Libro de Reclamaciones

| ID | Cuándo aplica / sujeto | Norma o criterio | Requisito a satisfacer | Responsable | UI / registro / evidencia del plugin | Fuente | Estado |
| --- | --- | --- | --- | --- | --- | --- | --- |
| J01-01 | Proveedor mediante plataforma de comercio electrónico | Ley 29571 arts.150/151 modificados por Ley 32495 | Libro físico o virtual y enlace permanente visible y fácil de acceder | Comerciante; plugin facilita enlace nativo/externo | R8-15 / catálogo / checkout / footer; constancia de enlace accesible | F01,F03 | BASE VERIFICADA |
| J01-02 | Opción nativa activada | DS 011-2011-PCM + formato y anexos aplicables | Hoja con identificación del proveedor/consumidor, bien o servicio, reclamo o queja, pretensión, fechas y correlativo conforme modelo vigente | Comerciante determina datos exigidos; plugin valida y guarda campos sólo legalmente necesarios | R8-15, pc_complaints, numeración, copia de hoja | F02,F03 | PARCIAL — campos exactos sujetos a reglamento vigente |
| J01-03 | Toda persona que reclama, compre o no | Ley 29571 y guía Indecopi | No exigir cuenta WordPress, pedido, pago ni OTP comercial para presentar reclamación | Plugin | R8-15 público, protección contra abuso sin trabas indebidas | F01,F03 | BASE VERIFICADA |
| J01-04 | Recepción del Libro nativo | DS 011-2011-PCM/guía Indecopi | Constancia y número/código; permitir imprimir o enviar copia conforme canal disponible, sin imponer correo al reclamante | Plugin; comerciante responde | pc_complaints secuencia y acuse, evento outbox, descarga segura de constancia | F02,F03 | PARCIAL — formato final |
| J01-05 | Reclamo o queja presentado | Ley 29571 y guía Indecopi | Responder dentro de 15 días HÁBILES improrrogables; no convertir WP-Cron en autoridad del plazo | Comerciante | submitted_at, due_at calculable con calendario legal, alertas, responded_at | F03,F23 | BASE VERIFICADA; calendario de feriados pendiente |
| J01-06 | Respuesta formal del negocio | Reglamento del Libro y guía Indecopi | Conservar texto, autor, fecha, modalidad y evidencia de remisión/recepción cuando esté disponible; wp_mail aceptado no equivale a recepción | Comerciante; plugin almacena constancia técnica | R8-42, pc_complaints, pc_outbox, bitácora | F02,F03 | BASE VERIFICADA como necesidad de evidencia |
| J01-07 | Datos de reclamo y respuesta | DS 011-2011-PCM art.12 modificado | Conservar como mínimo dos años hojas, reporte cuando corresponde y constancia de respuesta; revisar normas posteriores antes de fijar purga | Comerciante; plugin impide eliminación indiscriminada | pc_complaints, backups, P0-26 política de conservación | F02 | VERIFICADO EN TEXTO CONSOLIDADO; vigencia final a revalidar |
| J01-08 | Libro externo configurado | Ley 32495 | Enlace visible y funcional; responsable del cumplimiento sigue siendo el comerciante, no el plugin externo | Comerciante | Configuración admin R8-45/46, verificación manual de disponibilidad, no desactivar aviso obligatorio | F01,F03 | BASE VERIFICADA |
| J01-09 | Acceso del personal | E-02 + Ley 29733 | Expediente íntegro sólo administrador; operador máximo alerta operativa mínima | Plugin | R8-41/42 admin, R8-39 operador filtrado | F14 | POLÍTICA E-02/E-07 APROBADA |
| J01-10 | Comerciante sujeto a SIREC por umbral | Guía Indecopi | Evaluar deber separado de reportes SIREC en comercios con ingresos anuales >= 3000 UIT; no crear envío automático V1 | Comerciante | Checklist de configuración, requisito externo si supera umbral | F03 | CONDICIONAL — no asumir para cada tienda |
| J01-11 | Proyecto normativo de julio de 2026 | RM 244-2026-PCM | La resolución sólo publicó un PROYECTO; no programarlo como norma vigente | Propietario de producto revisa vigencia antes del release | Control de cambios normativos, fuente/fecha | F04 | PROYECTO, NO REGLA FINAL |
| J01-12 | Acceso al formulario desde móvil/desktop | Ley 32495 + D-16/17 UX | Mantener enlace claramente visible y la presentación accesible sin barreras indebidas | Plugin | Pantallas públicas; WCAG 2.2 AA objetivo, controles táctiles 44×44 | F01,F03 | POLÍTICA UX APROBADA; accesibilidad sin demostrar |

### J-02 — Derechos del consumidor y comercio electrónico

| ID | Cuándo aplica / sujeto | Norma o criterio | Requisito a satisfacer | Responsable | UI / registro / evidencia del plugin | Fuente | Estado |
| --- | --- | --- | --- | --- | --- | --- | --- |
| J02-01 | Venta a consumidor en canal digital | Ley 29571, DL 1729 art.24.4 | Mecanismo directo de atención de reclamos gratuito, accesible, eficaz y permanentemente operativo, además del Libro cuando corresponda | Comerciante; plugin ofrece canal | Pantalla ayuda/contacto/Libro, R8-15, panel de atención | F05,F03 | BASE VERIFICADA |
| J02-02 | Checkout de cualquier pedido | Ley 29571 + DL 1729 art.56.1.h | No inducir decisiones mediante mecanismos coercitivos; informar precio final, entrega, condiciones de cancelación y contratación | Comerciante informa; plugin presenta antes de Pagar | R8-05/06/07; snapshot condiciones E-03/E-04 | F05 | BASE VERIFICADA |
| J02-03 | Físico entregado con defecto o falta de idoneidad | Ley 29571 arts.18/19/97 y orientación Indecopi | El estado ENTREGADO no extingue remedios por incumplimiento; gestionar expediente de devolución/garantía separado | Comerciante; plugin registra | pc_cases, R8-12/23; sin convertir ENTREGADO a CANCELADO | F06 | BASE VERIFICADA; remedio concreto según supuesto |
| J02-04 | Producto de oferta, promoción u outlet | Orientación Indecopi 16-06-2026 | No usar regla genérica que prive cambios/devoluciones legalmente exigibles por defectos o falta de idoneidad | Comerciante; plugin limita políticas | Configuración R8-46, revisión admin y expediente | F06 | BASE VERIFICADA |
| J02-05 | Cliente cambia de opinión sin defecto acreditado | Ley 29571, políticas comerciales y norma sectorial | NO inventar desistimiento universal de 7/14/30 días; resolver derechos efectivamente aplicables o promesa comercial pactada | Comerciante | Motor E-03: clasificación elegibilidad por causa, contrato histórico | F05,F06 | NO VERIFICADA norma universal, por ello fuera de automatización |
| J02-06 | Digital habilitado o descargado | Ley 29571, E-03 D-86…95 | No revocar ni negar remedios automáticamente; reclamos de idoneidad continúan aunque se descargara | Comerciante administrador | pc_cases, derecho digital y evidencia; no estado DESCARGADO E-01 | F06 | CONDICIONAL por tipo de producto |
| J02-07 | Servicio presencial realizado o inasistencia | Ley 29571, contrato pactado y E-03 | No cobrar penalidad por NO_ASISTIO automáticamente; evaluar política informada y límites legales | Comerciante | Contrato/versionado, reprogramación historial y expediente separado | F05 | PENDIENTE sector y causal |
| J02-08 | Cancelación parcial y entrega | Ley 29571, E-03/E-04 | Reembolso y cargo delivery según prestación/costo legal real; no reparto automático del delivery ni cancelación de otras líneas | Administrador comerciante | Snapshots líneas, ajuste auditado, registro de devolución externa verificada | F05 | PENDIENTE calificación caso concreto |
| J02-09 | Comprador exige reembolso | Ley 29571, E-04 D-54/66 | El plugin no ejecuta refund MP; registrar resultado externo verificado distinto de nota de crédito externa | Comerciante | R8-27, pc_payment_events, pc_external_documents | F13 | POLÍTICA COMERCIAL APROBADA |
| J02-10 | Proveedor no domiciliado orientado a Perú sin mecanismo | DL 1729 art.24.5 | Correo electrónico mínimo es obligación específica de ese supuesto, no reemplazo general del Libro de un comercio domiciliado | Proveedor según domicilio | Checklist de sujeto obligado, no regla global de email de comprador | F05 | CONDICIONAL |
| J02-11 | Pedidos históricos y cambios posteriores | Ley 29571, E-03 D-87 | Condiciones pactadas deben conservar versión y evidencia; no reescribir transacción confirmada | Plugin y comerciante | pc_policy_versions, pc_orders snapshots, pc_order_adjustments | F05 | POLÍTICA APROBADA, detalle evidencial pendiente |

### J-03 — SUNAT, documentos y operación tributaria

| ID | Cuándo aplica / sujeto | Norma o criterio | Requisito a satisfacer | Responsable | UI / registro / evidencia del plugin | Fuente | Estado |
| --- | --- | --- | --- | --- | --- | --- | --- |
| J03-01 | Configuración tributaria de instalación | SUNAT regímenes | Sólo Nuevo RUS/RER/RMT/General o independiente cuarta según sujeto y actividad; no inferir por monto de venta | Comerciante valida su régimen | R8-45/46, valor configurado + vigencia; no API SUNAT | F07,F12 | BASE VERIFICADA; clasificación real del comercio requiere confirmación |
| J03-02 | Nuevo RUS | SUNAT NRUS | Boletas/tickets admitidos conforme reglas; NO factura empresarial ni RHE por elegir cualquiera en selector | Comerciante | Selector externo restringido por régimen, guía externa | F09 | BASE VERIFICADA |
| J03-03 | RER/RMT/General tercera categoría | SUNAT regímenes | Permiten boleta/factura y otros según operación; emisión CPE fuera del plugin | Comerciante | Comprobante referenciado: tipo/serie/número/fecha/referencia, sin generar CPE | F07,F10,F11 | BASE VERIFICADA, supuestos por emisor |
| J03-04 | Independiente con rentas de cuarta categoría | SUNAT Personas | Recibo por Honorarios Electrónico en servicio personal independiente, no usar como documento de venta empresarial indiscriminadamente | Emisor independiente | Selector tipo RHE sólo en modo válido; registro referencia externo | F12 | BASE VERIFICADA; no mezclas arbitrarias con giro empresarial |
| J03-05 | Venta gravada con IGV general en 2026 | SUNAT IGV Ley 32387 | Tasa conjunta 18%, composición 2026 IGV 15.5% + IPM 2.5%; no añadir 18% a precio final | Comerciante identifica operación | Dato referencia por vigencia, NO motor de impuesto V1 | F08 | VERIFICADO 2026 |
| J03-06 | Años posteriores | SUNAT Ley 32387 | Conservar vigencias publicadas 2027: IGV 15% IPM 3%; 2028 14.5%+3.5%; 2029 14%+4%; total 18% | Producto registra fuente/vigencia si se muestra información | No hardcode de desglose universal; datos de referencia y fecha | F08 | VERIFICADO plan normativo; revisar cambios legales futuros |
| J03-07 | Operación exonerada, inafecta, exportación u otra excepción | SUNAT Ley IGV | No presuponer gravabilidad por tipo físico/digital/servicio ni régimen solamente | Comerciante asesora clasificación | Sin cálculo ni desglose tributario vinculante en checkout V1; referencia externa | F08 | CONDICIONAL, revisión caso concreto |
| J03-08 | Cargo de delivery | SUNAT base imponible art.14 y E-04 | El precio comercial incluye tarifa pactada, pero su tratamiento fiscal depende del proveedor/operación; no prorratear tributo universal automáticamente | Comerciante/emisor externo | Una tarifa por pedido y snapshot, documento fiscal externo aparte | F22 | CONDICIONAL, sin motor fiscal |
| J03-09 | Cancelación, devolución, descuento | SUNAT nota de crédito | Referenciar nota de crédito o rectificación emitida externamente cuando proceda; no generar documento desde plugin ni equiparar refund a nota | Comerciante | pc_external_documents, pc_cases y movimiento financiero separado | F13 | BASE VERIFICADA |
| J03-10 | Físicos en traslado | SUNAT RMT/NRUS guías | Guías de remisión cuando sean exigibles; fuera de V1: registro informativo o enlace externo, no generación automática | Comerciante | Checklist de despacho, sin sistema SUNAT integrado | F09,F11 | CONDICIONAL según operación de transporte |
| J03-11 | Datos del comprador y comprobante | SUNAT comprobantes por supuesto | Pedir documento de identidad sólo cuando jurídicamente requerido y por tipo de comprobante; NO imponer DNI/RUC al checkout de toda compra | Comerciante | Campos fiscales condicionales, panel admin y referencia externa | F07,F12 | PENDIENTE matriz por documento/emisor |
| J03-12 | Dinero comercial y cálculo de tributos | D-96…108, D-167, E-04 | Céntimos PEN enteros; precisión exacta para cálculo comercial; no sumar IGV por defecto ni prometer base imponible fiscal calculada | Plugin | R8-05/06/07, MoneyCents, snapshots, conciliación | F08,F22 | POLÍTICA APROBADA; redondeo fiscal excluido V1 |
| J03-13 | Conservación documental tributaria | Código Tributario art.87.7 | Guardar antecedentes vinculados a obligaciones tributarias 5 años o prescripción mayor, con cómputo normativo por obligación | Comerciante titular; plugin no purga | pc_orders, pc_external_documents, backups y restore | F19 | BASE VERIFICADA; prescripción concreta por caso |

### J-04 — Protección de datos y ejercicio de derechos

| ID | Cuándo aplica / sujeto | Norma o criterio | Requisito a satisfacer | Responsable | UI / registro / evidencia del plugin | Fuente | Estado |
| --- | --- | --- | --- | --- | --- | --- | --- |
| J04-01 | Comercio recopila datos de compradores | Ley 29733 + DS016-2024-JUS | Comerciante determina finalidad/base legal, información previa, acceso por necesidad, no casilla genérica de marketing | Comerciante responsable; plugin ofrece herramientas | Aviso/versión privacidad en checkout, pc_policy_versions, E-07 | F14 | BASE NORMATIVA VERIFICADA; base por finalidad aún individual |
| J04-02 | Dato email o teléfono en checkout | E-07 y minimización | Correo obligatorio funcional para digital/servicio; teléfono para físicos; físico puro sin email forzado | Plugin | R8-05…07, DTO contacto, no usuario WordPress | F14 | POLÍTICA APROBADA |
| J04-03 | Comerciante titular de banco de datos | ANPD Reglamento/guía | Inscripción de banco de datos personales gratuita y de aprobación automática; no es registro central del plugin por instalación | Comerciante obligado cuando sea titular | Checklist administrativo y guía externa; nunca bloquear edición de software por trámite | F15 | BASE VERIFICADA; ámbito de bancos del comerciante a inventariar |
| J04-04 | Datos enviados a hosting/MP/email/mapas | ANPD registro/transferencias | Inventario por instalación de responsables/encargados y comunicaciones transfronterizas cuando correspondan | Comerciante responsable; proveedor real | Configuración de terceros y versión de aviso, geolocalización voluntaria | F14,F17 | CONDICIONAL a proveedor/localización real |
| J04-05 | Solicitud acceso de titular | ANPD ARCO | Orientación ANPD: 20 días hábiles para acceso desde solicitud válida según procedimiento | Comerciante | R8-43/44, pc_privacy_requests, verificación, alertas | F16 | BASE VERIFICADA; cómputo procesal a confirmar |
| J04-06 | Rectificación/cancelación/oposición | ANPD ARCO | Orientación ANPD: 10 días hábiles; determinar legitimación y obligación de retener legalmente antes de borrar | Comerciante | R8-43/44, registro de decisión y motivo | F16,F19 | BASE VERIFICADA; excepciones/retención por caso |
| J04-07 | Ejercicio de derechos sin email | Ley 29733, E-07 | Mantener canal administrativo verificado para comprador sólo teléfono; no exigir crear email o cuenta WordPress | Comerciante y plugin | Asistencia manual, evidencia proporcionada, sin fotocopia DNI por defecto | F14 | POLÍTICA E-07 APROBADA |
| J04-08 | Seguimiento de invitado | E-07 seguridad de producto | Token 256 bits y 90 días sólo lectura; no confundir expiración con caducidad de derechos del titular | Plugin | pc_guest_sessions, cookie privada R8-08/09 | F14 | POLÍTICA APROBADA, NO plazo legal |
| J04-09 | Acción sensible y digitales | E-07 seguridad | OTP 8 dígitos/10 min/5 intentos o verificación telefónica proporcional; token digital separado 24 h | Plugin | pc_otp_challenges, pc_download_tokens, R8-10…14 | F14 | POLÍTICA APROBADA, NO plazo legal |
| J04-10 | Datos de reclamaciones y exportaciones | Ley 29733, E-02/E-07 | Acceso íntegro sólo administrador; operador sólo alerta mínima; exportar por finalidad, limitar exposición en logs/backups | Plugin | R8-41…50, capabilities campo a campo | F14 | POLÍTICA APROBADA; seguridad real sin probar |
| J04-11 | Geolocalización, mapas y cookies | Ley 29733, E-07 | Mapa sólo por opción voluntaria y proveedor informado; sin trackers/marketing ni geolocalización invisible por defecto | Comerciante y plugin | Alternativa dirección manual, aviso proveedor tiles | F14 | POLÍTICA APROBADA |

### J-05 — Incidentes, conservación y recuperación

| ID | Cuándo aplica / sujeto | Norma o criterio | Requisito a satisfacer | Responsable | UI / registro / evidencia del plugin | Fuente | Estado |
| --- | --- | --- | --- | --- | --- | --- | --- |
| J05-01 | Incidente de datos de volumen/tipo significativo, sensibles o perjuicio a derechos | DS 016-2024-JUS art.34 | Evaluar notificación ANPD como máximo 48 horas desde conocimiento si encaja supuesto; no todos los errores técnicos son reportables | Comerciante responsable; plugin da evidencia | Registro de incidente, hora de conocimiento, categorías, alcance, decisión y constancia externa | F14,F20 | BASE VERIFICADA; aplicabilidad por incidente |
| J05-02 | Afectación a derechos de titulares | DS 016-2024-JUS art.34.3 | Cuando se cumplan condiciones, comunicación al titular sin dilación indebida, regla 48 horas con justificación de retraso | Comerciante | Bitácora de destinatarios/canales, evidencias, sin afirmar recibido por wp_mail | F14,F20 | BASE VERIFICADA condicional |
| J05-03 | Incidente en entorno digital | Decreto Urgencia 007-2020 y orientación PCM | Evaluar deber adicional de reporte digital al CNSD según caso, sin automatizar reportes externos por cada fallo | Comerciante y su equipo técnico | Checklist de escalamiento y constancia de evaluación | F18 | CONDICIONAL a régimen/alcance |
| J05-04 | Incidente detectado por encargado | DS 016-2024-JUS art.36 | Encargado informa de inmediato al responsable cuando conoce incidente de datos tratados | Proveedor/encargado; comerciante coordina | Registro proveedor, hora comunicación, tratamiento y medidas | F14 | BASE VERIFICADA, roles reales a documentar |
| J05-05 | Registro y trazabilidad de incidentes | DS 016-2024-JUS | Conservar evidencia mínima de descubrimiento, causa, medidas, evaluación y comunicaciones sin capturar secretos/PII innecesaria | Comerciante; plugin da estructura de registro | pc_cases/pc_audit_log según permiso, diagnóstico filtrado | F14 | BASE VERIFICADA como necesidad documental |
| J05-06 | Reclamaciones recibidas | DS 011-2011-PCM art.12 modificado | Conservación mínima 2 años para hoja, reporte si aplica y constancia de respuesta, sin purgar por vencer enlace de consulta | Comerciante | Política de retención reclamos, backup y log | F02 | VERIFICADO texto consolidado; revalidar antes lanzamiento |
| J05-07 | Antecedentes tributarios | Código Tributario art.87.7 | Cinco años o mayor plazo de prescripción por obligación; no fijar 5 años absolutos como purga automática | Comerciante | Retención de pedidos/documentos y restauración legible | F19 | BASE VERIFICADA |
| J05-08 | Tokens de compra/descarga | E-07 D-137/140 | 90 días y 24 horas son vigencias de acceso, no tiempo obligatorio de borrar comprobantes/pedidos | Plugin | Tokens hash, revocación sin eliminar historial | F14 | POLÍTICA APROBADA, no plazo legal |
| J05-09 | Actualización ZIP y recuperación | E-05/E-07/P0-26 | Respaldo consistente de BD/archivos privados/config; migración versionada, no asumir rollback SQL con ZIP anterior | Comerciante administrador | Runbook, versión de schema, log de migraciones, respaldo verificable | F19 | POLÍTICA TÉCNICA APROBADA; no ejecutada |
| J05-10 | Restauración anterior a pago real | E-05 | Conciliar con Mercado Pago antes de aceptar reintento de cobro, no duplicar ni reconstruir con parámetros de retorno navegador | Comerciante, backend | pc_payment_attempts/events/inbox y bitácora de conciliación | F19 | POLÍTICA APROBADA; sin comprobación real |
| J05-11 | Desinstalación | D-146/D-175 y retención legal | No eliminar datos por defecto; expediente y autorización administrativa para purga legalmente admisible | Comerciante | Desinstalación no destructiva, registro de proceso | F14,F19 | POLÍTICA APROBADA |
| J05-12 | Caché, logs y copias de WordPress | E-07 privacidad | No exponer OTP/secretos privados/archivos ni exportaciones por caché, CDN, backups o URL pública | Plugin/administrador hosting | no-store, almacenamiento privado, logs sin secretos, diagnóstico | F14 | POLÍTICA APROBADA; sin prueba en hosting |

## 4. Perfiles de comerciante: configuración inicial y obligaciones

| Perfil | Documento externo elegible en términos generales | Obligaciones comunes | Evaluaciones específicas |
| --- | --- | --- | --- |
| Tienda persona natural Nuevo RUS | Boleta/ticket; NO factura por NRUS | Precio y atención/Libro, privacidad, evidencias comerciales | Validez de régimen para actividad; guías según traslado; inscripción banco de datos |
| Comercio en RER | Boleta/factura y otros según SUNAT | Idem, con emisión externa electrónica cuando obligado | Actividades excluidas de RER; clasificación gravada/exonerada y cargo de delivery |
| Comercio en RMT | Boleta/factura y otros según SUNAT | Idem | Declaraciones/impuestos fuera del plugin; guías en traslado; régimen vigente |
| Comercio en régimen general | Boleta/factura y otros según SUNAT | Idem | Excepciones sectoriales y tratamiento tributario particular |
| Persona natural independiente cuarta categoría | RHE cuando hay servicio personal de cuarta categoría | Atención y privacidad según naturaleza de relación de consumo | No asumir RHE para ventas empresariales de tercera categoría; compatibilidad con carrito mixto debe evaluarse, no habilitar arbitrariamente |
| Actividad regulada o sector especial | Según emisor/sector | Todas las aplicables | Alimentación, salud, productos regulados, transporte, menores, datos sensibles: **no declarar cobertura genérica** sin estudio sectorial |

### Distinciones de producto

1. **Cierre funcional ≠ cumplimiento legal:** registrar número/fecha de documento externo no crea un CPE, ni una respuesta de Libro garantiza entrega al destinatario.
2. **El libro nativo y el enlace externo son modalidades aprobadas**; si la opción elegida no satisface el entorno normativo del comerciante, el instalador debe advertirlo y exigir configuración válida. No permitir ocultar el enlace obligatorio.
3. **Sin plazo universal de cambio por arrepentimiento:** una devolución por falla es distinta de cancelación voluntaria o garantía de satisfacción contractual; E-03 sigue siendo la política de evaluación.
4. **Régimen tributario no convierte una compra en gravada:** la clase de operación/emisor y su afectación determinan el tratamiento. El motor V1 se limita a total comercial y referencias de documentos externos.
5. **Un token que caduca no borra una obligación.** Seguimiento 90 días y descarga 24 h son controles de seguridad, no plazos de conservación de información legal.
6. **No inscribir bancos de datos por cuenta del comercio**, no enviar reportes a Indecopi/SUNAT/ANPD, no ejecutar notas de crédito o devoluciones de proveedor. Registrar controles y responsabilidades para la instalación.
7. El cambio de ley debe reflejarse en registros de **fuente, fecha de revisión, condición, transición y efecto**; no añadir una regla universal porque un proyecto normativo la mencione.

**Precisión documental P0-23/J-01/J-03/J-04 (2026-11-17):** `docs/35-CONTRATOS-LEGALES-PERMISOS-DTO-P0-23-P0-25-V1.md` describe 14 relaciones ruta/campo/finalidad, 10 controles AUTH y 10 LEG pendientes; una propuesta de DTO **no** equivale a identificar los campos legales exigibles para todo comerciante. R8-41/43 tienen solo Summary; falta mecanismo administrativo seguro para consultar caso completo antes de responder/resolver. El reglamento del Libro RM 244-2026-PCM sigue documentado como **proyecto**, no reforma definitiva verificada. El checkout no pide DNI/RUC universal por presencia de datos del Libro o factura.

**Cruce con persistencia propuesta P0-18:** `docs/36-RESOLUCIONES-PROPUESTAS-DB-A01-A16-P0-18-V1.md` enlaza las obligaciones de evidencia/retención con DB-A09 (documentos externos), DB-A10 (Libro) y DB-A11 (ARCO), además de idempotencia y auditoría. Son propuestas de estructura, **no certificación legal ni DDL aplicado**.

## 5. Datos legales y límites de la API vinculados al P0-23

| Dato | Captura/validación | Acceso | Persistencia propuesta | Verificación aún necesaria |
| --- | --- | --- | --- | --- |
| Tipo de reclamo/queja, detalle, pretensión, identificación pertinente | Formulario público Libro R8-15 con reglas contra abuso proporcionadas | Administrador íntegro; operador aviso mínimo | pc_complaints y outbox/constancia | Formato oficial final, campos obligatorios y calendario 15 días hábiles |
| Respuesta formal, fecha, canal, evidencia de recepción/remisión | R8-42 por administrador; no confundir wp_mail TRUE con recepción | Administrador | pc_complaints/audit/outbox | Supuestos de constancia y archivo conforme reglamento |
| Régimen emisor y documento externo (tipo/serie/número/fecha/estado) | Admin R8-45/46, pedido según operación; jamás factura automática | Admin; comprador sólo documento propio autorizado | pc_external_documents, snapshots de operación | J-03: condiciones por documento y datos de adquiriente |
| Clasificación comercial fiscal de operación | Informativa y manual, no cálculo de IGV V1 | Admin | Config y documento externo | Afectación y redondeo por operación si se expone desglose |
| Política/aviso privacidad versionados | Mostrar antes de confirmar, sin consentimiento comercial genérico forzado | Público versión vigente; admin historial | pc_policy_versions, order snapshot | Base jurídica por finalidad y proveedor/transferencia |
| Solicitud ARCO, verificación, decisión y fundamento | Admin R8-43/44; canal sin correo | Admin | pc_privacy_requests | Excepciones, cómputo real y retención legal |
| Incidente de datos, evaluación y constancias | Administrador bajo acceso protegido; nunca divulgación pública | Admin con necesidad de conocer | pc_cases/pc_audit_log, archivo privado de evidencia | Notificabilidad art.34 y escalamiento proveedor |
| Conservación y supresión | Regla por categoría, derecho y fundamento | Admin autorizado | P0-18/P0-26 | Revisión por comercio y régimen, backups y restablecimiento |

## 6. Escenarios documentales de aceptación — NO EJECUTADOS

| ID | Escenario | Comportamiento esperado |
| --- | --- | --- |
| J-X01 | Plataforma digital sin enlace visible al Libro | Configuración incumplida; no permitir afirmar conformidad |
| J-X02 | Comprador sin pedido ni email intenta presentar reclamo | Puede acceder a canal legal; no forzar cuenta ni comprobante |
| J-X03 | Reclamo capturado y respuesta registrada | Constancia correlativa y evidencia, sin afirmar entrega por éxito de wp_mail |
| J-X04 | Vence plazo de reclamación con WP-Cron inactivo | Fecha legal no depende de cron; alerta cuando el panel procesa consulta |
| J-X05 | 2 años desde reclamo, documentación fiscal asociada aún debe conservarse | No purga en cascada automática |
| J-X06 | Producto ofrecido con descuento presenta defecto | Registrar expediente/garantía, no bloqueo por etiqueta oferta |
| J-X07 | Comprador desea desistir sin causa de servicio ya prestado | Evaluación contractual/legal, no devolución universal automática |
| J-X08 | Usuario Nuevo RUS selecciona factura | Impedir configuración documental incompatible y orientar emisión externa |
| J-X09 | Independiente de cuarta intenta registrar factura empresarial en servicio personal | No asumir habilitación universal; sujeto y actividad requieren revisión |
| J-X10 | Comerciante necesita nota de crédito tras devolución | Registrar referencia externa; refund financiero no reemplaza nota |
| J-X11 | Operación exonerada con precio final | No añadir 18% ni fabricar base imponible |
| J-X12 | Cliente sin correo pide ejercer derechos | Canal manual validado, no crear email ficticio |
| J-X13 | Solicitud ARCO pide borrar pedido con obligación tributaria vigente | Denegar purga de esa categoría justificadamente, atender solicitud y documentar |
| J-X14 | Incidente menor sin exposición de datos | Registrar evaluación; no notificar automáticamente a ANPD sin causal |
| J-X15 | Filtración de gran volumen de datos | Registrar hora de conocimiento, evaluar notificación 48 h y constancias |
| J-X16 | Comercio usa mapas/hosting extranjero | Inventariar transferencia y actualizar información de privacidad según supuesto |
| J-X17 | Desinstalación o ZIP rollback | Conservar evidencia legal, restablecer solo backup coherente, reconciliar pagos |
| J-X18 | Documento vigente cambia por norma posterior | Revalidar fechas y actualizar matriz; nunca sustituir regulación vigente por proyecto |

## 7. Decisiones existentes, huecos y puerta real de cierre

**Aprobado previamente:** metodología P0-25 D-174, PEN/registro fiscal externo D-167, opción de Libro D-05, E-01…E-08. **Documentado en esta fase:** clasificación de 5 matrices, 59 requisitos/controles sujetos a condición, referencias oficiales y escenarios.

**Pendiente para afirmación jurídica completa:** (a) ratificar si hubo modificación definitiva después de RM 244-2026-PCM y su fecha de entrada en vigencia; (b) cerrar formato/correlativo/constancia del Libro para modalidad nativa según norma vigente; (c) casos tributarios, datos identificatorios, actividades y documentos externos por régimen; (d) bases y transferencias de datos por instalación, plazos y sector; (e) evaluaciones de incidentes y conservación específicas. No se confunden referencias normativas con certificación o implementación probada.

**Próximo anexo asociado:** docs/26-CONTRATO-TRIBUTARIO-P0-17-J03-V1.md para tratar la matriz SUNAT sin construir un motor fiscal V1.

**Referencias principales:** este documento y docs/26 alimentan docs/07 (normas generales), docs/16 (elegibilidad), docs/17 (dinero), docs/20 (datos), docs/21 (API), docs/24 (DTO). La aprobación de esas políticas no autoriza implementar, compilar, probar, registrar empresas ni desplegar.
