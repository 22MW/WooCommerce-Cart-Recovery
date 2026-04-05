# Reglas De Desarrollo

## Alcance
- Ejecutar solo el bloque aprobado.
- No hacer refactors ocultos, mejoras extra ni cambios fuera de alcance.
- Si aparece algo inesperado: parar y preguntar.
- Antes de ejecutar cambios, revisar los archivos necesarios y preparar un plan real para aprobacion.
- No ejecutar cambios hasta recibir conformacion explicita del usuario.
- Para preparar un plan no hay que preguntar primero: hay que revisar los archivos, funciones, hooks, queries, settings y flujo real que hagan falta.
- El plan debe salir de una revision real del codigo, no de suposiciones.
- El plan debe incluir posibles causas y posibles soluciones concretas.
- Tras entregar el plan, esperar preguntas o comando explicito para ejecutar.

## Filosofia De Codigo
- Funciones pequenas y reutilizables.
- Una sola responsabilidad por funcion.
- Objetivo de maximo 20-30 lineas por funcion.
- Nombres claros y descriptivos.
- No duplicar logica si el plugin ya tiene una funcion o bloque que resuelve lo mismo.
- Evitar efectos secundarios cuando sea posible.
- Mantener el codigo facil de testear y reutilizar.

## Reglas De Cambio
- Preguntar antes de cambios mayores.
- Reutilizar codigo existente siempre que sea posible, especialmente CSS.
- No usar iconos dentro de textos de interfaz.
- Cambios minimos: un problema, una solucion.
- Si aparece una mejora adicional, comentarla antes de aplicarla.

## Documentacion
- Anadir documentacion inline cuando aporte valor.
- Usar PHPDoc en PHP.
- Usar JSDoc en JavaScript.

## Validacion
- Ejecutar `php -l` si esta disponible.
- Ejecutar `git diff --check`.
- Revisar entradas relevantes de `error.log` cuando aplique.

## Versionado
- Subir la version del plugin solo cuando el usuario lo pida de forma explicita.
- No cambiar la version automaticamente.
- Cuando el usuario lo pida, incrementar el ultimo numero de version del plugin salvo que indique otra estrategia.
- Cuando haya versionado solicitado, escribir los cambios en `CHANGELOG.md` de forma corta, precisa y concreta.

## Reporte Final
- El reporte final debe ser corto.
- Incluir: HECHO/NO HECHO, archivos tocados, validacion, in-scope/out-of-scope y siguiente paso opcional.
