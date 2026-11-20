# Tareas — Spec 006: Ciclo de vida y asistencia

**Estado:** completado (todas las tareas marcadas; cabecera puesta al día el 2026-11-20)  
**Spec:** `specs/006-session-lifecycle-attendance/spec.md` · **Plan:** `plan.md`.

- [x] **T006-01. Añadir estado terminal y persistencia de asistencia auditada.** RF-006.1, RF-006.6–006.8; CA hija RF-006.1/6–8; CA padre CA-8.3/8.9, CA-11.1/11.6.
  - Hecho cuando: migraciones aditivas, enums y modelos guardan `not_completed`, status de asistencia y eventos append-only, mantienen `attended` compatible y persisten `end_at` con backfill e índices.
  - Corrección reautorizada completada (2026-10-13): se completan los artefactos omitidos por el plan 002 §6.1/§6.6/§7.1/§8 F2; `end_at` se deriva del instante UTC de inicio más la duración, y `not_completed_after_hours` queda en 48. No incluye automatización ni acciones de T006-03+.
- [x] **T006-02. Implementar reglas puras de transiciones y probar límites.** RF-006.1, RF-006.4; CA hija RF-006.1/4; CA padre CA-11.1/11.6, CA-8.8.
  - Hecho cuando: `LifecycleRules` con `now` explícito prueba inicio, fin+48h exacto, prioridad y estados terminales.
- [x] **T006-03. Programar transición automática idempotente de lifecycle.** RF-006.2–006.4; CA hija RF-006.2–4; CA padre CA-11.2/11.5/11.6.
  - Hecho cuando: comando programado bloquea sesión, persiste transiciones auditadas y una repetición no duplica cierre ni efectos.
- [x] **T006-04. Implementar reglas de ventana y precedencia de asistencia.** RF-006.5, RF-006.7; CA hija RF-006.5/7; CA padre CA-8.1/8.2/8.6/8.8.
  - Hecho cuando: unit tests prueban apertura en fin, cierre antes de fin+48h, responsable elegible por tipo, exclusión 005 y miembro prevalente en ambos órdenes.
- [x] **T006-05. Implementar cierre del mentor e inicialización de asistentes.** RF-006.1, RF-006.6; CA hija RF-006.1/6; CA padre CA-8.3/8.5, CA-11.1.
  - Hecho cuando: Action solo acepta mentor tras fin y crea una inicialización única por participante anclado a 005 sin sobrescribir confirmación existente.
- [x] **T006-06. Implementar acción/API de confirmación y compatibilidad de métrica.** RF-006.5–006.8; CA hija RF-006.5–8; CA padre CA-8.1–8.9, RNF-13.
  - Hecho cuando: Request/Action/Resource registran confirmación autorizada, conservan eventos sustituidos, sincronizan `attended` y no exponen otros asistentes.
  - Resultado: `PUT /sessions/{id}/attendance` usa actor implícito y autorización existente; la acción serializa por locks, conserva eventos y precedencia propia, y la lectura filtra asistencias por rol. Verificado con pruebas de ventana, responsables por tipo, privacidad y métrica sin cambiar Spec 001.
- [x] **T006-07. Probar concurrencia, terminales y contrato con hijas vecinas.** RF-006.1–006.8; CA hija RF-006.1–8; CA padre CA-8.1–8.9, CA-11.1–11.6.
  - Hecho cuando: suite Pest cubre confirmaciones concurrentes, caso 41, auto-cierre, transiciones prohibidas y lecturas de métrica sin alterar Spec 001.
  - Resultado: carreras con procesos PHP separados, conexiones PostgreSQL independientes y barrera; se verifican precedencia del miembro, conservación de ambos eventos, rechazo de completación/confirmación en fin+48h, auto-cierre y exclusión de caso 41. Pest completo: 367 passed / 1557 assertions.
