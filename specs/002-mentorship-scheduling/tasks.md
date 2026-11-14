# Tareas — Spec 002: Agenda y gestión de mentorías

**Estado:** aprobada; implementación en curso
**Spec:** `specs/002-mentorship-scheduling/spec.md` v0.4.2, aprobada
**Plan:** `specs/002-mentorship-scheduling/plan.md` v1.2.0, aprobado

> Este archivo cumple el límite de 10 tareas del planner y por ello enumera paquetes de trabajo, no las ~43 unidades atómicas de 20–30 minutos estimadas en §20 del plan. No se deben ejecutar directamente como tareas pequeñas: antes de implementar, se recomienda aprobar el desglose de la Spec 002 en specs hijas con tareas atómicas (sin cambiar el alcance aprobado). Este backlog permanece detenido para aprobación.

## Orden propuesto

- [ ] **T001. Preparar baseline y autorización administrativa explícita de agenda.** RF-10
  - Hecho cuando: están documentados los resultados baseline de Pest/Vitest/typecheck/lint; los permisos `scheduling.view.support` y `scheduling.manage.support` aparecen asignados explícitamente a `super_admin` y `admin`; pruebas verifican acceso permitido por permiso, denegado sin permiso, y respuesta 404 para recurso ajeno frente a 403 para recurso visible sin capacidad de operación.

- [ ] **T002. Añadir modelo de dominio, configuración y persistencia aditiva.** RF-1, RF-2, RF-3, RF-8, RF-11, RF-13
  - Hecho cuando: las migraciones mantienen compatibilidad con `attended` y los datos existentes; las entidades/estados/casts reflejan zona IANA, instante UTC, fin, disponibilidad, historial y participación; la inicialización de asistencia tiene restricción de unicidad por sesión/participante; se documentan backfills y las pruebas de migración/modelo pasan.

- [ ] **T003. Implementar reglas puras de agenda y sus pruebas unitarias.** RF-1, RF-2, RF-3, RF-5, RF-6, RF-7, RF-8, RF-11, RF-13
  - Hecho cuando: reglas reciben `now` explícito y cubren intervalos [inicio, fin), normalización de duplicados, precedencia de excepciones aunque no haya ventanas, zona horaria, cadencias/dedupe, ventana inclusiva `now >= end_at + 48 h`, precedencia del miembro en ambos órdenes, ciclo terminal y ancla de participantes en `end_at`; la suite unitaria pasa.

- [ ] **T004. Completar reservas, solapamientos concurrentes y sincronización colectiva prospectiva.** RF-1, RF-2, RF-10, RF-11, RF-13
  - Hecho cuando: crear sesión revalida disponibilidad/cuota/participantes dentro de transacción con locks ordenados de relación, colectivo y participantes; una reserva solapada de cualquier miembro rechaza el hueco colectivo completo; doble envío devuelve una sesión; altas/bajas son prospectivas, y el caso 41 no inscribe como participante al miembro con sesión propia solapada.

- [ ] **T005. Implementar reprogramación y cancelación trazables con revalidación.** RF-3, RF-5, RF-6, RF-7, RF-9, RF-10, RF-11, RF-13
  - Hecho cuando: solo se reprograman sesiones `scheduled` futuras con al menos 2 h y revalidación completa; rechazo deja intacta la sesión; historial conserva valores previos/nuevos y autor; cancelación tardía se registra sin bloquearse; la cancelación conserva el `meet_link` almacenado, lo oculta en toda respuesta API y emite un evento mínimo sin enlace para 008; esta tarea no elimina Meet ni envía avisos de cambio, efectos que consume 008; los permisos de soporte y los 404/403 respetan §5/§9.8 del plan.

- [ ] **T006. Implementar confirmación de asistencia y cierre de ciclo de vida auditados.** RF-8, RF-10, RF-11, RF-13
  - Hecho cuando: confirmación propia abre en `end_at` y cierra inclusivamente al llegar `end_at + 48 h`; mentor no confirma por otros; responsable solo confirma miembros a su cargo; ambos órdenes conservan eventos append-only y el estado efectivo propio prevalece; al completar y al pasar a `not_completed` se registra inicialización idempotente `not_confirmed` sin borrar confirmaciones previas; las transiciones automáticas/manuales quedan auditadas.

- [ ] **T007. Exponer contrato API de sesiones, calendario, disponibilidad y soporte.** RF-1, RF-2, RF-3, RF-4, RF-6, RF-7, RF-8, RF-9, RF-10, RF-11, RF-13
  - Hecho cuando: Form Requests, Resources, Policies, controladores y rutas ofrecen rangos para calendario, CRUD de disponibilidad, huecos sin detalle privado, acciones de sesión y lectura colectiva; las rutas administrativas usan las mismas Actions; pruebas de contrato/authz validan permisos, aislamiento, zona horaria y errores 401/403/404/422.

- [ ] **T008. Añadir recordatorios, avisos de cambio, transiciones programadas y automatización de Meet.** RF-3, RF-5, RF-7, RF-8, RF-9, RF-11, RF-13
  - Hecho cuando: comandos de `routes/console.php` son idempotentes; cada aviso lógico reutiliza un ID estable en persistencia y en todos sus intentos, `SessionReminder` publica ese mismo `notification_id`, y solo la confirmación del proveedor marca `sent`; fallos/resultados ambiguos siguen reintentables aunque la publicación de transporte pueda repetirse (sin prometer exactly-once); recordatorios usan participantes y fecha vigentes; aviso inmediato aplica solo si faltan menos de 24 h; Meet respeta zona, no añade invitados y reintenta periódicamente hasta inicio; al cancelar, 008 borra el evento externo best-effort y conserva el valor histórico oculto mientras `status=canceled`. Las pruebas de 008 verifican productor/estado/reintento; la deduplicación visible no se acredita en esta tarea y RF-008.2 queda pendiente de aceptación hasta que T010 compruebe el upsert del consumidor por el mismo ID. También se cubren éxito/fallo de borrado sin alterar/limpiar el valor histórico ni exponer `meet_link` (caso 42).

- [ ] **T009. Integrar contrato frontend, calendario, acciones, disponibilidad, accesibilidad e i18n.** RF-1, RF-2, RF-3, RF-4, RF-5, RF-6, RF-7, RF-8, RF-9, RF-10, RF-11, RF-12, RF-13
  - Hecho cuando: la ruta existente `/sessions` muestra calendario día/semana/mes y detalle; formularios/acciones y editor de disponibilidad usan hooks, query keys y esquemas Zod existentes; zonas/estados/error/loading se representan correctamente; la interfaz es teclado-operable, responsive y traducida en es/en/pt; Vitest, typecheck y lint pasan.
  - Nota de cobertura (2026-11-22): el alcance de interfaz de esta tarea padre se desglosa y se cubre en las specs hijas **009** (calendario día/semana/mes y detalle), **010** (idioma, accesibilidad y responsive — todas sus tareas completas), **016** (acciones de sesión: reprogramar/cancelar/completar/asistencia) y **017** (reserva de sesiones y editor de disponibilidad — `review.md`). La tarea permanece abierta hasta que se verifique su «Hecho cuando» de forma integral (p. ej. representación integral de zonas/estados/error en todas las superficies de la ruta y cualquier resto fuera del alcance aprobado de las hijas).

- [ ] **T010. Cerrar verificación, rendimiento y documentación sin cambiar el alcance.** RF-1, RF-2, RF-3, RF-4, RF-5, RF-6, RF-7, RF-8, RF-9, RF-10, RF-11, RF-12, RF-13
  - Hecho cuando: suites backend/frontend pasan; pruebas cubren límites inclusivos de 48 h, autorización explícita, precedencia de asistencia, caso 41, concurrencia e idempotencia; benchmark representativo verifica calendario ≤2 s p95; y la prueba integrada de T010 hija demuestra que redeliverar `SessionReminder` con el mismo `notification_id` reemplaza el único aviso visible, mientras IDs distintos siguen siendo distintos, sin nuevo canal/API/bandeja persistente. Solo tras ese test se acepta RF-008.2; `AGENTS.md` y `MEMORY.md` documentan el resultado al finalizar implementación, y la spec solo cambia a `implementada` tras aceptación.

## Dependencias y puntos de aprobación

- Secuencia: T001 → T002 → T003 → T004/T005/T006 → T007/T008 → T009 → T010 (T004–T006 pueden avanzar en paralelo tras T003 si se mantiene el mismo contrato de dominio).
- La secuencia de implementación no se reordena: 008 entrega el productor y su contrato; 009 se completa antes de 010; T010 integra el consumidor y ejecuta la validación final. Por tanto, **T008 (y RF-008.2) no puede aceptarse plenamente antes de T010**. La integración de 010 es puerta de aceptación cruzada, no dependencia que impida implementar 009 ni un ciclo de implementación.
- Ninguna tarea está iniciada. El usuario debe aprobar plan y tareas antes de continuar.
- Recomendación pendiente de aprobación: desglosar estos 10 paquetes en specs hijas y tareas atómicas de 20–30 minutos antes de implementar; conservar la Spec 002 aprobada como fuente de alcance.
