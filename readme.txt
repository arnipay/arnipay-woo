=== arnipay for Woo ===
Colaboradores: arnipay.com.py
Etiquetas: woocommerce, pasarela de pago, arnipay, Paraguay, pagos
Requiere al menos: 6.0
Probado hasta: 6.9
Requiere PHP: 8.2
Requiere WooCommerce: 9.0
Probado con WooCommerce: 10.4
Versión estable: 1.0.35
Licencia: GPLv2 or later
URI de licencia: https://www.gnu.org/licenses/gpl-2.0.html

Pasarela de pago arnipay para WooCommerce. Acepta pagos seguros en Guaraníes (PYG).

== Descripción ==

Este plugin integra la pasarela de pago arnipay con WooCommerce, permitiéndote aceptar pagos seguros en Guaraníes paraguayos (PYG).

**Características principales:**

* Procesamiento seguro de pagos a través de arnipay
* Generación de enlaces de pago
* Soporte de webhooks para actualización automática del estado de las órdenes
* Verificación de firma digital para mayor seguridad
* Configuración sencilla a través de los ajustes de WooCommerce
* Operación en producción con verificación de firma digital
* Compatible con WordPress Multisite
* Integración con tablas de órdenes personalizadas de WooCommerce (HPOS)

**Requisitos:**

* WooCommerce 9.0 o superior
* PHP 8.2 o superior
* Moneda configurada en Guaraníes (PYG)
* Certificado SSL (recomendado para producción)
* Cuenta activa en arnipay

== Instalación ==

1. Sube los archivos del plugin al directorio `/wp-content/plugins/arnipay-woo`
2. Activa el plugin a través del menú 'Plugins' en WordPress
3. Ve a WooCommerce > Ajustes > Pagos
4. Habilita arnipay y configura tus credenciales API
5. Guarda los cambios y prueba la integración

== Configuración ==

**Configuración inicial:**

1. Navega a WooCommerce > Ajustes > Pagos > arnipay
2. Habilita el método de pago
3. Ingresa tu Client ID y Client Secret proporcionados por arnipay
4. Configura la URL del webhook para actualizaciones automáticas de órdenes
5. Personaliza el texto del botón de pago y la descripción
6. Guarda los cambios

**Requisitos de moneda:**

* **IMPORTANTE:** El plugin solo funciona con la moneda Guaraní (PYG)
* Ve a WooCommerce > Ajustes > General
* Configura la moneda a "Guaraní paraguayo (₲)"
* Si intentas usar otra moneda, el plugin se desactivará automáticamente

**Webhooks:**

Los webhooks permiten que tu tienda reciba actualizaciones automáticas del estado de los pagos desde arnipay. La URL del webhook se genera automáticamente y debe ser configurada en tu panel de arnipay.

== Preguntas frecuentes ==

= ¿Necesito una cuenta de arnipay? =

Sí, necesitas registrarte en arnipay y obtener credenciales API (Client ID y Client Secret).

= ¿Por qué solo funciona con Guaraníes (PYG)? =

arnipay es una pasarela de pago específica para Paraguay y solo procesa transacciones en Guaraníes.

= ¿Es necesario SSL? =

SSL es altamente recomendado para entornos de producción para garantizar transacciones seguras y proteger la información de tus clientes.

= ¿Cómo funcionan los webhooks? =

Los webhooks actualizan automáticamente el estado de la orden cuando los pagos son confirmados por arnipay. Esto permite que tu tienda procese órdenes sin intervención manual.

= ¿El plugin es compatible con WordPress Multisite? =

Sí, el plugin es compatible con instalaciones multisite de WordPress.

= ¿Es compatible con las tablas de órdenes personalizadas de WooCommerce (HPOS)? =

Sí, el plugin declara compatibilidad con HPOS (High-Performance Order Storage) de WooCommerce.

= ¿Puedo probar el plugin antes de usarlo en producción? =

Actualmente el plugin opera en producción porque el sandbox de arnipay no está disponible en esta versión. Para pruebas, usa credenciales/órdenes de prueba coordinadas con arnipay.

= ¿Qué hago si el plugin no aparece como opción de pago? =

Verifica que:
1. WooCommerce esté instalado y activado
2. La moneda esté configurada en PYG
3. El plugin arnipay esté activado
4. Hayas completado la configuración con tus credenciales API

== Capturas de pantalla ==

1. Configuración de la pasarela de pago arnipay
2. Opciones del método de pago en el checkout
3. Página de pago de arnipay

== Registro de cambios ==

= 1.0.0 - 2025-10-10 =
* Versión inicial en desarrollo
* Integración básica con pasarela arnipay
* Generación de enlaces de pago
* Soporte de webhooks
* Verificación de firma digital
* Operación en producción con verificación de firma digital
* Compatibilidad con HPOS de WooCommerce
* Compatibilidad con WordPress Multisite

== Notas de actualización ==

= 1.0.35 =
* Añadido: iconos del plugin para la pantalla de actualizaciones de WordPress y detalles del plugin.
* Añadido: `assets/icon.svg`, `assets/icon-128x128.png` y `assets/icon-256x256.png` generados desde el isotipo oficial de arnipay.
* Mejorado: metadata explícita de iconos para Plugin Update Checker.
* Sin cambios funcionales en pagos, webhooks, checkout ni seguridad.

= 1.0.34 =
* URL del repositorio de actualizaciones configurada: https://github.com/arnipay/arnipay-woo
* Añadidos los archivos de proyecto para GitHub (README.md, LICENSE GPL v2, .gitignore, .gitattributes)
* Sin cambios funcionales en el plugin
= 1.0.33 =
* Añadido: sistema de actualizaciones automáticas desde GitHub Releases mediante Plugin Update Checker (PUC v5.5, MIT)
* El plugin ahora aparece en el panel "Actualizaciones" de WordPress cuando hay una versión nueva publicada en el repo
* La URL del repo está marcada con "CHANGE-ME" — debe actualizarse antes de la primera release pública
* Sin cambios funcionales en el flujo de pagos ni en la seguridad
= 1.0.32 =
* Añadido: 13 tests unitarios nuevos para format_order_note_lines (escape HTML, trim, filtro de vacíos, casos límite, preservación de acentos)
* Mejorado: stub esc_html en el bootstrap de tests para soportar las nuevas pruebas
* Sin cambios funcionales en el código de producción
= 1.0.31 =
* Mejorado: las notas de `payment.failed`, `payment.cancelled` y `payment.pending` ahora usan el mismo formato multilínea que `payment.completed`.

= 1.0.30 =
* Corregido: las notas internas de pago confirmado vuelven a mostrarse correctamente en WooCommerce usando saltos de línea seguros con `<br>`.

= 1.0.29 =
* Mejorado: las notas de pedido para pagos confirmados ahora se guardan en formato multilínea para facilitar lectura en WooCommerce.

= 1.0.28 =
* Refactor: extracción del bloque de UI del checkout a Arnipay_Woo_AW_Checkout_View (íconos, selección de método, catálogo de medios soportados)
* Refactor: extracción del bloque de UI admin a Arnipay_Woo_AW_Admin_Renderer (cabecera con estado, separadores, panel de verificación)
* Refactor: extracción de la verificación de credenciales y webhook a Arnipay_Woo_AW_Verification_Service
* La clase gateway pasó de 1232 a 664 líneas (-46% adicional, -63% acumulado desde 1812)
* Añadido: 9 tests unitarios nuevos para el sanitizado de texto y la normalización de métodos
* Sin cambios funcionales: toda la API pública (get_icon, payment_fields, validate_fields, ajax_verify_*, generate_arnipay_*_html) sigue idéntica para WooCommerce y los hooks
= 1.0.27 =
* Refactor: la lógica de resolución de pedidos y reconciliación de webhooks se extrajo a Arnipay_Woo_AW_Order_Resolver (clase de dominio pura, sin estado del gateway)
* Refactor: el manejo del webhook IPN se extrajo a Arnipay_Woo_AW_Webhook_Handler
* La clase gateway pasó de 1812 a 1232 líneas (-32%), con responsabilidades claras
* Añadido: suite de tests PHPUnit con 39 casos y 61 aserciones cubriendo identificadores, montos, idempotencia, firmas HMAC, anti-replay y reconciliación
* Corregido: la lista de webhooks procesados podía iniciar con una entrada vacía espuria por el cast (array)'' que devolvía WooCommerce — encontrado por los nuevos tests
* Sin cambios funcionales para el usuario final: todo el flujo de pago, seguridad y UI se preserva idéntico
= 1.0.26 =
* Corregido: el multiselect de Métodos habilitados podía perder la selección al guardar cuando Select2 no estaba inicializado a tiempo (regresión introducida en 1.0.21)
* Mejorado: el script de admin ahora declara dependencia explícita de "wc-enhanced-select" para garantizar que Select2 esté listo
* Documentado: parches aplicados al SDK incluido (timeouts y validación de respuesta) en lib/PATCHES.md, con marca visible en el propio archivo para que sobrevivan a futuras actualizaciones
= 1.0.25 =
* Seguridad/mantenimiento: se removió código muerto del panel visual avanzado que ya no estaba activo desde la simplificación del checkout.
* Seguridad: escape explícito de la URL de configuración de moneda en el aviso administrativo.
* Verificación: se mantienen sin cambios el flujo de pago, webhooks, HMAC, anti-replay, validación de monto y reembolsos.

= 1.0.24 =
* Mejorado: el selector nativo de WooCommerce ahora muestra solo los iconos de arnipay y métodos disponibles, evitando duplicar el texto "arnipay" junto al logo.
* Mejorado: el texto del método sigue presente en HTML para accesibilidad y compatibilidad, pero queda oculto visualmente en el checkout.
* Mejorado: título y subtítulo del bloque de selección de método tienen tamaños más compactos en móviles.

= 1.0.23 =
* Corregido: los iconos de QR y Personal Pay ahora se cargan con URL versionada para evitar caché vieja o iconos cruzados.
* Mejorado: selector de métodos de pago más compacto para escritorio.
* Mejorado: experiencia mobile-first menos invasiva, con cards horizontales, textos más pequeños y mejor espaciado.
* Mantenido: admin simple y flujo de pagos/webhooks sin cambios.

= 1.0.22 =
* Cambiado: se eliminó el panel visual avanzado de layouts y se volvió a una administración simple tipo 1.0.15.
* Cambiado: se dejó un solo diseño de checkout para arnipay, basado en selección visual de método de pago.
* Añadido: el cliente debe seleccionar Personal Pay, QR o tigo money dentro del bloque de arnipay antes de pagar.
* Corregido: el método elegido por el cliente se envía a arnipay para restringir el checkout al método seleccionado.
* Corregido: orden y mapeo de iconos para evitar cruces entre QR y Personal Pay.

= 1.0.21 =
* Corregido: los SVG de QR y Personal Pay estaban cruzados en el paquete; ahora cada método usa su icono correcto.
* Corregido: la vista previa del panel admin ahora cambia visualmente al seleccionar layouts.
* Mejorado: estilos del panel de apariencia para que se vea ordenado dentro de WooCommerce settings y no se rompa en staging.
* Mejorado: preview de escritorio/móvil más estable, con clases reactivas por layout.

= 1.0.20 =
* Corregido: flujo de carrera más robusto para webhooks rápidos. La referencia se guarda antes de crear el link para que el webhook pueda resolver la orden, pero luego solo se guardan metadatos frescos para no sobrescribir un pago completado.
* Corregido: si la creación del link falla después de mover una orden a pendiente, se restaura el estado anterior cuando corresponde.
* Mejorado: resolución por referencia con fallback seguro para el nuevo formato `PREFIJO-ID-NÚMERO`, validado contra la referencia esperada de la orden.
* Mejorado: comparación tolerante a mayúsculas/minúsculas para link_id/payment_id y almacenamiento de alias normalizados para futuras búsquedas.

= 1.0.19 =
* Corregido: condición de carrera entre la creación del pago y la llegada inmediata del webhook (afectaba métodos rápidos como QR)
* Corregido: la versión mostrada en el panel admin ya no estaba codificada de forma fija
= 1.0.18 =
* Seguridad: se limita el tamaño máximo aceptado en el endpoint público de webhooks para reducir riesgo de abuso/DoS por payloads grandes.
* Seguridad: el webhook ahora rechaza eventos cuyos identificadores firmados contradigan los datos ya guardados de la orden.
* Seguridad: textos personalizables del checkout ahora se sanitizan y limitan por longitud para evitar contenido excesivo o inesperado.
* Corregido: carga de assets del checkout más defensiva para evitar errores si WooCommerce todavía no inicializó los gateways.
* Mejorado: enlaces del plugin en el panel se generan con escape correcto.

= 1.0.17 =
* Añadido: panel visual profesional de Apariencia dentro de la configuración de arnipay.
* Añadido: vista previa de checkout en computadoras y móviles usando los iconos reales del plugin.
* Añadido: vista previa del botón final en estado normal, hover y proceso de redirección.
* Añadido: personalización avanzada de título, descripción corta, descripción detallada, texto de seguridad y texto del botón final.
* Mejorado: el botón final de WooCommerce puede mostrarse con estilo visual de arnipay cuando el método está seleccionado.
* Mantenido: no se modificó el procesamiento de pagos, redirección, webhook ni lógica de confirmación.

= 1.0.16 =
* Añadido: 4 layouts visuales de checkout para que el comercio elija cómo mostrar arnipay en escritorio y móviles.
* Añadido: selector independiente de layout para computadoras y para celulares.
* Mejorado: el bloque del checkout ahora explica claramente que arnipay es la pasarela y que QR, Personal Pay y tigo money se eligen dentro del entorno seguro de arnipay.
* Ajustado: el nombre visible por defecto del método ahora es `arnipay`, mientras que el botón final sigue usando “Pagar con arnipay”.

= 1.0.15 =
* Cambiado: el cliente ahora es redirigido directamente al checkout seguro de arnipay.
* Eliminado: flujo de popup/ventana emergente y polling en la página de gracias.
* Mejorado: el botón final de WooCommerce ahora puede mostrar “Pagar con arnipay”.
* Mejorado: descripción por defecto del checkout indicando que el pago se completa en el ambiente seguro de arnipay.

= 1.0.14 =
* Corregido: una notificación tardía de pago fallido/cancelado/pendiente ya no puede degradar una orden que ya fue pagada.
* Corregido: si arnipay no informa `payment_id`, WooCommerce ya no guarda la referencia/link como ID de transacción.
* Mejorado: la deduplicación de webhooks ahora usa identificadores firmados del payload para evitar reprocesar reintentos con nueva firma.
* Mejorado: la verificación del webhook ahora realiza un POST firmado real contra la URL pública del sitio y avisa si hay cambios sin guardar.
* Mejorado: las nuevas referencias incluyen el ID interno del pedido para evitar colisiones incluso con numeraciones personalizadas.
* Seguridad: el plugin bloquea reembolsos automáticos parciales porque el endpoint de reversa del SDK no recibe monto parcial.

= 1.0.13 =
* Mejora visual completa de la pantalla de configuración en WooCommerce.
* Nuevo encabezado profesional con estado de configuración, pasos y URL de webhook visible.
* Secciones ordenadas para checkout, credenciales, medios de pago, webhook y diagnóstico.
* Botón para copiar URL de webhook desde el panel.
* Panel de verificación rediseñado para credenciales y webhook.
* Textos visibles ajustados para respetar el nombre de marca en minúscula: arnipay.

= 1.0.12 =
* Mejorado: las referencias de pago dejan de usar el prefijo genérico `ORDER-*` y ahora usan un prefijo alfanumérico de 5 caracteres, único por comercio, derivado con HMAC de las credenciales y la URL del sitio sin exponer secretos.
* Mejorado: textos visibles de arnipay en minúscula y notas de pedido más limpias.
* Mejorado: los eventos `payment.cancelled` y `payment.canceled` ahora cambian la orden a cancelada y limpian la URL de pago.
* Mejorado: las notas del pedido ya no muestran `ID de pago: no informado`; solo se agrega el ID de pago cuando arnipay lo envía.
* Branding: textos visibles actualizados para respetar el nombre `arnipay` en minúscula.

= 1.0.11 =
* Corregido: la creación del pago ahora guarda el ID real del link devuelto por arnipay (`_arnipay_link_id`) además de la URL.
* Corregido: el webhook ya no depende únicamente de `reference`; ahora puede resolver órdenes por `reference`, `link_id` o `payment_id`.
* Seguridad: el webhook valida que `X-Client-ID` coincida con el comercio configurado y usa una ventana anti-replay de 15 minutos alineada con la API.
* Seguridad: `payment.completed` exige `amount`; si falta o no coincide con el monto esperado, la orden queda en revisión manual.
* Seguridad: la validación de monto usa `_arnipay_expected_amount`, guardado al crear el link, en vez del total editable de la orden.
* Corregido: los reembolsos usan `_arnipay_payment_id`/transaction ID real y evitan enviar referencias tipo `ORDER-*` o `link_id` al endpoint de reversa.
* Mejorado: el SDK incluido ahora usa timeouts cURL y valida respuestas JSON inválidas/vacías.
* Mejorado: el plugin evita errores fatales si WooCommerce no está activo al cargar requisitos.
= 1.0.10 =
* Seguridad: la URL de pago solo se muestra al cliente dueño del pedido
* Seguridad: el endpoint de estado del pedido ya no permite enumerar pedidos
* Seguridad: límite de frecuencia en la consulta de estado para prevenir abuso
* Seguridad: la URL de pago se elimina del pedido una vez completado
= 1.0.9 =
* Histórico: se probó flujo en ventana emergente, retirado desde 1.0.15 a favor de redirección directa.
* La página de pago detecta automáticamente cuando el pago se completa y avanza sola
* Histórico: el flujo actual recomendado es redirección directa a arnipay.
* El cliente nunca pierde de vista su pedido durante el pago
= 1.0.8 =
* Seguridad: protección contra ataques de repetición (replay) en el webhook mediante validación de antigüedad del timestamp firmado
* Seguridad: la idempotencia ahora se basa en datos firmados, no en un encabezado controlable por el emisor
* Seguridad: el endpoint del webhook solo acepta peticiones POST
* Seguridad: los mensajes de error del webhook son genéricos para evitar la enumeración de pedidos
* Seguridad: validación estricta del formato de la referencia de pago
* Seguridad: verificación de que el pedido resuelto pertenece realmente a esta pasarela
* Seguridad: el registro de depuración ya no vuelca estructuras de datos completas
* Añadido: caché de los medios de pago para no saturar la API de arnipay
* Añadido: validación del monto del pedido antes de crear el pago
= 1.0.7 =
* Corregido: las imágenes de los medios de pago en el checkout se mostraban deformadas, cortadas o demasiado grandes
* Cada medio de pago ahora usa un icono SVG independiente que conserva su proporción y se ve nítido en cualquier pantalla
* Reemplazado el sistema de sprite por iconos individuales, más fácil de mantener
= 1.0.6 =
* Quitada la opción de entorno: el plugin opera siempre en Producción (el sandbox de arnipay aún no está disponible)
* Añadido: botón para verificar las claves de acceso (ID del Cliente y Clave secreta) contra arnipay
* Añadido: botón para verificar la configuración del webhook antes de recibir notificaciones reales
* Añadido: la URL de notificación se muestra en un campo de solo lectura, fácil de copiar
* Reorganizada la pantalla de ajustes en secciones: Claves de acceso y Configuración de Webhooks
= 1.0.5 =
* Corregido: el entorno por defecto ahora es Producción (antes Pruebas), evitando cobros en sandbox por error
* Corregido: conversión robusta del valor de entorno (compatible con instalaciones existentes)
* Corregido: verificación SSL forzada también en el entorno de pruebas
* Corregido: uso de wc_get_order() para compatibilidad real con HPOS
* Añadido: verificación del monto del webhook contra el total de la orden
* Añadido: manejo del evento payment.pending
* Añadido: idempotencia de webhooks mediante el header X-Webhook-ID
* Añadido: soporte de reembolsos desde WooCommerce
* Seguridad: escape de salida en los avisos del administrador
* Mejorado: registro de depuración sin volcar el payload completo del webhook
= 1.0.3 =
* Actualizado formato de texto en las notas de la orden
= 1.0.2 =
* Actualizado formato de texto en las notas de la orden
= 1.0.1 =
* Arreglo evitar notificaciones de webhook duplicadas
= 1.0.0 =
Versión inicial en desarrollo del plugin de pasarela de pago arnipay para WooCommerce.

== Desarrollador ==

**SDK de arnipay:**

El plugin utiliza el SDK oficial de arnipay (geekwalletsrl/arnipay-sdk) que proporciona:

* Cliente HTTP para comunicación con la API
* Generación y gestión de enlaces de pago
* Servicio de verificación de firmas digitales
* Procesamiento de webhooks

**Estructura del plugin:**

* `/includes/` - Clases principales del plugin
* `/lib/` - Dependencias y SDK de arnipay
* `/assets/` - Recursos estáticos (CSS, JS, imágenes)

**Filtros y hooks disponibles:**

El plugin proporciona varios filtros y acciones para extender su funcionalidad. Consulta el código fuente para más detalles.

== Soporte ==

Para soporte técnico o reportar problemas:

* Contacta con el autor: https://arnipay.com.py
* Sitio web: https://arnipay.com.py

== Privacidad ==

Este plugin:

* Se conecta a la API de arnipay para procesar pagos
* Envía información de la orden (monto, referencia) a arnipay
* Recibe notificaciones de pago mediante webhooks
* No almacena información de tarjetas de crédito en tu servidor
* Toda la información sensible es manejada por arnipay

== Créditos ==

Fase beta: Saúl Morales Pacheco
para: arnipay.com.py
SDK de arnipay por GeekWallet SRL
Mantenimiento y mejoras: GeekWallet SRL
