# Criterios de la fábrica de plugins

## Alcance de las fichas

Las 131 carpetas representan categorías de investigación del inventario revisado el 20/11/2026. Son **propuestas, no especificaciones completas ni productos comprometidos**. Las fichas conservan finalidad, referentes, dirección técnica y dificultad estimada. Las referencias a otros plugins son objeto de estudio, no dependencias aprobadas. Las cifras de instalaciones, cuando aparecen, son observaciones históricas del 20/11/2026 y no prueban pagos o demanda para nuestros productos.

## Restricciones generales

- Cada plugin será instalable y versionable de forma independiente cuando se implemente. No instalar el monorepositorio completo.
- Reutilizar `shared/` solo si varios consumidores comparten efectivamente la necesidad; no crear un framework WordPress general por anticipado.
- Priorizar seguridad, permisos y privacidad, protección de datos, manejo de errores, rendimiento en hosting compartido y uso de APIs oficiales.
- Los productos pueden ofrecer español e inglés, con localización por país cuando el dominio lo exija; no declarar idiomas compatibles hasta implementarlos.
- No depender de Elementor ni de un constructor visual generalista sin decisión explícita del producto.
- IA, Abilities API, MCP, REST/headless y servicios externos son opcionales y se incorporarán solamente cuando mejoren el producto real.
- **Objetivo técnico actualizado (22/11/2026):** WordPress **7.1.5**, PHP **8.3 como mínimo**, Angular **22** y Angular Material **22** para cada interfaz cuando se implemente; consultar `docs/ESTANDAR-TECNICO-131.md`. Declarar `Requires at least`, `Requires PHP` y `Tested up to` según código/validaciones reales, sin afirmar compatibilidad por una ficha. **PeruCommerce conserva su header real 7.1.4 / PHP 8.3** hasta una revisión y autorización de modificación específicas. Vigilar nuevas versiones estables compatibles.
- **Política expresa del 22/11/2026:** todos los plugins son gratuitos por defecto. Desarrollo, mantenimiento y soporte voluntario NO cuentan como costo para decidir el precio. Si una integración requiere API o servicio facturado, debe usar la cuenta y cuota del cliente siempre que sea viable (BYOK). Solo se contempla cobrar por un servicio administrado opcional cuando exista un gasto externo real facturado a Vanguard; definirlo y limitarlo antes de ofrecerlo. Ver `docs/PORTAFOLIO-Y-SEPARACION.md`.
- Antes de implementar una categoría, resolver alcance V1, consumidor objetivo, solapamientos con otras carpetas, licencias/atribuciones, contratos, dependencias y riesgos.
- Documentar pruebas y compilaciones únicamente cuando se hayan ejecutado con autorización; diferenciar revisión estática, artefacto, instalación y producción.
- No copiar nombres, marcas, activos ni código de competidores sin licencia que lo permita.
- No modificar consumidores de otros repositorios ni instalaciones por inferencia.

## Flujo por plugin

1. Revisar su `plugins/<slug>/README.md` y [CATALOGO.md](CATALOGO.md).
2. Investigar referentes actuales y definir alcance comercial/funcional real, sin asumir viabilidad por el tamaño de un competidor.
3. Implementar solo el código necesario en esa carpeta; incorporar código común si hay necesidades repetidas demostradas.
4. Revisar estáticamente el cambio y los consumidores directamente afectados.
5. Registrar estado correcto en la ficha y el índice; ejecutar validación, empaquetado, releases o despliegues solo bajo autorización específica.
