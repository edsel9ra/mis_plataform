# Benchmark planificado — mapa y agregados de aprendizaje (Spec 013, RNF-013.5)

**Estado: definido, NO ejecutado.** No se reportan resultados de rendimiento.

## Objetivo

Verificar el criterio padre RNF-5: **p95 ≤ 2 s** desde recepción en servidor
hasta completar la respuesta API, bajo **50 consultas interactivas simultáneas**
(medición conjunta de lectura del mapa y del agregado colectivo), para:

- `GET /api/v1/progress/map` (nuevo, T013.7)
- `GET /api/v1/progress/collectives/{type}/{id}` (extendido con bloque `learning`, T013.7)
- `GET /api/v1/mentor/users/{user}/progress/map` (nuevo, T013.7; mismo servicio, rol mentor)
- `GET /api/v1/progress/panel` (panel existente, control de regresión)

## Herramienta

**k6** (sin dependencias del repo, vía `docker run grafana/k6` o binario local),
la misma elección de `specs/012-progreso-reto-modulo/benchmark.md`: percentiles
por endpoint en una sola corrida y VUs concurrentes sin código PHP extra. No hay
harness de carga previo en el repo; se creará
`specs/013-mapa-agregados-aprendizaje/k6/learning-map.js` cuando se ejecute.

## Dataset de referencia (seed en entorno dedicado, NO en dev compartido)

Reutilizable del dataset de la spec 012 si ya existe:

- 50 sujetos mentee con `user_learning_progress` en 2 rutas cada uno.
- 5 módulos por ruta (`path_modules`, `order` 1–5).
- 4 retos vinculados por módulo por sujeto + 2 retos sin vínculo.
- 1 cohorte con ≥3 miembros elegibles (para `state: "available"` en `learning`)
  y 1 cohorte con 2 miembros elegibles (para `state: "suppressed"`) — medir ambos
  caminos, porque la supresión corta el trabajo de agregación.
- 1 mentor con entitlement `MENTOR_WORKSPACE` y ≥1 mentee asignado con mapa
  (para el endpoint mentor).
- Objetivos individuales por miembro colectivo (condición de elegibilidad de la
  base general — ver `EligibleParticipantsResolver`).
- Seeding: script artisan one-shot o factory batch dentro de `DB::transaction`,
  documentado en el propio script k6/README.

## Escenario k6 (borrador)

```text
stages: 30s ramp a 50 VUs → 2min sostenido → 30s bajada
por VU: cookie Sanctum del sujeto → GET /progress/map;
        1 de cada 4 iteraciones también GET /progress/collectives/cohort/{id}
        (alternando cohorte available/suppressed entre iteraciones);
        1 VU dedicado con cookie mentor → GET /mentor/users/{mentee}/progress/map
umbrales: http_req_duration p(95) < 2000 por endpoint; http_req_failed < 1%
```

La medición es del servidor (latencia API); el cliente corre en la misma red
local de Compose para minimizar ruido de red. La lectura del mapa ejecuta 5
consultas por lotes por sujeto (ver `LearningMapReadTest`, límite ≤5 sin N+1);
el agregado colectivo ejecuta la misma carga por lotes para N sujetos (límite
≤10 verificado en `CollectiveLearningAggregateTest`).

## Salida esperada

Tabla p50/p95/p99 y tasa de error por endpoint, en `benchmark.md` al ejecutarse;
si p95 supera 2 s se adjunta `EXPLAIN ANALYZE` de las consultas del servicio
(carga por lotes en `PathProgressService::loadForSubjects` y resolución de base
en `EligibleParticipantsResolver`) antes de optimizar.
