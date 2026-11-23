# Hoja de ruta MCP para Claude Code y Codex — 131 proyectos

**Decisión de planificación (22/11/2026):** mantener la posibilidad de conectar los plugins a asistentes mediante el estándar abierto Model Context Protocol (MCP), **sin crear todavía 131 servidores MCP** ni convertirlo en requisito de instalación. No se ha construido, configurado ni verificado ningún servidor MCP de estos plugins.

## Qué es y qué no es

- **Claude Code y Codex son clientes potenciales de MCP**. El servidor MCP es una integración aparte que traduce las herramientas disponibles a consultas/operaciones autorizadas del plugin. Un README o archivo `.mcp.json` por sí solo **no implementa** esas herramientas.
- **No hay dependencia obligatoria de la API de Anthropic u OpenAI para utilizar un plugin WordPress.** El usuario conectará su propio cliente/asistente y asumirá sus suscripciones o facturas si son necesarias.
- MCP debe facilitar **tareas reales** (diagnóstico, consulta de recursos, operaciones auditadas), no añadirse como decoración para una solicitud de Claude for Open Source.
- El protocolo vigente consultado es **MCP 2026-07-28**. Revalidar versión, transportes y compatibilidad de SDK/cliente antes de implementar.

## Arquitectura por evaluar en F1/F2

```text
Claude Code / Codex (cliente con consentimiento del usuario)
            │ MCP
    Adaptador opcional por plugin o bridge local
            │ credenciales exclusivas del sitio y permisos limitados
 WordPress HTTPS / REST autorizada
            │ PHP, permisos por recurso, nonce/CSRF si aplica
   Datos en hosting WordPress del usuario
```

**Candidato preferente para hosting compartido:** bridge MCP local en el equipo del usuario (transporte STDIO) cuando resulte más simple y seguro. Consume WordPress REST sobre HTTPS con identidad/capacidades del usuario, sin servidor de Vanguard. **Alternativa condicional:** endpoint MCP remoto en la instalación WordPress solo si transporte HTTP, autenticación, hosting y restricciones de seguridad han sido comprobados; no asumir que PHP/Hostinger admite todas las modalidades o que el navegador puede usarlo automáticamente.

Cada plugin define **sus herramientas mínimas** tras aprobar el alcance: iniciar con herramientas de lectura y proyecciones filtradas; considerar operaciones de escritura solo cuando un caso real lo requiera y exista autorización explícita, validación de parámetros, trazabilidad, recuperación e idempotencia.

## Controles de seguridad no negociables

- Autorización en **cada invocación**: contexto de instalación, identidad de usuario, capabilities WordPress, recurso, campos permitidos y estado. Nunca inferir permiso de la sola conexión MCP.
- **Mínimo privilegio**: preferir recursos y herramientas de solo lectura. No exponer contraseñas, tokens, PII completa, pagos, archivos protegidos, logs privados o consultas SQL arbitrarias.
- Para dinero, stock, impuestos, emisión fiscal, borrado, usuarios, pagos, permisos y acciones destructivas: no se habilitan acciones autónomas; requerir aprobación humana, auditoría, idempotencia, límites y controles del dominio. Un asistente no sustituye autoridad empresarial ni validación del proveedor.
- Transportes **STDIO** y **HTTP** tienen modelos de autenticación distintos; seguir la especificación MCP vigente para OAuth/metadata de servidor HTTP cuando se habilite. Proteger credenciales en almacén del cliente, nunca en el repositorio público.
- Sin token genérico compartido entre los 131 plugins ni acceso transversal a distintas tiendas; sin proxy facturado a Vanguard por defecto; límites de tasa y desconexión/rotación.
- Mantener la UI Angular y operaciones manuales **plenamente funcionales sin IA ni MCP**.

## Secuencia verificable

| Fase | Entregable | Estado |
|---|---|---|
| M0 — Posibilidad | Documento de estrategia, propuesta de lectura por candidato y riesgos | Documentado; no implementado |
| M1 — Utilidad | Elegir 1–3 casos reales del producto aprobado; decidir si MCP aporta beneficio frente a REST y UI | Pendiente |
| M2 — Contrato | Nombres de tools, schemas, resultados, permisos, confirmaciones y límites; adaptación de versiones MCP/cliente | Pendiente |
| M3 — Implementación | Bridge o servidor integrado **opcional** que reuse el backend WordPress sin duplicar lógica | Pendiente |
| M4 — Verificación | Con autorización: Claude Code/Codex reales, credenciales sintéticas, negativas de permisos, reintentos, auditoría | Pendiente |
| M5 — Distribución | Documentación de conexión, ejemplo de configuración **sin secretos** y matriz de compatibilidad verificadas | Pendiente |

**PeruCommerce** está cubierto por esta intención futura, pero su POS y sus controles financieros actuales tienen prioridad; ninguna integración MCP de escritura debe saltarse su contrato de dinero y permisos.

## Referencias oficiales

- MCP release 2026-07-28: https://blog.modelcontextprotocol.io/posts/2026-07-28/
- MCP herramientas: https://modelcontextprotocol.io/specification/2026-07-28/server/tools
- MCP autorización: https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization
- Claude Code MCP: https://code.claude.com/docs/en/mcp
- Codex y MCP: https://learn.chatgpt.com/docs/extend/mcp
