# Tareas — Spec 015: Historial verificable de progreso

**Estado:** backlog propuesto (no implementado) · **Spec:** aprobada
**Orden:** después de 012 y 014; 013 puede ir tras 012. La retención de eliminación es una tarea bloqueada. Cada tarea activa de 20–30 min; pruebas planeadas, no ejecutadas.

- [ ] **T015.1 · Inventario de fuentes y contrato normalizado** (25 min). RF-015.1–.9. Delimitar whitelists por actividad_log, objectives/evidences, sources 012/014 y campos ausentes. Skills: `sdd`, `laravel-patterns`.
  - Hecho cuando: tabla de mapeo fuente→evento→campos disponibles/ausentes revisada; no agrega evento o dato no acreditado.
- [ ] **T015.2 · Funciones puras de normalización, fechas y orden** (25 min). RF-015.1, .7–.8. DTO normalizado, marca incompletitud y separa fechas nulas. Skills: `laravel-tdd`.
  - Hecho cuando: tests prueban orden estable, grupo `fecha no disponible`, campos faltantes y no usar reloj/fecha inventados.
- [ ] **T015.3 · Adapter de eventos de objetivos y evidencias** (25 min). RF-015.2. Leer activitylog y ObjectiveEvidence, sin copiar contenido innecesario. Skills: `laravel-specialist`, `spatie-laravel-php`.
  - Hecho cuando: tests solo emiten creación/transición/evidencia si existe el registro fuente y no infieren el primer inicio ausente.
- [ ] **T015.4 · Adapter de actividades/rutas y completitud de 012** (25 min). RF-015.3, .8. Normalizar cambios del reto/vínculo/completion, `UserLearningProgress` y evidencia registrada. Skills: `laravel-patterns`, `laravel-tdd`.
  - Hecho cuando: tests prueban actor sistema + desencadenante en transición automática, y no crean estados no registrados.
- [ ] **T015.5 · Adapter de competencias y fuentes 014** (25 min). RF-015.4–.6, .8. Normalizar links y cambios reales de nivel/estado, conservando cardinalidad/origen. Skills: `laravel-security`, `spatie-laravel-php`, `laravel-tdd`.
  - Hecho cuando: create/change/retire y transición real se representan con before/after; sin causalidad ni evento si fuente no transicionó.
- [ ] **T015.6 · Servicio de merge/cursor y límites de consulta** (25 min). RF-015.1, .7–.8. Cargar feeds por sujeto, ordenar y paginar con tie-break estable. Skills: `sql-optimization`, `laravel-specialist`.
  - Hecho cuando: tests de paginación aseguran orden sin duplicados, límite y separación de registros sin fecha.
- [ ] **T015.7 · Endpoint, resource y authorization de historia individual** (25 min). RF-015.1, .9–.10. Reutilizar titular/mentor/support vigentes; excluir rutas colectivas. Skills: `laravel-security`, `spatie-laravel-php`.
  - Hecho cuando: feature tests verifican titular, mentor autorizado, support permission, IDOR/no acceso colectivo, y serializer whitelist.
- [ ] **T015.8 · UI de cronología y consulta paginada** (25 min). RF-015.1–.8. Presentar eventos por fecha y grupo aparte sin fecha, sin tendencia/gráfico. Skills: `frontend-design`, `react-best-practices`, `tanstack-query-best-practices`.
  - Hecho cuando: Vitest prueba orden, incompleto, grupo undated, carga/error/empty y paginación sin perder eventos.
- [ ] **T015.9 · Accesibilidad, i18n, minimización y benchmark** (25 min). RNF-015.1–.6, RF-015.9. Locales es/en/pt, protección contenido, teclado/foco/320 px y preparar p95 padre. Skills: `accessibility`, `i18n`, `vitest`, `sql-optimization`.
  - Hecho cuando: revisión confirma no respuestas/score/datos de otro usuario, fallback idioma, UI teclado/320px y benchmark reproducible a 50 concurrentes con objetivo p95 ≤2 s (sin declarar medición ejecutada).
- [ ] **T015.10 · [BLOQUEADA] Retención mínima de eliminación** (25 min solo tras desbloqueo). RF-015.9. No ejecutar antes de documentar y aprobar la política general de conservación/eliminación. Skills: `sdd`, `laravel-security`, `laravel-tdd`.
  - Hecho cuando: la política está identificada y documentada, plan/tasks se revisan si procede, y la implementación posterior conserva solo fecha/tipo/metadato mínimo/actor pseudónimo audit-only, sin respuesta, score ni fuente; hasta entonces no se crea ni retiene evento de eliminación.
