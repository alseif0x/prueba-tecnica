# Decisiones

Responde en una o dos líneas cada punto.

## 1. Estados que no están en la documentación

En la respuesta real aparecen `IN_PROGRESS_RESERVED` y `NOT_FOUND`, que el PDF no menciona. El panel solo tiene
*pendiente*, *en curso* y *hecha*. ¿Qué haces con cada uno? ¿Y si mañana aparece otro estado nuevo?

> `IN_PROGRESS_*` (`RESERVED`, `ARRIVED`…) → *en curso*: son subestados de `IN_PROGRESS`. `NOT_FOUND` (la bici no estaba)
> no lo meto en *hecha* ni en *pendiente* porque cualquiera de las dos engaña al operador: sale en "todas" con el estado
> original de Ruteo, no aparece en ningún filtro y se registra un aviso; hay que preguntar a Ruteo y a negocio qué es.
> Cualquier estado nuevo recibe el mismo trato (sin clasificar + aviso en el log), nunca un `default` silencioso a *pendiente*.

## 2. Tareas repetidas

La tarea 88213 aparece dos veces con datos distintos. ¿Con cuál te quedas?

> Con la de `updated` más reciente (la 88213 en `DONE` de las 09:14, no la `IN_PROGRESS_ARRIVED` de las 08:50): son
> dos fotos de la misma tarea. Si empatan en fecha, gana la última que llega. Lo confirmaría con Ruteo.

## 3. Ruteo lento o caído

La respuesta tarda casi 5 segundos. ¿Desde dónde llamarías a Ruteo y qué ve el operador si Ruteo no responde?
No hace falta programarlo.

> Nunca desde la petición del operador: un job programado (cada 1-2 min, con timeout y reintentos con espera) sincroniza
> Ruteo con una tabla/caché nuestra y el panel lee siempre de ahí, rápido y filtrando en local. Si Ruteo no responde,
> el operador sigue viendo los últimos datos con un aviso "Datos de hace X min: Ruteo no responde"; nunca una página en blanco.

## 4. Antes de producción

¿Qué cambiarías del código antes de subirlo a producción?

> Ya cambiado: auth por `api_key`, formato real, fechas en Madrid, filtro de estado y de fechas en local, sin nombres de
> técnicos, sin reintentar 4xx y sin escribir la clave en los logs. Pendiente: confirmar con Ruteo la paginación, si
> respetan `from`, qué es `NOT_FOUND` y pedir auth por cabecera (la clave en la URL acaba en logs de proxies); timeout
> en el cliente HTTP, logger PSR-3 en vez de `error_log`, clave desde secretos/entorno y métricas de errores de Ruteo.
