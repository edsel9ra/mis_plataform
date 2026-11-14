# Benchmark planificado — lecturas de progreso por ruta (Spec 012, RNF-012.4)

**Estado: definido, NO ejecutado.** No se reportan resultados de rendimiento.

## Objetivo

Verificar el criterio padre RNF-5: **p95 ≤ 2 s** desde recepción en servidor
hasta completar la respuesta API, bajo **50 consultas interactivas simultáneas**,
para:

- `GET /api/v1/progress/learning-paths` (nuevo, T012.7)
- `GET /api/v1/progress/panel` (panel existente, control de regresión)

## Herramienta

**k6** (sin dependencias del repo, vía `docker run grafana/k6` o binario local).
Se elige k6 sobre `ab`/artisan porque expone percentiles por endpoint en una
sola corrida, soporta escenarios con VUs concurrentes y no requiere código PHP
en producción. No hay harness de carga previo en el repo; se crea
`specs/012-progreso-reto-modulo/k6/learning-paths.js` cuando se ejecute.

## Dataset de referencia (seed en entorno dedicado, NO en dev compartido)

- 50 sujetos mentee con `user_learning_progress` en 2 rutas cada uno.
- 5 módulos por ruta (`path_modules`, `order` 1–5).
- 4 retos vinculados por módulo por sujeto (mezcla `completed`/`in_progress`/
  `assigned`/`abandoned`) + 2 retos sin vínculo.
- Seeding: script artisan one-shot o factory batch dentro de
  `DB::transaction`, documentado en el propio script k6/README.

## Escenario k6 (borrador)

```text
stages: 30s ramp a 50 VUs → 2min sostenido → 30s bajada
por VU: cookie Sanctum del sujeto → GET /progress/learning-paths,
        1 de cada 5 iteraciones también GET /progress/panel
umbrales: http_req_duration p(95) < 2000 por endpoint; http_req_failed < 1%
```

La medición es del servidor (latencia API); el cliente corre en la misma red
local de Compose para minimizar ruido de red.

## Salida esperada

Tabla p50/p95/p99 y tasa de error por endpoint, en `benchmark.md` al ejecutarse;
si p95 supera 2 s se adjunta `EXPLAIN ANALYZE` de las 4 consultas del servicio
antes de optimizar.
