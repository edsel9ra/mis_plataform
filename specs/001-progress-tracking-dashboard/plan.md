# Plan de implementación — Spec 001: Panel de progreso y seguimiento

**Estado:** propuesta para revisión (no se ha tocado código)
**Versión:** 1.0.0
**Fecha:** 2026-10-11
**Spec de referencia:** `specs/001-progress-tracking-dashboard/spec.md` (v0.2.0)
**Constitución:** `docs/constitution.md` (v1.1.0, principios P1–P20 y decisiones §3.1)
**Ámbito:** decisiones de diseño posteriores a la spec (el CÓMO). No define todavía código ejecutable; los bloques marcados como *pseudocódigo* son lenguaje natural estructurado.

---

## 0. Resumen ejecutivo

El panel se construye **sobre la arquitectura vigente** (P2): una entidad nueva `Objective` en Laravel/PostgreSQL, dos endpoints de lectura (panel individual y agregado de colectivo) más el CRUD de objetivos, y una página Next.js nueva que **complementa** los dashboards existentes (no los reemplaza, spec §7). Todo indicador se calcula **en el servidor** con funciones puras testables (P6, P11, RNF-9) y cada respuesta declara su **fuente, regla, periodo y estado de datos** (`sin datos` / `0` / `incompleto`). La autorización se reutiliza de `MentorshipRelationship`, del Mentor Workspace y de `PlanEntitlementService`; el agregado de colectivo aplica el **umbral uniforme de 3 participantes elegibles** con supresión explícita.

No se introduce ninguna tecnología nueva: Laravel, PostgreSQL, Redis (solo caché), Next.js y react-query.

---

## 1. Principios de la constitución aplicables y cómo se cumplen

| Principio | Efecto en el diseño | Partes del plan |
|---|---|---|
| **P1** única fuente de verdad | Objetivos y estados se persisten en PostgreSQL; Redis solo cachea el payload calculado. No se crean tablas de agregados duplicados. | §5, §12 |
| **P2** arquitectura existente primero | Se reutilizan Enums/Models/Actions/Services/Policies/Resources y `PlanEntitlementService`. Sin dependencias nuevas. | §7, §12 |
| **P3** autorización en cada operación | Cada endpoint autoriza en servidor; el frontend solo pinta. | §5, §9.5, §13 |
| **P4** privacidad desde el diseño | El agregado no expone individuales; supresión bajo umbral; DTOs sin datos ajenos. | §5, §9.2, §11 |
| **P5** trazabilidad | Uso de `spatie/laravel-activitylog` (ya instalado) para transiciones de objetivo; bitácora inmutable por operación ordinaria. | §5, §7.4, §12 |
| **P6** progreso por evidencias | Una sesión `scheduled` no cuenta; `completed` sin asistencia tampoco. Vencido es derivado, no estado. | §9, §14 |
| **P11** métricas coherentes | Anexo A de la spec materializado en funciones puras únicas + periodo visible. | §8, §9, §16 |
| **P16** experiencia accesible | Estados de carga/error/sin datos, teclado, foco visible, distinción visual. | §11 |
| **P17** contratos explícitos | Validación con Form Requests; errores sin trazas; contrato versionado bajo `/api/v1`. | §5, §6 |
| **P18** cambios de datos seguros | Migraciones aditivas con FKs UUID e índices, con tests. | §7.3, §15 |
| **P19** calidad demostrable | Tests de cálculo puro + tests de permisos (permitido/denegado). | §15 |
| **P20** observabilidad | Logs estructurados por bloque y por denegación, sin datos personales. | §16 |

**Excepción formal documentada** (spec §7 + constitución §5): sin tendencias históricas/series temporales; medida compensatoria = RF-13 y RNF-6 (distinguir estados, tendencias y ausencia de datos). El plan **no** crea tablas de series temporales.

---

## 2. Supuestos de diseño (resuelven las dudas abiertas de la spec §9)

Se aplican por defecto; cada uno queda confirmable sin rehacer el diseño porque es un punto único (config o columna). Si el cliente cambia uno, solo se ajusta su punto de extensión.

| # | Duda abierta | Decisión de diseño (default) | Punto de cambio |
|---|---|---|---|
| D1 | ¿Objetivo en un idioma o multi-idioma? | **Texto plano** en `title`/`description` (idioma del autor). El contenido de objetivo lo escriben usuarios, no admins; traducirlo no es viable y forzarlo produciría falsos. | migración `objectives` |
| D2 | ¿Actividades = retos + módulos de ruta? | **Ambos** (§ RF-6 CA-6.1): retos `assigned`/`in_progress` + rutas con progreso no completado. | `ActivityMetricsCalculator` |
| D3 | ¿El % pondera por importancia? | **Todos los objetivos valen igual** (spec §9). | `ObjectiveMetricsCalculator` |
| D4 | ¿`abandonado`/eliminados salen del denominador? | `abandoned` **permanece** en el denominador (es un objetivo no logrado); los *soft-deleted* **salen**. | `ObjectiveMetricsCalculator` |
| D5 | Definición de «participante elegible» | Miembro **activo con pertenencia vigente y ≥1 objetivo individual** (el que aporta datos al agregado). | `EligibleParticipantsResolver` |
| D6 | ¿Suma vs media, cuál es la cifra principal? | **Media** del % individual como cifra principal; la **suma** logrados/total como cifra de conciliación secundaria. | `CollectiveAggregateCalculator` |
| D7 | ¿Objetivo colectivo entra en suma/media? | Se reporta como **línea propia del colectivo** (bloque separado), fuera de la media individual. | `CollectiveProgressService` |
| D8 | ¿Umbral configurable? | Constante de config `config('progress.collective_privacy_threshold') = 3` («umbral inicial»). Config por organización queda **fuera de alcance** (§7). | `config/progress.php` |
| D9 | ¿El mentor ve agregado de su cartera? | **No en v1**; ve panel individual por mentee + lista con banderas (RF-9). | — |
| D10 | Roles «responsables» de colectivo | `FamilyGroup.head_user_id`; `Cohort.created_by`; `Company.admin_id` (reutiliza `userCanManageSource`). Si no hay responsable, no hay vista de responsable. | `ProgressPanelController` |
| D11 | ¿Admins ven todo? | Solo con **permiso explícito** `progress.view.support`, con registro de auditoría. Sin él, `403`. | seeder de permisos + policy |

---

## 3. Vista de arquitectura y flujo

```
Next.js (RSC de shell + client components con react-query)
        │  GET /api/v1/progress/panel
        │  GET /api/v1/mentor/users/{user}/progress
        │  GET /api/v1/progress/collectives/{type}/{id}
        │  CRUD /api/v1/objectives ...
        ▼
Laravel: Controllers delgados
        ├── Form Requests (validación, P17)
        ├── ObjectivePolicy / autorización reutilizada (P3)
        ├── Services de lectura ──► Calculadoras PURAS (sin IO)  ← núcleo testeable (RNF-9)
        │        └── Redis cache (payload por sujeto, TTL corto) (RNF-5)
        ├── Actions de escritura (CreateObjective, TransitionObjectiveStatus, ...)
        └── API Resources (contrato estable)
        ▼
PostgreSQL (objectives, objective_evidences) + tablas existentes
        └── activitylog (P5)
```

Regla de oro del diseño: **los controladores no calculan, las calculadoras no consultan, los servicios orquestan.**

---

## 4. Trazabilidad rápida RF → bloques del plan

| RF | Título | Dónde se resuelve |
|---|---|---|
| RF-1 | Objetivos entidad de primera clase | §5.2, §6, §7.1–7.4, §8.1, §11.2 |
| RF-2 | Ciclo de vida y trazabilidad | §6, §7.1, §7.4, §9.1, §12 (D-P5) |
| RF-3 | Panel individual del mentee | §5.1, §9.0, §11.1, §11.3 |
| RF-4 | % objetivos logrados | §8.1, §9.1, §11.3 |
| RF-5 | Sesiones realizadas | §8.2, §9.2, §11.3 |
| RF-6 | Actividades pendientes/vencidas | §8.3, §9.3, §11.3 |
| RF-7 | Competencias (skills + OCEAN) | §5.4, §8.4, §9.4, §11.3 |
| RF-8 | Próximos compromisos (7 días) | §8.5, §9.3, §11.3 |
| RF-9 | Panel del mentor sobre mentees | §5.3, §9.5, §11.4 |
| RF-10 | Agregado para el responsable | §5.5, §8.6, §9.6, §11.5 |
| RF-11 | Agregado para el miembro | §5.5, §8.6, §9.6, §11.5 |
| RF-12 | Autorización en cada operación | §5, §7.5, §9.7, §13 |
| RF-13 | «Sin datos» vs «cero» | §8.0, §9.0, §11.6 |
| RF-14 | Periodo por naturaleza | §8.0, §9.0, §11.6 |
| RF-15 | i18n del panel | §7.7, §10, §17 |
| RNF-1..10 | Ver §13 y §15 |

---

## 5. Contrato de API (P17)

Todos los endpoints bajo `/api/v1`, autenticados (`auth:sanctum` + `active`). Respuestas con API Resources. Los errores no exponen trazas (P17).

### 5.1 `GET /progress/panel` — panel propio (RF-3)
- Entrada: ninguna (se deriva del usuario autenticado). Opcional `subject=user|collective` no; el propio usuario siempre es `user`.
- Salida: `ProgressPanelResource` con los 6 bloques (5 individuales + `collective`, si pertenece a alguno).
- Autorización: el sujeto es el propio `auth()->user()`.
- Errores: `401` no autenticado; `500` degradado se resuelve por bloque (§9.0), no tumba la respuesta.

### 5.2 CRUD de objetivos (RF-1, RF-2)
- `GET /objectives?subject_type=&subject_id=` → listado filtrado y paginado.
- `POST /objectives` → crear (mentor para individuales; responsable para colectivos). `StoreObjectiveRequest`.
- `PUT /objectives/{objective}` → editar campos. `UpdateObjectiveRequest`. Autorizado por `ObjectivePolicy@update`.
- `POST /objectives/{objective}/status` → transición de estado. `TransitionObjectiveStatusRequest` (`status in not_started,in_progress,achieved,abandoned`). Autorizado por `ObjectivePolicy@transition`.
- `POST /objectives/{objective}/proposals` → **mentee propone** un objetivo individual (crea propuesta sin permiso de administración; CA-1.5). `ProposeObjectiveRequest`.
- `POST /objectives/{objective}/evidences` → **mentee aporta evidencia** (`StoreObjectiveEvidenceRequest`); puede adjuntar `media_asset_id` existente o nota. `ObjectivePolicy@addEvidence`.
- Errores: `403` sin permiso (CA-1.6), `404` sujeto ajeno (no filtrar existencia, P3/RF-12), `422` sin criterio/titular (CA-1.4).

### 5.3 `GET /mentor/users/{user}/progress?relationship_id=` — panel del mentor (RF-9)
- Reutiliza `MentorWorkspaceAccessService::mentorPair()` (404 si no es su mentee, CA-9.3) y `PlanEntitlementService` (bloqueo con motivo si el plan no habilita el espacio, CA-9.2).
- Salida: mismo `ProgressPanelResource` que 5.1, calculado para el mentee.

### 5.4 `GET /mentor/progress/overview?plan_filter=&search=` — banderas de cartera (RF-9 CA-9.4)
- Reutiliza `MentorAssignmentService::assignedUsers()`; añade por mentee las banderas `has_overdue`, `days_without_activity`, `pending_count`. Sin exponer individuales de terceros.

### 5.5 `GET /progress/collectives/{type}/{id}` — agregado de colectivo (RF-10, RF-11)
- `type ∈ {family_group, cohort, company}`.
- Autorización: responsable (`userCanManageSource`) **o** miembro (`userCanAccessSource`); si no, `403` sin datos (CA-11.2, RF-12).
- Salida: `CollectiveProgressResource`:
  ```
  {
    subject: {type, id, name},
    viewer_role: "manager" | "member",
    metrics: { ...agregados... },
    base: { eligible_members, contributing_members },   // solo si NO suprimido
    suppressed: bool,
    suppression_reason: "insufficient_base" | null,
    period: {...}
  }
  ```
- Si `contributing_members < 3` (o `eligible_members < 3`): `suppressed=true`, `reason="insufficient_base"`, **sin** `metrics` ni `base` (RNF-2, CA-10.5, CA-11.3). El responsable **también** recibe supresión (decisión §3.1.5).
- Si el colectivo no tiene miembros con objetivos: `suppressed=false` + `metrics.state="no_data"` (CA-10.3), sin media engañosa.

### 5.6 `GET /admin/progress/panel?user_id=` — soporte (RF-12, D11)
- Requiere permiso explícito `progress.view.support`; deniega `403` si falta. Registra auditoría de la acción (P5/P3).

### 5.7 Códigos de error homogéneos
`403` denegación sin filtrar (RF-12); `404` recursos no-visibles para no revelar existencia (patrón ya usado por `MentorWorkspaceAccessService`); `422` validación; `503`/parcial nunca: degradación por bloque.

---

## 6. Modelo de datos (PostgreSQL) — RF-1, RF-2, P1, P18

### 6.1 Migración `..._create_objectives_table.php` (aditiva)
Tabla `objectives` (UUID, `HasUuids`):
- `id` (uuid pk)
- `scope` string(20) — `individual` | `collective` (enum `ObjectiveScope`), RF-1 CA-1.2
- `owner_type` string / `owner_id` uuid — morph (`user`, `family_group`, `cohort`, `company`), **mutuamente excluyente por `scope`** (CA-1.2). Se canonicaliza con el mismo mecanismo que `MentorshipRelationship`.
- `relationship_id` uuid null → FK `mentorship_relationships` (contexto del proceso; nullable para objetivos colectivos)
- `created_by` uuid → FK `users`
- `title` string(200) — texto plano (D1)
- `description` text null
- `success_criteria` text **not null** — criterio de logro explícito (CA-1.1)
- `status` string(20) default `not_started` — enum `ObjectiveStatus` (RF-2)
- `due_at` timestamp null — fecha límite (CA-1.3)
- `achieved_at` timestamp null — cuándo se marcó logrado
- `proposed_by` uuid nullable FK users — propuesta del mentee (CA-1.5)
- `timestamps` + `softDeletes`
- Índices: `(owner_type, owner_id)`, `(status)`, `(due_at)`, `(relationship_id)`.
- Restricción de integridad (P18): check `scope='individual'` ⇒ `owner_type='user'` y `owner_id` not null; `scope='collective'` ⇒ `owner_type in (family_group,cohort,company)`; `success_criteria <> ''`.

### 6.2 Migración `..._create_objective_evidences_table.php` (aditiva)
Tabla `objective_evidences`: `id`, `objective_id` FK, `user_id` FK (quién aporta), `note` text null, `media_asset_id` uuid null FK, `timestamps`. Índice `(objective_id)`. Sin borrado en cascada destructivo: los recursos retirados no invalidan evidencias pasadas (P10, caso 11).

### 6.3 Trazabilidad (P5, RF-2)
`spatie/laravel-activitylog` (ya en `composer.json`) sobre `Objective` con `LogsActivity` + `logOnly(['status','due_at','success_criteria','owner_id'])` y `logOnlyDirty()`, `dontSubmitEmptyLogs()`. Cada transición guarda autor, fecha, valor anterior y nuevo (CA-2.1). La bitácora no se modifica por operaciones ordinarias (RNF-3). Alternativa evaluada en §12 (D-P5).

### 6.4 Datos derivados (no persistidos)
«Vencido» se calcula al vuelo (`due_at < now` y `status != achieved`) — CA-2.4. No se almacena estado `overdue`.

---

## 7. Archivos backend: creados / modificados y responsabilidad

### 7.1 Creados — dominio de objetivos (RF-1, RF-2)
| Archivo | Responsabilidad | RF |
|---|---|---|
| `backend/app/Enums/ObjectiveScope.php` | `individual`/`collective` + `label()` i18n | RF-1 |
| `backend/app/Enums/ObjectiveStatus.php` | `not_started`/`in_progress`/`achieved`/`abandoned` + transiciones válidas (`canTransitionTo`) | RF-2 |
| `backend/app/Models/Objective.php` | Modelo UUID, `owner` morph, scopes (`forOwner`, `individual`, `pending`, `overdue`), `LogsActivity`, casts, `isOverdue(now)` | RF-1, RF-2 |
| `backend/app/Models/ObjectiveEvidence.php` | Evidencias vinculadas a objetivo y usuario | RF-1 (CA-1.5) |
| `backend/database/factories/ObjectiveFactory.php` | Datos de prueba (estados, due_at, individual/colectivo) | RNF-9 |
| `backend/database/factories/ObjectiveEvidenceFactory.php` | Datos de prueba de evidencias | RNF-9 |

### 7.2 Creados — escritura (RF-1, RF-2, P3)
| Archivo | Responsabilidad | RF |
|---|---|---|
| `backend/app/Actions/CreateObjectiveAction.php` | Crea objetivo validando titular/colectivo y criterio; fija `created_by` | RF-1 |
| `backend/app/Actions/ProposeObjectiveAction.php` | Mentee propone (no otorga administración) | RF-1 (CA-1.5) |
| `backend/app/Actions/UpdateObjectiveAction.php` | Edita campos administrables | RF-1 |
| `backend/app/Actions/TransitionObjectiveStatusAction.php` | Valida transición, fija `achieved_at`, registra activity | RF-2 |
| `backend/app/Actions/AttachObjectiveEvidenceAction.php` | Vincula evidencia (nota/media) | RF-1 |

### 7.3 Creados — lecturas/persistencia de cálculo (RF-3..RF-11)
| Archivo | Responsabilidad | RF |
|---|---|---|
| `backend/app/Services/Progress/ProgressPanelService.php` | Orquesta la construcción del panel individual: consulta fuentes, llama a calculadoras puras, arma el DTO, cachea (RNF-5), registra errores por bloque (RNF-8) | RF-3..RF-8, RF-13, RF-14 |
| `backend/app/Services/Progress/MentorProgressService.php` | Panel de un mentee (reutiliza `MentorWorkspaceAccessService`) + banderas de cartera | RF-9 |
| `backend/app/Services/Progress/CollectiveProgressService.php` | Orquesta el agregado de colectivo | RF-10, RF-11 |
| `backend/app/Services/Progress/EligibleParticipantsResolver.php` | Determina miembros y participantes elegibles con pertenencia vigente (D5) | RF-10, RF-11, RNF-2 |
| `backend/app/Services/Progress/Calculators/ObjectiveMetricsCalculator.php` | **Puro**: % , conteo por estado, vencido, individuales vs colectivos | RF-4, RF-13, RF-14 |
| `backend/app/Services/Progress/Calculators/SessionMetricsCalculator.php` | **Puro**: sesiones realizadas (completed+asistencia, mes), programadas | RF-5, RF-14 |
| `backend/app/Services/Progress/Calculators/ActivityMetricsCalculator.php` | **Puro**: pendientes/vencidas/completadas, excluye `canceled`/`template` | RF-6 |
| `backend/app/Services/Progress/Calculators/CompetencyMetricsCalculator.php` | **Puro**: skills + OCEAN, fuentes distinguibles, entitlement | RF-7 |
| `backend/app/Services/Progress/Calculators/CommitmentMetricsCalculator.php` | **Puro**: compromisos en ventana 7 días, orden y zona horaria | RF-8 |
| `backend/app/Services/Progress/Calculators/CollectiveAggregateCalculator.php` | **Puro**: suma + media, umbral y supresión | RF-10, RF-11, RNF-2 |
| `backend/app/Services/Progress/Support/MetricValue.php` | **Puro**: value object normalizado (`state`, valor, numerador/denominador, periodo, fuente) | RF-13, RF-14 |

### 7.4 Creados — API y contrato (P17)
| Archivo | Responsabilidad | RF |
|---|---|---|
| `backend/app/Http/Controllers/Api/ProgressPanelController.php` | `show` (propio), `mentorShow`, `mentorOverview` | RF-3, RF-9 |
| `backend/app/Http/Controllers/Api/CollectiveProgressController.php` | `show` agregado de colectivo | RF-10, RF-11 |
| `backend/app/Http/Controllers/Api/ObjectiveController.php` | CRUD + status + proposals + evidences | RF-1, RF-2 |
| `backend/app/Policies/ObjectivePolicy.php` | `view`/`update`/`transition`/`propose`/`addEvidence` según titular, mentor asignado, responsable de colectivo, admin con permiso | RF-1, RF-2, RF-12 |
| `backend/app/Http/Requests/Objective/StoreObjectiveRequest.php` | Validación estricta del alta | RF-1 (CA-1.3/1.4) |
| `backend/app/Http/Requests/Objective/UpdateObjectiveRequest.php` | Validación de edición | RF-1 |
| `backend/app/Http/Requests/Objective/TransitionObjectiveStatusRequest.php` | Validación de estado destino | RF-2 |
| `backend/app/Http/Requests/Objective/ProposeObjectiveRequest.php` | Validación de propuesta del mentee | RF-1 (CA-1.5) |
| `backend/app/Http/Requests/Objective/StoreObjectiveEvidenceRequest.php` | Validación de evidencia | RF-1 |
| `backend/app/Http/Resources/ObjectiveResource.php` | Serialización de objetivo (con `is_overdue` derivado) | RF-1, RF-2, RF-13 |
| `backend/app/Http/Resources/ObjectiveEvidenceResource.php` | Serialización de evidencia | RF-1 |
| `backend/app/Http/Resources/ProgressPanelResource.php` | Serializa los 6 bloques; **garantiza el contrato sin datos ajenos** | RF-3..RF-8, RF-13, RF-14 |
| `backend/app/Http/Resources/CollectiveProgressResource.php` | Serializa agregado + supresión | RF-10, RF-11 |

### 7.5 Modificados — backend
| Archivo | Cambio | RF |
|---|---|---|
| `backend/routes/api.php` | Nuevas rutas `progress/*`, `mentor/users/{user}/progress`, `mentor/progress/overview`, `objectives/*`, `admin/progress/panel` | RF-1..RF-12 |
| `backend/app/Models/User.php` | Relaciones nuevas: `objectives()` (morph many por `owner`) y accesos a colectivos reutilizados | RF-1, RF-10 |
| `backend/database/seeders/RolesPermissionsSeeder.php` (o equivalente existente) | Permiso `progress.view.support` para admin (D11) | RF-12, P3 |
| `backend/config/progress.php` (nuevo) | `collective_privacy_threshold=3`, `commitments_window_days=7`, `cache_ttl` | RF-8, RF-10, RF-11, D8 |
| `backend/lang/{es,en,pt}/progress.php` + `objectives.php` (nuevos) | Mensajes de validación/errores del dominio (RF-15) | RF-15 |

> **Nota P2/P18:** ninguna migración modifica o elimina columnas existentes; todas son **aditivas** y reversibles.

### 7.6 Modificados — documentación
| Archivo | Cambio |
|---|---|
| `AGENTS.md` | Añadir entidad `Objective`, endpoints `progress/*`, `config/progress.php`, umbral de colectivo |
| `MEMORY.md` | Estado de la feature, decisiones D1–D11 y gotchas |
| `specs/001-progress-tracking-dashboard/spec.md` | Marcar dudas §9 resueltas por las decisiones D1–D11 (§2 de este plan) |

---

## 8. Funciones puras de lógica necesarias (RNF-9, P6, P11)

Definición de «pura»: **sin acceso a base de datos, red, reloj global ni aleatoriedad**; recibe colecciones ya cargadas y `now`/`timezone` como parámetros. Esto permite probar cada regla del Anexo A con datos conocidos sin contenedores.

Estructura de retorno transversal `MetricValue` (§9.0): `{state, value, numerator?, denominator?, period, source, incomplete?}`.

| # | Función (calculadora) | Entradas | Salida | Regla / Anexo A | RF |
|---|---|---|---|---|---|
| F1 | `objectiveAchievement(objectives)` | objetivos individuales | `MetricValue` (% + `numerator`/`denominator`) | logrados ÷ total; sin datos si total=0 | RF-4 |
| F2 | `objectivesByStatus(objectives)` | objetivos individuales | conteo por estado | Anexo A; `abandoned` permanece | RF-2, RF-4 |
| F3 | `isOverdue(objective, now)` | objetivo + now | bool | `due_at < now` y `status != achieved` | RF-2 (CA-2.4) |
| F4 | `classifyObjectives(objectives, now)` | objetivos | `{pending, overdue, achieved, abandoned}` | excluye soft-deleted | RF-3, RF-6 |
| F5 | `realizedSessions(sessions, attendeeMap, month, tz)` | sesiones + asistencia + rango mes | `MetricValue` | solo `completed` **y** `attended=true` | RF-5 |
| F6 | `scheduledSessions(sessions, now)` | sesiones | lista/count | `scheduled` con fecha futura | RF-5, RF-8 |
| F7 | `classifyActivities(challenges, pathProgress, now)` | retos + progreso de rutas | `{pending, overdue, completed}` | excluye `canceled`/`template`; sin `due_at` nunca vencida | RF-6 |
| F8 | `buildCompetencies(skills, ocean, oceanEntitled)` | skills, evaluación, flag entitlement | `{skills, ocean, state}` | fuentes distinguibles; OCEAN `no_data` si no completada | RF-7 |
| F9 | `buildUpcoming(sessions, activities, objectives, now, windowDays, tz)` | fuentes + now | lista ordenada | sesiones futuras + deadlines en 7 días | RF-8 |
| F10 | `collectiveAggregate(memberObjectives, threshold)` | objetivos por miembro elegible | `{sum, mean, contributing, eligible, suppressed}` | suma alcanzados/total + media %; supresión < umbral | RF-10, RF-11 |
| F11 | `shouldSuppressAggregate(eligibleCount, threshold)` | conteos | bool + motivo | umbral uniforme | RF-10 (CA-10.5), RF-11 (CA-11.3) |
| F12 | `metricState(recordsCount, computedValue)` | registros + valor | `'no_data'|'zero'|'value'|'incomplete'` | distingue ausencia de 0 | RF-13 |
| F13 | `resolvePeriod(kind, now, tz)` | tipo de indicador | `{from,to,label}` | mes actual / estado actual / 7 días | RF-14 |
| F14 | `percent(numerator, denominator)` | enteros | `int|null` | devuelve null (sin datos) si denom.=0 | RF-4, RF-13 |

**Funciones de autorización (puras respecto a IO, reciben flags ya resueltos)** — se apoyan en los helpers existentes:
- `canManageObjective(viewer, objective, relationship)` — RF-1/RF-12
- `canViewCollective(viewer, collective, membership)` — RF-10/RF-11/RF-12

---

## 9. Algoritmo en pseudocódigo

### 9.0 Normalización de indicadores (transversal, RF-13 + RF-14)
```
RESOURCE MetricValue:
  state:        "value" | "no_data" | "incomplete" | "suppressed"
  value:        number | null
  numerator:    number | null
  denominator:  number | null
  period:       { type: "current_month" | "current_state" | "next_7_days",
                  from: ISO | null, to: ISO | null, label: string }
  source:       string            # trazabilidad P6/P11
  incomplete:   bool              # faltó total o parte de una fuente

FUNCTION makeMetric(records, computed, period, source, incomplete=false):
  IF incomplete:               RETURN {state:"incomplete", value:computed,...}
  IF records IS EMPTY:         RETURN {state:"no_data", value:null,...}
  IF computed IS ZERO:         RETURN {state:"value", value:0,...}
  RETURN {state:"value", value:computed,...}

# Nota: "no_data" ≠ "0". Solo se muestra 0 si HAY registros y el valor es 0.
```

### 9.1 Panel individual (orquestador, RF-3..RF-8)
```
FUNCTION buildPanel(subjectUser, viewer, now, tz):
  phase = resolvePeriod("current_month", now, tz)          # RF-14

  objectives   = load objectives WHERE owner=subject AND scope="individual" AND NOT deleted
  relationships= load active/past relationships of subject
  sessions     = load sessions of subject's relationships (+ attendees)
  challenges   = load challenges of subject's relationships
  paths        = load learning progress of subject
  skills       = load UserSkill + SkillTag of subject
  ocean        = load PersonalityAssessment of subject
  oceanOK      = PlanEntitlementService.allows(subjectPlan, OCEAN)   # RF-7 CA-7.3

  blocks = {}
  blocks.objectives = makeMetric(
      objectives,
      F1.objectiveAchievement(objectives),                 # RF-4
      {type:"current_state"}, source:"objectives")
  blocks.objectives.by_status = F2.objectivesByStatus(objectives)
  blocks.objectives.overdue   = F4.classifyObjectives(objectives, now).overdue
  blocks.objectives.allow_proposal = viewerCanPropose(viewer, subject)

  blocks.sessions = makeMetric(
      sessionsOfMonth(sessions, phase),
      F5.realizedSessions(sessions, attendees, phase, tz), # RF-5
      phase, source:"sessions+attendance")
  blocks.sessions.scheduled = F6.scheduledSessions(sessions, now)

  blocks.activities = F7.classifyActivities(challenges, paths, now)  # RF-6
  blocks.activities.metric = makeMetric(allPendingOrOverdue, count, {current_state}, "challenges+paths")

  blocks.competencies = F8.buildCompetencies(skills, ocean, oceanOK)  # RF-7

  blocks.commitments = F9.buildUpcoming(sessions, challenges, objectives,
                                       now, config.commitments_window_days, tz)  # RF-8

  RETURN panelsFrom(blocks)   # ProgressPanelResource
```

**Degradación parcial (caso 15):** cada `load` se envuelve en try/catch; si una fuente falla, su bloque se marca `incomplete=true` y los demás se devuelven igual. Nunca pantalla en blanco.

### 9.2 Sesiones realizadas (RF-5, decisión §3.1.3)
```
FUNCTION realizedSessions(sessions, attendeeMap, phase, tz):
  count = 0
  FOR s IN sessions:
    IF s.status != "completed": CONTINUE                 # CA-5.2
    IF monthOf(s.scheduled_at, tz) != phase: CONTINUE    # mes calendario §3.1.4
    att = attendeeMap[s.id][subjectUser.id]
    IF NOT att OR att.attended != true: CONTINUE         # CA-5.1 asistencia confirmada
    count = count + 1
  RETURN count
```

### 9.3 Actividades y compromisos (RF-6, RF-8)
```
FUNCTION classifyActivities(challenges, pathProgress, now):
  pending = []
  FOR c IN challenges:
    IF c.status IN ["canceled","template"]: CONTINUE     # CA-6.5
    IF c.status IN ["assigned","in_progress"]:            # CA-6.1
       pending.push({type:"challenge", due_at:c.due_at,
                     overdue: c.due_at != null AND c.due_at < now})  # CA-6.2/6.3
  FOR p IN pathProgress:
    IF p.status == "completed": CONTINUE
    pending.push({type:"learning_module", due_at:null, overdue:false})
  RETURN group(pending, overdue)


FUNCTION buildUpcoming(sessions, challenges, objectives, now, window, tz):
  items = []
  FOR s IN sessions WHERE status=="scheduled" AND scheduled_at>now:   # CA-8.1
     items.push({kind:"session", at:s.scheduled_at})
  FOR c IN challenges WHERE c.due_at IN (now, now+window]:
     items.push({kind:"activity", at:c.due_at})
  FOR o IN objectives WHERE o.due_at IN (now, now+window]
        AND o.status NOT IN ["achieved","abandoned"]:                 # CA-8.2
     items.push({kind:"objective", at:o.due_at})
  RETURN sortBy(at ASC)     # CA-8.3; zona horaria normalizada a ISO
```

### 9.4 Competencias (RF-7, P9/P15)
```
FUNCTION buildCompetencies(skills, ocean, oceanEntitled):
  result.skills = map(skills, {tag, level, source:"skills"})          # CA-7.1
  IF NOT oceanEntitled:
     result.ocean = {state:"blocked", reason:"entitlement_missing"}   # CA-7.3
  ELSE IF ocean IS NULL OR ocean.completed_at IS NULL:
     result.ocean = {state:"no_data"}                                 # CA-7.4
  ELSE:
     result.ocean = {factors:O,C,E,A,N, assessed_at:ocean.completed_at,
                     source:"personality", test_version:ocean.test_version}
  RETURN result
```

### 9.5 Autorización del panel (RF-9, RF-12 — servidor, P3)
```
FUNCTION authorizePanel(viewer, subjectType, subjectId, relationshipId?):
  IF viewer.hasPermission("progress.view.support"):
     audit(viewer, "progress.support.view", subjectId)
     RETURN {ok, role:"support"}

  IF subjectType=="user":
     IF subjectId == viewer.id: RETURN {ok, role:"self"}            # mentee
     IF viewer.isMentor:
        rel = activeRelationship(mentor_id=viewer.id, source=subjectId, status="active")
        IF rel IS NULL:
           IF NOT explicitHistoryGrant(viewer, subjectId): RETURN deny(404)   # CA-9.1/9.3
        access = PlanEntitlementService.forModel(subject, "personal")
        IF NOT allows(access, MENTOR_WORKSPACE): RETURN deny(403,"workspace_locked")  # CA-9.2
        RETURN {ok, role:"mentor", relationship:rel}
     RETURN deny(404)

  IF subjectType IN ["family_group","cohort","company"]:
     IF MentorshipRelationship.userCanAccessSource(viewer, subjectType, subjectId):
        role = userCanManageSource(viewer,...) ? "manager" : "member"  # CA-10.2 / CA-11.1
        RETURN {ok, role}
     RETURN deny(403)                                                  # CA-11.2 / RF-12

  RETURN deny(403)
```
> La UI **nunca** decide acceso (RF-12 CA-12.3): los botones se ocultan solo como *progressive enhancement*.

### 9.6 Agregado de colectivo con supresión (RF-10, RF-11, RNF-2)
```
FUNCTION buildCollective(collective, role, now, threshold):
  members = eligibleMembers(collective)          # D5: activos, pertenencia vigente
                                                 #     con >=1 objetivo individual
  threshold = config("progress.collective_privacy_threshold")  # 3

  eligibilityCount = count(members)

  # Supresión uniforme para TODOS los roles, incluido responsable (decisión §3.1.5)
  IF F11.shouldSuppressAggregate(eligibilityCount, threshold):
     RETURN {suppressed:true, reason:"insufficient_base",
             metrics:null, base:null}            # no revela ni el tamaño exacto (CA-11.3)

  IF all members have no individual objectives:  # CA-10.3
     RETURN {suppressed:false, metrics:{state:"no_data"}, base:{...}}

  agg = F10.collectiveAggregate(objectivesByMember(members), threshold)
  # agg = {sum:{achieved,total}, mean: percent, contributing_members}
  RETURN {suppressed:false,
          metrics:{ mean: agg.mean, sum: agg.sum, period:{current_state} },
          base:{ eligible_members:eligibilityCount,
                 contributing_members: agg.contributing_members }}   # CA-10.4
```
> Fuera de la media individual: los objetivos **colectivos** se reportan como línea propia del colectivo (D7), nunca mezclados con la media de personas.

### 9.7 Caché y reutilización (RNF-5)
```
FUNCTION cachedPanel(subjectKey):
  key = "progress:panel:" + subjectKey + ":" + currentMonthBucket(now)
  IF cache.has(key): RETURN cache.get(key)
  payload = buildPanel(...)
  cache.put(key, payload, ttl=config("progress.cache_ttl"))
  RETURN payload

# Invalidación: observer en Objective/Session/Challenge/UserLearningProgress/PersonalityAssessment
# que olvida las claves del sujeto afectado. Redis nunca es fuente de verdad (P1).
```

---

## 10. i18n (RF-15)

- **Backend:** `backend/lang/{es,en,pt}/progress.php` y `objectives.php` para mensajes de validación/errores y etiquetas de estado. El middleware de locale existente (`SetRequestLocale`) ya selecciona el idioma por cabecera `Accept-Language`/`X-MIS-Locale`.
- **Frontend:** namespace `progress` añadido a `frontend/src/i18n/messages/{es,en,pt}/common.json`. Nada de claves crudas (CA-15.2): `useTranslations('progress')` y fallback a `es`.
- El contenido del objetivo (`title`/`description`) es texto del autor (D1) y **no** se traduce; se marca explícitamente como tal en la UI.

---

## 11. Cómo se pinta en la interfaz (RF-3, RF-9, RF-10, RF-11, RNF-6, RNF-7)

### 11.1 Estructura de páginas
| Archivo (frontend) | Responsabilidad | RF |
|---|---|---|
| `src/app/[locale]/dashboard/progreso/page.tsx` | **RSC shell** + componente cliente `ProgressPanel` del usuario propio; incluye agregado si es miembro | RF-3, RF-11 |
| `src/app/[locale]/dashboard/progreso/loading.tsx` | Esqueleto de carga | RF-3 (CA-3.2) |
| `src/app/[locale]/dashboard/progreso/error.tsx` | Error recuperable con reintento | RF-3 (CA-3.3) |
| `src/app/[locale]/dashboard/mentor/users/[id]/progress/page.tsx` | Panel del mentee (o pestaña nueva en `MentorUserProfileTabs`) | RF-9 |
| `src/app/[locale]/dashboard/mentor/progress/page.tsx` | Lista de cartera con banderas (vencidos/sin actividad) | RF-9 (CA-9.4) |

### 11.2 Componentes (`frontend/src/components/progress/`)
| Componente | Responsabilidad | RF |
|---|---|---|
| `ProgressPanel.tsx` | Orquesta los bloques, estados de carga/error/parcial; `aria-live` | RF-3 |
| `ObjectivesBlock.tsx` | Lista + estados + formulario de gestión según rol | RF-1, RF-2 |
| `ObjectiveForm.tsx` | Alta/edición (react-hook-form + Zod) | RF-1 |
| `ObjectiveProposalForm.tsx` | Propuesta del mentee (sin administración) | RF-1, RF-12 |
| `ObjectiveEvidenceForm.tsx` | Aportar evidencia (nota/archivo) | RF-1 |
| `ObjectiveStatusControl.tsx` | Transición de estado con historial visible | RF-2 |
| `ObjectiveAchievementCard.tsx` | % con numerador/denominador visibles | RF-4 |
| `SessionsMetricCard.tsx` | Realizadas (mes) vs programadas, distinguidas | RF-5 |
| `ActivitiesCard.tsx` | Pendientes y vencidas, badge de vencido | RF-6 |
| `CompetenciesCard.tsx` | Dos subsecciones: «Skills» y «Personalidad»; bloqueo OCEAN con motivo | RF-7 |
| `UpcomingCommitmentsCard.tsx` | Lista ordenada con fecha/zona horaria | RF-8 |
| `MenteeFlagsList.tsx` | Cartera del mentor con banderas | RF-9 |
| `CollectiveProgressCard.tsx` | Media + suma + base; estado suprimido con motivo | RF-10, RF-11 |
| `MetricValue.tsx` | Pinta `valor`, `0` o «sin datos» de forma distinta y accesible | RF-13 |
| `PeriodBadge.tsx` | Etiqueta «Mes actual» / «Estado actual» / «Próximos 7 días» | RF-14 |

### 11.3 Cómo se ve el panel individual
- **Encabezado** con nombre y `PeriodBadge` por bloque (RF-14 CA-14.2/14.3/14.4): «Estado actual» en objetivos y competencias (sin selector de periodo), «Mes calendario actual» en sesiones, «Próximos 7 días» en compromisos.
- **Fila de tarjetas**: % de objetivos (con `12/20` debajo para conciliar, CA-4.3) · sesiones realizadas vs programadas · pendientes/vencidas · compromisos.
- **Bloques**: objetivos (lista con estado y vencido derivado), competencias (skills y OCEAN separadas por fuente).
- **`MetricValue`** nunca muestra `0` cuando `state="no_data"`; muestra el literal traducido «Sin datos» con icono distinto y `aria-label` (RF-13 CA-13.1/13.2).
- **Parcial** (caso 15): si un bloque llegó `incomplete`, se muestra con aviso «Datos incompletos» y el resto normal (RF-13 CA-13.3).
- **Estado vacío guiado** para usuario sin proceso activo (caso 16): CTA a completar onboarding/explorar mentores, no un error.

### 11.4 Cómo se ve el panel del mentor (RF-9)
- Lista de mentees (`MenteeFlagsList`) con banderas: «vencidos», «sin actividad», «pendientes»; clic abre el panel del mentee.
- Si el plan del mentee no habilita el espacio del mentor, el bloque se muestra **bloqueado con motivo** («El plan del mentee no incluye el espacio del mentor»), no en blanco (CA-9.2).
- Acceso denegado (no asignado) → pantalla `404` (no revela datos, CA-9.3).

### 11.5 Cómo se ve el agregado de colectivo (RF-10, RF-11)
- **Responsable:** media del % (cifra principal) + suma logrados/total como conciliación + `contributing_members` (CA-10.4) + objetivos colectivos como línea propia; **sin** desglose individual (CA-10.2).
- **Miembro:** mismas métricas agregadas; **sin** ninguna columna de personas (CA-11.1).
- **Suprimido (< umbral):** tarjeta con mensaje explícito «Se necesitan al menos 3 participantes para mostrar este agregado», **sin** cifras ni tamaño de base (CA-10.5/CA-11.3). Idéntico para miembro y responsable.

### 11.6 Accesibilidad y responsive (RNF-6, RNF-7, P16)
- Reutiliza `components/ui/*` (Button, Card, Badge, Alert, Tabs, Spinner, Input, Select, Textarea).
- Navegación por teclado en listas y formularios; `focus-visible:ring`; `role="alert"` en errores; `aria-live="polite"` para carga y supresión.
- Estados distinguidos **por texto + icono + color** (no solo color) — P16 y medida compensatoria de tendencias.
- Mobile-first: tarjetas apiladas en 1 columna, grid a 2/4 columnas en `sm`/`xl`; sin tablas horizontales.

### 11.7 Capa de datos y tipos (frontend)
| Archivo (frontend) | Responsabilidad | RF |
|---|---|---|
| `src/types/progress.ts` | Tipos TS del contrato (`ProgressPanel`, `MetricValue`, `CollectiveProgress`, `Objective`) | todos |
| `src/lib/progress.ts` | **Funciones puras**: mapa de `state`→UI, formateo de % y `no_data`, orden de compromisos, etiquetas de periodo, mensaje de supresión | RF-13, RF-14 |
| `src/lib/progress.test.ts` | Vitest de las funciones puras | RNF-9 |
| `src/lib/schemas/objective.ts` | Zod (título, criterio obligatorio, fecha, scope) | RF-1 |
| `src/hooks/use-progress.ts` | Queries/mutaciones con react-query; invalida claves tras mutar | RF-1, RF-3, RF-9 |
| `src/lib/query-keys.ts` (modificar) | Claves `progress`, `progressPanel(userId)`, `collectiveProgress(type,id)`, `objectives(subject)` | RF-3..RF-11 |
| `src/components/layout/AppShell.tsx` (modificar) | Enlace de navegación «Progreso» (traducido) | RF-3 |
| `src/i18n/messages/{es,en,pt}/common.json` (modificar) | Namespace `progress` | RF-15 |

---

## 12. Decisiones técnicas justificadas y alternativa descartada

| # | Decisión | Por qué | Alternativa descartada | Principio |
|---|---|---|---|---|
| DT-1 | **Objetivo como entidad propia** (`objectives`) | Sin criterio de logro y estado no hay progreso por evidencias ni trazabilidad | Reutilizar `MentorshipRelationship.objectives` (texto libre) o `Challenge.objective` | P6, P5, RF-1 |
| DT-2 | **`owner` morfológico** con tipos canónicos (`user`/`family_group`/`cohort`/`company`) | Coherencia con `MentorshipRelationship.source`; soporta individuales y colectivos con una sola FK lógica | Dos columnas nullable (`user_id` + `collective_id` + tipo) | P1, P18, RF-1 |
| DT-3 | **Funciones puras + servicio orquestador** separadas | Reglas del Anexo A testeables sin DB con datos conocidos (RNF-9); evita lógica de negocio en controladores/consultas | Calcular dentro de `Eloquent` con `withCount`/scopes | P6, P11, RNF-9 |
| DT-4 | **Cálculo en el servidor** (Laravel) | Única fuente de verdad y autorización real; el cliente no puede garantizar reglas | Calcular en el cliente como los dashboards actuales | P3, P6, RNF-4 |
| DT-5 | **Caché Redis de payload** con TTL corto e invalidación por observer | RNF-5 sin duplicar estado persistente | Tabla materializada de agregados | P1 |
| DT-6 | **activitylog** (ya instalado) para transiciones | Trazabilidad P5 sin dependencia nueva | Tabla `objective_events` dedicada | P2, P5 |
| DT-7 | **Umbral de 3** como constante de config | «Umbral inicial» sin panel de reglas (fuera de alcance) | Configurable por organización en BD | D8, RF-10 |
| DT-8 | **Supresión que oculta también el tamaño de base** | Bajo el umbral, el conteo puede inferir valores individuales | Mostrar el conteo con cifras censuradas | P4, RNF-2 |
| DT-9 | **Reutilizar autorización de `MentorshipRelationship` + `MentorWorkspaceAccessService`** | Evita duplicar checks de propiedad (AGENTS.md) | Nuevo sistema de permisos paralelo | P2, P3 |
| DT-10 | **Página nueva que complementa** los dashboards | Spec §7: no reemplazar; menor riesgo de regresión | Rediseñar `dashboard/personal` y `dashboard/mentor` | P2 |
| DT-11 | **`404` para recursos no visibles** | No revelar existencia de recursos ajenos | `403` con detalle del motivo | P3, RF-12 |
| DT-12 | **Vencido derivado, nunca persistido** | CA-2.4: es una condición frente a la fecha, no un estado | Estado `overdue` en la columna `status` | P6, P11 |
| DT-13 | **Texto plano** para título/descripción (D1) | Lo escriben usuarios, no admins; traducir no es viable en v1 | JSONB i18n como `LearningPath` | D1, P4 |
| DT-14 | **Mes calendario en la zona horaria del sujeto; almacenamiento UTC** | Evita errores de límite de mes (P8/RF-14) | Mes en UTC fijo | P8, P11 |
| DT-15 | **Acceso de soporte por permiso explícito + auditoría** | P3: los privilegios admin se conceden explícitamente | Admins ven todo por defecto | P3, P5 |
| DT-16 | **Sin tests de componentes (no hay Testing Library instalada)**: la lógica del cliente se extrae a `lib/progress.ts` y se testea con Vitest | Mantiene la estrategia actual del repo y evita dependencias nuevas (P2) | Añadir `@testing-library/react` (dependencia nueva) | P2, RNF-9 |

---

## 13. Autorización, privacidad y observabilidad (RF-12, RNF-1..3, RNF-8)

- **RF-12 / RNF-1:** toda ruta pasa por middleware `auth:sanctum`+`active` y por autorización de recurso (policy o `MentorWorkspaceAccessService`). La respuesta nunca incluye datos de sujetos no autorizados. El ocultado de UI es solo cosmético (CA-12.3).
- **RNF-2:** regla única `shouldSuppressAggregate` aplicada a miembro, responsable y (si se habilitara) mentor. El agregado jamás devuelve arrays de miembros.
- **RNF-3:** `LogsActivity` marca las transiciones; ninguna ruta de negocio edita la bitácora.
- **RNF-8:** logs `Log::info/warning` estructurados por bloque y por denegación (`progress.block_failed`, `progress.access_denied`), con `user_id`/`subject_id` pero **sin** contenido de objetivos ni datos personales.

---

## 14. Casos límite de la spec §6 y su cobertura

| # (spec) | Caso | Punto del plan |
|---|---|---|
| 1 | Sin objetivos → «sin datos», no `0 %` | F1/F12, §9.0, §11.3 |
| 2 | Denominador cero en agregado | F10 (`mean=null`), §9.6 |
| 3-4 | `scheduled` o `completed` sin asistencia no cuentan | F5, §9.2 |
| 5 | Objetivo vencido no logrado | F3/F4, §9.1 |
| 6 | Actividad sin fecha límite nunca vencida | F7, §9.3 |
| 7 | Colectivo < 3 → supresión para todos | F11, §9.6, §11.5 |
| 8 | Miembro sin permiso → denegado sin filtrar | §9.5, §13 |
| 9-10 | OCEAN sin entitlement / no completada | F8, §9.4 |
| 11 | Fuente archivada no rompe el panel ni invalida evidencias | §6.2 (evidencias sin cascada), degradación §9.1 |
| 12 | Relación finalizada → mentor pierde acceso salvo historial explícito | §9.5 (`explicitHistoryGrant`), CA-9.1 |
| 13 | Zona horaria consistente | F13, DT-14 |
| 14 | Cambio de colectivo refleja pertenencia vigente | `EligibleParticipantsResolver` (D5) |
| 15 | Datos parciales → degradación por bloque | §9.1 (try/catch por fuente), `incomplete` |
| 16 | Sin proceso activo → estado vacío guiado | §11.3 |
| 17 | `canceled`/`template` excluidos | F7, CA-6.5 |
| 18 | `completed` sin asistencia no cuenta | §9.2 |
| 19 | Objetivo colectivo fuera del % individual | F1 (filtra `individual`), §9.6 (D7) |
| 20 | Sin permiso de administración → denegado en servidor | `ObjectivePolicy`, §9.5 |

---

## 15. Estrategia de tests (RNF-9, P19)

### 15.1 Unit backend (Pest, `backend/tests/Unit/Progress/`)
Sobre **funciones puras**, con datos conocidos y `now`/`tz` inyectados (sin DB):
- `ObjectiveMetricsCalculatorTest` — F1..F4, F14: total 0 → `no_data`; 0 → `zero`; `abandoned` en denominador; `achieved` no vencido (CA-2.4/CA-4.2/CA-13).
- `SessionMetricsCalculatorTest` — F5/F6: `scheduled` no cuenta; `completed` sin asistencia no cuenta; mes calendario correcto con zonas límite.
- `ActivityMetricsCalculatorTest` — F7: excluye `canceled`/`template`; sin `due_at` no vencida; ruta no completada.
- `CompetencyMetricsCalculatorTest` — F8: sin skills → `no_data`; OCEAN no completada → `no_data`; sin entitlement → `blocked`.
- `CommitmentMetricsCalculatorTest` — F9: límite de 7 días exacto; orden por fecha; excluye logrados/abandonados.
- `CollectiveAggregateCalculatorTest` — F10/F11: base 3 muestra; base 2 suprime; media sin miembros con datos → `no_data`; suma conciliable.

### 15.2 Feature backend (Pest, `backend/tests/Feature/`)
- `ObjectiveTest.php` — **RF-1/RF-2:** creación válida; rechazo sin criterio o sin titular (422); transición registra autor/fecha/estado anterior y nuevo; revertir `achieved` conserva histórico.
- `ObjectiveAuthorizationTest.php` (o dentro de los `Security*`) — **RF-1/RF-12:** mentee propone y aporta evidencias pero **no** administra (403); mentor administra solo sus mentees; responsable administra colectivos de su ámbito; ajeno → 404/403.
- `ProgressPanelTest.php` — **RF-3..RF-8, RF-13, RF-14:** panel propio devuelve los 6 bloques; `no_data` vs `0`; periodo visible; bloque incompleto no tumba el resto; usuario sin proceso → estado vacío.
- `MentorProgressTest.php` — **RF-9:** mentor ve su mentee; mentor ajeno → 404; plan sin `mentor_workspace` → bloqueo con motivo; banderas de cartera.
- `CollectiveProgressTest.php` — **RF-10/RF-11/RNF-2:** responsable ve agregado con base ≥3; miembro ve solo agregado; base <3 suprime para ambos; no-miembro → 403; colectivo sin objetivos → `no_data`.
- `ProgressSecurityTest.php` — **RF-12:** IDOR (manipular `subject_id`/`{id}`), admin sin permiso → 403, admin con permiso registra auditoría.

Todas las llamadas externas (matching, etc.) se falsean con `Http::fake()` (convención del repo). Job/observer de invalidación de caché cubierto con `Cache::spy()`/`Cache::fake()`.

### 15.3 Frontend (Vitest, `frontend/src/lib/progress.test.ts`)
- Mapa `state`→UI: `no_data` ≠ `0`; `incomplete` marca aviso.
- Formateo de %/media con `null` → «sin datos».
- `resolvePeriodLabel` para los tres tipos y fallback de idioma.
- Orden de compromisos y manejo de fechas/zona.
- `suppressionMessage` para base insuficiente.

### 15.4 Matriz RF → test (resumen)

| RF | Unit | Feature | Vitest |
|---|---|---|---|
| RF-1, RF-2 | F1–F4 | Objective*, ObjectiveAuthorization | — |
| RF-3 | F12/F13 | ProgressPanel | ProgressPanel UI states |
| RF-4 | F1/F14 | ProgressPanel | formateo % |
| RF-5 | F5/F6 | ProgressPanel | — |
| RF-6 | F7 | ProgressPanel | — |
| RF-7 | F8 | ProgressPanel | — |
| RF-8 | F9 | ProgressPanel | orden |
| RF-9 | — | MentorProgress | — |
| RF-10, RF-11 | F10/F11 | CollectiveProgress | supresión |
| RF-12 | — | ProgressSecurity, ObjectiveAuthorization | — |
| RF-13 | F12 | ProgressPanel | no_data vs 0 |
| RF-14 | F13 | ProgressPanel | etiquetas periodo |
| RF-15 | — | (asserts de mensajes es/en/pt) | fallback |

**Comandos:** `make test-backend` (Pest), `make test-frontend` (`pnpm test`), focalizados por archivo. Nada se considera listo si fallan (P19).

---

## 16. Rendimiento y observabilidad (RNF-5, RNF-8)

- Consultas con `select` de columnas necesarias y `with` para evitar N+1; un panel individual no dispara más de un puñado de consultas acotadas.
- Caché de lectura por sujeto + bucket temporal; invalidación por observer en los modelos fuente (objetivos, sesiones, retos, progreso de rutas, evaluación de personalidad).
- Sin WebSockets ni exportación (fuera de alcance §7).
- Logs estructurados por bloque fallido y por denegación; sin secretos ni datos personales (P20).

---

## 17. Fuera de alcance y excepciones formales

Fuera de v1 (spec §7): tiempo real, exportación de reportes, IA predictiva, tendencias históricas, configuración de reglas por admin, app nativa, gamificación.

**Excepción formal (constitución §5):** la omisión de «tendencias» de P16 se compensa con RNF-6/DT y con el estado explícito `sin datos`/`incompleto` (RF-13). Responsable de aprobación: cliente/producto; revisión al introducir series temporales.

---

## 18. Actualizaciones de documentación al finalizar

- `AGENTS.md`: entidad `Objective`, rutas `progress/*`, `config/progress.php`, umbral de colectivo, permiso `progress.view.support`.
- `MEMORY.md`: estado de la feature + decisiones D1–D11/DT-1–16.
- `specs/001-progress-tracking-dashboard/spec.md`: marcar resueltas las dudas §9 con D1–D11.

---

## 19. Checklist de criterios obligatorios (constitución §4)

- [x] Cumple los principios aplicables (P1–P20 mapeados en §1).
- [x] Respeta la arquitectura existente; sin dependencias nuevas (P2, DT).
- [x] Implementa autorización en el servidor y protección de datos (§9.5, §13).
- [x] Define reglas de negocio y criterios de aceptación (Anexo A → §8/§9).
- [x] Incluye pruebas automatizadas de comportamientos críticos (§15).
- [x] Conserva integridad y trazabilidad de datos (§6, activitylog).
- [x] Documenta migraciones, dependencias y cambios de API (§6, §5, §18).
- [x] Permite diagnosticar fallos sin exponer información sensible (§16).

---

## 20. Orden de implementación sugerido (sin escribir código aún)

1. **Datos y dominio:** enums, migraciones, modelos, factories, policy.
2. **Núcleo puro:** calculadoras + `MetricValue` con sus tests unit (feedback rápido, sin DB).
3. **Escritura:** Actions + Form Requests + `ObjectiveController` + tests de permisos.
4. **Lectura:** `ProgressPanelService`, `CollectiveProgressService`, resources, controllers, rutas + tests feature.
5. **Contrato frontend:** tipos, `lib/progress.ts` + tests, `query-keys`, `use-progress`.
6. **UI:** componentes `progress/*`, páginas, integración en AppShell/mentor, i18n.
7. **Cierre:** degradación parcial, caché, observabilidad, accesibilidad responsive, actualizar docs.

Cada paso termina con sus tests en verde (`make test-backend`, `make test-frontend`).
