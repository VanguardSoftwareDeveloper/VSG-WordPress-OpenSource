# Estándares de UX, ergonomía móvil y accesibilidad — PeruCommerce

**Corte:** 2026-11-16. **Estado:** objetivos WCAG 2.2 AA y táctiles 44 × 44 px CSS CONFIRMADOS para V1 (D-16/D-17); heurísticas, layout, presupuesto de rendimiento y demás medidas siguen PROPUESTAS. No implica que el plugin o sus diseños estén implementados, probados ni certificados.

Este documento especifica reglas para diseñar y evaluar las interfaces públicas y administrativas. Complementa [04-UX-Y-FLUJOS.md](04-UX-Y-FLUJOS.md). Las decisiones de negocio confirmadas tienen precedencia: una heurística de UX no autoriza alterar pagos, stock, cumplimiento, reservas ni normativa.

## 1. Usuarios y propósito

**Objetivo confirmado:** permitir comprar y operar una tienda WordPress peruana con el menor esfuerzo razonable, sin registro obligatorio del comprador. No presumir un perfil homogéneo por edad o «generación». Diseñar para compradores nuevos y recurrentes, distintas habilidades, móviles económicos y de alta gama, pantallas grandes, conexión lenta o intermitente y condiciones de uso con una sola mano.

### Objetivos de experiencia propuestos

- Encontrar un artículo, conocer su disponibilidad y precio, agregarlo, seleccionar entrega/reserva y pagar sin navegación innecesaria.
- Pedir solo datos justificados por el artículo, método de entrega, pago y obligación normativa.
- Conservar entradas y selección de variantes/horarios durante errores, redirecciones y retornos de pago, cuando sea técnicamente seguro.
- Explicar qué se cobrará, cuándo se entregará y qué acción falta antes de comprometer una compra.
- Permitir corregir cualquier información relevante antes de confirmar, sin perder el carrito.
- Informar el estado real del pedido, incluso con proveedor externo demorado, sin mostrar falsamente «pagado».

**No adoptar reglas arbitrarias** como «tres clics como máximo», «todas las compras en 30 segundos» o «una pantalla sin desplazamiento». Medir tareas, campos, errores y tasa de éxito reales antes de fijar números.

## 2. Principios de interacción y heurísticas aplicables (guía para Codex)

**Clasificación:** Fitts, Hick-Hyman, Jakob, Tesler, Postel y Doherty son referencias de diseño con alcance y condiciones distintos; las restantes son heurísticas, efectos perceptivos o criterios de usabilidad. **No son 18 leyes normativas, métricas automáticas ni nuevas decisiones comerciales D.** Se aplican cuando mejoran la tarea concreta y siempre subordinadas a permisos, estados, dinero, privacidad y obligaciones aprobados. Solo **WCAG 2.2 AA (D-16)** y **objetivos táctiles independientes ≥ 44 × 44 px CSS (D-17)** tienen aquí condición expresa de requisito CONFIRMADO. El resto orienta decisiones y aceptación UX, con revisión contextual.

| Principio | Aplicación proporcionada a PeruCommerce | Evidencia observable / error que evitar |
| --- | --- | --- |
| **Ley de Fitts** | Acciones principales de compra, cantidad y agenda con área activable amplia, separación útil y posición alcanzable; sin ocultarlas tras teclado o barra fija | Control táctil ≥ 44 × 44 px CSS cuando sea independiente (D-17); ausencia de solapes y activaciones accidentales |
| **Ley de Hick-Hyman** | Mostrar solo alternativas de entrega y pago elegibles para el carrito y opciones de la tarea; agrupar elecciones complejas | No mezclar delivery/recojo/agenda/MP/efectivo cuando no apliquen ni suprimir opciones válidas |
| **Ley de Jakob** | Patrones familiares de catálogo, variantes, carrito, checkout, calendario y seguimiento; etiquetas reconocibles | Compra y navegación posibles sin aprender gestos o iconos propietarios |
| **Ley de Tesler (conservación de complejidad)** | Resolver reglas fiscales, stock, cupos, tarifas y elegibilidad en el backend; mostrar al usuario la decisión y su motivo, no el algoritmo | El frontend no obliga a calcular importes ni conocer estados internos, pero tampoco oculta información contractual |
| **Ley de Postel, aplicada con límites** | Tolerar pegar, espacios superficiales y formatos razonables de teléfono/dirección al capturar; normalizar solo transformaciones inequívocas antes de validar | El backend sigue estricto para UBIGEO de seis dígitos (incluidos ceros iniciales), dinero entero en céntimos, identidad, token, idempotencia, permisos y fechas; jamás aceptar ambigüedad por «ser flexible» |
| **Umbral de Doherty / fluidez percibida** | Feedback perceptible al interactuar, estados de espera y controles temporalmente protegidos de doble envío; carga progresiva de lo secundario | No imponer un máximo de 400 ms ni fabricar resultados: «procesando»/«verificando» hasta confirmación autoritativa |
| **Efecto de usabilidad estética** | Jerarquía limpia, tipografía legible, contraste y espaciado coherentes que inspiren confianza sin ornamentación innecesaria | La percepción visual no sustituye tareas completas, accesibilidad, rendimiento ni información de cobro honesta |
| **Gestalt: proximidad, agrupación, continuidad y jerarquía** | Agrupar variante/cantidad/precio, datos de entrega, agenda, resumen y pago por significado; continuidad clara entre secciones | Relaciones visibles sin depender únicamente del color o de una decoración |
| **Divulgación progresiva** | Mostrar datos fiscales, domicilio, mapa, hora, contacto y evidencias según producto, modalidad, fase y rol | Mantener accesibles los detalles necesarios y obligaciones legales; no esconder el total ni restricciones relevantes |
| **Reconocimiento antes que recuerdo** | Conservar a la vista o disponible el producto, la variante, la franja, la dirección y el total pactado | El comprador no necesita recordar una selección de otra pantalla ni volver a introducir datos correctos |
| **Prevención de errores** | Validación cerca del campo, disponibilidad revalidada por servidor y avisos antes de confirmar consecuencias relevantes | No permitir acciones imposibles sin explicación; tampoco bloquear injustificadamente una corrección |
| **Reducción de carga cognitiva** | Lenguaje cotidiano, opciones priorizadas y una acción principal por contexto; ayuda breve donde hay incertidumbre | No exponer identificadores técnicos, estados internos crudos o formularios innecesariamente extensos |
| **Visibilidad del estado del sistema** | Diferenciar pedido recibido, pago pendiente, pago acreditado, reserva confirmada, en tránsito e incidencia | No mostrar «pagado», «entregado» ni «reembolsado» hasta la prueba correspondiente |
| **Consistencia y estándares** | Etiquetas, iconos semánticos, comportamiento, foco y errores coherentes en comprador, operador y administrador, con composiciones móviles/escritorio propias | No renombrar el mismo hecho por pantalla o conceder permisos por parecido visual |
| **Control y libertad del usuario** | Poder volver, editar carrito/entrega/cita antes del compromiso y cerrar diálogos sin perder datos seguros | No ofrecer deshacer operaciones irreversibles, reembolsos automáticos o cancelar después de entregar fuera de las reglas aprobadas |
| **Flexibilidad y eficiencia para usuarios experimentados** | En el panel, búsqueda/filtros, navegación por teclado y acciones contextuales cuando simplifiquen trabajo repetido | No crear atajos que omitan autorización, evidencia, confirmación sensible o revalidación de versión |
| **Accesibilidad y ergonomía** | Teclado, lector de pantalla, zoom, foco, contraste, reflow, alternativa sin arrastre y áreas táctiles según D-16/D-17 | Se valida WCAG 2.2 AA en aplicación real; Figma o una inspección estática no certifican conformidad |
| **Recuperación contextual de errores** | Explicar qué ocurrió y la siguiente acción ante 409 de recotización, cupo/stock vencido, error OTP, red, descarga o pago incierto; preservar campos válidos | Reintentar de modo seguro sin duplicar pedido/cobro; no borrar el carrito ni revelar información privada |

**Regla de selección por flujo:** usar los principios pertinentes, no fabricar 18 controles por pantalla. En **catálogo** priorizar reconocimiento/Jakob/Fitts; en **checkout** Hick/Tesler/Postel/carga cognitiva; en **pago y seguimiento** estado/Doherty/recuperación; en **panel** Gestalt/consistencia/flexibilidad; en **Libro y ARCO** accesibilidad, protección de datos y claridad normativa. Ante conflictos, los contratos del backend y requisitos legales prevalecen sobre la facilidad aparente.

**Ni diseño ni prototipo acreditan rendimiento:** no imponer «máximo tres clics», un límite universal de 400 ms, «sin scroll» o métricas de conversión sin medición. Respuesta visual inmediata no equivale a aceptación de pago, cita o cambio de estado. Referencias de apoyo: https://www.nngroup.com/articles/fitts-law/ , https://www.nngroup.com/articles/ten-usability-heuristics/ , https://www.w3.org/WAI/WCAG22/quickref/ .

## 3. Diseños independientes por contexto, una sola lógica

**Confirmado:** composición de móvil y escritorio diferenciada. **Propuesta:** mismas reglas y estados, sin dos aplicaciones aisladas.

### Móvil

- Priorizar la acción comercial en zona cómoda y alcanzable, sin asumir que todos son diestros.
- Carrito inferior persistente en catálogo (según el diseño aprobado); al abrirlo, panel inferior accesible o pantalla completa según espacio.
- Checkout en una URL/página, de una columna y con secciones ordenadas; desplazamiento vertical es válido.
- La acción principal puede fijarse en la parte inferior **solo si** no oculta campos, mensajes, teclado, enlaces legales ni foco; respetar áreas seguras del dispositivo.
- Teclado virtual: mantener visible campo activo, error, botón para continuar y contexto del precio; restaurar enfoque adecuadamente.
- No usar hover como único mecanismo de interacción; no exigir gestos complejos ni deslizar para comprar.
- Calendario: selección de día y lista de horarios; alternativa a interacciones de arrastre.
- Mapa: selector de ubicación con pin, más forma alternativa sin gesto de arrastre y entrada de dirección manual cuando la cobertura pueda validarse.

### Escritorio

- Carrito lateral y checkout de columnas, con resumen accesible y no intrusivo, como patrón propuesto previamente.
- Acciones principales visibles sin competir con múltiples botones equivalentes.
- Navegación completa con teclado, orden de foco lógico, Escape para cerrar diálogo cuando corresponda y sin trampas de foco.
- No hacer obligatorio el uso del ratón para elegir fecha, cantidad, zona o variante.

### Variaciones y cobertura

Diseñar y revisar al menos ejemplos de 320, 360, 390/430, 768, 1024 y 1440 píxeles CSS de ancho; son **anchos de revisión propuestos**, no puntos de corte fijos del framework. Definir puntos de ruptura por contenido y evitar pérdida de función en zoom o rotación. Verificar teclado abierto, alto reducido, orientación horizontal y texto ampliado.

No insertar dos checkouts funcionales en el DOM y ocultar uno con CSS si causa duplicación de campos, problemas de foco o lectores de pantalla. Compartir componentes, modelos y estados y variar el layout con responsabilidad.

## 4. Accesibilidad como criterio de calidad

**Objetivo CONFIRMADO (D-16):** WCAG 2.2 **nivel AA** para interfaces públicas y administrativas de V1. Es un requisito verificable; cualquier afirmación de conformidad requiere evaluación real posterior.

- WCAG 2.2 AA establece un criterio de tamaño mínimo del objetivo de **24 × 24 píxeles CSS**, con excepciones (criterio 2.5.8). **Objetivo interno CONFIRMADO (D-17):** los controles táctiles independientes deberán tener áreas activables de **al menos 44 × 44 px CSS**; esto no obliga a que el icono visible ocupe toda el área. Los enlaces insertados dentro de texto corrido se evaluarán conforme al criterio WCAG y sus excepciones aplicables. Altura visual de botones primarios de aproximadamente 48–56 px sigue siendo PROPUESTA.
- **No confundir unidades:** Apple recomienda área de interacción de 44 × 44 **pt** en sus interfaces nativas; Android recomienda 48 × 48 **dp**. Estos valores orientan ergonomía, pero no son equivalencias automáticas a los px CSS de una web.
- Contraste: al menos **4.5:1** en texto normal y **3:1** en texto grande y componentes/estados relevantes, conforme a los criterios aplicables, con excepciones de WCAG.
- Foco visible y nunca totalmente tapado por encabezado/CTA fijo; navegación de teclado completa, sin gestos imprescindibles.
- Texto ampliable al 200 % y contenido refluible a 320 px CSS sin pérdida funcional, con excepciones normativas justificadas (por ejemplo, mapas).
- Etiquetas permanentes: un placeholder no sustituye al label. Incluir texto accesible para íconos, controles de cantidad y botones de calendario.
- Campos de error enlazados con mensajes específicos: qué pasó y cómo resolverlo. No depender únicamente de rojo/verde ni de iconografía.
- Dialog, drawer, modal de mapa/calendario: gestionar rol semántico, foco inicial, retorno de foco, cierre y scroll.
- Respetar preferencia de reducir animación, evitar parpadeo y cambios de contenido que desplacen la CTA.
- Para ubicar en mapa o elegir fecha, ofrecer método sin arrastre y operable con teclado.
- No introducir CAPTCHA con pruebas cognitivas como única vía; si hay autenticación, ofrecer métodos accesibles.
- Consentimiento de marketing independiente, nunca premarcado y sin coerción.

Referencias: https://www.w3.org/TR/WCAG22/ ; https://developer.apple.com/design/human-interface-guidelines/buttons ; https://support.google.com/accessibility/android/answer/7101858?hl=en

## 5. Formularios sin fricción

- Invitado por defecto; nunca condicionar la venta a crear una contraseña.
- Usar campos nativos y atributos de autocompletado del navegador apropiados al dato; permitir pegar y corregir.
- Tipos de teclado relevantes (teléfono, correo, números) sin limitar nombres y direcciones peruanas a patrones inválidos.
- Pedir nombre una sola vez si no hay necesidad legal de separarlo; explicar por qué se solicita teléfono/correo.
- No pedir distrito, mapa, dirección, DNI/RUC ni fecha de servicio si el carrito no lo exige.
- Datos de comprobante únicamente conforme a la modalidad/reglas aprobadas; la sencillez no puede omitir un campo obligatorio por ley.
- Mostrar obligatorios y opcionales explícitamente; validar con contexto y evitar mensajes de error antes de que el usuario pueda completar el campo.
- Conservar datos correctos después de un error; llevar al primer error, mostrar resumen accesible y permitir editar sin reiniciar.
- Evitar duplicar dirección de entrega/facturación si es la misma; ofrecer elección de reutilizarla.
- Si un pago sale a Mercado Pago, advertirlo y permitir un retorno seguro con estado real de conciliación.
- Cambios de costo/stock/horario antes de pagar: explicar la diferencia, solicitar aceptación de nuevos totales cuando proceda.

Investigación: https://baymard.com/research-articles/checkout-flow-average-form-fields ; https://web.dev/articles/payment-and-address-form-best-practices ; https://web.dev/learn/forms/autofill

## 6. Fricción específica de Perú

- Moneda PEN mostrada claramente como **S/**, con monto total, tarifa de entrega y cualquier condición antes de confirmar.
- Direcciones: departamento/provincia/distrito cuando proceda; permitir urbanización, calle, número, manzana, lote, interior y referencias según el comprador.
- UBIGEO o área servida no debe confundirse con coordenada exacta; mapa opcional y sin solicitar ubicación del dispositivo automáticamente.
- Delivery y recojo solo cuando aplican a los artículos; precio de reparto calculado por reglas del servidor.
- Reservas de servicios: fechas y horas disponibles claras, zona horaria identificada, bloqueo de cupo honesto y manejo de expiración.
- Digitales: explicar correo necesario, modo de entrega, acceso protegido y cuándo se habilita.
- Contraentrega: aclarar qué se paga, cuándo y a quién; no anunciar «pagado» al confirmar pedido.
- Verificar requisitos de transparencia, privacidad, comprobantes y Libro de Reclamaciones con [07-CUMPLIMIENTO-PERU.md](07-CUMPLIMIENTO-PERU.md); su acceso no debe ocultarse para simplificar la pantalla.

## 7. Rendimiento real y percibido

**Objetivos propuestos de Core Web Vitals** (no resultados verificados):
- LCP **≤ 2.5 s**, INP **≤ 200 ms** y CLS **≤ 0.1** en el percentil 75 de visitas reales, medidos por separado en móvil y escritorio.
- Definir presupuestos de JavaScript/CSS/imágenes *después* de elegir y medir el framework; no inventar umbrales de KB sin datos de alojamiento.
- No bloquear la compra por cargar imágenes secundarias, mapas, widgets, calendario o animaciones.
- Inicializar mapa, agenda y librerías de pago solo cuando el flujo los requiere, cuidando funcionalidad sin JavaScript donde corresponda al contenido público.
- Reservar espacio para imágenes y estados de carga para impedir saltos visuales.
- Mostrar feedback de interacción inmediato, sin fingir que el pedido se confirmó.
- Probar condiciones de red lenta/intermitente, reintento, volver atrás, recuperación de sesión y retorno desde proveedor.
- Evitar descargar assets del plugin en páginas de WordPress que no exhiben sus componentes.

Referencia oficial: https://developers.google.com/search/docs/appearance/core-web-vitals y https://web.dev/articles/vitals

## 8. Diseño honesto, confianza y privacidad

Prohibidos como diseño predeterminado: cargos ocultos, temporizadores de escasez falsos, reseñas o stock inventados, casillas publicitarias premarcadas, consentimiento forzado, banners intrusivos, ofertas añadidas al carrito sin autorización, interrupciones artificiales y bloqueos del botón Atrás.

Diferenciar claramente «pedido recibido», «pago pendiente», «pago confirmado», «reserva confirmada» y «entregado». La comunicación transparente prima sobre «hacer parecer más rápido» lo que sigue pendiente.

El cliente debe encontrar sin esfuerzo información de contacto, políticas pertinentes, atención posventa y Libro de Reclamaciones cuando corresponda.

## 9. Catálogo de criterios verificables de UX (criterios D-16/D-17 confirmados; restantes propuestos)

| ID | Caso | Comprobación requerida al validar |
| --- | --- | --- |
| UX-01 | Invitado | Compra sin crear cuenta |
| UX-02 | Móvil | Completa checkout sin desplazamiento horizontal ni CTA tapada |
| UX-03 | Escritorio | Carrito/resumen legibles y operables con teclado |
| UX-04 | Táctil — CONFIRMADO | Áreas de interacción de controles táctiles independientes ≥ 44 × 44 píxeles CSS; medir región activable, no solo icono visual |
| UX-05 | Carrito mixto | Solo aparecen campos pertinentes a tipos y métodos de pago |
| UX-06 | Campos | Autocompletado, etiquetas, obligatorio/opcional, errores recuperables |
| UX-07 | Compra rápida | «Comprar ahora» evita revisión innecesaria del carrito si es aplicable |
| UX-08 | Stock y citas | Se informa qué se venció y cómo volver a elegir, sin borrar otros datos |
| UX-09 | Pagos | Retorno externo no promete aprobación antes de verificar |
| UX-10 | Zonas/mapa | Sin cobertura o sin proveedor de mapas no muestra tarifa falsa |
| UX-11 | Accesibilidad | Teclado, foco, lector de pantalla, zoom y sin arrastre obligatorio |
| UX-12 | Responsive | Anchuras de revisión, teclado virtual y orientaciones sin pérdida de función |
| UX-13 | Rendimiento | CWV medidos en entornos definidos, no estimados por apariencia |
| UX-14 | Privacidad | No se solicita ubicación ni consentimiento publicitario innecesariamente |
| UX-15 | Confianza | Total, entrega, identidad del comercio y acceso a reclamaciones visibles |
| UX-16 | Tema WordPress | Estilos del tema/terceros no inutilizan los componentes ni viceversa |
| UX-17 | Normalización tolerante, validación estricta (Postel) | Pegar teléfono/dirección con formatos razonables conserva significado; UBIGEO, dinero, identidad, fechas e idempotencia ambiguos son rechazados sin conversión silenciosa |
| UX-18 | Feedback y progreso honesto (Doherty) | Cada acción muestra una respuesta perceptible apropiada; ante espera o timeout no se duplica operación ni se afirma éxito antes del servidor |
| UX-19 | Jerarquía, Gestalt y estética | En catálogo, carrito y panel las acciones/agrupaciones se comprenden visualmente, sin ocultar total, obligaciones o estados y sin depender solo del color |
| UX-20 | Divulgación y decisiones relevantes (Hick/Tesler) | Carrito físico puro, digital, servicio y mixto muestran solo opciones elegibles y campos necesarios sin perder acceso a información contractual |
| UX-21 | Reconocimiento y recuperación | Tras cambio de cotización, pérdida de cupo, OTP fallido o retorno MP, conserva selecciones válidas, explica causa y ofrece siguiente acción segura |
| UX-22 | Control y libertad | Se puede editar y volver antes de confirmar sin perder entradas seguras; las operaciones irreversibles no se presentan como deshacibles |
| UX-23 | Eficiencia del panel | Operador y administrador tienen búsqueda/filtros y navegación por teclado apropiadas sin ver capacidades, expedientes ni datos que su rol no autoriza |
| UX-24 | Evaluación UX contextual | Al revisar una pantalla se identifican principios pertinentes, evidencia observable, resultado/defecto y límites de revisión; ninguna maqueta se declara probada o conforme por apreciación estética |

## 10. Pendientes específicos (no resolver por inferencia)

- **CERRADO:** WCAG 2.2 AA (D-16) y 44 × 44 px CSS para controles táctiles independientes (D-17). Pendiente únicamente verificar el cumplimiento efectivo y cerrar el protocolo de evaluación.
- Prioridad visual concreta y tokens de identidad, tipografías, colores, tipología de iconos.
- Dispositivos/navegadores mínimos y condiciones de conexión de la matriz de compatibilidad.
- Qué se muestra en un carrito persistente móvil cuando el teclado aparece o el espacio vertical es escaso.
- Puntos en que se selecciona calendario y confirmación de cobertura de reparto.
- Método para medir éxito de tareas sin telemetría personal predeterminada.
- Instrumentación/entorno real de evaluación del rendimiento y revisión de accesibilidad.
- Capacidad y responsabilidad para analizar conformidad legal/accesibilidad; no declarar certificación sin verificación.

**Regla para Codex:** cuando se autorice implementación, aplicar **únicamente los principios pertinentes a cada pantalla** con criterios observables UX-01…UX-24, priorizando fluidez, comprensión, accesibilidad, recuperación y mínima complejidad sin crear componentes/animaciones/dependencias innecesarios. **WCAG 2.2 AA (D-16) y ≥ 44 × 44 px CSS (D-17)** permanecen requisitos CONFIRMADOS; el resto son guías y criterios propuestos, **no** 18 mandatos de negocio ni condiciones nuevas de P-A. Diferenciar evaluación documental, revisión estática y pruebas autorizadas en el software real; un Figma bonito no demuestra WCAG, rendimiento o conversión.
