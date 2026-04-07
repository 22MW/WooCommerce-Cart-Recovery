# Reglas De Desarrollo

## Alcance

- Pensar antes de actuar.
- Leer los archivos existentes necesarios antes de escribir codigo.
- Ejecutar solo el bloque aprobado.
- No hacer refactors ocultos, mejoras extra ni cambios fuera de alcance.
- Si aparece algo inesperado: parar y preguntar.
- Antes de ejecutar cambios, revisar los archivos necesarios y preparar un plan real para aprobacion.
- No ejecutar cambios hasta recibir conformacion explicita del usuario.
- Para preparar un plan no hay que preguntar primero: hay que revisar los archivos, funciones, hooks, queries, settings y flujo real que hagan falta.
- El plan debe salir de una revision real del codigo, no de suposiciones.
- El plan debe incluir posibles causas y posibles soluciones concretas.
- Tras entregar el plan, esperar preguntas o comando explicito para ejecutar.
- Las instrucciones del usuario siempre tienen prioridad sobre este archivo.

## Filosofia De Codigo

- Funciones pequenas y reutilizables.
- Una sola responsabilidad por funcion.
- Objetivo de maximo 20-30 lineas por funcion.
- Nombres claros y descriptivos.
- No duplicar logica si el plugin ya tiene una funcion o bloque que resuelve lo mismo.
- Si un flujo nuevo sustituye de verdad al anterior, el codigo viejo debe simplificarse o eliminarse en el mismo bloque cuando ya no sea necesario.
- Evitar mantener modos hibridos durante demasiado tiempo si ya existe una fuente principal clara.
- No dejar caminos legacy activos solo por inercia.
- Evitar efectos secundarios cuando sea posible.
- Mantener el codigo facil de testear y reutilizar.
- Debe respetar buenas practicas de seguridad de WordPress: sanitizacion, escaping, validacion, nonces, capability checks y acceso directo bloqueado.
- Debe seguir WordPress Coding Standards en la medida de lo posible.
- Debe estar preparado para internacionalizacion con text domain correcto.
- No debe incluir codigo ofuscado, minimizado de forma no revisable ni dependencias inseguras.
- No debe cargar codigo remoto ejecutable ni hacer tracking sin consentimiento explicito.

## Reglas

- Preguntar antes de cambios mayores.
- Reutilizar codigo existente siempre que sea posible, especialmente CSS.
- Preferir editar antes que reescribir archivos enteros.
- No volver a leer archivos ya revisados salvo que puedan haber cambiado.
- No usar iconos dentro de textos de interfaz.
- Cambios minimos: un problema, una solucion.
- Mantener las soluciones simples y directas.
- Durante la ejecucion, no narrar cada paso ni gastar tokens en seguimiento detallado salvo que el usuario lo pida.
- Reservar la explicacion mas detallada para el plan o para casos donde haga falta justificar una decision.
- Si aparece una mejora adicional, comentarla antes de aplicarla.
- Si una mejora obliga a retirar codigo viejo o consolidar logica duplicada para cumplir estas reglas, indicarlo en el plan antes de ejecutar.

## Documentacion

- Anadir documentacion inline cuando aporte valor.
- Usar PHPDoc en PHP.
- Usar JSDoc en JavaScript.

## Validacion

- Probar el codigo antes de darlo por terminado.
- Ejecutar `php -l` si esta disponible.
- Ejecutar `git diff --check`.
- Revisar entradas relevantes de `error.log` cuando aplique.

## Versionado

- Cuando el usuario lo pida, incrementar el ultimo numero de version del plugin salvo que indique otra estrategia.
- Cuando haya versionado solicitado, escribir los cambios en `CHANGELOG.md` de forma corta, precisa y concreta.
- Actualizar `README.md` y `readme.txt` si alguna funcionalidad nueva lo requiere.
- Commit y push, todos los archivos modificados, la version del plugin solo cuando el usuario lo pida de forma explicita.
- El flujo completo de publicacion es:
  1. Bump version en header del plugin y en `define('WCCR_VERSION', ...)`
  2. Entrada en `CHANGELOG.md`
  3. Actualizar `README.md` / `readme.txt` si aplica
  4. `php -l` en archivos tocados + `git diff --check`
  5. Commit + push a `dev`
  6. Merge `dev` → `main` + push `main`
  7. Tag `vX.Y.Z` + push tag → GitHub Action crea el Release con el ZIP automaticamente

## Reporte Final

- El reporte final debe ser corto.
- Ser conciso en el output.
- Sin introducciones aduladoras ni relleno al final.
- Incluir: HECHO/NO HECHO, archivos tocados, validacion, in-scope/out-of-scope y siguiente paso opcional.
