# WooCommerce Cart Recovery — Documentación Completa

**Versión:** 0.1.45  
**Autor:** 22MW  
**Licencia:** GPL-2.0-or-later  
**Requiere WordPress:** 6.7+  
**Requiere PHP:** 8.1+  
**Requiere WooCommerce:** 9.0+

---

## Índice

1. [Qué hace](#qué-hace)
2. [Qué no hace](#qué-no-hace)
3. [Requisitos mínimos](#requisitos-mínimos)
4. [Instalación](#instalación)
5. [Configuración](#configuración)
6. [Casos de uso rápidos](#casos-de-uso-rápidos)
7. [Hooks y filtros](#hooks-y-filtros)
8. [Compatibilidad](#compatibilidad)
9. [FAQ](#faq)
10. [Soporte](#soporte)
11. [Changelog](#changelog)

---

## Qué hace

WooCommerce Cart Recovery captura carritos abandonados y pedidos impagados, y envía hasta 3 emails de recuperación secuenciales con enlaces firmados, cupones nativos de WooCommerce y plantillas con soporte multidivisa y multiidioma.

**Flujo completo:**

1. El cliente añade productos al carrito e introduce su email (checkout o Blocks).
2. El plugin captura el carrito y lo guarda como `active`.
3. Si el cliente no completa la compra en el tiempo configurado, el carrito pasa a `abandoned`.
4. Se envían hasta 3 emails de recuperación con delays independientes por configuración.
5. Cada email puede incluir un cupón de descuento generado automáticamente.
6. El cliente hace clic en el enlace del email → el carrito se restaura exactamente como estaba.
7. Si el cliente completa la compra, el carrito pasa a `recovered` y todos los enlaces e cupones se invalidan.

**Funciones incluidas:**

- Captura desde checkout clásico y WooCommerce Checkout Blocks.
- Detección de abandono configurable (minutos de espera).
- Importación de pedidos `pending` y `failed` de WooCommerce como carritos recuperables.
- Generación de cupones nativos de WooCommerce por step (porcentaje, fijo o sin descuento).
- Fecha de expiración del cupón configurable.
- Enlace de recuperación firmado con token y expiración independiente.
- Restauración del carrito al hacer clic: el cliente llega al checkout con el carrito completo.
- Pre-relleno del campo `billing_email` en el checkout al recuperar.
- Registro de qué step de email obtuvo el clic.
- Marcado automático como recuperado al completar el pedido.
- Revocación de todos los cupones generados al recuperar.
- Exclusión de productos por ID o taxonomía (categorías, tags, etc.).
- Compatibilidad con WPML y Polylang: expansión automática de exclusiones a traducciones.
- Dashboard de estadísticas: abandonados, clicks, recuperados, ingresos recuperados, emails enviados.
- Limpieza automática de datos antiguos configurable (días de retención).
- Actualizaciones automáticas desde GitHub.
- Soporte para WooCommerce Bookings (el booking ID efímero se regenera en la recuperación).

---

## Qué no hace

- **No captura carritos sin email.** Si el cliente no ha introducido su email en el checkout, no se registra nada.
- **No envía emails a clientes que han completado la compra.** Una vez `recovered`, el flujo se detiene. Tampoco se importan pedidos `pending` o `failed` de clientes que ya tienen una compra posterior en estado completado, en proceso o en espera.
- **No gestiona SMS ni notificaciones push.** Solo email.
- **No es compatible con WooCommerce Subscriptions** de forma nativa (no probado).
- **No duplica carritos.** Si el cliente vuelve a añadir productos antes de que expire el intento activo, se actualiza la misma fila. En la importación de pedidos impagados, solo se crea una fila activa por email de cliente: si el mismo cliente tiene varios pedidos `failed` o `pending`, únicamente se importa el más antiguo elegible.
- **No envía emails si el step está desactivado** o si el tipo de descuento está configurado pero no se puede generar el cupón.
- **No modifica precios ni añade impuestos** durante la recuperación.

---

## Requisitos mínimos

| Requisito       | Versión mínima |
| --------------- | -------------- |
| WordPress       | 6.7            |
| PHP             | 8.1            |
| WooCommerce     | 9.0            |
| MySQL / MariaDB | 5.7 / 10.3     |

**Opcionales (con compatibilidad explícita):**

- WPML
- Polylang
- WooCommerce Bookings

---

## Instalación

### Desde el panel de WordPress

1. Descargar el ZIP de la última release desde GitHub.
2. Ir a **Plugins → Añadir nuevo → Subir plugin**.
3. Seleccionar el ZIP y hacer clic en **Instalar ahora**.
4. Activar el plugin.
5. Al activar, el plugin crea automáticamente su tabla en la base de datos y programa las tareas recurrentes con WooCommerce Action Scheduler.

### Manual (FTP / SSH)

1. Descomprimir el ZIP.
2. Subir la carpeta `woocommerce-cart-recovery/` a `wp-content/plugins/`.
3. Activar desde **Plugins → Plugins instalados**.

### Actualizaciones automáticas

El plugin incluye un actualizador propio via GitHub. Cuando hay una nueva versión disponible aparece la notificación estándar de WordPress en **Plugins → Plugins instalados**. Las actualizaciones se instalan igual que cualquier plugin del repositorio oficial.

---

## Configuración

Ir a **WooCommerce → Cart Recovery** en el menú lateral del admin.

### Ajustes generales

| Campo                           | Descripción                                                                    | Valor por defecto |
| ------------------------------- | ------------------------------------------------------------------------------ | ----------------- |
| Minutos de espera para abandono | Tiempo desde la última actividad hasta marcar el carrito como abandonado       | 60                |
| Días de retención de datos      | Los carritos más antiguos que este valor se eliminan en la limpieza automática | 90                |
| Días de expiración del cupón    | Días de validez del cupón generado contados desde el envío del email           | 7                 |
| Nombre del remitente            | Nombre que aparece en el campo "De:" del email                                 | Nombre del sitio  |
| Productos excluidos             | Productos que, si están en el carrito, excluyen ese carrito del flujo          | —                 |
| Términos excluidos              | Categorías, tags u otras taxonomías que excluyen el carrito                    | —                 |

### Emails (Steps 1, 2 y 3)

Cada step tiene configuración independiente:

| Campo                    | Descripción                                                                                                                                        |
| ------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Activado / Desactivado   | Switch para habilitar o deshabilitar el step                                                                                                       |
| Minutos de espera        | Tiempo después del abandono para enviar este email                                                                                                 |
| Tipo de descuento        | `Ninguno`, `Porcentaje` o `Fijo`                                                                                                                   |
| Importe del descuento    | Valor del descuento (ignorado si tipo es `Ninguno`)                                                                                                |
| Total mínimo del carrito | No enviar si el carrito es menor a este importe                                                                                                    |
| Asunto                   | Asunto del email. Soporta variables                                                                                                                |
| Cuerpo                   | Cuerpo del email. Soporta variables. El resto del email (tabla de productos, botón CTA, cupón si aplica) se añade automáticamente por la plantilla |

**Variables disponibles en asunto y cuerpo:**

| Variable          | Descripción                                           |
| ----------------- | ----------------------------------------------------- |
| `{customer_name}` | Nombre del cliente                                    |
| `{site_name}`     | Nombre del sitio                                      |
| `{cart_total}`    | Total del carrito                                     |
| `{coupon_code}`   | Código del cupón generado (vacío si no hay descuento) |
| `{coupon_label}`  | Etiqueta del descuento (ej. "10% off")                |
| `{recovery_link}` | URL de recuperación                                   |

### Configuración por idioma (Multiidioma)

Si el sitio usa WPML o Polylang, cada step muestra pestañas por idioma. El asunto y cuerpo se pueden personalizar por idioma de forma independiente.

### Importar pedidos impagados

Desde los ajustes hay un botón para importar manualmente los pedidos `pending` y `failed` existentes y añadirlos al flujo de recovery.

La sincronización automática (tarea recurrente) solo procesa pedidos creados **después** de activar el plugin. El botón manual cubre el histórico completo. En ambos casos se omiten pedidos cuyo cliente ya tiene una compra posterior completada, y se importa como máximo un pedido por email de cliente.

---

## Casos de uso rápidos

### Caso 1: Recordatorio simple sin descuento

- Step 1: 60 min, sin descuento, asunto "Olvidaste algo en tu carrito".
- Step 2 y 3: desactivados.

### Caso 2: Secuencia de 3 emails con descuento progresivo

- Step 1: 60 min, sin descuento — recordatorio neutro.
- Step 2: 1440 min (24h), 5% de descuento — incentivo suave.
- Step 3: 2880 min (48h), 10% de descuento — último intento.

### Caso 3: Solo clientes con carrito mínimo de 50€

- En cada step: `Total mínimo del carrito = 50`.
- Carritos por debajo de ese importe no recibirán emails.

### Caso 4: Excluir productos de una categoría concreta

- En **Términos excluidos**, buscar y añadir la categoría.
- Cualquier carrito que contenga un producto de esa categoría quedará fuera del flujo.

### Caso 5: Sitio multiidioma con WPML

- Configurar asunto y cuerpo por idioma en cada step.
- El plugin selecciona automáticamente el texto en el idioma del cliente al enviar.

---

## Hooks y filtros

### Filtros (apply_filters)

#### `wccr_abandon_after_minutes`

Modifica el tiempo de espera (en minutos) antes de marcar un carrito como abandonado.

```php
add_filter( 'wccr_abandon_after_minutes', function( $minutes ) {
    return 90; // 1.5 horas
} );
```

---

#### `wccr_cleanup_days`

Modifica los días de retención antes de limpiar datos antiguos.

```php
add_filter( 'wccr_cleanup_days', function( $days ) {
    return 180;
} );
```

---

#### `wccr_email_eligibility`

Permite bloquear el envío de un email a un carrito concreto. Devolver `false` cancela el envío.

```php
/**
 * @param bool  $eligible  Si el carrito es elegible para el envío.
 * @param array $cart      Datos del carrito.
 * @param array $settings  Configuración del plugin.
 */
add_filter( 'wccr_email_eligibility', function( $eligible, $cart, $settings ) {
    // Bloquear si el dominio es interno
    if ( str_ends_with( $cart['email'] ?? '', '@miempresa.com' ) ) {
        return false;
    }
    return $eligible;
}, 10, 3 );
```

---

#### `wccr_email_subject`

Modifica el asunto del email antes de enviarlo.

```php
/**
 * @param string $subject       Asunto procesado.
 * @param array  $cart          Datos del carrito.
 * @param array  $step_settings Configuración del step.
 */
add_filter( 'wccr_email_subject', function( $subject, $cart, $step_settings ) {
    return '[OFERTA] ' . $subject;
}, 10, 3 );
```

---

#### `wccr_email_content`

Modifica el HTML completo del email antes de enviarlo.

```php
/**
 * @param string $content       HTML del email.
 * @param array  $cart          Datos del carrito.
 * @param array  $step_settings Configuración del step.
 */
add_filter( 'wccr_email_content', function( $content, $cart, $step_settings ) {
    return $content . '<p>Texto adicional al pie.</p>';
}, 10, 3 );
```

---

#### `wccr_email_headers`

Modifica las cabeceras del email (por defecto solo `Content-Type: text/html`).

```php
/**
 * @param array $headers       Cabeceras actuales.
 * @param array $cart          Datos del carrito.
 * @param int   $step          Número de step (1, 2 o 3).
 */
add_filter( 'wccr_email_headers', function( $headers, $cart, $step ) {
    $headers[] = 'Cc: copia@miempresa.com';
    return $headers;
}, 10, 3 );
```

---

#### `wccr_coupon_args`

Modifica los argumentos del cupón de WooCommerce antes de crearlo.

```php
/**
 * @param WC_Coupon $coupon        Objeto cupón.
 * @param array     $cart          Datos del carrito.
 * @param array     $step_settings Configuración del step.
 */
add_filter( 'wccr_coupon_args', function( $coupon, $cart, $step_settings ) {
    $coupon->set_minimum_amount( 30 ); // mínimo de compra para aplicar el cupón
    return $coupon;
}, 10, 3 );
```

---

#### `wccr_recovery_url`

Modifica la URL de recuperación generada.

```php
/**
 * @param string $url      URL generada.
 * @param int    $cart_id  ID del carrito.
 * @param string $coupon   Código del cupón (puede estar vacío).
 * @param int    $step     Step que genera la URL.
 */
add_filter( 'wccr_recovery_url', function( $url, $cart_id, $coupon, $step ) {
    return $url; // modificar si se necesita un dominio o path personalizado
}, 10, 4 );
```

---

### Acciones (do_action)

#### `wccr_cart_marked_abandoned`

Se dispara cuando un carrito pasa a estado `abandoned`.

```php
/**
 * @param int $count Número de carritos marcados en esta ejecución.
 */
add_action( 'wccr_cart_marked_abandoned', function( $count ) {
    // Notificar, registrar en log externo, etc.
} );
```

---

#### `wccr_cart_recovery_clicked`

Se dispara cuando un cliente hace clic en el enlace de recuperación.

```php
/**
 * @param int $cart_id ID del carrito recuperado.
 */
add_action( 'wccr_cart_recovery_clicked', function( $cart_id ) {
    // Registrar el evento en analytics, CRM, etc.
} );
```

---

#### `wccr_cart_recovered`

Se dispara cuando un carrito queda marcado como recuperado (pedido completado).

```php
/**
 * @param int $cart_id  ID del carrito.
 * @param int $order_id ID del pedido de WooCommerce.
 */
add_action( 'wccr_cart_recovered', function( $cart_id, $order_id ) {
    // Integración con CRM, informes externos, etc.
}, 10, 2 );
```

---

#### `wccr_coupon_generated`

Se dispara al generar un cupón para un carrito.

```php
/**
 * @param string $code          Código del cupón generado.
 * @param array  $cart          Datos del carrito.
 * @param array  $step_settings Configuración del step.
 */
add_action( 'wccr_coupon_generated', function( $code, $cart, $step_settings ) {
    // Registrar en CRM o sistema externo.
}, 10, 3 );
```

---

#### `wccr_before_recovery_email_send`

Se dispara justo antes de enviar el email de recuperación.

```php
/**
 * @param array  $cart    Datos del carrito.
 * @param int    $step    Número de step.
 * @param string $subject Asunto del email.
 */
add_action( 'wccr_before_recovery_email_send', function( $cart, $step, $subject ) {
    // Log, throttling personalizado, etc.
}, 10, 3 );
```

---

#### `wccr_after_recovery_email_send`

Se dispara justo después de enviar el email correctamente.

```php
/**
 * @param array  $cart    Datos del carrito.
 * @param int    $step    Número de step.
 * @param string $subject Asunto del email.
 */
add_action( 'wccr_after_recovery_email_send', function( $cart, $step, $subject ) {
    // Incrementar contador externo, notificar, etc.
}, 10, 3 );
```

---

#### `wccr_cleanup_completed`

Se dispara al finalizar la limpieza automática de datos.

```php
/**
 * @param int $days Días de retención usados en la limpieza.
 */
add_action( 'wccr_cleanup_completed', function( $days ) {
    // Registrar en log, notificar al administrador, etc.
} );
```

---

## Compatibilidad

### WPML / Polylang

- Los textos de email se configuran por idioma en los ajustes del plugin.
- Las exclusiones por producto o término se expanden automáticamente a todas las traducciones.
- El plugin detecta el idioma del cliente al enviar y selecciona el texto correcto.

### WooCommerce Checkout Blocks

- Captura completamente soportada para el checkout basado en Blocks.
- El email se captura en cuanto el cliente interactúa con el campo de email en Blocks.

### WooCommerce Bookings

- Al capturar el carrito se elimina el `_booking_id` efímero del payload.
- Al restaurar el carrito, WooCommerce Bookings crea un booking nuevo automáticamente.
- Los bookings en estado `in-cart` propios del plugin de Bookings no interfieren con el flujo de recovery.

---

## FAQ

**¿El plugin captura carritos de clientes no registrados?**  
Sí, siempre que el cliente haya introducido su email en el checkout (aunque no haya completado la compra). No captura carritos sin email.

**¿Se envían emails a clientes que ya compraron?**  
No. En cuanto se detecta la conversión, el carrito pasa a `recovered` y el flujo se detiene. Los enlaces de recuperación ya enviados quedan invalidados.

**¿Qué pasa si el cliente hace clic en el email pero no compra?**  
El carrito pasa a estado `clicked`. Los emails de steps posteriores siguen enviándose ya que el carrito no está recuperado.

**¿Se puede usar sin descuentos?**  
Sí. El tipo de descuento por defecto es `Ninguno`. Si no hay descuento configurado, no se genera cupón y el email no muestra ninguna sección de descuento.

**¿El cupón generado expira?**  
Sí. La expiración se calcula desde el momento del envío del email, usando el valor configurado en "Días de expiración del cupón". El enlace de recuperación tiene su propia expiración independiente.

**¿Qué ocurre si el cliente abre el email de recuperación desde un navegador/dispositivo diferente?**  
El plugin fuerza la escritura de la sesión en base de datos y establece la cookie de sesión antes de redirigir. Esto permite que el carrito se restaure correctamente incluso en modo incógnito o en otro dispositivo.

**¿Se puede instalar en una tienda con muchos pedidos sin problemas de rendimiento?**  
Las tareas recurrentes usan WooCommerce Action Scheduler, que gestiona la cola de forma asíncrona. La limpieza automática evita la acumulación indefinida de datos. No se realizan queries pesadas en el frontoffice.

**¿Qué base de datos crea el plugin?**  
Una tabla: `{prefijo}wccr_carts`. Contiene todos los carritos capturados y sus estados.

**¿Se puede desinstalar limpiamente?**  
Al desactivar y eliminar el plugin, la tabla y las opciones del plugin se pueden limpiar. Revisar el comportamiento de desinstalación en `class-installer.php`.

**¿Hay un log de emails enviados?**  
Sí, el plugin mantiene un registro interno de qué emails se enviaron por carrito y paso, lo que evita el doble envío y permite mostrar estadísticas en el dashboard.

**¿Funciona con caché de página?**  
La captura del carrito y la restauración se realizan en contexto de usuario autenticado o con parámetros de URL firmados. No se almacena ningún dato sensible en caché de página.

**¿Qué pasa si el cliente tiene el email del step 1 y hace clic, pero luego no compra? ¿Recibe el step 2?**  
Sí. El plugin incluye los carritos en estado `clicked` en la cola de emails para los steps siguientes.

---

## Soporte

Para soporte, reportar bugs o solicitar funcionalidades:

- **GitHub Issues:** Abrir un issue en el repositorio del plugin.
- **Email:** Indicar en el issue de GitHub si prefieres contacto por email.

Al reportar un bug incluir:

1. Versión del plugin, WordPress, WooCommerce y PHP.
2. Descripción del problema y pasos para reproducirlo.
3. Logs de error relevantes (activar `WP_DEBUG_LOG` si es necesario).
4. Si aplica: captura de pantalla o URL de la instalación de prueba.

---

## Changelog

Ver [CHANGELOG.md](./CHANGELOG.md) para el historial completo de cambios.
