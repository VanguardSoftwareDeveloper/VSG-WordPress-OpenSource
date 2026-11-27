# P0-17 / J-03 — Contrato tributario y monetario contextual SUNAT V1

**Fecha de consulta:** 2026-11-17, Perú.  
**Estado:** ESPECIFICACIÓN DOCUMENTAL CONTEXTUAL PROPUESTA, derivada de las políticas D-45/D-46/D-49, E-04 y D-167 (P0-17 aprobado). J-03 contiene reglas generales verificadas, **no clasificación tributaria individual del comerciante, cálculo legal completo, validación CPE ni autorización de implementación**. Las matrices legales generales J-01…J-05 están en docs/25.

**DTO propuestos P0-23:** `docs/openapi/peru-commerce-v1.openapi.draft.json` (0.2.2-draft) incluye `FiscalSettings` solo como configuración fiscal declarada y referencia externa, NO cálculo de tasas/bases ni alta de comprobante por pedido. **No existe todavía ruta REST aprobada** para registrar un comprobante fiscal emitido externamente. Ver brecha en docs/23 (anexo 11) y docs/24, sin inventar endpoint.

**Actualización de contrato D-187/D-192 (2026-11-17):** la referencia de documento **emitido externamente** por pedido ya está cubierta por R8-64, exclusivamente administrador con permisos `perucommerce_settings_manage` **y** `perucommerce_orders_read`. En el corte histórico D-198 el esquema OpenAPI era **`0.5.0-draft`, 67 REST/64 rutas/117 DTO** (D-198; sin modificar el alcance fiscal de R8-64). La clase y campos obligatorios de boleta/factura/RHE/nota/guía se validan según **emisor, régimen y actividad real**; las notas/correctivos deben vincular documento fuente del mismo comercio. Registrar referencia NO emite ni comprueba autenticidad SUNAT, pago o reintegro. El marco condicional de D-192 cierra la política del plugin, no la calificación fiscal de cada instalación.

**FUENTE REST VIGENTE D-200 (18-11-2026):** OpenAPI `0.12.0-draft` (69 REST/66 rutas/138 esquemas). R8-64 sigue registrando documentos emitidos externamente; R8-70 solo cierra expediente físico parcial sin emisión, devolución ni efecto fiscal por sí mismo. Los textos del corte 0.5 no vuelven a ser fuentes vigentes. Sin emisión SUNAT ni pruebas.

**FUENTE REST VIGENTE D-210 (27-11-2026):** OpenAPI `0.13.0-draft` (82 REST/78 rutas/162 esquemas). R8-64 queda sin cambios para el canal web. El POS V1 añade R8-80/81 para registrar la referencia del comprobante emitido externamente por cada pedido `pos` (D-206) y R8-83 para la devolución presencial ([docs/48](48-CONTRATO-POS-V1-P2.md) §7, §8 y §11). Ambas siguen sin emitir, validar ni declarar CPE; solo se guarda `buyer_id_type`, nunca el número de documento. **DOCUMENTADO/NO IMPLEMENTADO**; sin pruebas.

## 1. Frontera irrevocable de V1

1. Un comercio y local por instalación WordPress; puede vender productos físicos, digitales y servicios presenciales, con un pedido mixto.
2. **PRECIO FINAL introducido por comerciante** en PEN, nunca precio neto al que el plugin agregue IGV automáticamente. El comprador conoce total comercial antes de Pagar.
3. **Importes comerciales exactos como BIGINT de céntimos PEN** en persistencia. DTO MoneyCents es string decimal entero; cantidad comercial de unidades es entero positivo. Multiplicación y suma exactas; backend única autoridad; PHP BCMath obligatorio si se necesitan cálculos decimales o precisión fuera de rango seguro. No usar float/double en contabilidad comercial.
4. **Operación financiera no equivale a documento tributario.** La venta/checkout/registro de efectivo no emiten boleta, factura o recibo por honorarios ni nota de crédito. El plugin únicamente conserva datos, referencias y evidencia de documentos **emitidos fuera de PeruCommerce**.
5. Un cobro inicial por pedido E-04: Mercado Pago Checkout Pro Orders o contraentrega elegible. No ejecutar reembolsos por API; sólo administrador registra **resultado externo verificado** sin borrar acreditación original.
6. La V1 **no implementa motor de liquidación fiscal** (IGV mensual, Renta, retenciones, percepciones, detracciones, crédito fiscal, prorrateo y redondeo tributario, emisión/validación CPE, guías). Si un dato fiscal exacto es obligatorio para un flujo, se gestiona por emisor externo o requiere nuevo contrato de producto, sin improvisarlo.

La regla de alcance del punto 6 resuelve la ambigüedad de “redondeo tributario P0-17”: **no existe redondeo fiscal automático en V1** porque no se calcula base tributaria ni CPE en este módulo; el total comercial ya son enteros de céntimos. Si se autoriza un desglose tributario vinculante futuro, será una funcionalidad separada, con fórmula, unidad de redondeo, clasificación, vigencia y pruebas fiscales por supuesto.

**Precisión D-207 (27-11-2026):** la regla anterior se mantiene. El redondeo de **efectivo** del POS y de R8-24 no es redondeo fiscal: es un ajuste a favor del consumidor de 0 a 9 céntimos que no cambia la base, el IGV ni el total original `T`. Se registra como evento `cash_rounding`, separado del efectivo realmente recibido `R` ([docs/48](48-CONTRATO-POS-V1-P2.md) §6). El comprobante externo puede reflejarlo con `PayableRoundingAmount` si el emisor lo admite; PeruCommerce no lo genera ni lo valida. Su tratamiento tributario y contable lo valida el contador del comercio **antes de P-D**; esta precisión no es certificación tributaria.

## 2. Tabla fiscal por régimen/emisor/operación — fuentes SUNAT

| ID | Régimen o supuesto | Tipo documental relevante | Precaución legal | Fuente | Comportamiento V1 |
| --- | --- | --- | --- | --- | --- |
| T03-01 | Nuevo RUS (persona natural/sujeto permitido) | Boleta de venta, ticket y guía cuando corresponda | Factura no habilitada en NRUS; sin asumir IGV discriminado | SUNAT RUS / F09 | Comerciante confirma actividad/régimen. Permitir registrar boleta o ticket externo; no emitir automáticamente. |
| T03-02 | RER (tercera categoría) | Factura, boleta y otros permitidos por SUNAT | Venta y servicio deben cumplir condiciones del régimen | SUNAT RER / F10 | Comerciante registra documento externo; no calcular renta del RER ni IGV mensual. |
| T03-03 | RMT (tercera categoría) | Factura, boleta; notas y guías cuando correspondan | Emisor/operación rigen requisitos y modalidad electrónica | SUNAT RMT / F11 | Registro externo, sin generar archivos CPE ni guías automáticas. |
| T03-04 | Régimen General | Factura, boleta y otros documentos permitidos según operación | No garantiza que toda venta sea gravada ni que toda actividad esté libre de reglas sectoriales | SUNAT regímenes / F07 | Registrar emisor/operación, no determinar tributos del régimen. |
| T03-05 | Persona natural con ingresos cuarta categoría por trabajo independiente | RHE al prestar servicio personal independiente sujeto a cuarta categoría | NO se asimila a factura empresarial; si opera tercera categoría no se habilita arbitrariamente RHE | SUNAT Personas / F12 | Habilitar referencia a RHE solo cuando la clasificación del comerciante sea compatible; sin emitir. |
| T03-06 | RUC no declarado, régimen no validado o configuración inconsistente | No elegir automáticamente comprobante tributario | Un ticket de orden interno no equivale a boleta ni factura SUNAT | SUNAT / F07 | El plugin permite registrar pedido comercial, pero no representa haber emitido comprobante; alerta de configuración legal al administrador. |
| T03-07 | Operación gravada con IGV en 2026 | Referencia tasa conjunta 18% (IGV 15.5% + IPM 2.5%) | No convertir tasa informativa en IGV añadido a precio final | SUNAT IGV / F08 | Conservar contexto/fecha y precio de venta; NO generar desglose fiscal vinculante V1. |
| T03-08 | Operación exonerada, inafecta, exportación o excepción | Puede no corresponder IGV general | Depende de hecho gravado, sujeto y norma específica; tipo de artículo no decide | SUNAT IGV / F08 | No generar impuesto por regla global de producto/servicio/digital. |
| T03-09 | Entrega a domicilio como cargo visible | El total comercial incluye una sola tarifa pactada | La base fiscal y su desglose pueden depender del servicio complementario y del sujeto emisor | SUNAT cálculo / F22 | Mantener cargo separado en snapshot y no prorratear automáticamente IGV por líneas. |
| T03-10 | Venta mixta físico/digital/servicio | Un carrito comercial, un cobro inicial permitido | No se deduce que todos los tipos comparten comprobante ni tratamiento fiscal | E-04 / J-03 | Registrar líneas y referencias fiscales externas según requerimiento; revisión administrativa si no representable V1. |
| T03-11 | Factura solicitada por adquiriente | Solo si el emisor y operación permiten factura | Datos del adquiriente según norma correspondiente; DNI/RUC no exigido universalmente | SUNAT regímenes / F07 | Campos condicionados en documento externo; no forzar DNI en cotización pública. |
| T03-12 | Anulación, descuento, devolución de comprobante anterior | Nota de crédito externa cuando proceda | La devolución de dinero NO es en sí una nota de crédito, ni viceversa | SUNAT notas crédito / F13 | Referencia separada a documento externo, vinculada a pedido y expediente. |
| T03-13 | Traslado de mercadería y guía | Guía de remisión cuando sea exigible | No se genera guía en V1, no presentar despacho como guía tributaria | SUNAT RUS/RMT / F09,F11 | Campo opcional de referencia externa de guía por entrega cuando corresponda, sometido a revisión. |
| T03-14 | Recibo por honorarios y retenciones de cuarta | Condiciones fiscales y retenciones según norma de cuarta | No calcular retenciones ni tributar ganancias en carrito V1 | SUNAT Personas / F12 | Guardar importe comercial y referencia externa; conciliación fiscal fuera del plugin. |
| T03-15 | Conservación de operaciones tributarias | 5 años o prescripción mayor (art.87.7) | No es TTL genérico de todas las categorías ni permite borrar datos por expiración del enlace | SUNAT Código Tributario / F19 | Retener evidencia requerida y restauración coherente; purga sólo por política jurídica aprobada. |

### Tasas como datos de referencia, NO cálculos universales

| Vigencia anual indicada por SUNAT | IGV | IPM | Total aplicable a operación gravada |
| --- | --- | --- | --- |
| 2026 | 15.5% | 2.5% | 18% |
| 2027 | 15.0% | 3.0% | 18% |
| 2028 | 14.5% | 3.5% | 18% |
| 2029 | 14.0% | 4.0% | 18% |

**Fuente:** [SUNAT, IGV y Ley 32387](https://emprender.sunat.gob.pe/principales-impuestos/impuesto-general-las-ventas-igv/impuesto-general-las-ventas). Las cifras futuras son las publicadas al corte; deberán revalidarse si se modifica la legislación. No proyectar que una venta exonerada/inafecta tenga 18% ni que el Nuevo RUS liquide el IGV de esa manera.

## 3. Datos permitidos / obligatorios por finalidad

| Dato lógico | Captura | Persistencia y acceso | Regla |
| --- | --- | --- | --- |
| Moneda / precio final / cantidad / tarifa / total | Obligatorio para pedido | pc_orders y pc_order_lines inmutables en céntimos; E-04 | PEN solamente, total servidor, no duplicar impuesto |
| Régimen y sujeto emisor | Configuración administrativa del comercio | WordPress settings protegido/versionado; admin | Selección declarada por comerciante, jamás inferida de producto ni RUC del comprador |
| Tipo de documento externo elegible | En administración, cuando emisor y operación lo permitan | pc_external_documents (propuesta P0-18) | Boleta/factura/RHE según supuesto; no emitir; rechazar selección incompatible evidente como NRUS + factura |
| Serie, número, fecha, referencia/estado externo | Sólo después de comprobante emitido fuera del plugin | pc_external_documents con referencia a pedido; admin | No inventar número/estado SUNAT ni afirmar validación externa |
| Datos de adquiriente para documento | Sólo cuando norma/documento/supuesto obligue | Snapshot fiscal protegido/referencia externa, acceso mínimo | No forzar DNI/RUC en todo checkout; evitar exponer identificación completa en operador |
| Nota de crédito externa | Cuando corresponda a documento anterior | Vincular referencia a documento base y expediente, sin alterar cobro | Distinta del refund MP y del evento comercial de cancelación |
| Guía de remisión externa | Según traslado y sujeto emisor | Referencia opcional protegida; no generar guía | No declarar que pedido DESPACHADO es guía válida |
| Clasificación gravada/exonerada/inafecta | Si se desea registrar contexto externo | Admin sujeto a comprobación con emisor | **No implica cálculo ni desglose fiscal vinculante del plugin** |

Los datos de consumidor en factura/boleta/RHE se decidirán **por tipo documental y sujeto**, no por regla de correo/teléfono funcional; el comprador físico puro conserva checkout sin correo (D-24).

## 4. Algoritmo comercial estable — sin matemática fiscal inventada

**Entrada:** líneas con precio final vigente en céntimos, cantidad entera; una entrega por pedido si aplica; fecha/cita y datos condicionales. **Salida:** presupuesto en céntimos, fingerprint y snapshots al intento; **NO** base imponible, porcentaje de IGV o factura.

- Para cada línea: importe_linea_cents = cantidad_entera × precio_final_unitario_cents. Precio de oferta manual activo sustituye al normal. No heredar precio de variante ni redondear en cálculo comercial.
- subtotal_cents = suma exacta de importes de todas las líneas.
- delivery_cents = única tarifa de reparto del distrito UBIGEO validada por servidor, o cero para recojo/sin físicos a domicilio.
- total_cents = subtotal_cents + delivery_cents. Rechazar valores negativos/no representables/exceso de rango; no añadir impuesto/comisión del proveedor.
- Al confirmar Pagar, revalidar precio, stock, franja, dirección, políticas y consentimiento informativo; si cambia el total, obligar nueva confirmación antes de iniciar pago.
- Pedido confirmado conserva snapshots comerciales originales. Cancelación de línea afecta ajuste posterior, no modifica el precio pactado ni otras líneas.
- MP usa total_amount como **string decimal PEN exacto** derivado de céntimos; los ítems comerciales reales y el concepto Entrega deben sumar lo mismo. Compatibilidad de Orders API con payer ausente, Entrega y PT15M sigue condición bloqueante D-157…159. Sin verificación, NO habilitar cobro MP.
- Efectivo registra importe NETO efectivamente aplicado, no billetes entregados/vuelto (D-105). Digital no se habilita hasta pago acreditado, incluso en contraentrega.

**Ejemplo de consistencia comercial (NO tributario):** producto S/ 12.50 × 2 = 2500 céntimos; entrega S/ 6.00 = 600; total = 3100 céntimos, cadena proveedor “31.00” sólo si el contrato MP se valida. **No** calcular base ni IGV del ejemplo por inferencia.

## 5. Matriz de situaciones tributarias difíciles

| Caso | Regla y tratamiento |
| --- | --- |
| NRUS pide factura | No habilitar factura en contexto NRUS; guía externa para comerciante, sin cambiar régimen en automático |
| Persona cuarta solicita vender bienes y emitir RHE por el carrito | No suponer que venta empresarial puede documentarse con RHE; identificar emisor/actividad antes de permitir seleccionar documento |
| Cliente empresa solicita comprobante tras pagar | Identificar tipo permitido y datos necesarios, emisión fuera del plugin, registrar referencia después |
| Precio final ya incluye tributos cuando corresponde | No adicionar porcentaje fijo; no producir campo igv_cents calculado por defecto |
| Digital con tratamiento especial o operación exonerada | Sin motor fiscal, no clasificar automáticamente y no prometer desglose correcto |
| Entrega con impuesto según operación | El cargo comercial es una línea de dinero, su afectación fiscal la resuelve emisor externo según norma |
| Rembolso parcial y nota de crédito | Registrar refund externo comprobado y documento fiscal externo **como hechos independientes** vinculados |
| Cancelación última línea física | Revisión jurídica de delivery realizado/gastos permitidos antes del ajuste; no devolución automática universal |
| Pago tardío MP y hold vencido | Incidencia de negocio E-01, no ingreso duplicado ni segundo CPE automático |
| Persistencia/restitución | Conservar eventos/snapshots externos sujetos al mayor de plazo legal aplicable, no purga por sesión vencida |

**Trazabilidad documental fiscal P0-23 (2026-11-17):** `docs/35-CONTRATOS-LEGALES-PERMISOS-DTO-P0-23-P0-25-V1.md` distingue configuración fiscal R8-45/46 de **alta de referencia de comprobante YA emitido externamente R8-64**. La legalidad de factura/boleta/RHE/nota/guía depende del emisor, tipo de operación y norma aplicable; no hay validador SUNAT ni motor fiscal. La clave idempotente R8-64 en OpenAPI se alineó con el mínimo técnico de 16 caracteres propuesto, sin ratificación final de campos tributarios obligatorios.

**Cruce con persistencia propuesta P0-18:** `docs/36-RESOLUCIONES-PROPUESTAS-DB-A01-A16-P0-18-V1.md` enlaza las obligaciones de evidencia/retención con DB-A09 (documentos externos), DB-A10 (Libro) y DB-A11 (ARCO), además de idempotencia y auditoría. Son propuestas de estructura, **no certificación legal ni DDL aplicado**.

## 6. Contratos REST/SQL afectados

- R8-01/03/05: precios finales y moneda PEN, sin información tributaria ficticia; el listado de catálogo puede mostrar “precio final” sin desglose impositivo obligatorio no justificado.
- R8-06/07: cotización válida, contacto funcional condicional, idempotencia, total exacto; no exigir documentos fiscales a todas las personas.
- R8-20/21: operador ve datos mínimos de pedido/entrega; datos fiscales completos y referencias sensibles sólo a administrador con finalidad. 
- R8-45/46: configuración administrativa de tipo de emisor, régimen declarado y políticas; evitar reglas incompatibles; cambios versionados, no reescribir pedidos históricos.
- R8-27: registrar devolución externa efectuada; **no** generar nota de crédito ni refund.
- Propuesta pc_external_documents: registro inmutable de referencia comprobante externo y eventuales documentos correctivos. **Pendiente físico:** columnas para emisor/fecha, relación documento original/ajuste, tipo de documento, evidencia, acceso, identificación del adquiriente y restricciones de exposición, sujetos a documento y norma.
- DTO de docs/openapi (BORRADOR PARCIAL) **no autoriza** nombres de campos fiscales universales; no introducir automaticamente `tax_rate`, `igv_amount` o `tax_total` calculados sin contrato separado.

## 7. Criterios documentales de aceptación — NO SON PRUEBAS

| ID | Caso | Resultado exigible |
| --- | --- | --- |
| TX-X01 | Producto 1250 cents ×2, entrega 600 cents | Total comercial 3100 cents; nunca 3658 por añadir 18% |
| TX-X02 | Dos productos mixtos con distintas afectaciones | Backend conserva precio final y no inventa desglose ni tasas |
| TX-X03 | Importe inválido fraccionario de céntimo | Rechazar, no truncar silenciosamente |
| TX-X04 | Variante sin precio propio | No vender copiando precio del producto principal |
| TX-X05 | Cambia oferta al iniciar pago | Nueva cotización y confirmación, no cobro antiguo |
| TX-X06 | NRUS factura | Bloquear selección fiscal incompatible, orientar trámite externo |
| TX-X07 | RHE de emisor empresarial | Exigir revisión de actividad/sujeto; no emitir RHE genérico |
| TX-X08 | Reembolso externo parcial comprobado | Evento financiero inmutable y referencia separada de nota de crédito |
| TX-X09 | Comisión MP reportada | Bruto comprador distinto de comisión/neto recibido del comercio |
| TX-X10 | 2027 nueva composición IGV/IPM | No modificar snapshots ni recalcular impuestos automáticamente |
| TX-X11 | Registro tributario debe conservarse | No borrar al vencer enlace de 90 días ni al desinstalar |
| TX-X12 | MP rechaza payer ausente o Entrega/PT15M en cuenta peruana | Bloquear MP y exigir decisión expresa, no email inventado |

## 8. Fuentes oficiales principales (IDs conforme docs/25)

| ID | URL |
| --- | --- |
| F07 | https://emprender.sunat.gob.pe/ruc/regimenes-tributarios-mype/regimenes-tributarios |
| F08 | https://emprender.sunat.gob.pe/principales-impuestos/impuesto-general-las-ventas-igv/impuesto-general-las-ventas |
| F09 | https://emprender.sunat.gob.pe/ruc/regimenes-tributarios-mype/nuevo-regimen-unico-simplificado-nuevo-rus |
| F10 | https://emprender.sunat.gob.pe/ruc/regimenes-tributarios-mype/regimen-especial-renta-rer |
| F11 | https://emprender.sunat.gob.pe/ruc/regimenes-tributarios-mype/regimen-mype-tributario |
| F12 | https://personas.sunat.gob.pe/trabajador-independiente/comprobantes-pago-registro |
| F13 | https://www.gob.pe/17414-emitir-nota-de-credito-electronica |
| F19 | https://www.sunat.gob.pe/legislacion/codigo/textoCompleto-TUO-CT.pdf |
| F22 | https://orientacion.sunat.gob.pe/3109-05-calculo-del-impuesto |

## 9. Estado y dependencias de cierre

**Verificado documentalmente:** clases generales de régimen y comprobante, diferencia entre negocio tercera y servicio personal cuarta categoría, tasa/compensación 2026 y progresión publicada, notas de crédito y retención tributaria de referencia. **Política aprobada:** dinero en céntimos, precio final, no motor fiscal, emisión externa, sin refund API, instantáneas históricas.

**No demostrado:** régimen concreto de ningún comercio, exoneración/afectación y bases tributarias por caso, validación real de documentos SUNAT, redondeo fiscal por comprobante (FUERA DE ALCANCE V1), integración MP/hosting, protección de datos de adquiriente, SQL/DTO final.

**Puerta:** completar y ratificar matriz por emisor, documento y datos legalmente exigidos para la configuración real de V1; resolver compatibilidad técnica MP y clausulas sectoriales antes de afirmar conformidad. No autoriza código, pruebas, compilación ni despliegue.
