# Fases del portafolio: 131 proyectos WordPress

**Estado:** 131 carpetas de investigación en este repositorio público; solo PeruCommerce tiene fuente ejecutable en el repositorio, todavía sin compilación ni pruebas acreditadas. Las 130 fichas restantes no tienen alcance aprobado.

| Fase | Entregable | Estado |
| --- | --- | --- |
| F0 — Preparación | Carpeta, README, ficha PLAN-BASE, arquitectura y criterios técnicos comunes | Documentada para los 131 |
| F1 — Alcance | Problema, usuarios, V1, exclusiones, competencia y criterios observables | Pendiente de definición del propietario (excepto decisiones previas de PeruCommerce) |
| F2 — Diseño | Contratos REST/datos/roles, flujo UX móvil/escritorio, dependencias, seguridad, integración y costos API | Pendiente |
| F3 — Backend | Implementación PHP/WordPress y persistencia justificada | Pendiente por candidato |
| F4 — UI | Angular + Angular Material, bundles limitados, estados y accesibilidad | Pendiente por candidato |
| F5 — Revisión | Contratos, consumidores, permisos, seguridad, migraciones y riesgos | Pendiente por candidato |
| F6 — Comprobación y distribución | Con autorización: build/tests, licencia, ZIP, fuente y publicación independiente | Pendiente por candidato |

**Regla:** F0 no autoriza ejecutar código, compilar, instalar dependencias, probar, migrar, generar ZIP, publicar ni desplegar. No crear archivos de alcance o API sin las decisiones de F1/F2. Descartar candidatos redundantes si WordPress, PeruCommerce u otro plugin ya resuelven el problema.

**PeruCommerce conserva su plan canónico** D-01…D-200 e IMP-01…IMP-72; la adenda POS docs/47 no queda sustituida por esta estructura general.

## Secuencia por plugin

1. Leer `AGENTS.md` de raíz, catálogo, estándar técnico, README y PLAN-BASE local. Para PeruCommerce leer además instrucciones y contratos propietarios.
2. Confirmar usuario destinatario, problema, versión V1 y límites; distinguir función del plugin, servicio externo pagado por usuario y servicio pagado por Vanguard.
3. Aprobar alcance antes de diseñar APIs/tablas/rutas; separar proyecto, versión, datos y licencias.
4. Implementar backend PHP y frontend Angular Material con carga selectiva y sin dependencias genéricas.
5. Revisar estáticamente cambios e interacciones afectadas; comprobar mediante pruebas/compilaciones únicamente con autorización.
6. Entregar ZIP por plugin, fuentes correspondientes y avisos de licencia cuando proceda, sin publicar el monorepositorio ni información privada.

## Extensiones transversales a decidir por producto

- **F1:** evaluar nombre de trabajo, riesgo de colisión, mercado/idiomas reales y utilidad concreta de integrar asistentes. Los nombres están en [identidades](IDENTIDADES-131.md).
- **F2:** definir i18n y traducciones según [política de idiomas](INTERNACIONALIZACION-131.md); si MCP aporta valor real, definir herramientas, permisos y modalidad de conexión con [estrategia MCP](MCP-CLAUDE-CODEX-131.md).
- **F3/F4:** implementar solo lo aprobado y mantener funcionamiento completo sin IA.
- **F5/F6:** revisar seguridad e internacionalización, y documentar integraciones MCP ejecutadas solo cuando existan y hayan sido probadas.
- **OSS:** conservar evidencia pública real de licencia, código, releases y contribuciones, según [registro de impacto](OPEN-SOURCE-IMPACT.md). Nunca fabricar señales para una selección.
