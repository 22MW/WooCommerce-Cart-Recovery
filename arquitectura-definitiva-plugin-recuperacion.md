# Arquitectura definitiva — Plugin WooCommerce Cart Recovery orientado a WordPress.org

## Resumen ejecutivo

La arquitectura recomendada es la de un plugin llamado provisionalmente **WooCommerce Cart Recovery**, desacoplado de WPML, compatible con WooCommerce checkout clásico y Checkout Blocks, preparado para WordPress.org y diseñado con capas separadas para captura, idioma, persistencia, envío de emails, generación de cupones, recuperación, limpieza y estadísticas.[cite:43][cite:47][cite:51][cite:53]

La clave del diseño es que el plugin no debe pensarse como “plugin para WPML”, sino como un plugin de recuperación de carritos **locale-aware**, con un resolvedor de idioma propio que pueda usar WPML, Polylang o el locale nativo de WordPress como fallback.[cite:51]

## Alcance funcional

El plugin debe cubrir estas funciones en su versión base:[cite:53]

- Captura de carritos abandonados.
- Captura de pedidos pendientes o fallidos recuperables.
- Programación de 3 recordatorios configurables.
- Emails editables por paso y por locale.
- Descuento progresivo opcional por paso.
- Cupones únicos nativos de WooCommerce.
- Restricción por valor mínimo del carrito.
- Exclusión de productos y categorías del descuento.
- Soporte para guest checkout y usuarios registrados.
- Limpieza automática por cron.
- Estadísticas básicas de recuperación.

## Requisitos WordPress.org

El plugin debe cumplir las directrices del directorio oficial, incluyendo licencia GPL compatible, ausencia de código ofuscado, ausencia de carga remota ejecutable, seguridad correcta, estructura revisable y readme en formato oficial.[cite:43]

También debe evitar tracking sin consentimiento explícito y no debe incluir publicidad invasiva, avisos secuestradores del escritorio ni prácticas que perjudiquen la experiencia de administración de WordPress.[cite:43]

## Nombre, slug y metadatos

Nombre recomendado:

- **WooCommerce Cart Recovery**

Slug recomendado:

- `woocommerce-cart-recovery`

Text domain recomendado:

- `woocommerce-cart-recovery`.[cite:51]

Domain path:

- `/languages`.[cite:51]

Licencia recomendada:

- `GPL-2.0-or-later`.[cite:43]

## Arquitectura general

La arquitectura debe dividirse en módulos con responsabilidad única.[cite:43]

### Núcleo

- `Plugin`: bootstrap, carga de dependencias, registro de hooks, arranque del plugin.
- `Requirements`: validación de versiones mínimas de PHP, WordPress, WooCommerce y soporte de HPOS si procede.
- `Container` o registrador simple de servicios, si quieres mantener la arquitectura ordenada sin complicarla demasiado.

### Dominio

- `CartCaptureService`: coordina la captura de email, carrito, idioma y sesión.
- `AbandonedCartDetector`: detecta cuándo una sesión pasa a estado abandonado.
- `PendingOrderDetector`: identifica pedidos `pending` o `failed` recuperables.
- `RecoveryService`: genera links firmados y restaura el carrito.
- `CouponService`: crea cupones únicos con WooCommerce nativo.[cite:53]
- `EmailScheduler`: determina qué envíos toca lanzar según cron y reglas.
- `EmailRenderer`: compone el contenido del email según locale y paso.
- `CleanupService`: purga registros antiguos, logs y cupones expirados.
- `StatsService`: consolida datos para informes básicos.

### Idioma

- `LocaleResolverInterface`: contrato de resolución del locale.
- `WpmlLocaleResolver`: adaptador para WPML cuando esté activo.
- `PolylangLocaleResolver`: adaptador para Polylang cuando esté activo.
- `DefaultLocaleResolver`: fallback a locale nativo de WordPress con `determine_locale()` o equivalente.[cite:51]
- `LocaleResolverManager`: elige qué resolver usar según el entorno.

### Checkout

- `ClassicCheckoutCaptureAdapter`: captura para checkout clásico.
- `BlocksCheckoutCaptureAdapter`: captura para Checkout Blocks / Store API / extensibilidad oficial de WooCommerce Blocks.[cite:47][cite:53]
- `CheckoutCaptureCoordinator`: fachada que selecciona el adaptador correcto.

### Persistencia

- `CartRepository`: acceso a carritos abandonados.
- `EmailLogRepository`: acceso a logs de envío.
- `SettingsRepository`: lectura de configuración del plugin.
- `CouponRepository` opcional, solo si separas la trazabilidad de cupones de WooCommerce.

### Administración

- `AdminMenu`: menú y pantallas.
- `SettingsPage`: ajustes.
- `AbandonedCartsPage`: listado de carritos.
- `StatsPage`: métricas.
- `PrivacyPage` opcional si incluyes ayudas de privacidad y export/erase.

## Compatibilidad multilenguaje

La compatibilidad multilenguaje debe ser **por locale**, no por plugin específico.[cite:51]

### Regla principal

Cada carrito guardado debe almacenar un campo `locale` o `site_locale`, por ejemplo `es_ES`, `en_GB` o `fr_FR`, resuelto en el momento de captura o en el primer punto fiable del checkout.[cite:51]

### Resolución recomendada

Orden de resolución:

1. Locale explícito ya guardado en el carrito.
2. Locale detectado por integrador multilenguaje activo.
3. Locale de usuario si está autenticado y existe contexto válido.
4. Locale por defecto del sitio.[cite:51]

### Lo que no conviene hacer

No conviene basar la arquitectura en archivos de plantilla separados por idioma como `templates/emails/es.php`, `templates/emails/en.php` como núcleo del sistema, porque eso escala mal, complica mantenimiento y no es una internacionalización real.[cite:51]

### Lo que sí conviene hacer

- Plantillas de email base únicas.
- Variables dinámicas del email separadas del idioma.
- Textos gestionados por locale en opciones o tabla dedicada.
- Todas las cadenas internas del plugin envueltas con el `text domain` correcto.[cite:51]

## Compatibilidad checkout

La compatibilidad debe cubrir dos caminos distintos porque checkout clásico y Checkout Blocks no comparten la misma superficie de integración.[cite:47][cite:53]

### Checkout clásico

Usar una mezcla de hooks WooCommerce y JS ligero para detectar y guardar:

- email del checkout,
- contenido del carrito,
- session key,
- locale,
- timestamp de actividad.

### Checkout Blocks

Debe usarse la extensibilidad oficial de WooCommerce Blocks y sus puntos de integración actuales, evitando confiar únicamente en selectores del DOM del checkout clásico.[cite:47][cite:53]

### Enfoque recomendado

Crear un coordinador que detecte si el entorno usa classic o blocks y cargue el adaptador correcto. Esa separación reduce roturas futuras cuando WooCommerce cambie internals del Checkout Block.[cite:47][cite:53]

## Modelo de datos

Para este caso sí es razonable usar tablas personalizadas porque hay un volumen potencialmente alto de eventos, cron jobs, estados y estadísticas, y no conviene cargar todo eso en `options` o abusar de `postmeta`.[cite:43]

### Tabla 1: abandoned carts

Nombre sugerido:

- `{$wpdb->prefix}wccr_abandoned_carts`

Campos sugeridos:

| Campo | Tipo | Uso |
|---|---|---|
| id | BIGINT UNSIGNED PK | Identificador |
| session_key | VARCHAR(191) | Relación con sesión/cart context |
| user_id | BIGINT UNSIGNED NULL | Usuario si existe |
| email | VARCHAR(190) NULL | Email capturado |
| locale | VARCHAR(20) | Locale del carrito [cite:51] |
| cart_hash | VARCHAR(64) | Hash de estado del carrito |
| cart_payload | LONGTEXT | Snapshot serializado/JSON del carrito |
| cart_total | DECIMAL(18,4) | Total del carrito |
| currency | VARCHAR(10) | Moneda |
| status | VARCHAR(20) | active, abandoned, recovering, recovered, expired |
| source | VARCHAR(20) | classic, blocks, order_pending |
| last_activity_gmt | DATETIME | Última actividad |
| abandoned_at_gmt | DATETIME NULL | Momento de abandono |
| recovered_at_gmt | DATETIME NULL | Momento de recuperación |
| created_at_gmt | DATETIME | Creación |
| updated_at_gmt | DATETIME | Actualización |

### Tabla 2: email queue / logs

Nombre sugerido:

- `{$wpdb->prefix}wccr_email_log`

Campos sugeridos:

| Campo | Tipo | Uso |
|---|---|---|
| id | BIGINT UNSIGNED PK | Identificador |
| cart_id | BIGINT UNSIGNED | Relación con carrito |
| step | TINYINT UNSIGNED | Paso 1, 2 o 3 |
| locale | VARCHAR(20) | Locale del envío [cite:51] |
| subject_snapshot | TEXT | Asunto usado |
| template_version | VARCHAR(30) | Versión de plantilla |
| coupon_code | VARCHAR(100) NULL | Cupón generado |
| status | VARCHAR(20) | queued, sent, failed, skipped |
| sent_at_gmt | DATETIME NULL | Fecha envío |
| error_message | TEXT NULL | Error si falla |
| created_at_gmt | DATETIME | Fecha creación |

### Tabla 3: stats daily opcional

Solo si quieres escalar bien el dashboard:

- `{$wpdb->prefix}wccr_daily_stats`

Si no, puedes calcular estadísticas en vivo desde las tablas anteriores para una primera versión.[cite:43]

## Emails y cupones

### Estrategia de emails

Cada uno de los 3 pasos debe tener configuración propia:

- activo/inactivo,
- delay en minutos,
- asunto por locale,
- cuerpo por locale,
- regla de descuento,
- umbral mínimo de carrito.[cite:51][cite:53]

### Cómo guardar plantillas

En vez de archivos por idioma, conviene guardar una estructura por locale, por ejemplo:

```php
[
  'step_1' => [
    'es_ES' => [ 'subject' => '...', 'body' => '...' ],
    'en_GB' => [ 'subject' => '...', 'body' => '...' ],
  ],
  'step_2' => [
    'es_ES' => [ 'subject' => '...', 'body' => '...' ],
  ],
]
```

Para un MVP puede vivir en una opción serializada; si crece mucho, se puede migrar a tabla propia.[cite:43][cite:51]

### Cupones únicos

El plugin debe generar cupones usando funciones nativas y tipos nativos de WooCommerce, sin requerir plugins adicionales.[cite:53]

Reglas recomendadas:

- cupón de un solo uso,
- expiración configurable,
- opcionalmente restringido al email del carrito,
- cantidad fija o porcentaje,
- exclusión de productos/categorías si aplica.[cite:53]

### Recomendación práctica

Generar el cupón solo cuando realmente se vaya a enviar el email correspondiente, no al detectar el abandono. Eso evita acumular cupones inútiles.[cite:53]

## Seguridad y privacidad

La arquitectura debe cumplir buenas prácticas estándar de WordPress y las directrices del repositorio.[cite:43]

### Seguridad mínima obligatoria

- `defined( 'ABSPATH' ) || exit;` en archivos PHP de entrada.[cite:43]
- Sanitización con `sanitize_text_field()`, `sanitize_email()`, `absint()`, etc.[cite:43]
- Escape de salida con `esc_html()`, `esc_attr()`, `wp_kses_post()` y similares.[cite:43]
- Verificación de nonces en acciones del admin y AJAX.[cite:43]
- `current_user_can()` para pantallas, acciones y guardado de settings.[cite:43]
- Preparación de consultas con `$wpdb->prepare()`.[cite:43]
- Tokens firmados y expirables para links de recuperación.[cite:43]

### Privacidad

No conviene guardar más datos personales de los necesarios. Lo prudente es limitarse a email, ID de usuario si existe, locale, snapshot del carrito, timestamps y estado.[cite:43][cite:51]

No debe haber tracking de apertura, click o comportamiento fuera de la recuperación si no existe consentimiento adecuado y una implementación clara y revisable, ya que el directorio prohíbe tracking sin consentimiento explícito.[cite:43]

### Recomendación de producto

Para una primera versión orientada a WordPress.org, es más seguro:

- no activar pixel tracking por defecto,
- no hacer tracking remoto,
- no enviar datos a terceros,
- no cargar SDKs externos.[cite:43]

## Estadísticas

Las estadísticas compatibles con WordPress.org deben ser locales, transparentes y sin enviar telemetría a servidores externos.[cite:43]

KPIs recomendados para MVP:

- carritos detectados,
- carritos abandonados,
- carritos recuperados,
- ingresos recuperados,
- ratio de recuperación,
- emails enviados por paso,
- cupones usados.

KPIs para versiones posteriores:

- segmentación por locale,
- segmentación por tipo de checkout,
- segmentación por rango de valor de carrito.[cite:51][cite:53]

## Estructura de archivos

```text
woocommerce-cart-recovery/
├── woocommerce-cart-recovery.php
├── readme.txt
├── uninstall.php
├── LICENSE
├── languages/
│   └── woocommerce-cart-recovery.pot
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── classic-checkout-capture.js
│       ├── blocks-checkout-capture.js
│       └── admin.js
├── includes/
│   ├── class-plugin.php
│   ├── class-requirements.php
│   ├── class-installer.php
│   ├── class-uninstaller.php
│   ├── interfaces/
│   │   └── interface-locale-resolver.php
│   ├── locale/
│   │   ├── class-locale-resolver-manager.php
│   │   ├── class-default-locale-resolver.php
│   │   ├── class-wpml-locale-resolver.php
│   │   └── class-polylang-locale-resolver.php
│   ├── checkout/
│   │   ├── class-checkout-capture-coordinator.php
│   │   ├── class-classic-checkout-capture-adapter.php
│   │   └── class-blocks-checkout-capture-adapter.php
│   ├── domain/
│   │   ├── class-cart-capture-service.php
│   │   ├── class-abandoned-cart-detector.php
│   │   ├── class-pending-order-detector.php
│   │   ├── class-email-scheduler.php
│   │   ├── class-email-renderer.php
│   │   ├── class-coupon-service.php
│   │   ├── class-recovery-service.php
│   │   ├── class-cleanup-service.php
│   │   └── class-stats-service.php
│   ├── repositories/
│   │   ├── class-cart-repository.php
│   │   ├── class-email-log-repository.php
│   │   └── class-settings-repository.php
│   ├── admin/
│   │   ├── class-admin-menu.php
│   │   ├── class-settings-page.php
│   │   ├── class-abandoned-carts-page.php
│   │   └── class-stats-page.php
│   └── compat/
│       ├── class-woocommerce-compat.php
│       └── class-hpos-compat.php
└── templates/
    └── emails/
        ├── base-email.php
        ├── partials/
        │   ├── header.php
        │   ├── footer.php
        │   └── cart-table.php
        └── plain/
            └── base-email.php
```

## Hooks y cron

### Hooks internos recomendados

- `wccr_cart_marked_abandoned`
- `wccr_before_recovery_email_send`
- `wccr_after_recovery_email_send`
- `wccr_coupon_generated`
- `wccr_cart_recovered`
- `wccr_cleanup_completed`

### Cron events recomendados

- `wccr_detect_abandoned_carts`
- `wccr_process_recovery_queue`
- `wccr_cleanup_old_data`

### Frecuencias orientativas

- Detección abandono: cada 15 minutos.
- Cola de emails: cada 15 minutos o cada hora, según carga.
- Limpieza: diario.[cite:43]

## Cabecera principal del plugin

```php
<?php
/**
 * Plugin Name:       WooCommerce Cart Recovery
 * Plugin URI:        https://example.com/plugins/woocommerce-cart-recovery
 * Description:       Recover abandoned carts and pending orders in WooCommerce with scheduled reminders, native coupons and multilingual locale-aware email support.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woocommerce-cart-recovery
 * Domain Path:       /languages
 * WC requires at least: 9.0
 * WC tested up to:   10.0
 */

defined( 'ABSPATH' ) || exit;
```

La recomendación de requisitos mínimos relativamente modernos ayuda a simplificar compatibilidad, tipado y mantenimiento, aunque puedes ajustar versiones según tu mercado objetivo.[cite:43]

## Ejemplo inicial de readme.txt

```text
=== WooCommerce Cart Recovery ===
Contributors: yourname
Tags: woocommerce, abandoned cart, cart recovery, checkout, coupons
Requires at least: 6.7
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recover abandoned WooCommerce carts and pending orders with scheduled reminders, native coupons and multilingual locale-aware emails.

== Description ==

WooCommerce Cart Recovery helps store owners recover abandoned carts and pending orders using scheduled reminders, native WooCommerce coupons and locale-aware email content.

Features:
* Detect abandoned carts.
* Recover pending and failed checkout attempts.
* Send 3 configurable reminder emails.
* Apply progressive discounts.
* Create unique native WooCommerce coupons.
* Support guest and logged-in customers.
* Work with classic checkout and Checkout Blocks.
* Resolve customer locale with multilingual compatibility.
* Provide recovery statistics inside WordPress.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/woocommerce-cart-recovery` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Ensure WooCommerce is active.
4. Go to WooCommerce > Cart Recovery to configure reminder rules.

== Frequently Asked Questions ==

= Does this plugin require WPML? =
No. The plugin is locale-aware and can work without WPML.

= Does it support WooCommerce Checkout Blocks? =
Yes, the architecture is designed for both classic checkout and Checkout Blocks.

= Does it create native WooCommerce coupons? =
Yes.

== Changelog ==

= 0.1.0 =
* Initial release.
```

## Estrategia de versionado

Recomendación simple y efectiva:

- `0.1.0` MVP privado.
- `0.2.0` beta funcional.
- `0.9.0` release candidate.
- `1.0.0` primera estable pública.
- Después, esquema semántico: `MAJOR.MINOR.PATCH`.[cite:43]

Ejemplos:

- `1.0.1`: fix menor.
- `1.1.0`: nueva feature compatible.
- `2.0.0`: cambio rompedor.

## Roadmap recomendado

### MVP

- Tablas custom.
- Captura de email básica en classic checkout.
- Detección de abandono.
- Email step 1.
- Recuperación de carrito.
- Settings base.
- Cupón único nativo.
- Compatibilidad locale por fallback WordPress.

### v0.2

- Step 2 y step 3.
- Segmentación por valor del carrito.
- Exclusión por productos/categorías.
- Dashboard básico.
- Pending orders recovery.

### v0.3

- Adaptador WPML.
- Adaptador Polylang.
- Compatibilidad inicial con Checkout Blocks.[cite:47][cite:53]

### v0.4

- Mejoras de dashboard.
- Herramientas privacy/export/erase.
- Mejor trazabilidad de cupones.
- HPOS hardening.

### v1.0

- Publicación estable.
- Readme final pulido.
- Revisión completa WPCS.
- Revisión con Plugin Check y pruebas de compatibilidad.[cite:43]

## Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Cambios internos en Checkout Blocks | Alto | Aislar integración en adapter específico [cite:47][cite:53] |
| Dependencia excesiva de WPML | Alto | Resolver idioma por interfaz y fallback locale [cite:51] |
| Guardar demasiados datos personales | Alto | Guardar solo datos mínimos y documentar retención [cite:43] |
| Exceso de cupones sin uso | Medio | Crear cupón solo al enviar email [cite:53] |
| Carga excesiva de consultas | Medio | Tablas custom e índices por estado/fecha [cite:43] |
| Incompatibilidad con guidelines WordPress.org | Alto | Diseñar desde el principio para cumplir guidelines y usar Plugin Check [cite:43] |

## Recomendación final

La mejor base de producto no es un plugin “para WPML”, sino un plugin de recuperación de carritos **multilingual-ready, locale-aware y WordPress.org-first**.[cite:43][cite:51]

Eso te permite usarlo con clientes monolingües o multilingües, adaptarlo a distintos stacks de traducción, minimizar deuda técnica y dejar el proyecto preparado tanto para repositorio oficial como para una futura versión premium propia.[cite:43][cite:51]
