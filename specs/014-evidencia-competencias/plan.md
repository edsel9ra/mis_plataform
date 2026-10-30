# Plan de implementación — Spec 014: Evidencia de evaluaciones y competencias

**Estado:** propuesto para implementación · **Spec:** aprobada
**Base:** Spec 001 v0.5.1 aprobada; baseline v0.3.0 implementado.
**Dependencia:** independiente de 012/013; 015 consumirá los eventos que esta ampliación produzca.
**Bloqueo explícito:** la retención de evento mínimo tras eliminación NO se implementa hasta que la política general de conservación/eliminación esté identificada y documentada. No se fija plazo ni se propone una política aquí.

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
| Retención de eliminación | RF-014.7–.8 **bloqueados** hasta política documentada; no se implementa aquí |
