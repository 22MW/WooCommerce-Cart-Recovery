# WooCommerce Cart Recovery

WooCommerce Cart Recovery ayuda a recuperar carritos abandonados y pedidos impagados con un flujo propio integrado con WooCommerce.

## Qué hace el plugin

- Captura carritos cuando existe un email válido.
- Soporta checkout clásico y WooCommerce Checkout Blocks.
- Detecta carritos abandonados según el tiempo configurado.
- Importa y reutiliza pedidos `pending` y `failed` de WooCommerce.
- Envía hasta 3 emails de recuperación con delays independientes.
- Genera cupones nativos de WooCommerce por email.
- Crea enlaces firmados de recuperación.
- Restaura el carrito al hacer click en el email.
- Registra en qué email hicieron click.
- Marca como recuperado cuando el pedido termina correctamente.
- Mantiene estadísticas de:
  - carritos abandonados
  - clicks de recuperación
  - carritos recuperados
  - ingresos recuperados
  - emails enviados
- Limpia datos antiguos del plugin y pedidos impagados gestionados por el propio flujo.

## Cómo captura correctamente

El plugin no guarda carritos “a ciegas”. Solo crea un caso cuando hay contexto suficiente.

### Carrito

- Usa la sesión de WooCommerce como identidad principal del carrito vivo.
- Mientras el carrito siga siendo un intento activo y limpio, actualiza la misma fila.
- Si ese intento ya tuvo abandono, click o recovery, un nuevo intento crea un caso nuevo.
- No reutiliza una fila vieja solo por coincidir el email.

### Checkout clásico

Captura desde hooks nativos de WooCommerce:

- `woocommerce_cart_updated`
- `woocommerce_checkout_update_order_review`

Con esto el plugin puede guardar email, nombre, contenido y total cuando el cliente ya interactúa con el checkout o el carrito.

### Checkout Blocks

Captura desde hooks y eventos del Store API:

- `woocommerce_store_api_cart_update_customer_from_request`
- `woocommerce_store_api_checkout_update_order_from_request`
- AJAX propio para capturar email/nombre cuanto antes en Blocks

Además enlaza pedidos creados desde Blocks usando:

- `woocommerce_store_api_checkout_order_processed`

## Cómo detecta el abandono

- Primero el caso queda como `active`.
- Cuando supera el tiempo configurado sin actividad, pasa a `abandoned`.
- La cola de emails solo trabaja sobre casos `abandoned`.

## Cómo gestiona pedidos impagados

El plugin puede trabajar también sobre pedidos reales de WooCommerce:

- detecta `pending`
- detecta `failed`
- puede importarlos manualmente desde ajustes
- si hay coincidencia clara con un carrito capturado, hace merge
- cuando existe pedido, el caso pasa a apoyarse en ese pedido como fuente principal

## Cómo funciona la recuperación

- Cada email puede llevar su propio cupón y enlace.
- El enlace va firmado con token.
- Al abrirlo:
  - restaura el carrito
  - aplica el cupón si existe
  - registra el step clicado
  - redirige a checkout

Mientras ese recovery está activo:

- el plugin no debe crear un carrito abandonado nuevo del mismo checkout restaurado

## Cómo marca una compra recuperada

El plugin marca `recovered` cuando puede enlazar el pedido al caso de recovery y el pedido pasa por estados válidos de compra:

- `on-hold`
- `processing`
- `completed`
- `payment_complete`

También soporta:

- `woocommerce_checkout_order_processed`
- `woocommerce_store_api_checkout_order_processed`

para enlazar correctamente pedidos de checkout clásico y Blocks.

## Qué guarda en cada caso

Cada caso de recovery tiene su propia identidad y puede guardar:

- email
- nombre del cliente
- sesión
- locale
- snapshot del carrito
- total y moneda
- origen del caso (`cart` o `order`)
- pedido enlazado si existe
- step clicado
- pedido recuperado final

## Seguridad y buenas prácticas

El plugin sigue un enfoque WordPress/WooCommerce:

- nonces en acciones de admin
- capability checks
- sanitización y escaping
- acceso directo bloqueado
- cupones nativos de WooCommerce
- hooks nativos de WooCommerce y Store API
- text domain listo para traducción

## Qué no hace

- no usa un sistema nativo de “abandoned carts” de WooCommerce, porque WooCommerce no trae un módulo completo de recovery
- no hace tracking remoto
- no carga código remoto ejecutable

## Pantallas de admin

- `Cart Recovery`
  - estadísticas
  - fichas de recovery
  - estado, origen, pedido enlazado y detalle por email

- `Settings`
  - tiempos
  - limpieza
  - steps de email
  - descuentos
  - importación de pedidos impagados
  - ejecución manual del detector y la cola
