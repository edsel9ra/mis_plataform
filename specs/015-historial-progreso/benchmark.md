# Benchmark — historial verificable de progreso (Spec 015, RNF-015.4)

**Estado: ejecutado el 2026-11-17 en el portátil de desarrollo. NO cumple p95 ≤ 2 s en ese host**: p95 ≈ 3,4 s en todos los endpoints, con 0 errores. Ver [Resultados](#resultados-2026-11-17). Las consultas SQL no son el cuello de botella.

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

**k6** (`docker run grafana/k6` o binario local), igual que en `specs/012-progreso-reto-modulo/benchmark.md` y `specs/013-mapa-agregados-aprendizaje/benchmark.md`. Da percentiles por endpoint en una sola corrida usando tags. El script está en `specs/015-historial-progreso/k6/progress-history.js`, y los comandos de ejecución están en la cabecera de cada script de `k6/`.

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

## Resultados (2026-11-17)

### Entorno

- **Host:** portátil de desarrollo con WSL2 y Docker Desktop: 8 vCPU y 5,7 GiB de RAM. Durante las corridas seguía levantado el stack dev completo (Next dev, Reverb, cola, scheduler, Meilisearch). **No es un entorno dedicado**, así que las cifras son una cota pesimista y no sirven como medida de producción.
- **Aislamiento:**
  - BD propia `mis_project_bench`, en el mismo Postgres 16.4 del stack dev, migrada y con solo `RolePermissionSeeder` y `SkillTagSeeder`.
  - Redis en las DB 5, 6 y 7 con prefijo `mis_bench_`.
  - La BD dev `mis_project` no se tocó.
- **Backend:** imagen de producción sin modificar (`docker/php/Dockerfile.production`, construida como `mis-backend:bench`), con `APP_ENV=production` y `APP_DEBUG=false`.
  - Antes de medir se cachearon config, rutas y eventos (`php artisan optimize`). `view:cache` falla porque el backend no tiene vistas Blade.
  - OPcache activo (`opcache.enable=On`, JIT desactivado).
  - Pool FPM de serie: `pm=dynamic`, `pm.max_children=5`.
- **Proxy y cliente:** nginx 1.27 con un vhost mínimo de FastCGI. k6 0.57.0 corría en un contenedor de la misma red de Compose.
- **Scripts:**
  - `k6/seed-dataset.php`: dataset.
  - `k6/progress-history.js`: escenario.
  - `k6/explain-page.php`: `EXPLAIN (ANALYZE, BUFFERS)` de cada consulta de una página.

### Dataset generado (en 156 s)

- **Sujetos:** 50, con **205 eventos visibles cada uno**, es decir, 9 páginas de 25. Se recorrió el historial completo de 3 sujetos: 205 eventos, 205 únicos.
  - 80 de objetivos y 40 evidencias.
  - 40 de retos: 10 cambios de vínculo y 30 transiciones.
  - 20 de entregas (enviada y revisada).
  - 3 de rutas.
  - 7 de completitud de módulo. El plan preveía 4 concesiones y 1 revocación; salen 2 eventos más porque, con la regla real, un reto abandonado deja de contar y el módulo se vuelve a conceder y a revocar.
  - 12 de vínculos de competencia.
  - 3 de borrado vigentes. Hay además 1 vencido sin purgar, que no se muestra.
- **Cómo se generó:** con las Actions y los modelos auditados reales. Varios eventos comparten instante (cambio de vínculo, primera transición, entrega y concesión de módulo) para recorrer el desempate por id.
- **Ruido en `activity_log`:** 200 000 filas de 2 000 usuarios ajenos, con la misma forma de `log_name`, `event` y `properties` en los cuatro feeds. La mitad se insertó antes de los datos de los sujetos y la otra mitad después, y al final se ejecutó `ANALYZE`.
  - Totales: `objective` 54 000, `challenge` 52 000, `module_completion` 50 350 y `progress` 50 800.
  - Esos usuarios de ruido no existen en `users`, porque el historial solo filtra por id.
- **Mentores y soporte:** 10 mentores con 5 mentees cada uno; las peticiones de mentor usan el primero. 1 admin con `progress.view.support`.
- **Escenario:** el de arriba, sin tiempo de espera entre peticiones. Así siempre hay 50 consultas en curso, más las de los VUs de mentor y de soporte.

### Corrida 1 — imagen tal cual (`pm.max_children=5`)

| tag | peticiones | p50 | p95 | p99 | máx. |
|---|---:|---:|---:|---:|---:|
| history_first | 923 | 2 765 ms | **3 424 ms** | 3 833 ms | 4 051 ms |
| history_next | 1 846 | 2 731 ms | **3 373 ms** | 3 779 ms | 4 002 ms |
| history_mentor | 91 | 2 564 ms | **3 401 ms** | 3 684 ms | 3 730 ms |
| history_support | 93 | 2 485 ms | **3 259 ms** | 3 775 ms | 3 831 ms |
| panel | 204 | 2 605 ms | **3 311 ms** | 3 505 ms | 3 768 ms |

- 3 157 peticiones, 17,5 peticiones/s.
- `http_req_failed` = 0, ids duplicados = 0 y violaciones de contrato = 0. Todas las páginas devolvieron 200 con exactamente `data`, `undated` y `next_cursor`.
- **Umbral p95 < 2 s superado en los 5 tags.**

### Diagnóstico

- **Consultas SQL** (`EXPLAIN (ANALYZE, BUFFERS)`, sujeto 0):
  - La 1.ª página hace 25 consultas y la siguiente, 17.
  - La consulta más lenta ejecuta en 2,3 ms. En la 1.ª página, la suma es 7,0 ms de ejecución más 6,3 ms de planificación.
  - Se usan los índices de T015.6 (`activity_log_progress_subject_idx`, `activity_log_module_completion_user_idx`, `challenges_mentee_id_index` y `challenge_submissions_mentee_challenge_index`), además del índice `subject`.
  - Solo hay recorridos completos sobre tablas pequeñas (`users`, `objective_evidences`, `user_learning_progress`, `path_modules`, `learning_paths`, `skill_tags`, `evidence_deletion_events`), sin JIT.
  - `page()` tarda 85 ms en CLI.
  - **Conclusión: el SQL no explica los segundos de latencia.**
- **Latencia sin concurrencia:** recorriendo las 9 páginas de un sujeto por HTTP, cada página tarda entre 103 y 232 ms.
- **Corrida 2, solo para diagnóstico** (pool fijo de 16 workers montando `zz-bench.conf`; no es la configuración desplegable):
  - El rendimiento pasa de 17,5 a solo 21,0 peticiones/s.
  - p50 entre 1,8 y 2,2 s; p95 entre 3,2 y 3,6 s; 0 errores.
  - Con más workers no mejora, así que **el límite es la CPU del host, no el tamaño del pool**.
  - En plena carga, `docker stats` mostraba unos 2,3 núcleos en PHP-FPM y unos 2,8 en Postgres.
- **Coste de conexión:** cada petición PHP abre una conexión nueva a Postgres con autenticación `scram-sha-256` (4 096 iteraciones).
  - Una conexión nueva más `SELECT 1` cuesta **21,9 ms** de media, frente a 0,8 ms en una conexión ya abierta.
  - **Hipótesis por confirmar en un entorno dedicado:** el establecimiento de conexiones, más unas 25 idas y vueltas por petición, explica gran parte de la CPU de Postgres. Por petición consume mucho más de los ~13 ms que suman sus consultas.

### Conclusión y siguientes pasos (pendientes de decisión)

- **RNF-015.4 no queda verificado.** En este host falla, y el host no es representativo, así que tampoco queda refutado para producción.
- La paginación es correcta bajo carga: 0 duplicados y 0 errores. El plan de índices funciona con 200 000 filas de ruido.
- Opciones, en orden de evidencia necesaria:
  1. Repetir el benchmark en un entorno dedicado, con CPU reservada y sin el stack dev.
  2. Evaluar conexiones persistentes o PgBouncer, y dimensionar `pm.max_children` según los núcleos. La imagen de producción hoy usa los valores de serie de FPM.
  3. Solo con datos del entorno dedicado, reducir consultas por petición o añadir caché.
