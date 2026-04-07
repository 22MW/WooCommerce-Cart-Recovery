# QA Checklist

Checklist de validacion para `WooCommerce Cart Recovery` antes de release, push a `main` o envio a revision.

## 1. Requisitos WP y WooCommerce

- [ ] WordPress activa el plugin sin fatals.
- [ ] WooCommerce activo: el plugin arranca correctamente.
- [ ] WooCommerce desactivado: el plugin muestra requisito y no rompe admin/front.
- [ ] PHP minimo real compatible con `Requires PHP`.
- [ ] Version del plugin alineada en:
  - [woocommerce-cart-recovery.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/woocommerce-cart-recovery.php)
  - [CHANGELOG.md](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/CHANGELOG.md)
  - [readme.txt](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/readme.txt)

## 2. Seguridad

- [ ] Todas las acciones de admin usan `current_user_can()`.
- [ ] Formularios sensibles usan `nonce`.
- [ ] Acciones de borrado usan `nonce` y capability check.
- [ ] Entradas `$_GET`, `$_POST` y AJAX se sanitizan.
- [ ] Salidas HTML usan escaping adecuado.
- [ ] No hay SQL sin preparar.
- [ ] Acceso directo bloqueado con `ABSPATH`.
- [ ] No hay open redirects.
- [ ] No hay HTML del usuario renderizado sin control.
- [ ] No hay codigo remoto ejecutable.
- [ ] No hay tracking externo sin consentimiento.

## 3. Captura de carrito

- [ ] Captura desde carrito/checkout clasico con email valido.
- [ ] Captura desde Checkout Blocks / Store API.
- [ ] No crea filas si falta email util.
- [ ] Reutiliza correctamente el intento activo por sesion cuando toca.
- [ ] No duplica casos de forma inesperada en un mismo flujo.
- [ ] Si el carrito contiene una exclusion, no se captura.

Archivos clave:
- [class-cart-capture-service.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/domain/class-cart-capture-service.php)
- [class-cart-repository.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/repositories/class-cart-repository.php)
- [class-checkout-capture-coordinator.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/checkout/class-checkout-capture-coordinator.php)
- [blocks-checkout-capture.js](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/assets/js/blocks-checkout-capture.js)

## 4. Deteccion de abandono

- [ ] `active -> abandoned` ocurre tras el delay configurado.
- [ ] No pasa a abandonado si sigue habiendo actividad real.
- [ ] El flujo funciona con Action Scheduler.
- [ ] No se crean jobs duplicados.

Archivos clave:
- [class-abandoned-cart-detector.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/domain/class-abandoned-cart-detector.php)
- [class-action-scheduler.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/class-action-scheduler.php)
- [class-plugin.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/class-plugin.php)

## 5. Emails de recovery

- [ ] Email 1 se envia cuando corresponde.
- [ ] Email 2 se envia solo si cumple sus reglas.
- [ ] Email 3 se envia solo si cumple sus reglas.
- [ ] Un step desactivado no se envia.
- [ ] Un step con `min_cart_total` no se envia por debajo del minimo.
- [ ] Si no hay cupon real, el email no muestra descuento/codigo.
- [ ] El renderer no falla con snapshots serializados.
- [ ] El email usa wrapper WooCommerce sin depender de objetos de carrito vivos.

Archivos clave:
- [class-email-scheduler.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/domain/class-email-scheduler.php)
- [class-email-eligibility-service.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/domain/class-email-eligibility-service.php)
- [class-email-renderer.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/domain/class-email-renderer.php)
- [base-email.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/templates/emails/base-email.php)

## 6. Cupones

- [ ] Cupones nativos se crean solo cuando el step lo requiere.
- [ ] Tipo de descuento correcto:
  - [ ] none
  - [ ] percent
  - [ ] fixed_cart
- [ ] Expiracion correcta.
- [ ] Minimo de carrito respetado.
- [ ] No se muestran placeholders de descuento sin cupon real.

Archivo clave:
- [class-coupon-service.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/domain/class-coupon-service.php)

## 7. Recovery URL y compra recuperada

- [ ] El enlace firmado restaura el carrito.
- [ ] Aplica cupon cuando existe.
- [ ] Registra click del email correcto.
- [ ] Marca pedido recuperado cuando se completa correctamente.
- [ ] Atribuye `resolved` al step correcto.

Archivos clave:
- [class-recovery-service.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/domain/class-recovery-service.php)
- [class-email-log-repository.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/repositories/class-email-log-repository.php)

## 8. Pedidos impagados

- [ ] Importa `pending` y `failed` elegibles.
- [ ] No reimporta pedidos ya tratados.
- [ ] Hace merge cuando corresponde.
- [ ] No importa si contiene exclusiones.

Archivo clave:
- [class-pending-order-detector.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/domain/class-pending-order-detector.php)

## 9. Exclusiones

- [ ] Exclusion por producto funciona.
- [ ] Exclusion por termino taxonomico funciona.
- [ ] El carrito entero queda fuera si contiene una exclusion.
- [ ] La importacion de pedidos impagados tambien respeta exclusiones.
- [ ] Con WPML/Polylang, al excluir un item se expanden sus traducciones.
- [ ] El autocomplete de exclusiones busca y guarda correctamente.

Archivos clave:
- [class-exclusion-service.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/domain/class-exclusion-service.php)
- [class-exclusion-translation-service.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/domain/class-exclusion-translation-service.php)
- [class-settings-page.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/admin/class-settings-page.php)
- [admin.js](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/assets/js/admin.js)

## 10. Multidioma

- [ ] Detecta locale real del carrito.
- [ ] Detecta locale real del pedido importado.
- [ ] Tabs por idioma salen correctamente.
- [ ] Los defaults traducidos se resuelven bien.
- [ ] `Reset to translated defaults` funciona por `step + locale`.
- [ ] Envio real en:
  - [ ] es_ES
  - [ ] en_US
  - [ ] de_DE
  - [ ] ca_ES si aplica
- [ ] Fallback correcto si falta traduccion.

Archivos clave:
- [class-locale-resolver-manager.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/locale/class-locale-resolver-manager.php)
- [class-wpml-locale-resolver.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/locale/class-wpml-locale-resolver.php)
- [class-plugin-locale-switcher.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/locale/class-plugin-locale-switcher.php)
- [class-settings-repository.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/repositories/class-settings-repository.php)

## 11. Admin UI

- [ ] Header sticky correcto.
- [ ] Notices no quedan tapados.
- [ ] Layout responsive en `Carts`.
- [ ] Layout responsive en `Settings`.
- [ ] Toolbar funcional.
- [ ] Stats correctas visualmente y en datos.
- [ ] `View email details` abre/cierra correctamente.
- [ ] `Delete` borra correctamente.
- [ ] `Copy URL` copia correctamente.

Archivos clave:
- [class-admin-menu.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/admin/class-admin-menu.php)
- [class-abandoned-carts-page.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/admin/class-abandoned-carts-page.php)
- [admin.css](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/assets/css/admin.css)
- [admin.js](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/assets/js/admin.js)

## 12. Estadisticas y logs

- [ ] Carritos abandonados correctos.
- [ ] Clicks correctos.
- [ ] Carritos recuperados correctos.
- [ ] Ingresos recuperados correctos.
- [ ] Emails enviados correctos.
- [ ] Logs por step consistentes.
- [ ] `Last error` muestra algo util cuando hay fallo.

Archivos clave:
- [class-stats-service.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/domain/class-stats-service.php)
- [class-stats-repository.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/repositories/class-stats-repository.php)
- [class-email-log-repository.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/repositories/class-email-log-repository.php)

## 13. Activacion, actualizacion y uninstall

- [ ] Activacion crea tablas/opciones necesarias.
- [ ] Actualizacion desde version anterior no rompe datos.
- [ ] Uninstall limpia solo lo que debe limpiar.
- [ ] No borra datos ajenos a este plugin.

Archivos clave:
- [class-installer.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/includes/class-installer.php)
- [uninstall.php](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/uninstall.php)

## 14. I18n y catalogos

- [ ] Todos los strings pasan por el text domain correcto.
- [ ] `pot` actualizado.
- [ ] `po/mo` actualizados.
- [ ] `readme.txt` alineado con la version actual.
- [ ] `README.md` alineado con funcionalidades reales.

Archivos clave:
- [languages](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/languages)
- [README.md](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/README.md)
- [readme.txt](/Users/22mw/Local%20Sites/bodegas-local/app/public/wp-content/plugins/woocommerce-cart-recovery/readme.txt)

## 15. Checks tecnicos finales

- [ ] `php -l` en archivos tocados.
- [ ] `git diff --check`
- [ ] `WP_DEBUG` sin fatals/notices relevantes del plugin.
- [ ] Probar en instalacion limpia.
- [ ] Probar upgrade desde version previa.
- [ ] Revisar `error.log` si hubo cualquier fallo.

## 16. Checklist rapido de release

- [ ] Version subida.
- [ ] Changelog corto y preciso.
- [ ] Readmes al dia.
- [ ] Traducciones regeneradas si hubo strings nuevos.
- [ ] Commit limpio.
- [ ] Push a `origin/dev`.
- [ ] Si procede, merge/fast-forward a `origin/main`.
