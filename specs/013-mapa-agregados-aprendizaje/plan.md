# Plan de implementación — Spec 013: Mapa y agregados de aprendizaje

**Estado:** implementado (cabecera puesta al día el 2026-11-20: todas las tareas de `tasks.md` están completadas)
**Base:** Spec 001 v0.5.1 aprobada; baseline v0.3.0 implementado.
**Dependencia obligatoria:** Spec 012 antes de integrar tasas de reto/módulo. Spec 015 consume sus eventos, pero no es requisito para esta vista.

## 0. Ajuste tras implementar la Spec 012 (aprobado por el usuario, 2026-11-14)

La Spec 012 ya entregó piezas que este plan preveía crear:

- `App\Services\Progress\PathProgressService::forSubject(User)`: rutas consultables del sujeto (`SubjectLearningPaths`, asignadas o iniciadas), módulos ordenados por `order`, tasa de actividad, completitud por módulo y tasa de ruta, en 4 consultas por lotes.
- `GET /progress/learning-paths` y `GET /mentor/users/{user}/progress/learning-paths`, con la misma autorización del panel (`ProgressAccessService::assertUserPanel`).
- `LearningPathsCard` en el panel propio y en el del mentor.

Para no duplicar reglas ni consultas:

1. **Mapa individual (T013.1, T013.2, T013.5, T013.7):** `LearningMap` no será un servicio paralelo. Se extiende `PathProgressService` (o se compone sobre él) para añadir, por módulo, los elementos con vínculo explícito —hoy solo retos vinculados (id, título, estado de la fuente)— con desempate estable por `id` en el orden de módulos. `GET /progress/map` y su variante de mentor reutilizan ese servicio y la misma autorización. Objetivos y logros no se ubican porque no tienen vínculo registrado con ruta o módulo (RF-013.4).
2. **Vista (T013.8):** `/dashboard/progreso/mapa` es la vista independiente enlazada desde el panel; `LearningPathsCard` se mantiene como resumen y enlaza al mapa.
3. **Agregados colectivos (T013.3, T013.4, T013.6):** sin cambios respecto a este plan; usan las tasas individuales de `PathProgressService` como entrada.

## 1. Alcance y archivos

La vista será una lectura nueva, independiente, accesible desde el panel existente. Se reutilizan `LearningPath`, `PathModule`, `UserLearningProgress`, `ProgressAccessService`, `EligibleParticipantsResolver`, `CollectiveAggregateCalculator` y `MetricValue`; no se reemplaza el panel ni se altera la agregación baseline de objetivos.

| Archivo o área | Responsabilidad | RF |
|---|---|---|
| `backend/app/Services/Progress/Calculators/{LearningMap,LearningAggregate}Calculator.php` | Orden vigente, normalización de estados/relaciones y media calculable por métrica/ruta/versión; funciones puras. | RF-013.1, .3–.8 |
| `backend/app/Services/Progress/{LearningMap,CollectiveProgressService}.php` | Cargar rutas, módulos, objetivos relacionados solo si el vínculo existe, retos/completion de Spec 012; agregar nuevos promedios sin alterar objetivos baseline. | RF-013.1–.8 |
| `backend/app/Http/{Controllers/Api,Requests,Resources}/Progress/*`, `backend/routes/api.php` | Lectura de mapa propio/mentor y extensión aditiva del resource agregado, con contrato validado y autorización por sujeto. | RF-013.1–.9 |
| `backend/tests/{Unit,Feature}/Progress/*` | Orden, relaciones, agrupación comparable, dos supresiones, access control, ausencia de inferencias y no exposición individual. | Todos |
| `frontend/src/app/[locale]/dashboard/progreso/mapa/*`, `components/progress/*` | Mapa como vista separada con enlace desde el panel; secuencia lineal de módulos, sin etapas/grupos nuevos. | RF-013.1–.4 |
| `frontend/src/{types,lib,hooks}/progress*`, `lib/query-keys.ts` | Contrato, orden/presentación pura, queries, cache keys e invalidaciones; sin calcular métricas de negocio en cliente. | RF-013.1–.9 |
| `frontend/src/i18n/messages/{es,en,pt}/*` | Mensajes diferenciados de supresión por base general/contribuciones, sin conteos. | RF-013.7, RNF-013.2, .7 |

## 2. Datos y algoritmos

- Los módulos se obtienen de `LearningPath.modules()` y se ordenan por `order` con un desempate determinista por `id`; se conserva únicamente su secuencia actual. El mapa no crea secciones/etapas.
- Fuente de rutas visibles: el progreso/registro de asignación de `UserLearningProgress` para el participante; una ruta de catálogo sin asignación/inicio no se filtra a su mapa. Para mentor se reutiliza la resolución de sujeto/ruta de `ProgressAccessService` y RF-9/CA-16.7; soporte conserva solo permiso ya existente.
- La relación reto-módulo y las completitudes salen exclusivamente de la persistencia explícita de Spec 012. Un objetivo solo se ubica cuando exista vínculo fuente registrado; al no haber actualmente relación objetivo-ruta/módulo, no se crea ni se infiere una.
- Para promediar rutas se usa identidad de ruta más una **clave de versión de la definición vigente** (identidad de ruta y su estructura ordenada persistida). Esta clave sirve para no mezclar definiciones distintas; no crea una historia/versionado de rutas ni preserva mapas antiguos, que están fuera de alcance. Si existe un identificador de versión fuente en una futura evolución, debe reemplazar esta clave mediante cambio aprobado.
- La base general sigue siendo exactamente la resuelta por `EligibleParticipantsResolver` (miembro activo, pertenencia vigente y ≥1 objetivo individual). Para cada nueva tasa: filtrar quienes tengan porcentaje individual `value` comparable, excluir `no_data`/`incomplete` de contribuciones sin convertirlos a 0, y promediar únicamente la misma métrica y ruta/versión.
- Política de supresión independiente: base general `<3` suprime el agregado de colectivo; con base suficiente, cada tasa nueva con `<3` contribuciones calculables se suprime únicamente para esa tasa. La respuesta contiene motivo enum traducible, pero no conteos, numeradores, denominadores ni tamaños de base bajo supresión. Las reglas/serialización de objetivos baseline quedan byte-a-byte funcionalmente equivalentes.

### Funciones puras

```text
orderModules(modules): sort(order ASC, id ASC)
routeVersionKey(path, orderedModules): stable fingerprint of current persisted definition
mapState(sourceRecord, linked): source status + explicit placement; never infer missing link
meanComparable(metrics, threshold):
  values = metrics where state == value and key(metric) matches exactly
  if count(values) < threshold: {suppressed, reason: insufficient_metric_contributions}
  else: arithmetic mean(values), count only internally for threshold; no leaked count on suppression
collectiveState(eligibleCount, metricValues, threshold):
  if eligibleCount < threshold: suppress all with insufficient_eligible_base
  else calculate each new metric independently with meanComparable
```

Las funciones no consultan base de datos ni reloj global; toda fecha/instante de presentación se recibe como argumento (p. ej. `now` para estados derivados). La política de estados de módulo/actividad y la fórmula fuente no se duplican: se invoca la calculadora/servicio de Spec 012.

## 3. Contratos de lectura

- `GET /api/v1/progress/map` — propio; opcionalmente selecciona un `path_id` ya visible. Incluye rutas/módulos ordenados, elemento fuente/estado, colocación explícita y tasas de Spec 012 (`value`, `no_data`, `incomplete`, `source`, numerador/denominador cuando aplica).
- `GET /api/v1/mentor/users/{user}/progress/map` — misma representación, solo con relación/entitlement y rutas dentro de la vista autorizada del sujeto.
- `GET /api/v1/progress/collectives/{type}/{id}` — adición de `activity` y tasas de ruta/version a recurso existente; no cambia métrica de objetivos ni devuelve mapas/IDs de miembros.
- Errores de recursos no visibles siguen códigos/mensajes homogéneos existentes (`404`/`403`) sin filtrar existencia. Recursos serializan únicamente registros autorizados; requests validan UUID/tipo y límites de página si aplica.

## 4. Decisiones técnicas y alternativas descartadas

| Decisión | Alternativa descartada | Justificación |
|---|---|---|
| Componente de mapa nuevo enlazado desde el panel | Rediseñar o sustituir el dashboard base | La spec complementa el baseline, minimiza regresión (P2). |
| Reutilizar asignación existente y vínculos explícitos de 012 | Mostrar todas las rutas o inferir elementos por título/progreso | Mantiene alcance y evita asociaciones falsas (P3/P6). |
| Calcular tasas en servicios/calc puras compartidas | Agregar porcentajes individuales en React o duplicar query por rol | Un único contrato y cálculo verificable (P1/P11). |
| Aplicar thresholds general y de contribución de forma independiente | Mostrar proporciones parciales, ceros por faltantes o conteos suprimidos | No permite deducir datos de participantes (P4/RNF-2). |
| Clave de versión de definición actual, sin snapshots históricos | Introducir editor/versionado de rutas y migrar mapas antiguos | No hay campo de versión en LearningPath; versionado/historial de catálogo queda fuera de esta spec. |
| Eager-load/indexes y una sola resolución agregada por cohorte | Consultas por miembro/módulo dentro de loops | Control de N+1 y viabilidad del p95. |

## 5. Seguridad, operación y rendimiento

Autorización por request en servidor, reutilizando `ProgressAccessService` y membership resolver; roles de responsable/miembro/mentor siguen las decisiones de Spec 001. No ampliar el acceso de soporte. El agregado no serializa IDs, mapas, eventos ni arrays individuales. No exponer números al suprimir; el motivo es un código que la UI traduce.

Registrar `progress.map_failed`, `progress.access_denied` y fallos agregados con campos estructurados mínimos (request scope/tipo de excepción/IDs necesarios), sin títulos, datos personales o métricas individuales. Invalidar caché por eventos de reto, completion, asignación/ruta/módulo y objetivo; Redis no es verdad persistente. Probar índices y número de queries; benchmark conjunto panel/mapa a 50 consultas simultáneas, p95 ≤2 s medido servidor desde recepción a fin de response. Comprobar viewport 320 px y controles teclado/tacto.

## 6. Estrategia de tests (planificada; no ejecutada)

- **Unit Pest:** secuencia estable con empates, clave ruta/versión, elemento sin vínculo, valor cero/no_data/incomplete, media exacta y threshold calculable; RF-013.1, .3–.8.
- **Feature Pest/PostgreSQL:** rutas asignadas/iniciadas vs catálogo no asignado, mentor y soporte autorizado, IDOR/no miembro/mentor ajeno, versión distinta no mezclada, base 2 suprime todo, base 3 con 2 contribuciones suprime solo tasa nueva, reason distinto sin counts, serializers no incluyen miembro/mapa/denominador de tasa suprimida; RF-013.2, .5–.9.
- **Regresión baseline:** comparar payload/cálculo de objetivos de `CollectiveProgressService` antes/después para las mismas fixtures y verificar supresión uniforme sin alterar suma/media existente; RF-013.5–.8.
- **Frontend Vitest/accesibilidad:** secuencia, enlace independent, states/suppression reasons, locale fallback; inspección teclado/foco/320 px sin scroll horizontal; RNF-013.4, .5, .7.
- **Load test planeado:** 50 lectores concurrentes y p95 según RNF padre. Tests descritos como plan; no se ejecutan durante esta fase SDD.

## 7. Trazabilidad RF → partes

| Parte | RF cubiertos |
|---|---|
| Mapa API/servicio/orden de módulos y visibilidad | RF-013.1–.4, .9 |
| Agregador de métricas nuevas y privacy gates | RF-013.5–.8 |
| Recursos y autorización de servidor | RF-013.2, .5, .7–.9; RNF-013.1–.3 |
| Vista independiente, estados, traducciones y responsive | RF-013.1–.4; RNF-013.4, .5, .7 |
