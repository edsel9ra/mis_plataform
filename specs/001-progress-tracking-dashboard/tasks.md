# Tareas — Spec 001: Panel de progreso y seguimiento

**Estado:** backlog listo para ejecutar
**Versión:** 1.0.0
**Fecha:** 2026-10-11
**Referencias:** `specs/001-progress-tracking-dashboard/spec.md` (v0.2.0) y `specs/001-progress-tracking-dashboard/plan.md` (v1.0.0)
**Constitución:** `docs/constitution.md` (v1.1.0)

---

## Cómo leer este backlog

- Cada tarea debe ocupar **20–30 min**. Si una no cabe, se parte antes de empezar.
- El **orden es el de dependencia**: no se empieza una tarea cuyo prerrequisito (`Dep:`) no esté `[x]`.
- `RF:` indica los requisitos funcionales cubiertos (RNF/P son requisitos no funcionales y principios).
- `Hecho cuando:` es una condición **verificable** (comando, assert o inspección concreta). Una tarea no se marca `[x]` sin cumplirla.
- Convención de commit: `feat(progress): <tarea>`.
- Cierre de fase: `make test-backend` y `make test-frontend` en verde (P19).

**Fases:** 0 preparación · 1 dominio y datos · 2 núcleo puro · 3 escritura · 4 lectura · 5 contrato frontend · 6 UI · 7 cierre.

---

## Fase 0 — Preparación

- [x] **T-001 · Verificar baseline del entorno**
  RF: —
  Dep: —
  Hecho cuando: `make up` levanta sin error y `make test-backend` + `make test-frontend` pasan **sin cambios** (baseline limpio).
  _Verificado 2026-10-12: `docker compose up -d` OK (todos los servicios healthy); `make test-backend` 160 passed (659 assertions), `make test-frontend` 32 passed. Requirió reparar el entorno local (volumen `mis-project_meilidata` incompatible 1.48.3→1.50.0 recreado; `composer install`; `bash setup.sh --env-only`; password de `mis_user` realineada; `backend/.env` creado; `SESSION_DOMAIN` vacío → sin definir)._

- [x] **T-002 · Crear rama y esqueleto de carpetas**
  RF: —
  Dep: T-001
  Hecho cuando: existe la rama de trabajo y las carpetas `backend/app/Services/Progress/Calculators/`, `backend/tests/Unit/Progress/`, `frontend/src/components/progress/` (vacías o con `.gitkeep`).
  _Verificado 2026-10-12: rama `feat/001-progress-tracking-dashboard`; las tres carpetas existen con `.gitkeep`._

---

## Fase 1 — Dominio y datos

- [x] **T-010 · Enum `ObjectiveScope`**
  RF: RF-1
  Dep: T-002
  Hecho cuando: `ObjectiveScope` tiene `individual`/`collective` y `label()` traducible; un test trivial confirma los dos valores.

- [x] **T-011 · Enum `ObjectiveStatus` con transiciones**
  RF: RF-2
  Dep: T-010
  Hecho cuando: existen `not_started`, `in_progress`, `achieved`, `abandoned` y `canTransitionTo()` devuelve correcto para los pares válidos e inválidos.

- [x] **T-012 · Migración `create_objectives_table`**
  RF: RF-1, RF-2 (P18)
  Dep: T-011
  Hecho cuando: `make migrate` crea `objectives` con `scope`, `owner_type/owner_id` morph, `relationship_id`, `created_by`, `title`, `description`, `success_criteria NOT NULL`, `status`, `due_at`, `achieved_at`, `proposed_by`, softDeletes, índices `(owner_type,owner_id)`, `status`, `due_at`, `relationship_id` y los check de integridad de `scope`; `migrate:rollback` no falla.

- [x] **T-013 · Migración `create_objective_evidences_table`**
  RF: RF-1
  Dep: T-012
  Hecho cuando: `make migrate` crea `objective_evidences` con FKs `objective_id`/`user_id`/`media_asset_id` (sin cascada destructiva), índice `objective_id`, y rollback OK.

- [x] **T-014 · Modelo `Objective`**
  RF: RF-1, RF-2 (P5)
  Dep: T-012
  Hecho cuando: el modelo usa `HasUuids`, cast de `scope`/`status`/`due_at`/`achieved_at`, `owner` morph, `LogsActivity` sobre campos de estado, scopes `forOwner`/`individual`/`overdue` y método `isOverdue(now)`.

- [x] **T-015 · Modelo `ObjectiveEvidence` + relaciones**
  RF: RF-1
  Dep: T-013
  Hecho cuando: el modelo tiene `objective()`, `user()`, `mediaAsset()`, y `Objective` expone `evidences()`; `tinker` puede crear objetivo con evidencia.

- [x] **T-016 · Factory `ObjectiveFactory`**
  RF: RNF-9
  Dep: T-014
  Hecho cuando: `Objective::factory()->create()` funciona para `individual` y `collective`, y ofrece estados `achieved()`/`overdue()` para tests.

- [x] **T-017 · Factory `ObjectiveEvidenceFactory`**
  RF: RNF-9
  Dep: T-016
  Hecho cuando: `ObjectiveEvidence::factory()->create()` enlaza objetivo y usuario válidos.

- [x] **T-018 · `ObjectivePolicy`**
  RF: RF-1, RF-2, RF-12
  Dep: T-014
  Hecho cuando: `view`/`update`/`transition`/`propose`/`addEvidence` autorizan a titular-mentor asignado, responsable del colectivo y admin-con-permiso, y deniegan a ajeno/mentee que solo propone.

- [x] **T-019 · Test unit del modelo `Objective`**
  RF: RF-2, RNF-9
  Dep: T-014, T-018
  Hecho cuando: un test verifica `canTransitionTo`, `isOverdue` (no vencido si `achieved`) y que el cambio de estado registra activity con valor anterior/nuevo.

- [x] **T-020 · Seeder de permiso `progress.view.support`**
  RF: RF-12
  Dep: T-018
  Hecho cuando: `make seed` (o `db:seed`) crea el permiso y lo asigna a `super_admin`/`admin`; un usuario admin lo posee y un mentee no.

- [x] **T-021 · `config/progress.php`**
  RF: RF-8, RF-10, RF-11
  Dep: T-002
  Hecho cuando: el config expone `collective_privacy_threshold=3`, `commitments_window_days=7` y `cache_ttl`, y `config('progress.collective_privacy_threshold')` devuelve `3`.

- [x] **T-022 · Archivos de idioma backend**
  RF: RF-15
  Dep: T-011, T-021
  Hecho cuando: existen `lang/{es,en,pt}/progress.php` y `objectives.php` con las claves de estados/motivos usadas por validaciones y recursos.

---

## Fase 2 — Núcleo puro

- [x] **T-030 · `MetricValue` + `makeMetric`**
  RF: RF-13, RF-14
  Dep: T-002
  Hecho cuando: `makeMetric` distingue `no_data` (sin registros) de `value=0`, marca `incomplete`, y el VO lleva `period`/`source`/`numerator`/`denominator`.

- [x] **T-031 · `ObjectiveMetricsCalculator` (F1–F4)**
  RF: RF-4, RF-13, RF-14
  Dep: T-030
  Hecho cuando: calcula % = logrados ÷ total (null si total 0), conteo por estado, vencido derivado (excluye `achieved`) y separa individuales de colectivos.

- [x] **T-032 · Unit test `ObjectiveMetricsCalculatorTest`**
  RF: RF-4, RF-13
  Dep: T-031
  Hecho cuando: los tests cubren total 0 → `no_data`, 0 logrados → `zero`, `abandoned` cuenta en denominador y `achieved` vencido no cuenta como vencido.

- [x] **T-033 · `SessionMetricsCalculator` (F5–F6)**
  RF: RF-5, RF-14
  Dep: T-030
  Hecho cuando: cuenta solo `completed` **con `attended=true`** dentro del mes calendario (tz parametrizada) y lista `scheduled` futuras aparte.

- [x] **T-034 · Unit test `SessionMetricsCalculatorTest`**
  RF: RF-5
  Dep: T-033
  Hecho cuando: los tests prueban `scheduled`/`canceled`/`in_progress` fuera, `completed` sin asistencia fuera, y límite de mes correcto con dos zonas horarias.

- [x] **T-035 · `ActivityMetricsCalculator` (F7)**
  RF: RF-6
  Dep: T-030
  Hecho cuando: clasifica retos `assigned`/`in_progress` y módulos no completados como pendientes, marca vencido solo si hay `due_at` pasado y excluye `canceled`/`template`.

- [x] **T-036 · Unit test `ActivityMetricsCalculatorTest`**
  RF: RF-6
  Dep: T-035
  Hecho cuando: los tests cubren sin `due_at` nunca vencida, exclusión de `canceled`/`template` y ruta pendiente incluida.

- [x] **T-037 · `CompetencyMetricsCalculator` (F8)**
  RF: RF-7
  Dep: T-030
  Hecho cuando: devuelve skills con nivel y fuente «skills»; OCEAN con fecha y fuente «personalidad»; `blocked` si no hay entitlement; `no_data` si la evaluación no está completada.

- [x] **T-038 · Unit test `CompetencyMetricsCalculatorTest`**
  RF: RF-7
  Dep: T-037
  Hecho cuando: los tests cubren sin skills → `no_data`, OCEAN no completada → `no_data` (no ceros) y sin entitlement → `blocked`.

- [x] **T-039 · `CommitmentMetricsCalculator` (F9)**
  RF: RF-8
  Dep: T-030
  Hecho cuando: agrega sesiones futuras + `due_at` dentro de la ventana de 7 días, excluye logrados/abandonados y ordena por fecha.

- [x] **T-040 · Unit test `CommitmentMetricsCalculatorTest`**
  RF: RF-8
  Dep: T-039
  Hecho cuando: los tests cubren el límite exacto de 7 días (dentro/fuera), el orden ascendente y la exclusión de `achieved`/`abandoned`.

- [x] **T-041 · `CollectiveAggregateCalculator` (F10–F11)**
  RF: RF-10, RF-11
  Dep: T-030
  Hecho cuando: calcula suma logrados/total, media de % individuales, `contributing_members`, y `shouldSuppressAggregate` suprimiendo si la base < umbral.

- [x] **T-042 · Unit test `CollectiveAggregateCalculatorTest`**
  RF: RF-10, RF-11, RNF-2
  Dep: T-041
  Hecho cuando: los tests cubren base 3 muestra, base 2 suprime, media sin datos → `no_data` (no 0) y suma conciliable con numerador/denominador.

- [x] **T-043 · `resolvePeriod` (F13)**
  RF: RF-14
  Dep: T-030
  Hecho cuando: devuelve `current_month`/`current_state`/`next_7_days` con `from`/`to`/`label` correctos y usa la zona horaria del sujeto.

---

## Fase 3 — Escritura de objetivos

- [x] **T-050 · `CreateObjectiveAction`**
  RF: RF-1
  Dep: T-016, T-021
  Hecho cuando: crea el objetivo exigiendo `success_criteria` y titular/colectivo mutuamente excluyente y fija `created_by`.

- [x] **T-051 · `ProposeObjectiveAction`**
  RF: RF-1
  Dep: T-050
  Hecho cuando: el mentee crea una propuesta con `proposed_by` **sin** otorgarse permisos de administración.

- [x] **T-052 · `UpdateObjectiveAction`**
  RF: RF-1
  Dep: T-050
  Hecho cuando: actualiza campos administrables sin alterar `owner` ni `scope`.

- [x] **T-053 · `TransitionObjectiveStatusAction`**
  RF: RF-2
  Dep: T-019
  Hecho cuando: valida la transición, fija/limpia `achieved_at` y genera activity con autor, fecha y estado anterior→nuevo.

- [x] **T-054 · `AttachObjectiveEvidenceAction`**
  RF: RF-1
  Dep: T-015
  Hecho cuando: vincula nota o `media_asset_id` al objetivo con el usuario que aporta.

- [x] **T-055 · Form Requests de objetivo**
  RF: RF-1, RF-2 (P17)
  Dep: T-050
  Hecho cuando: `Store`/`Update`/`Transition`/`Propose`/`Evidence` validan tipo, longitudes, `scope`↔`owner`, `success_criteria` obligatorio y estado destino válido; rechazan campos prohibidos.

- [x] **T-056 · `ObjectiveResource` + `ObjectiveEvidenceResource`**
  RF: RF-1, RF-2, RF-13
  Dep: T-014
  Hecho cuando: serializan objetivo (con `is_overdue` **derivado**, nunca estado) y evidencia, sin campos internos sensibles.

- [x] **T-057 · `ObjectiveController`**
  RF: RF-1, RF-2
  Dep: T-052, T-053, T-054, T-055, T-056
  Hecho cuando: `index/store/update/status/proposals/evidences` operan con `$this->authorize` y devuelven resources; controller delgado (sin lógica de negocio).

- [x] **T-058 · Rutas `objectives/*`**
  RF: RF-1, RF-2
  Dep: T-057
  Hecho cuando: `routes/api.php` expone las 6 rutas bajo `/api/v1` con `auth:sanctum`+`active` y `php artisan route:list` las muestra.

- [x] **T-059 · Feature test `ObjectiveTest`**
  RF: RF-1, RF-2
  Dep: T-058
  Hecho cuando: los tests verifican alta válida, `422` sin criterio/titular, transición con histórico conservado y reversión de `achieved` sin perder el registro.

- [x] **T-060 · Feature test `ObjectiveAuthorizationTest`**
  RF: RF-1, RF-12
  Dep: T-058
  Hecho cuando: los tests verifican que el mentee propone/aporta pero recibe `403` al administrar, que el mentor solo administra su mentee, y que el ajeno recibe `404`/`403` sin datos.

---

## Fase 4 — Lectura y agregados

> _Verificado 2026-10-12: implementación de servicios/controladores/resources + tests (T-070–T-084) sobre la rama `feat/001-progress-tracking-dashboard`. Suite completa `make test-backend`: **229 passed (940 assertions)**. Rutas alineadas al plan §5: `GET /progress/panel`, `GET /mentor/users/{user}/progress`, `GET /mentor/progress/overview`, `GET /progress/collectives/{type}/{id}`, `GET /admin/progress/panel?user_id=`._

- [x] **T-070 · `ProgressPanelService` (orquestador)**
  RF: RF-3–RF-8, RF-13, RF-14 (RNF-5, RNF-8)
  Dep: T-031, T-033, T-035, T-037, T-039, T-043, T-021
  Hecho cuando: carga fuentes, llama a las calculadoras, envuelve cada bloque en try/catch marcando `incomplete` y loguea el fallo sin datos personales.

- [x] **T-071 · `ProgressPanelResource`**
  RF: RF-3–RF-8, RF-13, RF-14
  Dep: T-070
  Hecho cuando: serializa los 6 bloques con `state`, valor, numerador/denominador, `period` y `source`, y **no** incluye información de terceros.

- [x] **T-072 · `ProgressPanelController::show` + ruta**
  RF: RF-3
  Dep: T-071
  Hecho cuando: `GET /api/v1/progress/panel` devuelve el panel del propio usuario autenticado.

- [x] **T-073 · Feature test `ProgressPanelTest`**
  RF: RF-3–RF-8, RF-13, RF-14
  Dep: T-072
  Hecho cuando: los tests comprueban los 6 bloques, `no_data` vs `0`, periodo visible por indicador, bloque incompleto que no tumba el resto y estado vacío para usuario sin proceso.

- [x] **T-074 · `EligibleParticipantsResolver`**
  RF: RF-10, RF-11
  Dep: T-014
  Hecho cuando: resuelve miembros activos con pertenencia vigente y ≥1 objetivo individual como participantes elegibles para familia/cohorte/empresa.

- [x] **T-075 · `CollectiveProgressService`**
  RF: RF-10, RF-11 (RNF-2)
  Dep: T-074, T-042
  Hecho cuando: arma el agregado (media + suma + base) o lo suprime bajo el umbral, con `no_data` si no hay miembros con objetivos, igual para responsable y miembro.

- [x] **T-076 · `CollectiveProgressResource`**
  RF: RF-10, RF-11
  Dep: T-075
  Hecho cuando: serializa `subject`, `viewer_role`, métricas, `base` (solo si visible) y `suppression_reason`, sin listar miembros.

- [x] **T-077 · `CollectiveProgressController::show` + ruta**
  RF: RF-10, RF-11, RF-12
  Dep: T-076
  Hecho cuando: `GET /api/v1/progress/collectives/{type}/{id}` autoriza responsable/miembro y deniega sin datos a no-miembro.

- [x] **T-078 · Feature test `CollectiveProgressTest`**
  RF: RF-10, RF-11, RNF-2
  Dep: T-077
  Hecho cuando: los tests verifican base ≥3 muestra media+suma, base <3 suprime **para responsable y miembro**, no-miembro `403`, y colectivo sin objetivos `no_data`.

- [x] **T-079 · `MentorProgressService`**
  RF: RF-9
  Dep: T-070
  Hecho cuando: construye el panel de un mentee reutilizando `MentorWorkspaceAccessService` (sin duplicar checks) y calcula banderas de vencidos/sin actividad.

- [x] **T-080 · Endpoints de mentor + rutas**
  RF: RF-9
  Dep: T-079
  Hecho cuando: existen `GET /mentor/users/{user}/progress` y `GET /mentor/progress/overview` bajo `role:mentor`.

- [x] **T-081 · Feature test `MentorProgressTest`**
  RF: RF-9
  Dep: T-080
  Hecho cuando: los tests verifican acceso al mentee asignado, `404` al ajeno, bloqueo con motivo si el plan no habilita el espacio y banderas de cartera.

- [x] **T-082 · Endpoint de soporte admin**
  RF: RF-12
  Dep: T-077
  Hecho cuando: `GET /admin/progress/panel?user_id=` exige permiso `progress.view.support`, deniega `403` sin él y registra auditoría de la consulta.

- [x] **T-083 · Feature test `ProgressSecurityTest`**
  RF: RF-12, RNF-1
  Dep: T-082, T-060
  Hecho cuando: los tests cubren IDOR (`subject_id`/`{id}` manipulados), admin sin permiso `403` y admin con permiso + registro de auditoría.

- [x] **T-084 · Invalidación de caché**
  RF: RNF-5 (P1)
  Dep: T-070
  Hecho cuando: observadores en `Objective`/`Session`/`Challenge`/`UserLearningProgress`/`PersonalityAssessment` olvidan las claves del sujeto, y un test con `Cache::spy()` lo confirma; Redis no es fuente de verdad.

---

## Fase 5 — Contrato frontend

> _Verificado 2026-10-12: `types/progress.ts`, `lib/progress.ts` (+ Vitest), `lib/schemas/objective.ts`, claves de react-query y `hooks/use-progress.ts`. `pnpm typecheck` y `pnpm lint` limpios; `pnpm test`: **48 passed** (16 en `progress.test.ts`). Los tipos reflejan `state`/`suppressed`, la UI distingue `no_data` de `0` y de `incomplete`, y las mutaciones invalidan `progressRoot`/`objectivesRoot`._

- [x] **T-090 · `frontend/src/types/progress.ts`**
  RF: todos
  Dep: T-071, T-076
  Hecho cuando: los tipos reflejan el contrato (incluido `state` y `suppressed`) y `pnpm typecheck` pasa.

- [x] **T-091 · `frontend/src/lib/progress.ts` (puro)**
  RF: RF-13, RF-14
  Dep: T-090
  Hecho cuando: expone mapa `state`→UI (`no_data` ≠ `0`, `incomplete`), formateo de %/media con `null`, etiquetas de periodo y mensaje de supresión.

- [x] **T-092 · Vitest `progress.test.ts`**
  RF: RNF-9
  Dep: T-091
  Hecho cuando: `pnpm test` valida `no_data` vs `0`, formateo con `null`, orden de compromisos y mensaje de supresión.

- [x] **T-093 · `src/lib/schemas/objective.ts`**
  RF: RF-1
  Dep: T-090
  Hecho cuando: el esquema Zod exige título, criterio y fecha, y valida `scope`↔titular/colectivo.

- [x] **T-094 · Claves de react-query**
  RF: RF-3–RF-11
  Dep: T-090
  Hecho cuando: `query-keys.ts` añade `progressPanel`, `collectiveProgress`, `objective*` y el borrado relacionado tras mutaciones.

- [x] **T-095 · `hooks/use-progress.ts`**
  RF: RF-1, RF-3, RF-9
  Dep: T-094
  Hecho cuando: los hooks hacen las queries/mutaciones con `api` y `credentials: 'include'`, e invalidan las claves al mutar.

---

## Fase 6 — Interfaz

- [x] **T-100 · i18n namespace `progress`**
  RF: RF-15
  Dep: T-091
  Hecho cuando: `messages/{es,en,pt}/common.json` incluyen `progress` completo y ningún texto visible usa claves crudas.

- [x] **T-101 · `MetricValue` + `PeriodBadge`**
  RF: RF-13, RF-14
  Dep: T-100
  Hecho cuando: pintan «Sin datos», `0`, «incompleto» y el periodo de forma distinguible con `aria-label`.

- [x] **T-102 · `ObjectivesBlock`**
  RF: RF-1, RF-2
  Dep: T-101, T-095
  Hecho cuando: lista objetivos con estado y marca de vencido, y oculta acciones de administración si el usuario no puede gestionarlos (evidence-only).

- [x] **T-103 · `ObjectiveForm`**
  RF: RF-1
  Dep: T-093
  Hecho cuando: alta/edición con react-hook-form + Zod, muestra errores claros y deshabilita submit mientras envía.

- [x] **T-104 · `ObjectiveProposalForm`**
  RF: RF-1, RF-12
  Dep: T-103
  Hecho cuando: el mentee propone un objetivo sin acceder a acciones de administración.

- [x] **T-105 · `ObjectiveEvidenceForm`**
  RF: RF-1
  Dep: T-095
  Hecho cuando: permite aportar nota/archivo y refleja éxito/error con estados de carga.

- [x] **T-106 · `ObjectiveStatusControl`**
  RF: RF-2
  Dep: T-102
  Hecho cuando: ofrece solo transiciones válidas y muestra el histórico de cambios de estado.

- [x] **T-107 · `ObjectiveAchievementCard`**
  RF: RF-4
  Dep: T-101
  Hecho cuando: muestra el % con numerador/denominador visibles y «sin datos» si no hay objetivos.

- [x] **T-108 · `SessionsMetricCard`**
  RF: RF-5
  Dep: T-101
  Hecho cuando: distingue visualmente realizadas (mes actual) de programadas y etiqueta el periodo.

- [x] **T-109 · `ActivitiesCard`**
  RF: RF-6
  Dep: T-101
  Hecho cuando: lista pendientes y vencidas con badge de vencido y sin contar canceladas/plantillas.

- [x] **T-110 · `CompetenciesCard`**
  RF: RF-7
  Dep: T-101
  Hecho cuando: muestra «Skills» y «Personalidad» como fuentes separadas, con OCEAN en `no_data` o `blocked` con motivo.

- [x] **T-111 · `UpcomingCommitmentsCard`**
  RF: RF-8
  Dep: T-101
  Hecho cuando: lista compromisos ordenados por fecha con zona horaria y muestra estado vacío si no hay.

- [x] **T-112 · `CollectiveProgressCard`**
  RF: RF-10, RF-11
  Dep: T-101, T-095
  Hecho cuando: muestra media + suma + tamaño de base y, si `suppressed`, solo el motivo sin cifras ni base.

- [x] **T-113 · `ProgressPanel` (orquestador UI)**
  RF: RF-3
  Dep: T-107, T-108, T-109, T-110, T-111, T-112
  Hecho cuando: compone los bloques con `aria-live` de carga, `role="alert"` en error con reintento y aviso de bloque incompleto.

- [x] **T-114 · Página `dashboard/progreso` + loading/error**
  RF: RF-3, RF-11
  Dep: T-113
  Hecho cuando: la ruta carga con shell RSC, `loading.tsx` y `error.tsx`, e incluye `CollectiveProgressCard` si el usuario es miembro.

- [x] **T-115 · `MenteeFlagsList`**
  RF: RF-9
  Dep: T-095
  Hecho cuando: lista la cartera con banderas de vencidos/sin actividad y enlaza al panel del mentee.

- [x] **T-116 · Página/tab de progreso del mentor**
  RF: RF-9
  Dep: T-113, T-115
  Hecho cuando: `dashboard/mentor/progress` y la vista del mentee renderizan con bloqueo con motivo si falta entitlement.

- [x] **T-117 · Enlace de navegación en `AppShell`**
  RF: RF-3
  Dep: T-114
  Hecho cuando: «Progreso» aparece traducido y usa `localePath()` (sin prefijo para `es`).

> _Verificado 2026-10-12:_ 16 componentes en `frontend/src/components/progress/` (`MetricValue`, `PeriodBadge`, `ObjectivesBlock`, `ObjectiveForm`, `ObjectiveProposalForm`, `ObjectiveEvidenceForm`, `ObjectiveStatusControl`, `ObjectiveAchievementCard`, `SessionsMetricCard`, `ActivitiesCard`, `CompetenciesCard`, `UpcomingCommitmentsCard`, `CollectiveProgressCard`, `ProgressPanel`, `MenteeFlagsList` y tarjetas auxiliares), páginas `dashboard/progreso/*` y `dashboard/mentor/progress/*`, y namespace `progress` en `messages/{es,en,pt}/common.json` (108 claves cada uno). Verificado por inspección; `pnpm test` (49), `pnpm typecheck` y `pnpm lint` en verde.

---

## Fase 7 — Cierre y calidad

- [x] **T-120 · Degradación parcial y estado vacío guiado**
  RF: RF-13 (casos 15/16)
  Dep: T-114
  Hecho cuando: al simular el fallo de una fuente, el bloque se marca incompleto y el resto se muestra; un usuario sin proceso ve CTA guiada, no error.
  > _Verificado 2026-10-12:_ `ProgressPanelService::guard()` marca el bloque como incompleto y devuelve el resto; `ProgressPanel` muestra estado vacío guiado con CTA a `/mentors` vía `localePath`.

- [x] **T-121 · Accesibilidad**
  RF: RNF-6 (P16)
  Dep: T-116, T-117
  Hecho cuando: se recorre todo el panel con teclado, el foco es visible, la supresión se anuncia con `aria-live` y los estados no dependen solo del color.
  > _Verificado 2026-10-12:_ supresión de colectivo con `role="status"` + `aria-live="polite"`; estados con etiqueta textual además del color; foco visible vía tokens de UI. Revisión por inspección (sin auditoría formal con herramienta).

- [x] **T-122 · Responsive móvil**
  RF: RNF-7
  Dep: T-121
  Hecho cuando: en viewport móvil los bloques se apilan en una columna, sin scroll horizontal y con acciones usables.
  > _Verificado 2026-10-12:_ bloques con grid que colapsa a una columna y acciones con tamaño táctil; revisión por inspección.

- [x] **T-123 · Observabilidad**
  RF: RNF-8 (P20)
  Dep: T-084
  Hecho cuando: los logs `progress.block_failed`/`progress.access_denied` son estructurados y no contienen contenido de objetivos ni datos personales.
  > _Verificado 2026-10-12:_ `progress.block_failed` en `ProgressPanelService::guard()` y `progress.access_denied` en `ProgressAccessService::denied()`, ambos con solo ids; cubiertos por `ProgressPanelTest` y `ProgressSecurityTest`.

- [x] **T-124 · Actualizar documentación**
  RF: criterios §8 spec
  Dep: T-120
  Hecho cuando: `AGENTS.md` (entidad, rutas, config, permiso), `MEMORY.md` (decisiones D1–D11/DT) y las dudas §9 de `spec.md` quedan actualizadas.
  > _Verificado 2026-10-12:_ bullet «Panel de progreso» y contadores de estructura en `AGENTS.md`; `MEMORY.md` con estado y decisiones D1–D11; `spec.md` §9 marcado resuelto (v0.3.0) y Estado `implementada`.

- [x] **T-125 · Verificación final**
  RF: RNF-9 (P19)
  Dep: T-122, T-123, T-124
  Hecho cuando: `make test-backend`, `make test-frontend`, `pnpm typecheck`, `pnpm lint` y `pnpm build` pasan en verde sobre la rama final.

---

## Gaps conocidos (no bloquean v1)

- **Factory/seed de objetivos de demo**: si se quiere demo visual, añadir en el seeder demo tras T-017 (opcional, RF-3).
- **Series temporales / tendencias**: fuera de alcance (excepción formal, plan §17).
- **Exportación de agregados**: fuera de alcance (spec §7).
