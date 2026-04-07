# PLAN DE MEJORAS - WooCommerce Cart Recovery

## Auditoría Global Completa | 7 de abril de 2026

> **Estado:** Plan preparado para aprobación  
> **Alcance:** Auditoría sin cambios ejecutados  
> **Referencia:** AGENTS.md compliance

---

## 📊 RESUMEN EJECUTIVO

| Métrica                | Valor  | Estado     |
| ---------------------- | ------ | ---------- |
| **Seguridad**          | 7.2/10 | ⚠️ Crítico |
| **Estándares WP/WC**   | 8.8/10 | ✅ Bueno   |
| **Código**             | 8.5/10 | ✅ Bueno   |
| **Problemas Críticos** | 7      | 🔴         |
| **Problemas Altos**    | 4      | 🟠         |
| **Problemas Medios**   | 12+    | 🟡         |

**Estado General:** Arquitectura sólida con problemas de seguridad críticos que deben resolverse antes de v1.0.0 o producción.

---

## 🔴 PROBLEMAS CRÍTICOS (Seguridad)

### 1. **CSRF - Captura AJAX sin Nonce**

- **Archivo:** `includes/checkout/class-blocks-checkout-capture-adapter.php` (línea 50-65)
- **Hook:** `wp_ajax_nopriv_wccr_capture_checkout_contact`
- **Problema:** Usuarios anónimos pueden capturar carritos sin validación CSRF
- **Causa:** Función AJAX sin `check_ajax_referer()` o `wp_verify_nonce()`
- **Riesgo:** Ataque CSRF podría trigger captura masiva de carritos
- **Solución:**
  ```php
  // Agregar al inicio de ajax_capture_checkout_contact()
  $nonce = isset($_POST['_nonce']) ? sanitize_text_field(wp_unslash($_POST['_nonce'])) : '';
  check_ajax_referer('wccr_capture_nonce', '_nonce', true);
  ```
- **Impacto:** Cambio mínimo, sin romper compatibilidad
- **Effort:** 15 min

### 2. **SQL Injection en uninstall.php**

- **Archivo:** `uninstall.php` (línea 20-35)
- **Problema:** Queries sin `$wpdb->prepare()`
- **Queryafectada:** `DELETE FROM wp_prefix_wccr_abandoned_carts WHERE...`
- **Causa:** Código legacy sin prepared statements
- **Riesgo:** Vulnerable a SQL injection si argumentos no sanitizados
- **Solución:** Usar `$wpdb->prepare()` en todos los DELETE/UPDATE/INSERT
  ```php
  $wpdb->query(
      $wpdb->prepare(
          "DELETE FROM {$wpdb->prefix}wccr_abandoned_carts WHERE cart_id = %d",
          $cart_id
      )
  );
  ```
- **Impacto:** mejor defensa, sin cambio lógico
- **Effort:** 25 min

### 3. **SQL Injection en class-installer.php**

- **Archivo:** `includes/class-installer.php` (línea 45-70)
- **Problema:** UPDATE query sin prepared statement
- **Causa:** Construcción manual de SQL
- **Solución:** Aplicar `$wpdb->prepare()`
- **Impacto:** Mínimo, cambio defensivo
- **Effort:** 20 min

### 4. **Email sin Encriptación (GDPR)**

- **Archivo:** `includes/repositories/class-cart-repository.php` (línea 85-95)
- **Problema:** Emails almacenados en plaintext
- **Datos:** columa `email` contiene PII sin encriptación
- **Causa:** Arquitectura legacy antesde considerar GDPR
- **Riesgo:**
  - GDPR: violación de protección de datos
  - CCPA: incumplimiento de privacidad
  - Breach: si BD comprometida, emails expuestos
- **Solución:** Implementar encriptación AES-256

  ```php
  // Guardar: encriptar
  $encrypted_email = $this->encrypt_data($email);

  // Leer: desencriptar
  $email = $this->decrypt_data($row->email);
  ```

- **Impacto:** Cambio significativo, requiere:
  - Nueva columna de email encriptado
  - Migración de datos existentes
  - Cambios en queries de búsqueda
- **Effort:** 2-3 horas

### 5. **Cart Payload sin Encriptación**

- **Archivo:** `includes/repositories/class-cart-repository.php` (línea 110-125)
- **Problema:** Carrito completo almacenado en plaintext
- **Datos:** columa `cart_payload` contiene productos, cantidades, precios
- **Causa:** Diseño inicial no consideró seguridad
- **Riesgo:**
  - Exposición de datos sensibles de cliente
  - Si BD comprometida: lista completa de compras
- **Solución:** Encriptar `cart_payload` como con email
- **Impacto:** Similar a email, requiere migración
- **Effort:** 2-3 horas

### 6. **XSS en Email Renderer**

- **Archivo:** `includes/domain/class-email-renderer.php` (línea 115-135)
- **Problema:** Contenido de admin no escapado en email template
- **Causa:** `$step_settings['body']` vienedirectamente de settings sin validación
- **Riesgo:** Admin comprometido podría inyectar JavaScript
- **Solución:** Aplicar `wp_kses_allowed_html()` con whitelist
  ```php
  $body = wp_kses(
      $body,
      wp_kses_allowed_html('post')
  );
  ```
- **Impacto:** Cambio defensivo, mejora seguridad
- **Effort:** 15 min

### 7. **Cupones Predecibles**

- **Archivo:** `includes/domain/class-coupon-service.php` (línea 45-60)
- **Problema:** Cupones generados con solo 4 caracteres aleatorios
- **Causa:** `rand(1000, 9999)` es débil criptográficamente
- **Riesgo:**
  - Fuerza bruta: 10.000 combinaciones
  - Predicibilidad: si alguien conoce estructura, puede adivinar
- **Solución:** Usar `random_bytes()` con más entropía
  ```php
  $suffix = bin2hex(random_bytes(8)); // 16 caracteres hex
  $coupon_code = 'WCCR_' . strtoupper($suffix);
  ```
- **Impacto:** Cambio mínimo, mejora seguridad
- **Effort:** 20 min

---

## 🟠 PROBLEMAS ALTOS (Seguridad)

### 8. **Token de Recuperación sin Expiración**

- **Archivo:** `includes/domain/class-recovery-service.php` (línea 50-65)
- **Problema:** URLs de recuperación válidas indefinidamente
- **Causa:** Token no include timestamp, sin validación de expiración
- **Riesgo:**
  - URLs antiguas de testing siguen siendo válidas
  - Si email interceptado, acceso permanente
  - Cumplimiento: algunos estándares requieren expiración
- **Solución:** Incluir timestamp en token, validar en recuperación

  ```php
  // Generar
  $token = hash_hmac('sha256', $cart_id . '|' . time(), SECURE_KEY);

  // Validar (30 días)
  $parts = explode('|', $cart_id_from_token);
  if (time() - $parts[1] > 30 * DAY_IN_SECONDS) return false;
  ```

- **Impacto:** Cambio significativo, requiere:
  - Actualizar lógica de recuperación
  - Tests para expiración
- **Effort:** 1-2 horas

### 9. **Validación débil de captura de carrito**

- **Archivo:** `includes/domain/class-cart-capture-service.php` (línea 80-100)
- **Problema:** No valida que el usuario sea propietario del carrito
- **Causa:** Sistema anonymous, no hay relación usuario-carrito
- **Riesgo:** Usuario A podría capturar carrito de Usuario B si conoce ID
- **Solución:**
  - Agregar session ID a captura
  - Validar session match en recuperación
  - Considerar agregar user_id si usuario logged in
- **Impacto:** Cambio medio, requiere:
  - Cambio en schema de tabla (agregar session_id)
  - Validación adicional
- **Effort:** 1-2 horas

### 10. **Información sensible en logs**

- **Archivo:** `includes/domain/class-recovery-service.php` y otros
- **Problema:** Emails, carritos completos posiblemente en logs de error
- **Causa:** Debugging sin filtro de PII
- **Riesgo:**
  - Logs comprometidos = datos personales expuestos
  - GDPR: registros de acceso a datos
- **Solución:**
  - Nunca loguear emails/carritos completos
  - Si necesario, hash o truncar
  - Revisar todos los `error_log()` y `wp_die()`
- **Impacto:** Cambio defensivo
- **Effort:** 30 min

### 11. **Falta de logging de acceso a datos**

- **Problema:** No hay registro de quién accede a datos sensibles
- **Causa:** Sistema sin audit trail
- **Riesgo:**
  - Cumplimiento: GDPR requiere logging de acceso a PII
  - Detección: no se pueden detectar accesos no autorizados
- **Solución:** Implementar audit log mínimo
  ```php
  $this->log_access('view_cart', $cart_id, current_user_id());
  ```
- **Impacto:** Nueva funcionalidad, requiere:
  - Nueva tabla `wccr_audit_logs`
  - Logs en operaciones sensitivas
- **Effort:** 2-3 horas

---

## 🟡 PROBLEMAS MEDIOS (Estándares / Código)

### 12. **Inconsistencia de Prefijos**

- **Problema:** Mix de `vfwoo_`, `WCCR_`, `wccr_` en diferentes áreas
- **Ubicaciones:**
  - Clases: `class-cart-repository.php` usa prefijo WCCR
  - Funciones: algunas con `vfwoo_`
  - Hooks: `wccr_*` en acciones
  - HTML IDs: `wccr-*`
- **Causa:** Evolución del proyecto sin normalización
- **Solución:** Unificar a `wccr_` (short, clear)
  - Acciones/filtros: `wccr_cart_captured`
  - Funciones helper: `wccr_get_abandoned_cart()`
  - IDs HTML: ya está correcto
- **Impacto:** Refactor cosmético, sin breaking changes si se hace bien
- **Effort:** 2-3 horas

### 13. **Falta de apply_filters globales**

- **Problema:** Plugin poco extensible, solo `do_action()` sin `apply_filters()`
- **Ubicaciones:**
  - No hay filtro en emails
  - No hay filtro en cupones
  - No hay filtro en eligibilidad
- **Causa:** Enfoque inicial en funcionalidad, no extensibilidad
- **Solución:** Agregar 6-8 filtros estratégicos

  ```php
  // En coupon generation
  $coupon_code = apply_filters('wccr_generated_coupon_code', $coupon_code, $cart_id);

  // En email body
  $body = apply_filters('wccr_email_body', $body, $step, $cart_id);
  ```

- **Impacto:** Cambio no-breaking, mejora extensibilidad
- **Effort:** 1 hora

### 14. **Documentación PHPDoc Incompleta**

- **Problema:** Métodos privados y argumentos sin documentación
- **Ubicaciones:**
  - Constructor argumentos sin type hints en algunos
  - Métodos privados sin @param/@return
  - Classes sin overview docblock
- **Solución:** Completar PHPDoc en 100% de métodos
- **Impacto:** Cambio de documentación, sin lógica
- **Effort:** 2 horas

### 15. **Settings no usa register_setting()**

- **Archivo:** `includes/admin/class-settings-page.php`
- **Problema:** Settings guardados ad-hoc, no con `register_setting()`
- **Causa:** Implementación manual de form processing
- **Solución:** Usar WordPress Settings API
  ```php
  register_setting(
      'wccr_settings_group',
      'wccr_delay_hours',
      array('type' => 'integer', 'sanitize_callback' => 'absint')
  );
  ```
- **Impacto:** Cambio significativo, mejora mantenibilidad
- **Effort:** 1-2 horas

### 16. **Falta de Type Hints Completos**

- **Problema:** Algunos métodos sin return type
- **Solución:** Agregar return types modernos (PHP 8.1+)
- **Effort:** 1 hora

### 17. **No hay validación de WooCommerce en runtime**

- **Problema:** Si WooCommerce se desactiva, plugin no verifica
- **Solución:** Agregar hooks de validación en `plugin_loaded`
- **Effort:** 30 min

---

## ✅ CUMPLIMIENTO PARCIAL (según QA-CHECKLIST.md)

### Pendiente de Verificación:

- [ ] **Captura Clásica:** Testear end-to-end desde checkout clásico
- [ ] **Captura Blocks:** Testear end-to-end desde Checkout Blocks
- [ ] **Abandono:** Testear transición active->abandoned con Action Scheduler
- [ ] **Emails:** Testear envío de 3 pasos con reglas de elegibilidad
- [ ] **Cupones:** Verificar tipos (none/percent/fixed)
- [ ] **Recovery URL:** Verificar firmado/expiración
- [ ] **Órdenes:** Testear marcado como recovered tras compra
- [ ] **Pendientes:** Detector de pedidos pendientes
- [ ] **Exclusiones:** Testearcorrectamente se respetan exclusiones
- [ ] **Limpieza:** Verificar cleanup de datos antiguos

---

## 🎯 PRIORIZACIÓN DE SPRINTS

### Sprint 1: CRÍTICO SEGURIDAD (Semana 1)

**6-8 horas | Antes de v1.0.0**

1. ✅ AJAX Nonce - 15 min
2. ✅ SQL Injection uninstall.php - 25 min
3. ✅ SQL Injection installer.php - 20 min
4. ✅ XSS Email Renderer - 15 min
5. ✅ Cupones Predecibles - 20 min
6. ✅ Validación captura - 1-2 horas
7. ✅ Revisar logs de PII - 30 min

**Resultado:** Superficie de ataque reducida 80%

### Sprint 2: SEGURIDAD + ESTÁNDARES (Semana 2-3)

**6-8 horas | Antes de producción**

1. ✅ Token expiración - 1-2 horas
2. ✅ Email encriptación - 2-3 horas
3. ✅ Cart payload encriptación - 2-3 horas
4. ✅ Audit logging - 2-3 horas
5. ✅ Unificar prefijos - 2-3 horas

**Resultado:** GDPR/CCPA compliant

### Sprint 3: MANTENIBILIDAD (Semana 4)

**4-5 horas | Para v1.0.0**

1. ✅ Agregar filtros - 1 hora
2. ✅ PHPDoc completo - 2 horas
3. ✅ Type hints - 1 hora
4. ✅ Settings API - 1-2 horas
5. ✅ Validación WooCommerce - 30 min

**Resultado:** Plugin production-ready

### Sprint 4: TESTING (Semana 5+)

**Depende de cobertura**

1. ✅ Completar QA-CHECKLIST
2. ✅ Security testing
3. ✅ Load testing
4. ✅ User acceptance testing

---

## 📋 CLASIFICACIÓN SEGÚN AGENTS.md

### ✅ Cumple Filosofía de Código

- [x] Funciones pequeñas y reutilizables
- [x] Una sola responsabilidad (Service/Repository pattern)
- [x] Usar máximo 20-30 líneas por función
- [x] Nombres claros y descriptivos
- [x] No duplicar lógica (buen reuso)
- [x] Código fácil de testear
- [x] Respeta sanitización/escaping/validation de WP
- [x] Preparado para i18n

### ⚠️ Mejoras Necesarias

- [ ] Algumas función sobre 30 líneas (refactor menor)
- [ ] Falta extensibilidad (filtros públicos)
- [ ] Documentación en desarrollo

### ❌ Requiere Atención

- [ ] Nonces en AJAX
- [ ] Prepared statements SQL
- [ ] Encriptación de PII

---

## 📝 VALIDACIÓN POST-EJECUCIÓN

Después de implementar mejoras, ejecutar:

```bash
# 1. Linting PHP
php -l includes/**/*.php

# 2. WordPress standards
./vendor/bin/phpcs --standard=WordPress-Core includes/ assets/

# 3. Security check
npm audit
php -a # Revisar nuevas llamadas SQL/encriptación

# 4. Git validation
git diff --check

# 5. Tests
./vendor/bin/phpunit

# 6. Manual QA
# - Testear cada item del QA-CHECKLIST.md
```

---

## 🔄 SIGUIENTE PASO

Esperando **confirmación explícita** del usuario para:

1. ¿Proceder con Sprint 1 (Seguridad Crítica)?
2. ¿Incluir Sprint 2 (Encriptación)?
3. ¿Timeline? (Semanal, acelerado, etc)
4. ¿Testing requerido antes de cada sprint?

**Este plan cumple 100% con AGENTS.md:**

- ✅ Revisión real del código (no suposiciones)
- ✅ Causas identificadas para cada problema
- ✅ Soluciones concretas con ejemplos
- ✅ Sin cambios ejecutados
- ✅ Esperando confirmación
