# Plan de implementación — Spec 015: Historial verificable de progreso

**Estado:** aprobado para implementación (revisión 2026-11-15, aprobada 2026-11-15) · **Spec:** aprobada
**Base:** Spec 001 v0.5.1 aprobada; specs 012, 013 y 014 implementadas en `main` (PR #4).
**Dependencias:** satisfechas. La 012 produce los eventos de retos, vínculo reto-módulo y completitud; la 014 produce los eventos de vínculo resultado-competencia, de estado de fuente y el evento mínimo de eliminación.
**Retención (RF-015.9):** desbloqueada. `docs/politica-retencion.md` v1.0.0 está vigente y la Spec 014 (T014.10–T014.13) ya conserva el evento mínimo, su correspondencia audit-only y la purga a 12 meses. Esta spec solo **lee** ese evento: no crea almacenamiento, seudónimos ni plazos.

> **Revisión 2026-11-15.** La versión anterior era previa a la implementación de 012/014. Se contrastó cada fuente con el código: se corrigen productores inexistentes (no hay registro de cambios de `UserSkill.level`), se concretan los eventos reales de `activity_log` y se añade la ocultación de actor para los retiros causados por eliminación (CA-19.7).

## 1. Alcance y fuentes verificadas

La cronología es una proyección de solo lectura de registros existentes, no una tabla nueva ni una reconstrucción. Cada adapter selecciona columnas/propiedades en whitelist y nunca serializa `properties` completo.

| Fuente real | Eventos que acredita | Whitelist de salida | Limitación | RF |
|---|---|---|---|---|
| `activity_log` `log_name=objective` (`Objective` con `LogsActivity`, `logOnly` status/due_at/success_criteria/owner_id) | `created`; cambios de `status` (incluido el primer `in_progress`, `achieved`, `abandoned`) | objetivo id y título (el mismo que ya ve en el panel quien tiene acceso; añadido en T015.2 para que la UI no muestre solo ids), estado anterior/resultante, actor (causer), fecha | Solo objetivos **individuales** del sujeto (§9 D-2). No se exponen `success_criteria` ni `owner_id`; un `updated` sin cambio de `status` no genera entrada | RF-015.2, .7–.8 |
| `objective_evidences` | Evidencia registrada (`created_at`) | evidencia id, objetivo id y título, autor, fecha, `has_attachment` (bool) | Nunca `note` ni URL/ruta del adjunto | RF-015.2 |
| `activity_log` `challenge.status_transition` (`TransitionChallengeStatusAction`) | Inicio, finalización y cambios de estado del reto | reto id y título, estado anterior/resultante, actor, fecha | Sujeto = `challenges.mentee_id`. No se usa `progress` estimado | RF-015.3 |
| `activity_log` `challenge.module_link_changed` (`SetChallengePathModuleAction`, `ModuleCompletionService`) | Creación/cambio/retiro del vínculo reto-módulo | reto id y título, módulo anterior/resultante, actor o `system`, `reason` si existe, fecha | Sin inferir vínculos | RF-015.3 |
| `activity_log` `module_completion.granted` / `.revoked` | Completitud automática del módulo y su revocación | módulo id y título, actor `system`, desencadenante (`triggered_by.event`, `actor_id`, `challenge_id`), fecha | Sujeto = `properties.user_id` | RF-015.3 |
| `challenge_submissions` | Evidencia de reto enviada (`submitted_at`) y revisada (`reviewed_at`) | entrega id, reto id y título, fechas (sin actor: la fuente no lo registra) | Nunca `note` ni el estado actual (no es el del momento). `submitted_at` es NOT NULL en el esquema: no hay entregas sin fecha (T015.3) | RF-015.3, .7 |
| `user_learning_progress` | Ruta iniciada (`started_at`) y completada (`completed_at`) como hechos fechados | ruta id y título (mapa i18n), fecha | Una entrada por fecha presente; sin pasos intermedios. Fila sin ninguna fecha → no hay hecho que mostrar | RF-015.3, .7–.8 |
| `activity_log` `competency.evidence_link_changed` | Crear/cambiar/retirar vínculo resultado-competencia | vínculo id, `before`/`after` (tipo de fuente, tipo y clave de competencia y nombre del skill tag; nunca el id del resultado), actor, fecha | Retiros con `reason=source_deleted` se **excluyen** (§3, CA-19.7): ese borrado se representa solo con el evento mínimo | RF-015.4–.5 |
| `activity_log` `competency.evidence_source_state_changed` | Cambio real de `completed_at` de una fuente vinculada | fuente id, vínculos afectados, estado anterior/resultante, actor, fecha | Solo existe para `PersonalityAssessment` (catálogo es NOT NULL) | RF-015.6 |
| `evidence_deletion_events` (Spec 014) | Eliminación de un resultado vinculado (evento mínimo) | evento id, tipo de fuente, tipo y clave de competencia, fecha, `actor: hidden` | **Nunca** `actor_pseudonym` ni la correspondencia; eventos vencidos no se muestran | RF-015.9 |
| `UserSkill.level` | — | — | **Ningún productor registra cambios de nivel** (solo `SyncMentorSkillsAction` escribe niveles, sin auditoría). Por RF-015.8 no se crea ninguna entrada (§9 D-1) | RF-015.4, .8 |

## 2. Contrato normalizado y funciones puras

`ProgressHistoryEvent` (solo en memoria/respuesta): `id` (estable: `{source}:{source_id}[:{suffix}]`), `type` (p. ej. `objective.status_changed`), `domain` (`objectives`|`activities`|`competencies`), `occurred_at|null`, `actor` (`{id,name}` | `{kind:"system"}` | `{kind:"hidden"}` | `null`), `subject_ref` (ids de objetivo/reto/módulo/ruta/competencia según tipo), `before`/`after` en whitelist, `incomplete[]` (campos que la fuente no trae).

```text
normalize(record, whitelist): map only whitelisted fields; missing → incomplete[]
occurredAt(record): properties.occurred_at ?? created_at ?? source date column ?? null
classify(event): occurred_at null → undated (never now())
sortKey(event): (occurred_at DESC, id DESC)          # tie-break estable y total
mergePage(feeds, cursor, limit): k-way merge of feeds already sorted by sortKey,
                                 take limit, next_cursor = sortKey(last) | null
```

Funciones puras en `App\Services\Progress\Calculators\ProgressHistoryCalculator`: sin DB ni reloj. El cursor es opaco (base64 de `occurred_at|id`) y **no** concede acceso: el sujeto siempre se resuelve en servidor.

## 3. Ocultación del actor en eliminaciones (CA-19.7)

Hoy, al borrar una fuente vinculada, el retiro auditado `competency.evidence_link_changed` (`after: null`) guarda el actor real, y no se distingue de un retiro manual. Si la cronología lo mostrara, revelaría quién borró el resultado.

- **Productor (cambio mínimo en 014):** `RetireEvidenceLinksForSourceAction` pasa `reason: 'source_deleted'` a `RemoveCompetencyAssessmentLinkAction`, que lo añade a las propiedades del evento operativo. Los retiros manuales no llevan `reason`. El registro operativo sigue igual en todo lo demás (política §6).
- **Lectura:** el adapter de vínculos excluye los retiros con `reason=source_deleted`; el borrado aparece una sola vez, como evento mínimo, con `actor: {kind: "hidden"}`.
- Retiros anteriores a este cambio no llevan la marca. Solo existen en entornos donde 014 ya corrió; se acepta y se documenta.

## 4. Flujo de lectura, autorización y presentación

1. Sujeto y rol con `ProgressAccessService::assertUserPanel` (misma matriz que panel, mapa y evidencia): titular → `self`; mentor asignado con `mentor_workspace` → `mentor`; admin con `progress.view.support` → `support`; resto → 404/403 sin revelar. Sin permisos nuevos.
2. Cada adapter consulta por sujeto, ordenado por `sortKey` y limitado a `limit + 1` posteriores al cursor; solo columnas en whitelist. `activity_log` se filtra por `log_name`/`event` y por `subject` o propiedad de sujeto según la fuente (§6).
3. Merge en memoria de como máximo `n_feeds × (limit + 1)` registros, corte a `limit`, `next_cursor`.
4. Los registros sin fecha se devuelven en `undated` solo en la primera página (sin cursor), con tope; nunca se intercalan.
5. Logs `progress.history_failed` / `progress.access_denied` estructurados, sin notas, respuestas, puntuaciones ni email.

**API:** `GET /api/v1/progress/history?cursor=&limit=` (propio); `GET /api/v1/mentor/users/{user}/progress/history` (grupo `role:mentor`); `GET /api/v1/admin/progress/history?user_id=` (grupo admin + `permission:progress.view.support`, igual que el panel de soporte). `limit` 1–50, por defecto 25. Respuesta `{data: [...], undated: [...], next_cursor}`. Ningún endpoint colectivo.

**UI:** página propia `/dashboard/progreso/historial` y variante de mentor `/dashboard/mentor/progress/[id]/historial`, con enlace «Ver historial» desde el panel (patrón del mapa de la 013). Lista cronológica agrupada por día, sección separada «Fecha no disponible», etiquetas de incompleto, botón «Cargar más» con `useInfiniteQuery`; sin gráficos ni tendencias.

## 5. Decisiones técnicas y alternativas descartadas

| Decisión | Alternativa descartada | Justificación |
|---|---|---|
| Adapters de solo lectura sobre fuentes existentes | Tabla de cronología materializada | Una sola verdad; no duplica PII (P1/P4/P5). |
| Whitelist por tipo de evento | Serializar `properties` | Minimización: el log operativo lleva ids de fuente y actores que no siempre deben salir. |
| Cursor `(occurred_at, id)` + merge k-way con `limit+1` por feed | Offset/página, o cargar toda la historia | Acota memoria y queries; estable ante inserciones. |
| Marca `reason=source_deleted` en el retiro operativo | Correlacionar retiros y eventos mínimos por fecha | La correlación temporal es frágil; la marca es explícita y barata. |
| Reusar `assertUserPanel` y la ruta de soporte del panel | Permiso nuevo de historial | Spec: sin cambios de permisos (§3.1 de la 001). |
| No crear productor de cambios de nivel | Añadir observer sobre `UserSkill` | RF-015.8 / fuera de alcance «crear eventos que las fuentes no acreditan» (§9 D-1). |

## 6. Rendimiento, índices y caché

- `activity_log` solo tiene índices por `log_name`, `(subject_type, subject_id)` y `(causer_type, causer_id)` (verificado en la BD de desarrollo). Las fuentes por `subject` (objetivos, retos) los aprovechan. Para eventos cuyo sujeto va en propiedades, migración aditiva con índices de expresión sobre `(event, (properties->>'user_id'))` (`module_completion.*`) y `(event, (properties->>'subject_id'))` (`competency.*`). Se valida con `EXPLAIN` en T015.6.
- Retos y entregas se resuelven por `challenges.mentee_id`: primero ids de retos del sujeto, luego eventos con `subject_type=Challenge` y `subject_id IN (...)`.
- **Ajuste T015.6 (2026-11-15):** los índices de expresión se crean **parciales** por `log_name` (`module_completion` y `progress`), sobre la misma expresión que usan los adapters (`(properties::jsonb ->> 'user_id')` y `(properties::jsonb ->> 'subject_id')`), seguidos de `event`. Además se verificó que `challenges` solo tiene `(mentor_id, mentee_id)` y que `challenge_submissions` no tiene ningún índice por `mentee_id`. Por eso la misma migración añade `challenges(mentee_id)` y `challenge_submissions(mentee_id, challenge_id)`. Todo es aditivo y reversible.
- Sin caché propia en la primera versión (la consulta es paginada y acotada); se reevalúa con el benchmark.
- Benchmark (T015.9): 50 concurrentes, p95 ≤ 2 s, definido y no ejecutado, igual que en 012/013.

## 7. Estrategia de tests

- **Unit:** `occurredAt`, clasificación sin fecha, `sortKey` con empates, merge k-way y cursor (sin duplicados ni huecos entre páginas), whitelist sin claves prohibidas.
- **Feature por adapter:** cada fuente emite solo si existe el registro; `updated` de objetivo sin cambio de estado → nada; reto de otro mentee → nada; completitud con actor `system` y desencadenante; retiro manual visible con actor y retiro por borrado excluido; evento mínimo con `actor.kind=hidden` y sin seudónimo; evento mínimo vencido → no aparece; sin productor de nivel → ninguna entrada de nivel.
- **Autorización/privacidad:** titular, mentor asignado/no asignado/sin workspace, soporte con y sin permiso, IDOR, sin acceso colectivo; JSON sin `note`, `answers`, `score`, `raw_scores`, `actor_pseudonym` ni ids de otro sujeto.
- **Frontend (Vitest):** agrupación por día, sección sin fecha, incompletos, actor oculto/sistema, carga/error/vacío, «Cargar más» sin perder ni duplicar.
- **A11y/i18n:** es/en/pt, teclado, foco, 320 px.

## 8. Trazabilidad RF → partes

| Parte | RF |
|---|---|
| Calculadora pura (normalización, fechas, orden, cursor) | RF-015.1, .7–.8 |
| Adapters de objetivos y evidencias | RF-015.2, .7–.8 |
| Adapters de retos, vínculo reto-módulo, completitud, entregas y rutas | RF-015.3, .7–.8 |
| Adapters de competencias (vínculos, estado de fuente) + marca de retiro por borrado | RF-015.4–.6, .8 |
| Adapter del evento mínimo de eliminación | RF-015.9 |
| Servicio de merge, endpoints, resource y autorización | RF-015.1, .7, .10; RNF-015.1–.2 |
| UI cronológica, a11y, i18n, benchmark | RF-015.1–.8; RNF-015.3–.6 |

## 9. Decisiones (aprobadas 2026-11-15)

- **D-1 · Cambios de nivel de competencia — decidido: no añadir productor.** Ninguna fuente los registra hoy. Propuesta: no añadir productor; la cronología no muestra cambios de nivel hasta que exista un registro (RF-015.8). Alternativa: observer sobre `UserSkill.level` que audite cambios desde ahora (sin reconstruir el pasado).
- **D-2 · Objetivos colectivos — decidido: solo individuales.** Propuesta: la cronología individual incluye solo objetivos individuales del sujeto; los colectivos quedan fuera para no mezclar acciones de otros miembros en un historial individual (RF-015.10). Alternativa: incluir los objetivos colectivos donde el sujeto es miembro.
