# Idiomas e internacionalización — política 2026-11-22

## Decisiones confirmadas de producto

- **PeruCommerce:** orientado exclusivamente al mercado peruano. UI y mensajes de producto solo en español de Perú, locale objetivo `es_PE`. Sus contratos fiscales, moneda PEN, UBIGEO y controles específicos de Perú no se generalizan a otros países por esta política. No implementar traducciones adicionales para PeruCommerce sin una decisión posterior. El nombre comercial se mantiene.
- **Los otros 130 candidatos:** productos internacionales, con **inglés `en_US` como idioma base de textos fuente** y diseño traducible. Primeras localizaciones **objetivo**, no completadas: español `es_ES` y `es_MX`, portugués de Brasil `pt_BR`. Otros locales solo cuando haya traducciones reales, colaboradores y revisión del contexto. No llamar «compatible» a una traducción inexistente.
- **Nombres:** todos los 131 nombres comerciales de trabajo terminan en «VSG» (incluido «PeruCommerce VSG»); no se traducen los nombres comerciales ni se alteran los identificadores de WordPress. Los nombres en inglés son **nombres de trabajo**; marca legal, colisiones y slugs deberán comprobarse. El idioma visible puede traducir descripciones, nunca cambiar IDs, permisos, nombres de recursos ni contratos por accidente.
- La localización de la interfaz del plugin **no traduce automáticamente contenido generado por usuarios**, datos de proveedores, términos legales, ni transforma operaciones de un país en cumplimiento de otro.

## WordPress / PHP

1. Mantener un `Text Domain` **igual al slug real del plugin instalable** (no al nombre de presentación) y código fuente preparado para gettext usando las funciones de i18n WordPress.
2. Traducir frases completas con contexto y plurales, proteger variables mediante placeholders y respetar escaping.
3. Conservar mensajes REST internacionalizables sin exponer errores ni datos sensibles, separando códigos estables de descripciones para humanos.
4. Usar la configuración de locale de WordPress por usuario/sitio donde corresponda; no imponer un locale global a los demás plugins.
5. En PeruCommerce, respetar `es_PE` y requisitos fiscales/peruanos sin convertir el backend en un motor multijurisdiccional.

## Angular + Angular Material

- Al crear cada workspace, escoger estrategia i18n compatible con Angular 22 (por ejemplo `@angular/localize`) según si se necesitan bundles por idioma o cambio dinámico. No instalarlo en 131 carpetas vacías ni afirmar que hay translations listas.
- Extraer cadenas de plantillas y componentes, traducir validaciones y accesibilidad, respetar longitud del texto y formatos de fecha, moneda, números y zona horaria según locale.
- Cargar assets y mensajes del idioma activo sin cargar todos los bundles globalmente; mantener sincronía entre PHP, Material, WordPress REST y frontend.
- El idioma de la UI **no** debe afectar precisión de cantidades/cálculos, reglas fiscales, datos almacenados, hashes o identificadores.
- Definir un proceso sencillo para contribuciones comunitarias de traducciones, revisión y atribución, sin obligar a pagar APIs de traducción.

## Evidencia requerida en un release

Registrar qué locales se han implementado realmente, cobertura textual, muestras de pantallas, revisión por hablantes, fallbacks, configuraciones WordPress/Angular y tests ejecutados. No inferir disponibilidad a partir de este documento.

## Referencias oficiales

- WordPress Plugin Handbook: https://developer.wordpress.org/plugins/internationalization/
- WordPress text domains: https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/
- Angular i18n: https://angular.dev/guide/i18n
- Angular localize: https://angular.dev/guide/i18n/add-package
