# Plan de implementación — Spec 014: Evidencia de evaluaciones y competencias

**Estado:** aprobado para implementación (2026-11-14) · **Spec:** aprobada
**Base:** Spec 001 v0.5.1 aprobada; baseline v0.3.0 implementado.
**Dependencia:** independiente de 012/013; 015 consumirá los eventos que esta ampliación produzca.
**Bloqueo explícito:** la retención de evento mínimo tras eliminación NO se implementa hasta que la política general de conservación/eliminación esté identificada y documentada. No se fija plazo ni se propone una política aquí.
**Enmienda 2026-11-15 (aprobada 2026-11-15):** el prerrequisito se cumple con `docs/politica-retencion.md` v1.0.0, que está vigente. La §9 de este plan describe cómo implementar la retención del evento mínimo con lo que fija esa política. El plazo no lo decide este plan, sino la política. El bloqueo de la línea anterior queda levantado únicamente para lo que cubre la §9.

## 1. Alcance y archivos

Agregar vínculo explícito entre resultado individual y competencia ya existente, sin crear instrumentos, interpretaciones ni niveles. Los orígenes distintos se mantienen distintos: `AssessmentResult` con `Assessment`; `PersonalityAssessment` con `test_version`. La vista reusa datos de skills/OCEAN del bloque `ProgressPanelService` y entitlements actuales.

| Archivo o área | Responsabilidad | RF |
|---|---|---|
| `backend/database/migrations/*competency_assessment_links*` | Relación many-to-many tipada con FKs nullable, `user_id`, checks source/competency XOR, índices únicos parciales y restricciones de cardinalidad/duplicados. | RF-014.2–.6, .9 |
| `backend/app/Models/{CompetencyAssessmentLink,AssessmentResult,PersonalityAssessment,UserSkill}.php` | Relaciones inversas, origen preservado, competencia skill existente o factor OCEAN; no se introduce catálogo nuevo. | RF-014.1–.6 |
| `backend/app/Actions/{Set,Remove}CompetencyAssessmentLinkAction.php`, Request/Policy | Autorización y validación de resultado/competencia/sujeto; cambio auditable y sin efecto en nivel de competencia. | RF-014.3–.5, .9 |
| `backend/app/Services/Progress/{CompetencyEvidence,AssessmentEvidence}Service.php` y calculadora pura | Resuelve exclusivamente relaciones explícitas y clasifica evidencia `current`/`incomplete` según fuente y entitlement. | RF-014.1–.3, .5–.6, .9 |
| `backend/app/Observers/*Assessment*Observer.php` y event logging | Detecta solo cambios de estado realmente persistidos con vínculo vigente; no inventa transiciones. Emite evento whitelisted para Spec 015. | RF-014.4, .6–.8 |
| `backend/app/Http/{Controllers/Api,Requests,Resources}/Progress/*`, `backend/routes/api.php` | Contrato de lectura y mutaciones autorizadas con source discriminator, fecha/contexto disponible, no score/respuestas. | RF-014.1–.6, .9 |
| `backend/tests/{Unit,Feature}/Progress/*` | Cardinalidades, fuentes, autorización, contexto incompleto y privacidad/minimización. | Todos desbloqueados |
| `frontend/src/{types,lib,hooks,components/progress,i18n/messages/*}` | Presentar contexto como evidencia vinculada, editar relación según permiso y comunicar fuente/estado; no editar nivel automáticamente. | RF-014.1–.6, .9; RNF-014.3, .6 |

## 2. Modelo de relación e integridad

Propuesta de tabla `competency_assessment_links` con UUID, `user_id`, `assessment_result_id` nullable, `personality_assessment_id` nullable, `skill_tag_id` nullable, `ocean_factor` nullable y timestamps. Restricciones CHECK exigen exactamente un origen de resultado y exactamente un tipo de competencia: `skill_tag_id` **o** `ocean_factor` en `{O,C,E,A,N}`. FKs no destructivas a los modelos existentes; si un source se elimina no hacer cascade que borre silenciosamente historial. Índices únicos parciales separan pares de catálogo/OCEAN y skill/factor; índice por `(user_id, ...)` soporta lectura y acceso. No duplicar el nivel: skill identifica `SkillTag` y el nivel vigente sigue viniendo de `UserSkill`.

La base garantiza cardinalidad/ausencia de duplicados, tipos permitidos e integridad de cada FK; una Action valida además que origen, competencia y `user_id` pertenezcan al mismo sujeto. El acceso cross-row a titularidad no se confía a la UI. Las migraciones son aditivas, con UUID compatibles y rollback probado sobre datos de referencia.

## 3. Reglas puras y pseudocódigo

```text
completedSource(source): source.completed_at != null
evidenceState(link, source):
  if source missing because soft/hard deletion: no active evidence; deletion retention gate applies
  if completedSource(source): current
  else: incomplete (retain link; never current evidence)

buildCompetencyEvidence(links, catalogResults, personalityResults, skills, entitlement):
  for each explicit link only:
    validate source discriminator; keep AssessmentResult+Assessment or PersonalityAssessment+test_version
    if OCEAN and !entitlement: return blocked reason under existing policy
    include contextual fields/date only; omit answers, scores and raw_scores
  never write UserSkill.level or infer links
```

Functions accept normalized arrays and `now` as an explicit parameter if presentation requires a derived date classification; no database, network, global clock or mutations inside calculators. The source state is `completed_at != null`, as evidenced by current model. The result representation never fabricates `Assessment` for personality results.

## 4. Operation flow and contract

1. Resolve authenticated actor and subject. Reuse current progress/mentor authorization, relationship validity, history access, and `PlanEntitlementService` (including OCEAN entitlement); no new permission is granted.
2. Validate source is a completed individual result, belongs to subject, and is an actual `AssessmentResult` or `PersonalityAssessment`. Validate target is an existing `SkillTag` or allowed OCEAN factor. Reject mismatch/duplicate with validation error.
3. Apply create/change/retire in a DB transaction; preserve pre/post relation (absence for creation/removal) in append-oriented existing `spatie/laravel-activitylog` event. Do not mutate `UserSkill.level` or source result. Invalidate subject progress cache.
4. Read resource includes only linked results with source discriminator, instrument reference or `test_version`, completion date and context already authorized. Excludes answers, score and raw OCEAN payload by default.
5. If source status changes while link exists, record an immutable event only when a real persisted transition is available; retain the link and render incomplete. Existing APIs do not define an incomplete-result transition, so no synthetic state mutation is added.
6. Deletion retention is a separate gated capability: no event payload store, actor pseudonym mapping, retention timer, or deadline is implemented in this plan before the policy document exists. No current deletion route is added.

Potential API: `GET /progress/competencies/evidence`, `POST /progress/competencies/evidence-links`, `PUT /progress/competencies/evidence-links/{id}`, `DELETE /progress/competencies/evidence-links/{id}` and authorized mentor equivalents or a shared subject-scoped route. Requests use Form Requests; resources omit sensitive assessment content; errors do not disclose whether an unrelated result exists.

## 5. Decisiones técnicas y alternativas descartadas

| Decisión | Alternativa descartada | Justificación |
|---|---|---|
| Fuente discriminada en FK `AssessmentResult` vs `PersonalityAssessment` | Un resultado polimórfico opaco, o crear un `Assessment` falso para OCEAN | FKs/contratos claros y fidelidad de procedencia conforme a RF-014.2/RF-014.3. |
| Competencia = `SkillTag` o factor OCEAN existente; nivel skill se lee de `UserSkill` | Nueva entidad de competencia o recalcular/actualizar niveles | No se inventa catálogo ni se modifica nivel (RF-014.5). |
| FK/checks/índices únicos parciales en PostgreSQL + validación de sujeto en Action | Validación solo en PHP o JSON de vínculos | Evita enlaces corruptos y duplicados bajo concurrencia (P18). |
| Evento de vínculo en `activitylog` existente, consumible por Spec 015 | Crear una cronología duplicada en esta spec | Reusa dependencia instalada, conserva autor/fecha/diff y evita doble fuente (P2/P5). |
| No persistir answers/score en recursos o log | Serializar el modelo completo de resultado | Minimización, privacidad y separación de contexto de evidencia (P4/P9). |
| Retención de eliminación bloqueada hasta documentación formal | Asumir plazo, conservar actor identificable o implementar borrado ad hoc | Constituye prerrequisito de producto/privacidad; la implementación no puede decidirlo (RF-014.8). |

## 6. Seguridad, observabilidad y rendimiento

Servidor valida sujeto, estado de relación mentor, permiso de historial, vínculo, fuente y entitlement en cada consulta/mutación. No usar solo la policy de UI ni ID recibido. Las respuestas evitan datos de otro sujeto; denegaciones loguean `progress.access_denied` con excepción/tipo/IDs mínimos, no evaluación o competencia descriptiva. Cambios autorizados registran actor/momento/vínculo anterior y posterior. No se cambia ni revoca permiso de objetivos.

El acceso a fuentes se hace en lotes/eager loading con índices por sujeto y origen; se cachea conforme a la invalidación de progreso existente. Las lecturas que formen parte de mapa/panel se miden con benchmark conjunto: 50 consultas simultáneas, p95 ≤2 s. UI responsive a 320 px, teclado, foco y estados visibles no solo por color. Las futuras métricas/logs jamás incluyen respuestas, puntajes, raw_scores ni secretos.

## 7. Estrategia de tests (planificada; no ejecutada)

- **Unit Pest:** cada `completed_at`, fuente de catálogo/personality, `Assessment`/`test_version`, multiplicidad, `incomplete`, sin link=no evidence, entitlement, cero mutaciones a niveles; RF-014.1–.6, .9.
- **Feature Pest/PostgreSQL:** CHECK/XOR, FKs, índices/duplicados, many-to-many, titularía cruzada, source incompleto, mentor asignado/ajeno, sujeto equivocado, IDs manipulados y denegación sin cambio; RNF-014.1, .5.
- **Trazabilidad:** assert activity log registra actor, instante y relation before/after con ausencia explícita; prueba source state observer solo con cambio real y link activo; no causalidad ni nivel mutado; RF-014.4–.6.
- **Privacy:** serializer y activity properties no contienen `answers`, `score`, `raw_scores`; no crear fixture/código que retenga resultado eliminado mientras el prerrequisito no exista. La retención post-eliminación queda sin test de implementación hasta documentar política; se mantiene como gate, no como PASS ficticio.
- **Vitest/a11y/i18n:** fuente y estado diferenciables, relación ausente/incompleta, niveles unchanged, traducciones es/en/pt, teclado/foco/320 px. P95 del padre se verifica con benchmark posterior, no en esta fase SDD.

## 8. Trazabilidad RF → partes

| Parte | RF cubiertos |
|---|---|
| Tabla/modelos y verificadores de fuente | RF-014.2–.3, .5–.6 |
| Actions, policy y registro de cambios | RF-014.3–.5, .9 |
| Lectura de evidencia contextual y UI | RF-014.1–.3, .5–.6, .9 |
| Events del resultado con relación activa | RF-014.4, .6–.7 (solo transiciones disponibles) |
| Retención de eliminación | RF-014.7–.8: política v1.0.0 vigente; implementación según §9 (enmienda aprobada 2026-11-15), tareas T014.10–T014.13 |

## 9. Enmienda — Retención del evento mínimo de eliminación (RF-014.7–.8)

**Fuente normativa:** `docs/politica-retencion.md` v1.0.0, §4, §5, §6, §7 y §9. Esta sección solo aplica lo que fija la política y no añade plazos ni categorías nuevas. Cubre la retención y la identificación audit-only. Mostrar el evento en la cronología del historial corresponde a la Spec 015 (T015.10).

### 9.1. Archivos y responsabilidades

| Archivo o área | Responsabilidad | RF |
|---|---|---|
| `backend/database/migrations/*evidence_deletion_events*` | Dos tablas. **`evidence_deletion_events`**: UUID, `subject_id` (FK `users`, `cascadeOnDelete`), `source_type`, `competency_type`, `competency_key` (string sin FK, para que el evento no bloquee el borrado de un skill tag), `change_type` (`source_deleted`), `actor_pseudonym` (UUID aleatorio, único), `occurred_at`, `expires_at` e índice por `expires_at` y por `(subject_id, occurred_at)`. **`evidence_deletion_actor_links`**: UUID, `event_id` (FK único, `cascadeOnDelete`) y `actor_id` (FK `users`, `nullOnDelete`). Ninguna de las dos tiene columnas de respuestas, puntuación, id de resultado ni id de vínculo. Checks para `source_type`, `competency_type` y `change_type`. | RF-014.7 |
| `backend/app/Models/{EvidenceDeletionEvent,EvidenceDeletionActorLink}.php` | Los modelos. El evento no expone ninguna relación con el actor. La correspondencia solo se lee desde la Action de identificación. `$hidden` sobre `subject_id` en la serialización por defecto. | RF-014.7 |
| `backend/config/progress.php` | `deletion_event_retention_months`, por defecto `12` (política §5.1). Con `0` o `null` no se crean eventos nuevos (política §5.3). El valor debe coincidir con la política vigente; un test fija el valor por defecto. | RF-014.8 |
| `backend/app/Actions/RecordEvidenceDeletionEventAction.php` | Recibe un vínculo que se va a retirar porque su fuente se borra. Crea el evento mínimo y su correspondencia en la transacción del llamador. Si el plazo está deshabilitado, no hace nada. | RF-014.7–.8 |
| `backend/app/Actions/RetireEvidenceLinksForSourceAction.php` | Por cada vínculo: llama a `RecordEvidenceDeletionEventAction` y después al retiro auditado que ya existe. El registro operativo no cambia (política §6). | RF-014.7 |
| `backend/app/Http/Controllers/Api/PersonalityController.php` | El retake mete el retiro y el `delete()` en una `DB::transaction`, igual que ya hacen las dos rutas de admin. Así un fallo no deja el evento sin borrado ni el borrado sin evento. | RF-014.7 |
| `backend/database/seeders/RolePermissionSeeder.php` | Permiso nuevo `audit.identify_actor`, asignado a `super_admin` y `admin` (política §4.4). Se sigue la convención actual: los permisos se dan de alta en el seeder, no en migraciones. | RF-014.7 |
| `backend/app/Actions/IdentifyEvidenceDeletionActorAction.php` + `EvidenceDeletionAuditController` + Resource | `GET /api/v1/admin/audit/evidence-deletion-events/{event}/actor`, dentro del grupo `role:super_admin\|admin` y con `permission:audit.identify_actor`. Devuelve solo `{actor: {id, name} \| null, event_id}`. Cada consulta deja una actividad `audit.evidence_deletion_actor_identified` con auditor, evento y fecha, sin el contenido del evento (política §4.5). Si el evento no existe o ha expirado → 404. | RF-014.7 |
| `backend/app/Console/Commands/ExpireEvidenceDeletionEvents.php` + `routes/console.php` | `app:expire-evidence-deletion-events`, una vez al día con `withoutOverlapping`. Borra los eventos con `expires_at <= now` (la correspondencia se va en cascada). Registra una actividad `competency.evidence_deletion_events_expired` que solo lleva el recuento (política §5.2). Es el único proceso de purga que habilita la excepción de la política §7. | RF-014.8 |
| `backend/lang/{es,en,pt}/*` | Mensajes de la ruta de auditoría, si hacen falta. | RNF-014.6 |
| `backend/tests/Feature/Progress/EvidenceDeletion*Test.php` | Ver §9.5. | RNF-014.5 |

### 9.2. Reglas puras y pseudocódigo

```text
retentionMonths(config): int|null        # null o <= 0 → retención deshabilitada (§5.3)
expiresAt(occurredAt, months): occurredAt + months   # "now" llega como parámetro
minimalEvent(link, subjectId, now, months):
  { subject_id, source_type: link.sourceType(), competency_type: link.competencyType(),
    competency_key: link.competencyKey(), change_type: 'source_deleted',
    actor_pseudonym: random UUID, occurred_at: now, expires_at: expiresAt(now, months) }
  # nunca: source_id, link_id, answers, score, raw_scores, results, level

retire(source, actor, now):
  for link in links(source):
    if retentionMonths() enabled:
      event = insert minimalEvent(link, link.user_id, now, months)
      insert actorLink(event.id, actor.id)
    RemoveCompetencyAssessmentLinkAction(link)   # registro operativo existente, sin cambios
```

`minimalEvent` y `expiresAt` son funciones puras: reciben `now` y los meses como parámetros, sin reloj global, y tienen tests unitarios.

### 9.3. Decisiones técnicas y alternativas descartadas

| Decisión | Alternativa descartada | Justificación |
|---|---|---|
| Tablas propias para el evento y la correspondencia | Guardarlos en `activity_log` | El evento tiene su propio plazo (12 meses) y el log operativo no tiene purga (política §6). Mezclarlos obligaría a purgar solo parte del log y metería al actor real junto al seudónimo. |
| Seudónimo UUID aleatorio por evento y correspondencia en tabla aparte | HMAC/hash determinista del `actor_id` | La política §4.5 lo prohíbe: un valor determinista permite cruzar eventos del mismo actor y no se puede borrar la correspondencia de un evento concreto. |
| Correspondencia en la misma base de datos, en tabla separada y solo accesible desde una Action con permiso | Una conexión o base de datos aparte | La política pide separación lógica y control en el servidor. Una base aparte complica las transacciones atómicas con el borrado de la fuente sin mejorar el control que se exige. |
| Un evento por vínculo retirado | Un evento por fuente borrada | Cada vínculo es una competencia afectada. La Spec 015 tiene que mostrar el efecto en cada competencia (CA-19.3). |
| `competency_key` como string sin FK | FK a `skill_tags` | Con FK, un evento que dura 12 meses impediría borrar etiquetas. La clave es un metadato del vínculo, no una relación viva. |
| `actor_id` con `nullOnDelete` | `restrict` o `cascade` | `restrict` bloquearía el borrado definitivo de un administrador. `cascade` borraría el evento antes de tiempo. Con `null`, el evento sigue y la identificación responde «actor no disponible». |
| Permiso dado de alta en el seeder | Migración de permisos | Es la convención actual del proyecto (`progress.view.support`, `scheduling.*`). En entornos desplegados hay que volver a ejecutar `RolePermissionSeeder`. |
| Plazo configurable con valor por defecto igual a la política | Plazo fijo en el código | Permite cumplir la política §5.3 (desactivar la creación si se retira el plazo) sin desplegar código. Un test fija el valor por defecto en 12. |

### 9.4. Eliminación del participante — **decidido 2026-11-15: propuesta (cascada solo en borrado definitivo)**

La política §4.1 dice que, si se elimina la cuenta del participante, sus eventos y correspondencias se eliminan con ella. Sin embargo, hoy `User` usa `SoftDeletes` y no hay ningún borrado definitivo (`AdminController::userDestroy` hace `delete()` y no existe `forceDelete`). Con soft delete, la FK `cascadeOnDelete` no se dispara.

- **Propuesta:** aplicar la cascada solo en el borrado definitivo, igual que ya ocurre con los vínculos y el resto de datos del usuario. Un usuario dado de baja con soft delete conserva sus eventos hasta que caduquen (12 meses como máximo).
- **Alternativa (descartada):** un observer en `User::deleted` (soft delete) que borre también sus eventos y correspondencias. Sería la lectura más estricta de §4.1, pero rompería la simetría con el resto de datos, que se conservan cuando se restaura una cuenta.

Un test de T014.10 fija esta decisión: el soft delete del sujeto conserva sus eventos y el borrado definitivo los elimina en cascada junto con su correspondencia.

### 9.5. Estrategia de tests

- **Unit:** `expiresAt` con 12 meses, incluido un 31 → fin de mes; `retentionMonths` con 0, null y negativo → deshabilitado; `minimalEvent` no contiene `source_id`, `link_id`, `answers`, `score`, `raw_scores`, `results` ni `level` (comprobado sobre JSON).
- **Feature, creación:** las tres rutas de borrado (retake, `admin/assessments/{id}`, `admin/evaluation-catalog/{id}`) crean un evento por vínculo con los metadatos aprobados, el `subject` correcto y `expires_at` = ahora + 12 meses. La correspondencia apunta al actor real y el evento no lleva el `actor_id`. Dos borrados del mismo actor generan seudónimos distintos. Sin vínculos no se crea evento. Con la retención deshabilitada, se retira el vínculo pero no se crea evento. Si el `delete()` falla, el evento se revierte (atomicidad del retake). El registro operativo sigue igual.
- **Feature, identificación:** `super_admin` y `admin` con el permiso → 200 con `{id, name}` y actividad de auditoría registrada. Admin sin el permiso → 403. Mentor o participante → 403 por el grupo de rol. Evento inexistente o expirado → 404. Actor nulo → `actor: null`. La respuesta no lleva contenido del evento más allá de `event_id`.
- **Feature, expiración:** el comando borra solo lo vencido, la correspondencia se va en cascada, después la identificación devuelve 404, la actividad registra solo el recuento y el comando está programado a diario en `routes/console.php`.
- **Privacidad:** ni el panel ni ningún endpoint de progreso (propio o de mentor) exponen eventos, seudónimos o correspondencias. El JSON del evento persistido no contiene ninguna clave prohibida.
- **Esquema:** checks y FKs comprobados por SQLSTATE con savepoints (patrón T014.1); rollback y reaplicación limpios.
