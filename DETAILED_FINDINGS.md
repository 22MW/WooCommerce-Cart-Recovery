# HALLAZGOS DETALLADOS Y REFERENCIAS ESPECÍFICAS

## WooCommerce Cart Recovery v0.1.29

---

## TABLA DE CONTENIDOS

1. [Inconsistencias Identificadas](#inconsistencias-identificadas)
2. [Problemas de Seguridad](#problemas-de-seguridad)
3. [Brechas de Funcionalidad](#brechas-de-funcionalidad)
4. [Documentación Faltante](#documentación-faltante)
5. [Recomendaciones Específicas](#recomendaciones-específicas)

---

## INCONSISTENCIAS IDENTIFICADAS

### 1. Prefijos Inconsistentes

**Problema:** Mezcla de tres prefijos diferentes en el mismo plugin

#### Ubicación 1: Text Domain en header

```
Archivo: woocommerce-cart-recovery.php (línea 11)
Text Domain:     vfwoo_woocommerce-cart-recovery  ← PREFIJO: vfwoo_
```

#### Ubicación 2: Clases PHP

```
Archivo: includes/class-plugin.php
Clase:   WCCR_Plugin                              ← PREFIJO: WCCR_
Clase:   WCCR_Settings_Repository                 ← PREFIJO: WCCR_
... todos usan WCCR_
```

#### Ubicación 3: Opciones de WordPress

```
Archivo: includes/repositories/class-settings-repository.php (línea 18)
Option:  'wccr_settings'                          ← PREFIJO: wccr_
```

#### Ubicación 4: Tablas de BD

```
Archivo: includes/class-installer.php (línea 28)
Tabla:   wp_wccr_abandoned_carts                  ← PREFIJO: wccr_
Tabla:   wp_wccr_email_log                        ← PREFIJO: wccr_
```

#### Ubicación 5: Hooks y Actions

```
Archivo: includes/domain/class-email-scheduler.php (línea 73)
Hook:    do_action( 'wccr_before_recovery_email_send', ... )  ← PREFIJO: wccr_
```

**Impacto:** Confusión en documentación, dificultad para identificar elementos del plugin

**Decisión Requerida:**

```
OPCIÓN A: Unificar a WCCR_ / wccr_
   - Renombrar text domain a 'wccr' (req. migración de traducciones)
   - Cambiar class attributes WCCR_ ✅ (ya están correctas)
   - Mantener opciones wccr_ ✅ (OK)

OPCIÓN B: Unificar a VFWOO_ / vfwoo_
   - Mantener text domain vfwoo_woocommerce-cart-recovery (ya activo)
   - Renombrar todas las clases WCCR_ → VFWOO_
   - Renombrar opciones wccr_settings → vfwoo_settings
   - Renombrar tablas wp_wccr_* → wp_vfwoo_*

RECOMENDACIÓN: OPCIÓN A es más simple (cambio menor)
```

---

### 2. Documentación de Parámetros en Templates

**Problema:** Variables inyectadas en templates no están documentadas

#### Ubicación: Templates/emails/base-email.php (línea 1-20)

```php
<?php
/**
 * Cart Recovery Email Template
 *
 * SIN DOCUMENTACIÓN DE PARÁMETROS:
 * ¿Qué variables se pasan a este template?
 * ¿Son strings? ¿Arrays?
 */
defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<!-- ... template ... -->
<p><?php echo esc_html( sprintf( /* translators: %s: customer name */ __( 'Hello %s,', 'vfwoo_woocommerce-cart-recovery' ), $customer_name ) ); ?></p>
<!-- Aquí se asume que $customer_name existe, pero no hay documentación -->
```

**Variables referenciadas sin ser documentadas:**

- `$customer_name` - Presumiblemente string, pero ¿puede estar vacío?
- Hay más variables que deberían estar documentadas

**Recomendación:**

```php
<?php
/**
 * Cart Recovery Email Template
 *
 * Este template representa el email de recuperación de carrito.
 *
 * Variables disponibles:
 * @param string $customer_name    Nombre del cliente (ej: "John Doe")
 * @param string $recovery_url     URL para recuperar carrito con token
 * @param string $coupon_code      Código de cupón (puede estar vacío)
 * @param string $site_name        Nombre del sitio WordPress
 * @param string $cart_total       Total del carrito formateado
 */
defined( 'ABSPATH' ) || exit;
?>
```

---

## PROBLEMAS DE SEGURIDAD

### 1. CRÍTICO: AJAX sin Nonce Verification

**Severidad:** CRÍTICA  
**Archivo:** `includes/checkout/class-blocks-checkout-capture-adapter.php`  
**Líneas:** 13-55

```php
// LÍNEA 13 - Hook registrado SIN protección
add_action( 'wp_ajax_nopriv_wccr_capture_checkout_contact', array( $this, 'ajax_capture_checkout_contact' ) );

// LÍNEA 50-55 - Método sin verificación de nonce
public function ajax_capture_checkout_contact(): void {
    $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

    $this->cart_capture_service->capture_current_cart(
        '' !== $email ? $email : null,
        '' !== $name ? $name : null,
        'blocks'
    );

    wp_send_json_success();
    // ❌ NO HAY check_ajax_referer() AQUÍ
}
```

**¿Qué se genera en enqueue_capture_script? (línea 30-45)**

```php
wp_localize_script(
    'wccr-blocks-capture',
    'WCCRCheckoutCapture',
    array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'wccr_capture_checkout_contact' ),  // ✅ Se crea nonce
    )
);
```

**El nonce se CREA pero NO se VERIFICA:**

```
✅ wp_create_nonce() genera correctamente
✅ Se pasa al JavaScript
❌ check_ajax_referer() FALTA en el handler
```

**Fix requerido:**

```php
public function ajax_capture_checkout_contact(): void {
    // AGREGAR ESTA LÍNEA al inicio:
    check_ajax_referer( 'wccr_capture_checkout_contact', 'nonce', true );

    $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

    $this->cart_capture_service->capture_current_cart(
        '' !== $email ? $email : null,
        '' !== $name ? $name : null,
        'blocks'
    );

    wp_send_json_success();
}
```

---

### 2. IMPORTANTE: Tokens de recuperación sin expiración

**Severidad:** ALTA  
**Archivo:** `includes/domain/class-recovery-service.php`  
**Línea:** 19-54

```php
public function build_recovery_url( int $cart_id, ?string $coupon_code = null, int $step = 0 ): string {
    // LÍNEA 20 - Token sin expiración
    $token = wp_hash( $cart_id . '|' . wp_salt( 'auth' ) );
    // Token es determinístico basado en:
    // - ID de carrito (número)
    // - Salt de autenticación (fijo)
    // ❌ Sin timestamp
    // ❌ Sin límite de tiempo de validación

    $args = array(
        'wccr_recover' => $cart_id,
        'wccr_token'   => $token,  // ❌ Este token es permanente
    );
    // ...
}

public function maybe_restore_cart(): void {
    // LÍNEA 42-43 - Validación sin timestamp
    if ( ! $cart_id || ! $token || ! hash_equals( wp_hash( $cart_id . '|' . wp_salt( 'auth' ) ), $token ) ) {
        return;
    }
    // ❌ Solo verifica que el hash sea correcto
    // ❌ No verifica antigüedad del carrito
    // ❌ No hay log de intentos fallidos
}
```

**Problema realista:**

```
1. Usuario recibe email con token hace 180 días
2. Token SIGUE siendo válido indefinidamente
3. Si se filtra la URL, cualquiera puede recuperar el carrito
4. Sin auditoría de intentos fallidos
```

**Fix requerido:**

```php
public function build_recovery_url( int $cart_id, ?string $coupon_code = null, int $step = 0 ): string {
    // Incluir timestamp en el token
    $expiry_timestamp = time() + ( 30 * DAY_IN_SECONDS );
    $token = wp_hash( $cart_id . '|' . $expiry_timestamp . '|' . wp_salt( 'auth' ) );

    $args = array(
        'wccr_recover' => $cart_id,
        'wccr_token'   => $token,
        'wccr_exp'     => $expiry_timestamp,  // Pasar timestamp
    );
    // ...
}

public function maybe_restore_cart(): void {
    $expiry = isset( $_GET['wccr_exp'] ) ? absint( wp_unslash( $_GET['wccr_exp'] ) ) : 0;

    // Verificar expiración (30 días)
    if ( $expiry < time() ) {
        // Token expirado
        return;
    }

    if ( ! hash_equals( wp_hash( $cart_id . '|' . $expiry . '|' . wp_salt( 'auth' ) ), $token ) ) {
        // Registrar intento fallido
        do_action( 'wccr_recovery_token_validation_failed', $cart_id );
        return;
    }
    // ...
}
```

---

### 3. IMPORTANTE: Datos sensibles almacenados en plaintext

**Severidad:** MEDIA/ALTA  
**Archivos:**

- `includes/repositories/class-cart-repository.php` (línea 47)
- `includes/class-installer.php` (línea 15-60)

```sql
-- TABLA: wp_wccr_abandoned_carts
CREATE TABLE wp_wccr_abandoned_carts (
    ...
    email VARCHAR(190) NULL,           -- ❌ SIN ENCRIPTACIÓN
    customer_name VARCHAR(190) NULL,   -- ❌ SIN ENCRIPTACIÓN
    cart_payload LONGTEXT NULL,        -- ❌ SIN ENCRIPTACIÓN (incluye productos, precios)
    ...
);
```

**Qué se almacena (PII):**

```
✗ Email del cliente
✗ Nombre del cliente
✗ Carrito completo (productos, cantidades, precios)
✗ Dirección de envío (si está en cart_payload)
✗ Notas personales (si las hay)
```

**Cumplimiento GDPR/CCPA:**

```
❌ GDPR Art. 32: "Cifrado de datos personales" - NO cumple
❌ CCPA: "Reasonable security measures" - Discutible
```

**Recomendación:**

```php
// En class-installer.php - Modificar columnas:
CREATE TABLE {$carts_table} (
    ...
    email_hash VARCHAR(64) NOT NULL,              -- Hash para búsqueda
    email_encrypted LONGTEXT NULL,                -- Email cifrado
    customer_name_encrypted LONGTEXT NULL,        -- Nombre cifrado
    cart_payload_encrypted LONGTEXT NULL,         -- Carrito cifrado
    ...
);

// En wp-config.php
define( 'WCCR_ENCRYPTION_KEY', 'your-secret-key-here' );  // Cambiar por algo seguro

// En class-cart-repository.php
public function insert_new_active_cart( array $data, string $now_gmt ): void {
    $data['email_encrypted'] = $this->encrypt_data( $data['email'] );
    $data['email_hash'] = hash( 'sha256', sanitize_email( $data['email'] . wp_salt() ) );
    // No almacenar email en texto plano
}
```

---

## BRECHAS DE FUNCIONALIDAD

### 1. CRÍTICO: No hay apply_filters (solo do_action)

**Severidad:** ALTA (extensibilidad)  
**Impacto:** Otros plugins NO PUEDEN filtrar/modificar el comportamiento

#### Archivos con do_action:

```
✅ includes/domain/class-abandoned-cart-detector.php:21
   do_action( 'wccr_cart_marked_abandoned', $updated );

✅ includes/domain/class-email-scheduler.php:73
   do_action( 'wccr_before_recovery_email_send', $cart, $step, $subject );

✅ includes/domain/class-email-scheduler.php:77
   do_action( 'wccr_after_recovery_email_send', $cart, $step, $subject );

✅ includes/domain/class-coupon-service.php:32
   do_action( 'wccr_coupon_generated', $code, $cart, $step_settings );

✅ includes/domain/class-cleanup-service.php:27
   do_action( 'wccr_cleanup_completed', $days );

✅ includes/domain/class-pending-order-detector.php:158
   do_action( 'wccr_cart_recovered', $cart_id, $order_id );

✅ includes/domain/class-recovery-service.php:94
   do_action( 'wccr_cart_recovery_clicked', $cart_id );
```

#### Filtros que DEBERÍAN existir:

```php
❌ apply_filters( 'wccr_email_subject_before_send', $subject, $cart, $step )
❌ apply_filters( 'wccr_email_body_before_send', $body, $cart, $step )
❌ apply_filters( 'wccr_coupon_code_generated', $code, $cart, $step_settings )
❌ apply_filters( 'wccr_recovery_url', $recovery_url, $cart_id )
❌ apply_filters( 'wccr_eligible_carts', $carts, $settings )
❌ apply_filters( 'wccr_settings_validated', $settings )
❌ apply_filters( 'wccr_excluded_products', $product_ids, $settings )
```

**Implementación de ejemplo:**

```php
// En class-email-scheduler.php - LÍNEA ~65 (después de renderizar email)
$email = $this->email_renderer->render( $cart, $step_settings, $recovery_url, $coupon_code );

// AGREGAR:
$email['subject'] = apply_filters( 'wccr_recovery_email_subject', (string)( $email['subject'] ?? '' ), $cart, $step, $coupon_code );
$email['message'] = apply_filters( 'wccr_recovery_email_body', (string)( $email['message'] ?? '' ), $cart, $step, $coupon_code );
```

---

### 2. IMPORTANTE: No usa WordPress Settings API (register_setting)

**Severidad:** MEDIA  
**Archivo:** `includes/admin/class-settings-page.php` (línea 32)

```php
// ACTUAL - Sin register_setting
public function maybe_save(): void {
    // ... validaciones ...
    $this->settings_repository->save( $settings );  // Guarda directo en option
}

// En class-settings-repository.php (línea 32)
public function save( array $settings ): void {
    update_option( self::OPTION_KEY, $settings );  // ❌ Sin sanitización centralizada
}
```

**Problemas:**

```
❌ No hay sanitización automática mediante register_setting
❌ No aparece en REST API Settings (si la quisieras exponer)
❌ No hay auditoría integrada de WordPress
❌ Difícil debugging de qué cambió
```

**Fix:**

```php
// Añadir en class-plugin.php init()
add_action( 'init', function() {
    register_setting(
        'wccr_settings_group',         // Opción group
        'wccr_settings',               // Opción name
        array(
            'type'              => 'array',
            'schema'            => array(
                'type'       => 'object',
                'properties' => array(
                    'abandon_after_minutes' => array( 'type' => 'integer' ),
                    'cleanup_days'          => array( 'type' => 'integer' ),
                    // ... más campos
                ),
            ),
            'sanitize_callback' => array( 'WCCR_Settings_Repository', 'sanitize_settings' ),
            'show_in_rest'      => false,  // No exponer públicamente
        )
    );
});

// En class-settings-repository.php
public static function sanitize_settings( $input ) {
    if ( ! is_array( $input ) ) {
        return self::default_settings();
    }

    return array(
        'abandon_after_minutes' => absint( $input['abandon_after_minutes'] ?? 60 ),
        'cleanup_days'          => absint( $input['cleanup_days'] ?? 90 ),
        // ... sanitizar todos los campos
    );
}
```

---

## DOCUMENTACIÓN FALTANTE

### 1. Métodos Privados sin PHPDoc

**Archivo:** `includes/admin/class-admin-menu.php`

```php
// LÍNEA 71 - SIN DOCUMENTACIÓN
private function render_active_tab(string $tab): void {
    if ('settings' === $tab) {
        $this->settings_page->render_content();
        return;
    }

    $this->carts_page->render_content();
}

// DEBERÍA SER:
/**
 * Render the selected tab content.
 *
 * @param string $tab The tab identifier. Expected values: 'carts', 'settings'.
 * @return void
 */
private function render_active_tab(string $tab): void {
```

**Otros métodos privados sin doc:**

- `get_current_tab()` (línea 83)
- `get_tab_url()` (línea 92)
- `maybe_handle_delete()` (en class-abandoned-carts-page.php)

### 2. JavaScript sin JSDoc

**Archivo:** `assets/js/admin.js` (línea 1-80)

```javascript
// SIN JSDoc ni comentarios
function getLabel(key, defaultValue) {
  if ("undefined" === typeof WCCRAdminI18n || !WCCRAdminI18n[key]) {
    return defaultValue;
  }
  return WCCRAdminI18n[key];
}

// DEBERÍA SER:
/**
 * Get a localized label from the WCCRAdminI18n object.
 *
 * @param {string} key          The translation key.
 * @param {string} defaultValue Fallback value if key not found.
 * @return {string} The localized label or default value.
 */
function getLabel(key, defaultValue) {
  // ...
}
```

---

## RECOMENDACIONES ESPECÍFICAS

### Sprint 1: Fixes Críticos (Para v0.1.30)

```
[ ] 1. Implementar check_ajax_referer() en AJAX handlers
       Archivo: includes/checkout/class-blocks-checkout-capture-adapter.php
       Estimado: 15 min

[ ] 2. Agregar timestamp a recovery tokens
       Archivo: includes/domain/class-recovery-service.php
       Estimado: 30 min

[ ] 3. Implementar mínimo 5 apply_filters estratégicos
       Archivos: domain/*.php
       Estimado: 1 hora
```

### Sprint 2: Mejoras Importantes (Para v0.1.31)

```
[ ] 4. Unificar prefijos a WCCR_ / wccr_ EN TODO
       Archivos: Múltiples
       Estimado: 2 horas

[ ] 5. Completar documentación PHPDoc en métodos privados
       Archivos: admin/*, domain/*
       Estimado: 1.5 horas

[ ] 6. Implementar register_setting() para settings
       Archivo: class-plugin.php
       Estimado: 1 hora
```

### Sprint 3: Mejoras Opcionales (Para v0.1.32)

```
[ ] 7. Implementar encriptación de datos sensibles
       Archivos: repositories/class-cart-repository.php
       Estimado: 3 horas

[ ] 8. Agregar JSDoc a assets/js/admin.js
       Archivo: assets/js/admin.js
       Estimado: 45 min

[ ] 9. Crear documentación de hooks públicos (Developer Guide)
       Nuevo: docs/HOOKS.md
       Estimado: 1 hora
```

---

## CHECKLIST DE VERIFICACIÓN

### Antes de v1.0.0 Release

```
Seguridad:
  [ ] AJAX con protección de nonce
  [ ] Tokens con expiración
  [ ] Datos sensibles cifrados (considerado)
  [ ] Rate limiting en recovery URLs
  [ ] Validación de CSRF consistente

Funcionalidad:
  [ ] Mínimo 10 apply_filters globales
  [ ] Documentación de hooks en README
  [ ] Ejemplos para desarrolladores
  [ ] WooCommerce Settings API (si aplicable)

Calidad de Código:
  [ ] PHPDoc 100% en métodos públicos
  [ ] JSDoc en todos los functions
  [ ] Prefijos consistentes
  [ ] Naming conventions WPCS

Testing:
  [ ] Tests unitarios (50%+ cobertura)
  [ ] Tests de integración WooCommerce
  [ ] Tests de seguridad OWASP
```

---

**Documento generado:** 7 de abril de 2026  
**Versión analizada:** 0.1.29  
**Siguiente revisión:** Post-implementación de fixes
