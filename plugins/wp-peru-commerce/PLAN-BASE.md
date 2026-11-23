# wp-peru-commerce — Plan base (60/131)

**Estado:** PeruCommerce posee código, contratos previos y revisiones estáticas; IMP-72/POS sin completar.

**Precedencia:** este índice no modifica D-01…D-200, IMP-01…IMP-72, `AGENTS.md`, `docs/43` ni `docs/47` de PeruCommerce.

## Descripción

Gestionar productos, carrito, pedidos y ventas.

**Familia:** Ventas y comercio. **Usuario preliminar:** comerciante y operador (por confirmar).

## Arquitectura propuesta — pendiente de alcance

- **Responsabilidad WordPress/PHP:** gestionar el dominio descrito mediante pedidos, inventario y eventos monetarios con transacciones si el alcance lo exige; valorar qué funciones nativas del núcleo sirven antes de definir tablas, endpoints o estado.
- **Frontend Angular Material:** interfaz de trabajo preliminar para catálogo, tareas comerciales, detalles y estados de operación. Las pantallas exactas se definirán tras F1, con vistas móviles/escritorio, permisos en backend y assets solo en superficies propias.
- **Datos y reglas:** las entidades se identificarán desde gestionar productos, carrito, pedidos y ventas. al definir contratos reales; no se aprueban todavía tablas, campos, roles o rutas REST.
- **Servicios externos:** ninguno es requisito confirmado todavía; intentar ejecución local en hosting del usuario. Integraciones futuras requieren alcance explícito y, si tienen costo, cuenta del usuario.
- **Riesgo y límite relevante:** El POS se integra en PeruCommerce antes de la publicación propuesta, sujeto a contratos y verificaciones que todavía faltan.

## Compatibilidad prevista

- WordPress **7.1.5**, PHP **8.3 como mínimo** y revisiones de estables actuales; Angular **22** y Angular Material/CDK **22** para sus interfaces. Reglas y notas de Hostinger en [estándar técnico](../../docs/ESTANDAR-TECNICO-131.md).
- Compilación de Angular **fuera de Hostinger**, sin Node de producción. Plugin instalable independientemente, sin WooCommerce ni framework común obligatorio.
- Distribución **gratuita** prevista. Si hay servicios externos, la cuenta/clave y cuota serán del usuario siempre que el proveedor lo permita.
- Objetivos de compatibilidad, **no pruebas ejecutadas ni código implementado**.

## Decisiones que faltan

- [ ] Caso de uso concreto, destinatarios, diferenciación frente a opciones existentes.
- [ ] V1, exclusiones, requisitos de accesibilidad y pantallas Angular Material.
- [ ] Entidades, flujos, datos, roles, endpoints y mecanismos de seguridad.
- [ ] Integraciones, servicios externos, licencias, país, costos y cuentas pagadoras.
- [ ] Escenarios de aceptación y reglas de distribución.

## Fases

- [x] **F0:** registro de proyecto y complemento documental; el plan canónico conserva su avance original.
- [ ] **POS/contratos y código:** ejecutar el alcance aprobado en docs/47 antes del cierre final.
- [ ] **IMP-72:** auditoría/validación y empaquetado con autorización específica.
- [ ] **Publicación pública:** repositorio propio seguro, sin historial ajeno ni secretos.

## Documentos mínimos

- [README.md](README.md): finalidad y estado actual; este `PLAN-BASE.md`: preparación técnica y fases.
- Futuro `docs/ALCANCE.md` **solo tras decisión de F1**. Crear `docs/CONTRATOS.md` solo cuando datos/API/estados lo justifiquen y `docs/VERIFICACION.md` con evidencia real tras ejecutar pruebas autorizadas.
- [Fases comunes](../../docs/FASES-131.md). **No construir ni distribuir este candidato por la sola presencia de la ficha.**
