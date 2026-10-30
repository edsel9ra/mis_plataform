# Tareas — Spec 012: Progreso verificable de retos y módulos

**Estado:** backlog propuesto (no implementado) · **Spec:** aprobada
**Depende de:** baseline 001 v0.3.0 implementado. **Precede a:** 013.
Cada tarea es una unidad acotada de 20–30 min; tests planeados, no ejecutados. Skill indicada para quien implemente.

- [ ] **T012.1 · Esquema aditivo reto-módulo y completitud individual** (25 min). RF-012.2, .8, .11. Añadir FK nullable/index en Challenge y tabla UUID de completitud, unique `(user_id,path_module_id)`, FKs/checks; sin backfill de relación. Skills: `sdd`, `laravel-specialist`, `sql-optimization`.
  - Hecho cuando: migración reversible pasa sobre esquema con datos representativos y PostgreSQL rechaza FK/duplicados inválidos.
- [ ] **T012.2 · Relaciones de modelos y estados válidos** (25 min). RF-012.2, .7, .12. Exponer relaciones y ciclo `abandoned`; impedir transiciones distintas a las aprobadas. Skills: `laravel-patterns`, `laravel-tdd`, `spatie-laravel-php`.
  - Hecho cuando: pruebas unitarias verifican relaciones UUID y todos los pares de transición permitido/rechazado.
- [ ] **T012.3 · Calculadoras puras de tasas y completitud** (25 min). RF-012.1–.6, .11–.12. Implementar tasas de actividad, módulo y ruta en funciones/clases puras; dependencias de fecha reciben `now`. Skills: `sdd`, `laravel-tdd`.
  - Hecho cuando: tests cubren estado desconocido bloqueante, estados excluidos, denominador cero, numeradores conciliables y ausencia de parciales.
- [ ] **T012.4 · Action y auditoría de transición de reto** (25 min). RF-012.7, .11. Centralizar transition, rechazo temprano y log actor/momento/estados; eliminar bypass de estado en controller sin alterar edición de otros campos. Skills: `laravel-patterns`, `laravel-security`, `spatie-laravel-php`.
  - Hecho cuando: pruebas feature confirman cada transición del ciclo `abandoned`, estados inmutados al rechazar y traza anterior/resultante.
- [ ] **T012.5 · Policy y Action transaccional de vínculo** (25 min). RF-012.8–.10, .12. Autorizar reto y destino; al retirar autorizar también vínculo/ruta actuales; guardar presencia/ausencia y auditoría. Skills: `laravel-security`, `laravel-specialist`, `laravel-tdd`.
  - Hecho cuando: matriz titular/mentor asignado/ajeno y ruta visible/no visible confirma permitido/denegado sin filtración ni mutación parcial.
- [ ] **T012.6 · Recalcular completion y revocación atómica** (25 min). RF-012.2, .4–.6, .11. Recalcular módulo anterior/nuevo tras mutación y persistir/revocar completion con locks. Skills: `laravel-patterns`, `sql-optimization`, `laravel-tdd`.
  - Hecho cuando: pruebas confirman completion solo con todos completados, revocación al reactivar/no-completar y system actor + desencadenante.
- [ ] **T012.7 · Contrato API de métricas y vínculo** (25 min). RF-012.1, .8–.12. Form Request, resource/controller delgado y rutas `/api/v1`; reutilizar autorizadores y resource semantics del panel. Skills: `laravel-patterns`, `laravel-security`, `spatie-laravel-php`.
  - Hecho cuando: contrato incluye state/source/numerador/denominador, requests inválidos dan 422 y tests cubren IDOR, 403/404 sin vínculo filtrado.
- [ ] **T012.8 · Presentación accesible, traducciones y cierre focalizado** (25 min). RF-012.1–.12, RNF-012.3–.6. Añadir tipos/hooks/componentes para gestionar vínculo y estados; preparar prueba de carga padre. Skills: `i18n`, `accessibility`, `tanstack-query-best-practices`, `vitest`.
  - Hecho cuando: Vitest cubre `no_data`/`incomplete`/cero, textos es/en/pt sin claves crudas; inspección 320 px/teclado pasa y queda definido el comando/dataset del benchmark p95, sin afirmar una medición no realizada.
