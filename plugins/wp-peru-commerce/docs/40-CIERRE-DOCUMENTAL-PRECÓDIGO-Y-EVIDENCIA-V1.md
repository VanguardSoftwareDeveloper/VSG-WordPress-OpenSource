# PeruCommerce — antecedente de cierre documental (antes de D-195…D-197)

**ESTADO HISTÓRICO:** este documento recoge el corte anterior a D-195…D-197. La fuente vigente es [docs/41 — cierre de Fase 0](41-CIERRE-FASE-0-BASELINE-PRECODIGO-V1.md): **P-A cerrada** en decisiones y baseline de 34 frames, sin código/pruebas autorizados. Las referencias más abajo a «PRE-07 sin aprobar» y «P-A incompleta» **no son bloqueos actuales de diseño**.

**Corte:** 17-11-2026 (Perú). **Estado:** DECISIONES TÉCNICAS PRE-CÓDIGO RATIFICADAS D-189…D-194; PRE-07 DISEÑO EN FIGMA CREADO, NO APROBADO VISUALMENTE. **No es autorización de implementación, pruebas, SQL, compilación ni distribución.** La fuente normativa, los servicios de terceros y la instalación real continúan sometidos a validación explícita por entorno.

## 1. Resultado verificable del cierre solicitado

| PRE | Decisión adoptada | Documento autoritativo | Tipo de cierre y reserva |
| --- | --- | --- | --- |
| PRE-01 | Dos GET privados R8-65/66 para Libro y ARCO en Angular; R8-41/43 resúmenes, R8-42/44 respuestas y resoluciones, sin operador ni GET público de expediente | D-189; docs/21; docs/24; OpenAPI 0.4.0-draft | CERRADO EN CONTRATO DE API. Seguridad runtime sin probar |
| PRE-02 | Admisión MP 60 s sin renovar; grant sensible 5 min/uso único; cookie invitado 30 min de inactividad hasta máximo enlace 90 d. OTP 10m, descarga 24h y hold 15m invariables | D-190; docs/20/37 | CERRADO EN POLÍTICA. CSRF/replay/compatibilidad por verificar |
| PRE-03 | Mantener 39 tablas y adoptar 16 tratamientos DB-A con índices/locks/estados/auditoría/CAS y fail-closed | D-191; docs/23/36/38 | CERRADO EN DISEÑO LÓGICO. DDL ejecutable/migraciones y tests aún inexistentes |
| PRE-04 | Separar datos del checkout frente a Libro/ARCO/fiscalidad; identificar deber por modalidad, actividad y régimen, sin SUNAT automático ni IGV universal | D-192; docs/25/26/35 | CERRADO COMO POLÍTICA DE APLICABILIDAD. La conformidad de cada comercio no se certifica por diseñar el plugin |
| PRE-05 | INEI oficial, archivo distrital versionado y local, delivery por códigos y fallback a recojo; revisar recurso 2025-12 contra datos completos 2023 y consulta oficial | D-193; docs/33 | CERRADO COMO ESTRATEGIA. **Sin dataset íntegro verificado no habilitar delivery** |
| PRE-06 | Entregar ZIP, fuente correspondiente, GPL-2.0-or-later, avisos y metadatos de cada misma versión desde un canal accesible a sus destinatarios | D-194; docs/32 | CERRADO COMO POLÍTICA DE DISTRIBUCIÓN. ZIP/fuentes/lockfile/licencias reales sin auditar |
| PRE-07 | Archivo Figma editable con **34 frames** de diecisiete pares móvil/escritorio: catálogo, checkout, cita, expedientes legales, error de cotización, seguimiento, Libro público, inventario, digital y confirmación | docs/11; https://www.figma.com/design/qJaGwnjfaI35gYDjFwzBub | ENTREGABLE CREADO. **Falta aprobación visual del propietario** y completar estados secundarios del contrato UX |

**Evidencia estática del OpenAPI:** `docs/openapi/peru-commerce-v1.openapi.draft.json` versión `0.4.0-draft` cuenta con **65 operaciones REST, 62 rutas y 115 esquemas**. R8-18 es página WordPress fuera del OpenAPI. Los anteriores conteos 63/60/113 pertenecen al inventario histórico previo a D-189. PER-09 exportación comercial general sigue excluida por D-188.

## 2. No confundir diseño con verificación

| Puerta | Estado al corte | Evidencia pendiente |
| --- | --- | --- |
| P-A — especificación antes de código | Funcionalidades/políticas y seis PRE cerrados; **PRE-07 sin aprobación visual** y cobertura de excepciones secundarias aún pendiente | Revisión y aprobación de los frames/variantes/estados requeridos por V1 y consistencia final de P-A |
| P-B — autorización de código | **No concedida** | Solicitud explícita del propietario; alcance por módulo, repositorio y archivo |
| P-C — pruebas y validación de comportamiento | **No ejecutada** | Compilar y probar solo con permiso futuro específico; concurrencia, IDOR, CSRF, tokens, caché, ZIP/restore, accesibilidad |
| P-D — release e instalación real | **No habilitada** | MP Perú Orders (payer omitido/ítem Entrega/PT15M), InnoDB/Hostinger, dataset UBIGEO completo/licencia, storage privado, normativa por comercio, fuente GPL y recuperación comprobada |

**Compatibilidad no verificada:** no afirmar que un WordPress/Hostinger concreto admite bloqueo de filas, almacenamiento privado, método de pago o volumen sin pruebas. Tampoco afirmar que la publicación del proyecto RM 244-2026-PCM sea el decreto final de reforma del Libro; antes de release verificar reglamento vigente para modalidad del comercio. Los instrumentos J-01…J-05 y controles de retención son contractuales, no asesoría jurídica individual.

## 3. Orden de implementación cuando el propietario lo autorice

1. Preparar diseño de migraciones y capa de persistencia/seguridad con 39 tablas e invariantes D-191. No activar cobro/stock/cita ante ausencia de InnoDB real.
2. Renderizar catálogo indexable desde WordPress/PHP, con frontend Angular solo en superficies interactivas, respetando diseño Figma aprobado y permisos.
3. Construir pedidos, estados, stock, agenda y contraentrega en transacciones, sin HTTP MP dentro de locks SQL.
4. Habilitar integraciones optativas únicamente después de demostrar preflight del entorno: Mercado Pago en cuenta autorizada; digital con storage privado; delivery con datos INEI válidos y tarifa configurada.
5. Completar trámites de consumidor/Libro/ARCO, configuraciones del comercio y garantías de registro externo fiscal sin automatizar SUNAT; cerrar licencias/ZIP/restore y criterios de entrega.

Este orden organiza trabajo futuro y **no autoriza crear código por sí mismo**.

## 4. Fuentes y rastro de decisiones

- Decisiones: `docs/01-DECISIONES-Y-ESTADO.md` (D-01…D-194).
- Políticas iniciales E-01…E-08: `docs/14`…`docs/21`; extensiones y matrices `docs/22`…`docs/39`.
- Fuente de REST: `docs/openapi/peru-commerce-v1.openapi.draft.json` 0.4.0-draft.
- Fuente de UX: archivo Figma enlazado arriba; aprobación aún pendiente.
- WordPress: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
- INEI fuente completa 2023: https://www.datosabiertos.gob.pe/dataset/ubigeos-c%C3%B3digos-de-ubicaci%C3%B3n-geogr%C3%A1fica-instituto-nacional-de-estad%C3%ADstica-e-inform%C3%A1tica-inei
- INEI ficha 2025-12: https://www.datosabiertos.gob.pe/dataset/c%C3%B3digo-de-ubicaci%C3%B3n-geogr%C3%A1fica-en-el-per%C3%BA-instituto-nacional-de-estad%C3%ADstica-e-inform%C3%A1tica
- Mercado Pago Perú, Orders: https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/create-order
- Proyecto PCM RM 244-2026-PCM: https://www.gob.pe/institucion/pcm/normas-legales/8406634-244-2026-pcm

**Declaración final responsable:** hay un contrato pre-código más cerrado, pero **no sería correcto marcar LISTA_PARA_IMPLEMENTAR toda la V1** mientras PRE-07 carezca de revisión/aprobación visual y persistan sus estados secundarios. Tampoco es correcto condicionar la primera línea de código a pruebas de una integración todavía inexistente; P-C/P-D son pasos distintos.
