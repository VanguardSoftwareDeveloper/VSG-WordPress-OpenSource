# PeruCommerce VSG — Visión del proyecto

**Estado real:** único candidato con fuente implementada, todavía sin validación ejecutada. El POS integrado está pendiente. Esta ficha **no sustituye** los contratos D-01…D-200 ni el plan IMP-01…IMP-72.

## Valor público propuesto

Facilitar comercio electrónico peruano y un futuro POS integrado. Comercio electrónico orientado a pequeños negocios peruanos sin obligación de adoptar un SaaS del fabricante. El código existente declara GPL-2.0-or-later; aún no hay un paquete instalable validado ni publicación pública.

**Idioma:** solo español de Perú (`es_PE`), conforme a la decisión del propietario. No presentar el producto como multijurisdiccional o traducido a otros idiomas.

## Integración MCP futura (Claude Code / Codex)

**Hipótesis:** consultas autorizadas y minimizadas sobre el estado comercial, sin exponer datos privados ni realizar cargos, anulaciones, movimientos de inventario o cambios fiscales por una llamada sin aprobación humana. La prioridad es completar y verificar el checkout/POS existente.

**Estado:** no hay servidor ni herramientas MCP implementadas. Se decidirá si conviene un bridge local hacia REST WordPress o un endpoint MCP remoto conforme a la especificación vigente, con controles por instalación y capacidades.

## Próximas evidencias verificables

- Completar el POS y mantener coherencia con ventas web, dinero y stock.
- Ejecutar IMP-72 y validación con autorización específica, registrar resultados y preparar fuente GPL correspondiente.
- Preparar repositorio propio, revisar historial y secretos, publicar solo después de las puertas de seguridad.
- Acreditar adopción, contribuciones y resultados reales; la documentación no demuestra impacto.

## References

- [Existing description](README.md) · [Architecture and phases](PLAN-BASE.md)
- [Portfolio impact and evidence](../../docs/OPEN-SOURCE-IMPACT.md)
- [MCP roadmap](../../docs/MCP-CLAUDE-CODEX-131.md) · [Language policy](../../docs/INTERNACIONALIZACION-131.md)

**This is a project concept/roadmap, not an Anthropic or OpenAI endorsement, qualification, approved integration or proof of impact.**
