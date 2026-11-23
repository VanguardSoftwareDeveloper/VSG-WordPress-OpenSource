**FUENTE VIGENTE D-200/DOC-QA31 (18-11-2026):** OpenAPI **`0.12.0-draft`: 69 REST/66 rutas/138 esquemas**, 70 entradas R8 con página WordPress R8-18, 200 D, 56 PER y 39 tablas. El expediente físico parcial tiene cierre por **R8-70** (rechazo/retirada con verificación renovada para retiro) sin impacto monetario/inventario; si R8-26 aplica el ajuste, termina el mismo expediente **`closed_adjusted`** atómicamente y exige `expected_case_version`. Los cortes 0.8/0.10/0.11 que aparecen después son **históricos**, no contratos alternativos. **P-A documental cerrada**; permiso de código P-B separado, y P-C/P-D requieren pruebas/entorno/instalación autorizados.

**CORTE HISTÓRICO tras DOC-QA30/D-199 (18-11-2026):** `docs/openapi/peru-commerce-v1.openapi.draft.json` **0.11.0-draft, 68 REST/65 rutas/136 DTO**, 69 R8 incluyendo R8-18 página WP; 56 PER, 39 tablas, decisiones D-01…D-199. Se cerró únicamente el recorrido de atención manual por teléfono para reducir parcialmente unidades físicas: R8-69 abre expediente solo A con dos capacidades, verificaciones E07-E y sin efectos; R8-21 lee detalle protegido y R8-26 decide en otra operación. Los conteos 0.8…0.10 de tablas históricas describen cortes anteriores. **P-A cerrada documentalmente; P-B código aún no autorizado; P-C/P-D siguen sin ejecución.**

# PeruCommerce — separación de cierre documental, autorización de código y habilitación comercial V1

**Corte:** 2026-11-17. **Estado:** organización de alcance **DOCUMENTAL**, sin crear implementación, SQL ejecutable, cuentas, pruebas ni deployment. Esta matriz explica por qué es incorrecto exigir pruebas de software aún inexistente para autorizar escribir software, y por qué es igual de incorrecto declarar que las pruebas ya están hechas. Prevalecen AGENTS.md y las 200 decisiones D-01…D-200, con docs/41/42 como fuentes vigentes. La matriz se conserva por trazabilidad.

## 1. Cuatro puertas distintas

| Puerta | Qué requiere | Qué permite afirmar | Qué NO permite afirmar |
| --- | --- | --- | --- |
| P-A — Diseño pre-código | Alcance V1, decisiones no contradictorias, REST/DTO/permiso, reglas críticas de dinero/stock/citas/archivos, datos legales condicionales, modelo físico y UX especificados y ratificados | Diseño aprobado para el módulo autorizado | Ninguna integración funciona, sin compilación ni prueba |
| P-B — Implementación | P-A aceptada y autorización explícita del propietario para cambios de código del módulo elegido | Se permite escribir fuente en el alcance autorizado | No permite compilar/ejecutar/desplegar si no se autoriza por separado |
| P-C — Verificación de módulos | Código existente y autorización expresa para pruebas/compilación en entorno identificado, capturas de comportamiento y defectos corregidos | Comportamiento medido para ese entorno/versión | No autoriza producción, normativa universal ni otras instalaciones |
| P-D — Publicación/habilitación | P-C, verificación normativa por comerciante, seguridad/MP/hosting/almacenamiento/dataset, ZIP GPL con fuente y restore comprobados | Release/habilitación de módulos con evidencia y limitaciones | No certifica otros comercios ni ausencia absoluta de problemas |

**Situación definitiva D-195…D-198:** PRE-01…07 están **CERRADAS COMO DECISIONES DE DISEÑO PRE-CÓDIGO**. Las 34 pantallas Figma son baseline de ingeniería (no se afirma aprobación estética frame a frame ni WCAG probado), el recurso INEI de desarrollo quedó elegido con D-196 y las condiciones fail-closed se mantienen para activación. **P-A CERRADA por D-197; P-B NO AUTORIZADA**, P-C/P-D no ejecutadas. Fuente docs/41.

## 2. Reclasificación explícita de las 19 puertas F0

| F0 | Entregable de P-A previo a código | Evidencia que por su naturaleza pertenece a P-C/P-D | Estado al corte |
| --- | --- | --- | --- |
| 01 | Contrato precio PEN/céntimos, campos fiscales por emisor, referencia externa y exclusiones | Comprobante real y aplicabilidad de régimen por comercio | Política aprobada; detalle legal condicional pendiente |
| 02 | Esquema lógico de 39 tablas, índices/locks/CAS, tratamientos DB-A01…16 y criterio de migración | DDL real, InnoDB y concurrencia/migración/restore | Tratamientos de diseño lógico ratificados D-191, sin SQL ejecutable ni pruebas |
| 03 | Lista verificable de WP/PHP/DB/caché/storage/permisos/HTTPS con fail-closed | Preflight de instalación real HOST-01…12 | Requisitos documentados; sitio sin verificar |
| 04 | Contrato de montajes Angular/manifest/CDN/base URL y fallback | Build Angular y pruebas de integración/browsers | Documentado docs/31; sin build |
| 05 | Seleccionar edición y licencia de fuente INEI, formato/columnas y proveedor tiles opcional | Hash del archivo, importación/atribución y cobertura real | Dos fuentes oficiales distintas; archivo final no elegido |
| 06 | REST/DTO exactos, capabilities, secretos, CSRF, ttl, UX legal de consulta por ID | IDOR/nonce/cache/replay probados | R8-65…68 ratificados D-189/D-198, TTL D-190 y OpenAPI 0.5.0-draft; seguridad real sin probar |
| 07 | Método de entrega de fuentes GPL del mismo release, terceros y atribuciones | ZIP, LICENSE, lockfile, hashes, notices y fuente real | Política GPL aprobada; release no existe |
| 08 | Modalidad y campos Libro por texto vigente; folio, constancia, plazo y expediente íntegro | Reclamación real, impresión/copia y recepción probada | Legal concreto/LEG-01…04 por ratificar |
| 09 | Derecho del consumidor por tipo de línea y reparación sin retracto universal | Escenarios jurídicos/operativos de negocio concreto | E-03 aprobado; aplicación sectorial pendiente |
| 10 | Formularios y referencias documento externo por régimen/operación | Emisión externa verificada por el comercio, no por plugin | D-187 aprobado, J-03 condicional |
| 11 | Bases de tratamiento, consentimiento/canales/ARCO/retención y acceso por finalidad | Evaluación ANPD y prueba de solicitudes en instalaciones | E-07 aprobado; LEG-05/06/10 pendientes |
| 12 | Política por categoría de incidentes, alertas y deberes conservatorios | Ejercicio real de recuperación/reporte aplicable | Política aprobada; cumplimiento futuro |
| 13 | Runbook de ZIP/migración/respaldo/conservación/conciliación | ZIP firmado/verificado, restore y conciliación | Documentado docs/30; no ejecutado |
| 14 | Payload esperado para MP sin email físico; rechazo seguro si cuenta no lo acepta | Prueba real Orders API Perú del comerciante | Fuente oficial revisada, cuenta sin verificar |
| 15 | Total de líneas + entrega = total_amount, comprobación exacta | Prueba MP Perú de ítem Entrega | Fuente oficial revisada, cuenta sin verificar |
| 16 | Hold comercial local 15m separado de expiration_time/medio, pago tardío a incidencia | Prueba MP Perú de PT15M y eventual pago tardío | Duración documentada, cuenta sin verificar |
| 17 | Arquitectura privada de archivos, secretos, links y no-cache/IDOR | Ataques de prueba contra LiteSpeed/CDN/hosting y recuperaciones | Política aprobada; medio físico sin verificar |
| 18 | Matriz de pantallas móvil/escritorio/estados/errores y handoff Figma | Auditoría WCAG/rendimiento de frontend real; branding refinable con cambio controlado | D-195 congela 34 marcos como baseline UX técnico; no prueba navegación ni conformidad visual en runtime |
| 19 | Exclusión de exportación comercial masiva V1 D-188 | Ninguna para esta exclusión | **CERRADO EN ALCANCE** |

La existencia de una fila P-C/P-D pendiente **no debe convertirse artificialmente en requisito de ejecución pre-código**, pero cualquier deficiencia de contrato de P-A sí bloquea programar el componente correspondiente. No permitir una primera integración monetaria «temporalmente insegura».

## 3. Estado de las siete áreas PRE antes del código

| Ref | Propuesta técnica documentada | Decisión que queda por formalizar |
| --- | --- | --- |
| PRE-01 | Dos GET privados de detalle R8-65/66 coherentes con Angular y sin pantalla WP excepcional | **CERRADO EN DISEÑO D-189/D-198**; 67 REST/64 rutas/117 DTO en OpenAPI 0.5.0-draft, falta código y prueba |
| PRE-02 | Admisión 60 s, grant sensible 5 min/uso único y cookie canjeada 30 min de inactividad; conserva 90d/24h/10m/15m confirmados | **CERRADO EN POLÍTICA D-190**; revocación/replay/retención se verifican al implementar |
| PRE-03 | Conservar 39 tablas y tratamientos DB-A01…16, CAS real de configuración o fallo seguro | **CERRADO EN DISEÑO LÓGICO D-191**; DDL/índices/aislamiento solo se verifican en entorno autorizado |
| PRE-04 | Campos normativos condicionales según formulario, régimen, actividad; no fiscal universal ni SUNAT automático | **CERRADO EN POLÍTICA D-192**; vigencia/derechos y aplicabilidad del comercio se revisan antes de habilitar V1 |
| PRE-05 | INEI oficial versionado, catálogo local, fallback recojo; para desarrollo elegir dataset completo INEI 2023/edición 2022 (D-196) | **CERRADO EN FUENTE DE DISEÑO D-193/D-196**; la actualización/verificación de bytes/licencia es gate del módulo delivery y release, no una elección previa al código |
| PRE-06 | ZIP y fuente correspondiente del mismo release mediante canal accesible a destinatario | **CERRADO EN POLÍTICA D-194**, pendiente auditoría real de dependencias y paquetes antes de distribuir |
| PRE-07 | 34 pantallas editables (17 móvil + 17 escritorio), reglas de error/estado complementarias E-01…E-08 y docs/04/10/11 | **CERRADO EN BASELINE DE INGENIERÍA D-195**. No se declara aprobación estética personal, navegación o accesibilidad probada; QA/ajustes posteriores no reabren definición de negocio |

**No atribuir al propietario una elección específica no aprobada.** Esta solicitud autorizó **trabajo documental** de cierre, no integraciones/proveedores/pruebas/código ni habilitación de comercio.

## 4. Ruta operativa siguiente sin trabajo redundante

1. PRE-01…07 cerrados por D-189…D-197 y rectificados por D-198. **No abrir nuevas rondas de opciones antes de escribir código.** La baseline Figma D-195 es referencia para desarrollo, no auto-certificación estética/funcional.
2. **P-A cerrada documentalmente** en docs/41; materializar controladores, DDL, assets y preflight requiere autorización de P-B y no puede fingirse terminado por este documento.
3. **Solicitar autorización específica de código** al propietario para el siguiente módulo y archivos; no compilar/probar/instalar sin permiso propio y conservar módulos externos deshabilitados hasta P-C/P-D.
4. Cuando exista código y se autoricen pruebas, atravesar P-C por entorno/módulo; habilitar MP/digital/entrega **solo** si sus comprobaciones P-D pasan. Los fallos de una integración externa no fuerzan a habilitarla ni degradar privacidad.
5. Antes de cada ZIP comercial: revisión de fuentes/licencias, fecha y versión de normas, datos INEI reales, configuración/regímenes y comprobación de restauración. No prometer cumplimiento total del plugin por instalación genérica.

**Evidencia al corte:** F0-19 cerrada; 18 puertas con trabajo de distinta naturaleza, algunas documentalmente especificadas pero ninguna certificada por pruebas. Los documentos 37/38 son propuesta de cierre técnico. **Resultado honesto: todavía NO «lista para implementar» según AGENTS.md, mucho menos lista para distribuir.**

## Revisión de contratos de consumidores 0.7.0-draft (18-11-2026)
Cuatro inconsistencias adicionales de P-A se cerraron documentalmente: listas de exposición del API; edición y publicación de términos/privacidad con versiones; lecturas recuperables de versiones e identificadores para mutaciones digitales, líneas, casos y avisos; y conciliación MP previa al pedido. El OpenAPI del corte histórico DOC-QA12…15 documentaba **67 operaciones REST, 64 rutas y 129 esquemas**, con los mismos 56 PER, 39 tablas lógicas, permisos/roles y estados. No altera el alcance de V1.

Las verificaciones de P-C/P-D siguen abiertas: Mercado Pago Perú, MariaDB/Hostinger/CAS/locks, archivos privados, dataset INEI, normativa específica por comercio, accesibilidad, ZIP/fuentes y restauración. **Cierre documental no equivale a ejecución ni a garantía de perfección absoluta.** Esta autorización solo alcanza a corregir/publicar documentación, no a iniciar código, compilar, probar o desplegar.

## Última reconciliación de consumidores y puertas (18-11-2026)

El OpenAPI del corte histórico DOC-QA16…21 fue **0.8.0-draft, 67 REST/64 rutas/134 esquemas**; 56 PER y 39 tablas documentales. D-01…D-198 y P-A se conservan. Las omisiones documentales DOC-QA16…21 quedan corregidas para: R8-21 detalle de caso, textos de políticas de pedido, cuota digital opcional, tiles por R8-45/46, idempotencia de R8-12 y precedencia actual. Ningún cambio habilita operador O a leer casos ni reembolsar, ni añade ruta, tabla o estado E-01. La corrección de `pc_case_events` incluye `actor_kind` y `actor_user_id` nullable para invitado/sistema, sin crear cuenta WordPress falsa. **P-B no autorizada por este encargo**, P-C/P-D requieren evidencia de software y comercio real. No afirmar ausencia absoluta de defectos.
