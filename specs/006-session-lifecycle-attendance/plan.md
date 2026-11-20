# Plan — Spec 006: Ciclo de vida y asistencia de sesiones

**Estado:** implementado (cabecera puesta al día el 2026-11-20: todas las tareas de `tasks.md` están completadas)  
**Spec:** `specs/006-session-lifecycle-attendance/spec.md`; scope aprobado.  
**Base:** Spec 002 v0.4.0, plan 002 v1.2.0 y compatibilidad de Spec 001 §3.1.3.  
**Dependencias:** 003 acceso/tiempo, 004 sesiones y participantes iniciales, 005 pertenencia/ancla. 007 consumirá terminales; 008 consumirá transiciones como eventos.

## Trazabilidad

| RF hija | Padre/CA exactos | Cobertura |
|---|---|---|
| RF-006.1–006.4 | RF-11, CA-11.1–11.6; RF-8 CA-8.8 | Grafo, exclusión de terminales, automatización, cierre sin acción y concurrencia/idempotencia de estado. |
| RF-006.5–006.8 | RF-8, CA-8.1–8.9; RNF-13; Spec 001 §3.1.3 | Confirmación, ventana, precedencia, privacidad, estado de asistencia y compatibilidad con `attended`. |
| RNF-006.1–006.3 | RNF-2/3/4/11/13, P5/P6/P19 | Registro inalterable, reloj de referencia del sujeto y pruebas. |

## Archivos y responsabilidades previstos

- Migraciones aditivas a `sessions` y `session_attendees`; nuevas `session_attendance_events` append-only; factories (RF-006.1/4–8).
- `SessionStatus` (estado `not_completed` y terminalidad), `AttendanceStatus`, `Session`, `SessionAttendee`, `SessionAttendanceEvent` (RF-006.1–8).
- Reglas puras `LifecycleRules.php`, `AttendanceRules.php`, `ParticipantRules.php` (la regla participante pertenece a 005; 006 solo la consume y añade tests de contrato) (RF-006.1/4–7).
- Actions `CompleteSessionAction.php`, `ConfirmAttendanceAction.php`; policy `SessionPolicy.php` se integra con 003; ninguna acción de reschedule/cancel (RF-006.1/5–8).
- `app/Console/Commands/AdvanceSessionLifecycle.php`, `backend/routes/console.php`: transiciones automáticas una vez por sesión de negocio, con reloj inyectable/testeable (RF-006.1/4).
- Requests/Resources `CompleteSessionRequest`, `ConfirmAttendanceRequest`, `SessionAttendeeResource`; ruta de complete/attendance y resources requeridos (RF-006.5–8).
- Pest unit/feature `tests/{Unit,Feature}/Scheduling/{LifecycleRules,AttendanceRules,SessionLifecycle,SessionAttendance}Test.php`; integración de métrica como contrato contra Spec 001 sin cambiar calculadora (RF-006.7/8).

## Funciones puras e interfaces

- `LifecycleRules::sessionEnd(startAt, duration): Instant`: un solo cálculo de fin (CA-8.8/11.6).
- `LifecycleRules::next(status, startAt, endAt, now): ?SessionStatus`: terminal no cambia; a inicio pasa a `in_progress`; a fin+48h no completada tiene precedencia; límites inclusivos según padre (CA-11.1/11.6).
- `AttendanceRules::canConfirm(session, attendee, actor, target, now): Decision`: sesión no cancelada/no completada, `now >= end_at`, `now < end_at+48h`, fila válida según 005 y autorización actor-target según 003 (CA-8.1/2/8).
- `AttendanceRules::applyPrecedence(current, incoming, actorRelation): EffectiveAttendance`: propio prevalece sobre responsable en ambos órdenes sin quitar ningún evento; no es «última escritura gana» (CA-8.6).
- Interfaz de persistencia `ConfirmAttendanceAction(sessionId, status, target?, actor, now)`; `target` ausente significa propio; responsable solo por miembros a cargo; mentor solo por sí mismo (CA-8.1).
- `CompleteSessionAction(sessionId, mentor, now)` es exclusiva del mentor, valida hora de fin y crea inicialización `not_confirmed` para conjunto 005; una inicialización única por sesión/asistente evita duplicados, y no sobrescribe confirmaciones existentes (CA-8.3/11.1).
- El booleano `attended` existente se mantiene como reflejo `attendance_status === attended`; la lectura de métricas permanece sin cambio (CA-8.9/RNF-13).

## Algoritmo

```text
advance(session, now):
  si terminal: sin efecto
  target = LifecycleRules.next(status, start, end, now)
  si target == not_completed: inicializar no_confirmada para participantes de 005 anclados a end_at
  persistir transición con actor sistema; insertar audit/event una sola vez

complete(session, mentor, now):
  transacción + lock sesión; autorizar únicamente mentor de relación
  rechazar terminal o now >= end_at+48h; rechazar now < end_at
  si scheduled: registrar promoción intermedia in_progress
  guardar completed; crear inicializaciones idempotentes de 005 ancladas a end_at

confirm(session, actor, target, status, now):
  transacción + locks sesión/asistente; validar miembro registrado 005, ventana y estado
  autorizar propia o responsable elegible por tipo; mentor no confirma por otros
  append evento de confirmación (incluye antecedente de responsable)
  aplicar precedencia propia; actualizar attended como reflejo; commit
```

Automatización en `routes/console.php` cada minuto según plan 002; ni leer la sesión debe simular una transición. Evento de transición se emite tras persistencia y no duplica efectos en reintento.

## Decisiones y alternativas descartadas

1. **`not_completed` como quinto estado persistido y terminal:** hace explícito el cierre requerido; descarta derivarlo de forma distinta en cada consulta/UI.
2. **`end_at` persistido/cálculo único y reloj inyectado:** facilita comparación/indexado del plazo y repetibilidad; se descarta leer reloj global dentro de las reglas.
3. **Estado efectivo en columnas + eventos append-only:** soporta consultas rápidas y conserva confirmaciones sustituidas; se descarta «última confirmación gana» y guardar solo auditoría genérica no consumible.
4. **`attended` reflejo, no reemplazo:** preserva Spec 001; se descarta eliminarlo o cambiar semántica histórica.
5. **Comando programado idempotente:** garantiza disparador temporal aun sin lecturas; se descartan observadores de lectura como disparador.

## Tests y cierre

- Unit: grafo, terminales, inicio/fin/fin+48h exacto, prioridad not_completed, ventana de asistencia abierta al fin y cerrada inclusivamente a 48 h, matriz de precedencia con `now` fijo (RF-006.1/4–7).
- Feature: permisos de cierre/confirmación, responsable por tipo, mentor no confirma otros, cancelado/no completado no permite asistencia, confirmación temprana/tardía denegada (CA-8.1/2/8, CA-11.1/6).
- Append-only: finalización y not_completed inicializan asistentes 005 una vez, conservan confirmación previa, miembro excluido caso 41 carece de fila, ambos órdenes de responsable/miembro preservan eventos (CA-8.3/5/6; contrato 005).
- Integración de compatibilidad: solo `attended=true` con estado «asistió»; no alterar formula/fechas publicadas de Spec 001; registrar las horas de inicio conforme a zona del sujeto (CA-8.4/9, RNF-13).

## Dependencias contractuales

- **De 003:** autorización por operación y conversión; `now` de servidor consistente.
- **De 004/005:** sesión, asistentes y conjunto registrado en fin; marca de exclusión caso 41 significa no crear registro ni admitir confirmación.
- **A 007:** únicamente estados/transiciones/terminales y locks de lifecycle; la política de reprogramar/cancelar y su historial es 007.
- **A 008:** transiciones persistidas identificables e idempotentes; 008 escucha cambios sin reabrir estado.
- **A Spec 001:** significado invariable de `attended`; ninguna modificación de calculadora o indicador es parte de esta hija.
