# Tareas — Spec 014: Evidencia de evaluaciones y competencias

**Estado:** backlog propuesto (no implementado) · **Spec:** aprobada
La retención post-eliminación permanece bloqueada hasta documentar la política general; no implementar antes. Tareas activas de 20–30 min; pruebas planificadas, no ejecutadas.

- [ ] **T014.1 · Migración y restricciones de vínculos many-to-many** (25 min). RF-014.2–.4, .9. Crear FK discriminadas, checks XOR, índices y constraints parciales para competencias skill/OCEAN. Skills: `sdd`, `laravel-specialist`, `sql-optimization`.
  - Hecho cuando: rollback/migrate y tests Postgres prueban orígenes/tipos válidos, FKs y rechazo de combinaciones/duplicados inválidos.
- [ ] **T014.2 · Modelos, relaciones y fixtures de fuente** (25 min). RF-014.2–.3, .6. Enlazar fuentes/competencias sin nivel nuevo y distinguir `AssessmentResult` de `PersonalityAssessment`. Skills: `laravel-patterns`, `spatie-laravel-php`, `laravel-tdd`.
  - Hecho cuando: unit tests recorren ambos orígenes, skill/OCEAN y cardinalidad sin crear `Assessment` para OCEAN.
- [ ] **T014.3 · Clasificador puro de evidencia y completitud** (25 min). RF-014.1–.3, .5–.6. Usar vínculo explícito y `completed_at`; nada de inferencia/IO. Skills: `laravel-tdd`.
  - Hecho cuando: casos completado/incompleto/sin relación muestran estados distintos y ningún caso escribe nivel.
- [ ] **T014.4 · Action transaccional de crear/cambiar/retirar vínculo** (25 min). RF-014.3–.5. Validar ownership de sujeto y fuente, preservar antes/después e invalidar caché. Skills: `laravel-specialist`, `laravel-security`, `laravel-tdd`.
  - Hecho cuando: feature tests prueban many-to-many, ausencia anterior/resultante, duplicado y rollback sin mutar `UserSkill.level`.
- [ ] **T014.5 · Policy, requests y rutas de gestión** (25 min). RF-014.4, .9. Reutilizar acceso progresos/mentor y entitlements actuales, incluidos OCEAN. Skills: `laravel-security`, `laravel-patterns`, `spatie-laravel-php`.
  - Hecho cuando: titular/mentor asignado pueden dentro del ámbito; actor no asignado, sujeto cruzado o IDOR deniega sin revelar/cambiar vínculo.
- [ ] **T014.6 · Lectura serializada de evidencias en panel** (25 min). RF-014.1–.3, .5–.6, .9. Integrar contexto por relaciones explícitas en bloque de competencias. Skills: `laravel-specialist`, `sql-optimization`.
  - Hecho cuando: response distingue fuente, Assessment/test_version y fecha; omite vínculos ausentes, respuestas, score y raw_scores.
- [ ] **T014.7 · Trazas de link y eventos reales de cambio de estado** (25 min). RF-014.4, .6–.7. Registrar eventos whitelisted para consumo 015; enlazado incompleto mantiene relación. Skills: `spatie-laravel-php`, `laravel-tdd`.
  - Hecho cuando: pruebas verifican actor/fecha/diff y solo una transición fuente real con vínculo activo produce evento, sin mutar nivel.
- [ ] **T014.8 · Tipos, hook y controles de vínculo en frontend** (25 min). RF-014.1–.6, .9. UI de contexto y mutación autorizada; estados vigente/incompleto y fuentes separados. Skills: `react-best-practices`, `tanstack-query-best-practices`, `vitest`.
  - Hecho cuando: tests puros de presentación muestran vínculo explícito, no cambian nivel y estados incompletos no se presentan como evidencia vigente.
- [ ] **T014.9 · Traducciones, privacidad, accesibilidad y regresión** (25 min). RNF-014.1–.6, RF-014.1–.6. Textos es/en/pt, teclado/foco y checks de serializer/log. Skills: `i18n`, `accessibility`, `laravel-security`, `vitest`.
  - Hecho cuando: tests/inspección prueban ausencia de respuestas/puntuaciones en API/log, fallback idioma y operación a 320 px con teclado.
- [ ] **T014.10 · [BLOQUEADA] Retención mínima tras eliminación** (25 min solo tras desbloqueo). RF-014.7–.8. No ejecutar hasta identificar y documentar formalmente la política general de conservación/eliminación; sin decidir plazo ni alternativa en código. Skills: `sdd`, `laravel-security`, `laravel-tdd`.
  - Hecho cuando: el prerrequisito está documentado y aprobado, se actualizan plan/tasks si el alcance requiere ajuste y la implementación posterior retiene exclusivamente evento mínimo con pseudónimo audit-only; hasta entonces permanece bloqueada y no se persiste evento de eliminación.
