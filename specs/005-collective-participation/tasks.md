# Tareas — Spec 005: Participación dinámica colectiva

**Estado:** completado (todas las tareas marcadas; cabecera puesta al día el 2026-11-20)  
**Spec:** `specs/005-collective-participation/spec.md` · **Plan:** `plan.md`.

- [x] **T005-01. Persistir baja y exclusión de participación por sesión.** RF-005.1–005.3; CA hija RF-005.1–3; CA padre CA-13.2–13.4.
  - Hecho cuando: migración/modelo conservan attendee histórico, `left_at` y exclusión de caso 41 sin borrar asistencia/eventos; pruebas de modelo/migración pasan.
- [x] **T005-02. Implementar regla pura de alta elegible y ancla temporal.** RF-005.1–005.3; CA hija RF-005.1–3; CA padre CA-13.2–13.4, caso 41.
  - Hecho cuando: unit tests con `now` cubren solape propio, estados excluidos, exclusión permanente y participación válida en la hora de fin.
- [x] **T005-03. Sincronizar altas/bajas con observers idempotentes.** RF-005.1–005.2; CA hija RF-005.1/2; CA padre CA-13.2/13.3.
  - Hecho cuando: observer llama servicio bajo locks, alta válida incorpora miembro a sesiones futuras y baja corta participación futura sin borrar historial; repetición no duplica efecto.
- [x] **T005-04. Validar casos de membresía y contratos para 006/008.** RF-005.1–005.3; CA hija RF-005.1–3; CA padre CA-13.2–13.4, RNF-11.
  - Hecho cuando: feature tests familia/cohorte/empresa incluyen caso 41 y alta/baja antes/después de `end_at`; tests contractuales confirman que 006/008 excluyen correctamente sin implementar su lógica.
