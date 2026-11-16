# Benchmark planificado — historial verificable de progreso (Spec 015, RNF-015.4)

**Estado: definido, NO ejecutado.** No se reportan resultados de rendimiento.

## Objetivo

Verificar el criterio padre RNF-5 aplicado por RNF-015.4: **p95 ≤ 2 s**, medido desde
que el servidor recibe la petición hasta que completa la respuesta de la API, bajo
**50 consultas interactivas simultáneas** de historial, para:

- `GET /api/v1/progress/history` (propio): primera página sin cursor y páginas siguientes con `cursor`.
- `GET /api/v1/mentor/users/{user}/progress/history` (mentor asignado con `mentor_workspace`).
- `GET /api/v1/admin/progress/history?user_id=` (soporte con `progress.view.support`).
- `GET /api/v1/progress/panel` (control de regresión: comparte las tablas de origen).

## Qué carga cada petición (verificado en tests, no medido)

- `ProgressHistoryService::page()` hace **20 consultas por página**, un número fijo que no depende del volumen (`ProgressHistoryServiceTest`). Cada uno de los 8 adapters lee como máximo `limit + 1` filas, y el merge en memoria se limita a `8 × (limit + 1)` eventos (plan §4).
- La primera página añade la consulta de los eventos sin fecha. Hoy ningún adapter tiene eventos sin fecha, así que esa consulta no lanza queries.
- Índices de T015.6, verificados con `EXPLAIN` (`ProgressHistoryIndexesTest`):
  - `activity_log_module_completion_user_idx` y `activity_log_progress_subject_idx`, parciales de expresión;
  - `challenges_mentee_id_index`;
  - `challenge_submissions_mentee_challenge_index`.
  - Los objetivos y los retos usan además el índice `subject` que ya tenía `activity_log`.

## Herramienta

**k6** (`docker run grafana/k6` o binario local), igual que en `specs/012-progreso-reto-modulo/benchmark.md` y `specs/013-mapa-agregados-aprendizaje/benchmark.md`. Da percentiles por endpoint en una sola corrida usando tags. El script se creará en `specs/015-historial-progreso/k6/progress-history.js` al ejecutar el benchmark.

## Dataset de referencia (en un entorno dedicado, NO en el dev compartido)

El historial se ordena y pagina sobre la unión de 8 fuentes. El dataset debe dar volumen a todas, y sobre todo a `activity_log`, que es compartida por todos los usuarios y donde importa la selectividad de los índices:

- 50 sujetos mentee. Cada uno tiene:
  - 20 objetivos individuales con 3 cambios de estado cada uno y 2 evidencias por objetivo;
  - 10 retos con 3 transiciones, 1 cambio de vínculo con un módulo y 1 entrega revisada cada uno;
  - 2 rutas iniciadas y 1 completada;
  - 6 vínculos de competencia con 1 cambio cada uno;
  - 4 completitudes de módulo, de las que 1 está revocada;
  - 3 eventos mínimos de eliminación vigentes y 1 vencido sin purgar.

  En total son unos 300 eventos por sujeto, lo que da unas 12 páginas de 25.
- **Ruido en `activity_log`:** ≥ 200 000 filas de otros 2 000 usuarios con los mismos `log_name` y `event`. Sin ese ruido no se puede validar que los índices parciales filtren por sujeto.
- Varios eventos por sujeto con el mismo instante exacto, para recorrer el desempate por id con el cursor.
- 1 mentor con `MENTOR_WORKSPACE` y 5 mentees asignados.
- 1 admin con `progress.view.support`.
- Para generar los datos: un script artisan de un solo uso o factories por lotes dentro de `DB::transaction`, usando las Actions reales cuando escriban `activity_log`. Ejecutar `ANALYZE` al terminar, para que el planner tenga estadísticas.

## Escenario k6 (borrador)

```text
stages: 30 s de subida a 50 VUs → 2 min sostenido → 30 s de bajada
por VU (cookie Sanctum de un sujeto distinto):
  GET /progress/history?limit=25                     (tag: history_first)
  sigue next_cursor 2 veces más                      (tag: history_next)
  1 de cada 5 iteraciones: GET /progress/panel        (tag: panel)
1 VU con cookie de mentor: GET /mentor/users/{mentee}/progress/history, rotando entre mentees (tag: history_mentor)
1 VU con cookie de soporte: GET /admin/progress/history?user_id={sujeto}, rotando (tag: history_support)
umbrales: http_req_duration{tag} p(95) < 2000 para cada tag; http_req_failed < 1 %
comprobaciones: status 200; claves exactas data/undated/next_cursor; ningún id repetido entre páginas del mismo VU
```

La medición corresponde a la latencia de la API. El cliente corre en la misma red local de Compose para reducir el ruido de red. Cada VU usa un sujeto distinto para no medir solo la caché de páginas de Postgres de un único sujeto.

## Salida esperada

Al ejecutarlo, se añade a este documento una tabla por tag con p50, p95, p99 y tasa de error.

Si algún tag supera p95 de 2 s, antes de optimizar se adjunta un `EXPLAIN (ANALYZE, BUFFERS)` de la consulta de cada adapter sobre el dataset de referencia. Hay que vigilar en especial:

- el `COALESCE(occurred_at, created_at)` de los adapters de `activity_log`: el orden se aplica después de filtrar por sujeto;
- las uniones UNION ALL de entregas y rutas.

Primero se decide con esos datos y solo después se considera añadir caché.
