# wp-admin-columns — Plan base (107/131)

**Estado:** proyecto documental; aún sin implementación PHP/Angular, pruebas, ZIP, instalación ni distribución.

**Sin alcance funcional aprobado:** la arquitectura y las pantallas descritas son hipótesis de trabajo, no requisitos V1.

## Descripción

Personalizar columnas, ordenamiento y filtros en listados del panel.

**Familia:** Administración avanzada. **Usuario preliminar:** administrador técnico (por confirmar).

## Arquitectura propuesta — pendiente de alcance

- **Responsabilidad WordPress/PHP:** gestionar el dominio descrito mediante capacidades del core, APIs de sistema y registro de operaciones sensibles; valorar qué funciones nativas del núcleo sirven antes de definir tablas, endpoints o estado.
- **Frontend Angular Material:** interfaz de trabajo preliminar para pantallas administrativas protegidas con previsualización y confirmación. Las pantallas exactas se definirán tras F1, con vistas móviles/escritorio, permisos en backend y assets solo en superficies propias.
- **Datos y reglas:** las entidades se identificarán desde personalizar columnas, ordenamiento y filtros en listados del panel. al definir contratos reales; no se aprueban todavía tablas, campos, roles o rutas REST.
- **Servicios externos:** ninguno es requisito confirmado todavía; intentar ejecución local en hosting del usuario. Integraciones futuras requieren alcance explícito y, si tienen costo, cuenta del usuario.
- **Riesgo y límite relevante:** prevenir escalada de privilegios, RCE y operaciones destructivas

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

- [x] **F0:** descripción y arquitectura inicial como hipótesis, sin código.
- [ ] **F1:** definir usuarios, objetivo, alcance V1, exclusiones y aceptación.
- [ ] **F2:** resolver diseño UI, dominio, REST, roles, persistencia y APIs externas.
- [ ] **F3:** implementar backend PHP WordPress.
- [ ] **F4:** implementar UI Angular + Angular Material.
- [ ] **F5:** revisión estática y corrección de coherencia.
- [ ] **F6:** comprobación, ZIP y publicación solo con autorización.

## Documentos mínimos

- [README.md](README.md): finalidad y estado actual; este `PLAN-BASE.md`: preparación técnica y fases.
- Futuro `docs/ALCANCE.md` **solo tras decisión de F1**. Crear `docs/CONTRATOS.md` solo cuando datos/API/estados lo justifiquen y `docs/VERIFICACION.md` con evidencia real tras ejecutar pruebas autorizadas.
- [Fases comunes](../../docs/FASES-131.md). **No construir ni distribuir este candidato por la sola presencia de la ficha.**
