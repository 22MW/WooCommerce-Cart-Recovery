# Prompt maestro — Plugin WooCommerce Abandoned Cart Recovery listo para WordPress.org

Actúa como un **Senior WordPress Plugin Developer** especializado en WooCommerce, arquitectura de plugins publicables en WordPress.org, compatibilidad con checkout clásico y Checkout Blocks, internacionalización, seguridad y rendimiento.

Tu tarea es diseñar y especificar un plugin profesional para **recuperación de carritos abandonados en WooCommerce**, con compatibilidad multilenguaje desacoplada de un único plugin de traducción, y cumpliendo las directrices oficiales del directorio de plugins de WordPress.org.[cite:43][cite:51][cite:53]

## Objetivo del plugin

Diseñar un plugin llamado provisionalmente **WooCommerce Cart Recovery** que permita recuperar carritos abandonados y pedidos incompletos en tiendas WooCommerce, enviando recordatorios automáticos, aplicando descuentos progresivos con cupones únicos nativos de WooCommerce, generando estadísticas y funcionando correctamente tanto en tiendas monolingües como multilingües.[cite:43][cite:51][cite:53]

## Requisitos funcionales

El plugin debe incluir como mínimo estas capacidades:

- Detectar carritos abandonados en WooCommerce.[cite:53]
- Detectar pedidos incompletos o pendientes de pago para recuperación posterior.[cite:53]
- Enviar recordatorios automáticos en 3 pasos configurables, por ejemplo 1 hora, 24 horas y 48 horas después del abandono.[cite:53]
- Permitir editar los tiempos de envío desde el panel de administración.[cite:43]
- Permitir configurar asunto y contenido del email para cada paso.[cite:51]
- Permitir descuento progresivo, por ejemplo: primer email sin descuento, segundo con descuento pequeño, tercero con descuento más agresivo para carritos de mayor valor.[cite:53]
- Generar cupones únicos o dinámicos usando exclusivamente funcionalidades nativas de WooCommerce, sin requerir otro plugin de cupones.[cite:53]
- Restringir uso del cupón al email del cliente cuando sea viable con WooCommerce nativo.[cite:53]
- Segmentar reglas por valor mínimo del carrito.[cite:53]
- Permitir excluir productos, categorías u otros criterios del uso de descuentos.[cite:53]
- Soportar usuarios registrados y usuarios invitados.[cite:53]
- Incluir tareas automáticas de limpieza de carritos antiguos, logs y cupones expirados mediante WP-Cron.[cite:43]
- Incluir estadísticas básicas: carritos abandonados, recuperados, ingresos recuperados, ratio de recuperación, emails enviados, aperturas/clicks si existe consentimiento y si la implementación es compatible con WordPress.org.[cite:43]

## Requisitos multilenguaje

La arquitectura **no debe depender únicamente de WPML**. Debe diseñarse de forma desacoplada para funcionar en estos escenarios:

- Tienda sin plugin multilenguaje.[cite:51]
- Tienda con WPML.[cite:51]
- Tienda con Polylang.[cite:51]
- Escenario genérico con locale de WordPress como fallback.[cite:51]

El sistema debe:

- Resolver el idioma o locale del carrito usando una capa de abstracción tipo `LocaleResolverInterface`.[cite:51]
- Evitar arquitectura con archivos por idioma del tipo `es.php`, `en.php`, `fr.php` como base rígida del sistema.[cite:51]
- Estar preparado para cualquier idioma soportado por WordPress usando locale, por ejemplo `es_ES`, `en_GB`, `fr_FR`.[cite:51]
- Ser completamente internacionalizable con `text domain` correcto, carpeta `languages/` y funciones nativas de i18n de WordPress.[cite:51]
- Poder renderizar emails según locale detectado del carrito, del usuario o del sitio.[cite:51]

## Compatibilidad checkout

La solución debe contemplar explícitamente:

- WooCommerce checkout clásico.[cite:53]
- WooCommerce Checkout Blocks.[cite:47][cite:53]

La arquitectura debe proponer una capa de captura del email y del idioma compatible con ambos modelos, evitando acoplarse solo a `#billing_email` del checkout clásico.[cite:47][cite:53]

## Requisitos WordPress.org

La solución debe estar diseñada para ser **100% compatible con las directrices oficiales del directorio de plugins de WordPress.org**.[cite:43]

Debe cumplir todo esto:

- Licencia GPL v2 o posterior, o compatible GPL.[cite:43]
- Cabeceras válidas en el archivo principal del plugin.[cite:43]
- `readme.txt` en formato oficial del repositorio WordPress.org.[cite:43]
- Estructura correcta para subida al repositorio.[cite:43]
- Sin código ofuscado ni minificado de forma no revisable.[cite:43]
- Sin carga remota de código ejecutable.[cite:43]
- Sin tracking sin consentimiento explícito del usuario.[cite:43]
- Sin publicidad invasiva, avisos agresivos ni secuestro del admin.[cite:43]
- Con seguridad WordPress estándar: sanitización, escaping, validación, nonces, capability checks y bloqueo de acceso directo.[cite:43]
- Seguir WordPress Coding Standards en la medida de lo posible.[cite:43]
- Preparado para internacionalización con `text domain` correcto.[cite:51]
- Declarar versión, requisitos mínimos de WordPress y versión mínima de PHP.[cite:43]
- Incrementar versión en cada cambio publicado.[cite:43]

## Requisitos técnicos de la respuesta

La respuesta debe entregar una **arquitectura definitiva completa**, no solo ideas generales.

Debe incluir obligatoriamente:

1. Nombre provisional del plugin y slug recomendado.
2. Cabecera completa del archivo principal del plugin.
3. Estructura definitiva de carpetas y archivos.
4. Lista de clases principales con responsabilidad única por clase.
5. Hooks, filtros y cron events propuestos.
6. Estrategia para compatibilidad con checkout clásico y Checkout Blocks.[cite:47][cite:53]
7. Estrategia de compatibilidad multilenguaje desacoplada de WPML.[cite:51]
8. Diseño de tablas personalizadas si realmente hacen falta, justificando por qué no basta con options o post meta.[cite:43]
9. Estrategia de cupones únicos con WooCommerce nativo.[cite:53]
10. Estrategia de seguridad completa.
11. Estrategia de limpieza de datos y uninstall.
12. Estrategia de estadísticas compatible con WordPress.org.[cite:43]
13. Recomendación sobre qué datos guardar y cuáles no guardar para no incumplir privacidad o guidelines.[cite:43]
14. Ejemplo de `readme.txt` inicial para WordPress.org.[cite:43]
15. Requisitos mínimos recomendados de WordPress, WooCommerce y PHP.
16. Plan de versionado semántico o similar.
17. Fases recomendadas de desarrollo: MVP, v1.1, v1.2, etc.
18. Riesgos técnicos principales y cómo mitigarlos.

## Criterios de calidad

La solución debe priorizar:

- Arquitectura limpia, mantenible y extensible.
- Desacoplamiento entre capas de captura, persistencia, recuperación, administración, emails e idioma.
- Máxima compatibilidad con WordPress.org y WooCommerce.[cite:43][cite:53]
- Diseño orientado a producto real, no a prototipo rápido.
- Evitar vendor lock-in con WPML o cualquier plugin concreto.[cite:51]
- Evitar dependencias innecesarias.
- Preparación real para publicación en repositorio o venta premium derivada.[cite:43]

## Formato de salida esperado

Responde en Markdown claro y profesional con estos apartados:

- Resumen ejecutivo
- Alcance funcional
- Requisitos WordPress.org
- Arquitectura general
- Compatibilidad multilenguaje
- Compatibilidad checkout
- Modelo de datos
- Emails y cupones
- Seguridad y privacidad
- Estadísticas
- Estructura de archivos
- Hooks y cron
- Cabecera principal del plugin
- Ejemplo de readme.txt
- Roadmap de versiones
- Riesgos y mitigaciones
- Recomendación final

La respuesta debe ser práctica, específica y lista para servir como base de desarrollo real.
