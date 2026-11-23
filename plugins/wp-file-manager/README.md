# wp-file-manager — Gestión de archivos del servidor

**Nombre comercial propuesto:** **File Browser VSG**. Nombre de presentación; los identificadores técnicos permanecen sin cambios.

**Categoría:** 108/131 — Gestión de archivos del servidor  
**Estado:** Carpeta creada para planificar; sin PHP ejecutable, instalación ni ZIP.  
**Monetización:** Gratuito por defecto; APIs externas facturadas al usuario cuando proceda.

## Para qué serviría

Administrar archivos del sitio desde WordPress, fuera de la biblioteca de medios.

## Dirección técnica inicial (por evaluar)

Contención de rutas, bloqueo de directorios sensibles, auditoría y mínimo privilegio.

## Referentes del mercado (solo investigación)

[File Manager](https://wordpress.org/plugins/wp-file-manager/)

**Instalaciones de referencia:** 1 millón+ (observación del catálogo al 20/11/2026; no son ventas verificadas).

**Relación con otras categorías:** Alto riesgo de seguridad; no otorgar escritura de archivos vía MCP por defecto.

**Dificultad estimada:** 5/5. Estimación de ingeniería del inventario, no un cálculo de costo ni tiempo.

## Antes de implementar

Delimitar la V1, resolver solapamientos con las demás carpetas, definir permisos y datos, verificar compatibilidad WordPress/PHP, licencias y dependencias, y decidir el modelo comercial. Mantener versión, distribución y contratos propios; la biblioteca `shared/` es opcional. No afirmar que existe un plugin instalable por tener esta ficha.

[Volver al catálogo operativo](../../docs/CATALOGO.md).
