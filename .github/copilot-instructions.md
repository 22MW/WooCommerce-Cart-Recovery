# Reglas De Desarrollo

## ⛔ Regla Absoluta — Sin Excepciones

Ante cualquier petición de cambio de código:

1. Leer los archivos relevantes.
2. Presentar un plan con: qué se cambia, dónde, por qué.
3. Esperar confirmación explícita del usuario ("ok", "ejecuta", "adelante").
4. Solo entonces escribir código.

Si el usuario no confirma → no se ejecuta nada. Sin excepciones.

## Principios

- Las instrucciones del usuario siempre tienen prioridad sobre este archivo.
- Pensar antes de actuar. Leer antes de escribir.
- Cambios mínimos: un problema, una solución.
- Preferir editar antes que reescribir archivos enteros.
- No volver a leer archivos ya leídos, salvo que puedan haber cambiado.
- No hacer refactors ocultos, mejoras extra ni cambios fuera de alcance.
- Si aparece algo inesperado: parar y preguntar.
- Si aparece una mejora adicional: comentarla, no aplicarla.
- Durante la ejecución, ser conciso. Reservar la explicación detallada para el plan.

## Calidad De Código

- Funciones pequeñas, una sola responsabilidad, máximo 20-30 líneas.
- Nombres claros y descriptivos.
- No duplicar lógica existente en el plugin.
- Reutilizar código existente, especialmente CSS.
- No usar iconos dentro de textos de interfaz.
- Seguridad WordPress: sanitización, escaping, validación, nonces, capability checks, acceso directo bloqueado.
- WordPress Coding Standards en la medida de lo posible.
- Preparado para internacionalización con text domain correcto.
- Sin código ofuscado, dependencias inseguras ni tracking sin consentimiento.

## Documentación

- Documentación inline cuando aporte valor. PHPDoc en PHP. JSDoc en JS.

## Validación

- `php -l` en archivos tocados.
- `git diff --check`.
- Revisar `error.log` cuando aplique.

## Versionado

- Incrementar el último número de versión salvo que el usuario indique otra estrategia.
- Cambios en `CHANGELOG.md`: cortos, precisos, en inglés.
- Actualizar `README.md` y `readme.txt` solo si hay funcionalidad nueva que lo requiera.
- Commit y push solo cuando el usuario lo pida de forma explícita.
- Flujo completo de publicación:
  1. Bump version en header del plugin y en `define('WCCR_VERSION', ...)`
  2. Entrada en `CHANGELOG.md`
  3. Actualizar `README.md` / `readme.txt` si aplica
  4. `php -l` + `git diff --check`
  5. Commit + push a `dev`
  6. Merge `dev` → `main` + push `main`
  7. Tag `vX.Y.Z` + push tag → GitHub Action crea el Release con el ZIP

## Reporte Final

- Corto y conciso. Sin introducciones ni relleno.
- Incluir: HECHO/NO HECHO, archivos tocados, validación, in-scope/out-of-scope, siguiente paso opcional.
