# Plan — Spec 005: Participación dinámica en sesiones colectivas

**Estado:** implementado (cabecera puesta al día el 2026-11-20: todas las tareas de `tasks.md` están completadas)  
**Spec:** `specs/005-collective-participation/spec.md`; hija aprobada como scope.  
**Base:** Spec 002 v0.4.0 y plan 002 v1.2.0.  
**Dependencias:** 003 (autorización/tiempo) y 004 (inscripción inicial y miembro activo). Es contrato obligatorio de 006 y 008.

## Trazabilidad RF/CA

| RF hija | Cobertura exacta padre | Contrato |
|---|---|---|
| RF-005.1 | RF-13, CA-13.2; caso límite 41; CA-5.1/5.8 para efectos de aviso | Alta después de reserva: comprobar ocupación propia; si conflicto, alta válida pero exclusión permanente de esa sesión, sin alterar la colectiva. |
| RF-005.2 | RF-13, CA-13.3; caso 35 | Baja prospectiva: corta avisos y confirmación desde la baja, conserva fila, asistencia y eventos. |
| RF-005.3 | RF-13, CA-13.3–13.4; casos 35/41 | Participantes finales anclados al `end_at` y a la inscripción válida; sin reescritura tras el fin. |
| RNF-005.1–005.3 | RNF-1/2/11; P3/P5/P14 | No exponer otros colectivos, historial íntegro y repetición inocua. |

## Archivos y responsabilidades previstos

- Migración aditiva sobre `session_attendees` (p. ej. `left_at` y representación persistente de exclusión del caso 41 si es necesaria) y `SessionAttendee.php`; no borrar asistentes ni eventos previos (RF-005.1–3).
- `backend/app/Services/Scheduling/Rules/ParticipantRules.php`: resolver actividad/ancla y decisión de inclusión por conflicto con `now` explícito (RF-005.1–3).
- `backend/app/Services/Scheduling/SessionParticipantSyncService.php`: aplicar altas/bajas bajo locks con idempotencia, reutilizando relaciones/colectivos existentes (RF-005.1/2).
- Observadores de `FamilyMember`, `CohortMember` y `Employee` (altas, baja y cambio a estado no activo), registrados en `AppServiceProvider` (RF-005.1/2).
- `CreateSessionAction`/`SessionBookingService` solo se ajustan para que la fila inicial y la marca de exclusión sean consumibles; la política de reserva inicial sigue siendo propiedad de 004 (RF-005.1/7).
- `backend/tests/Unit/Scheduling/ParticipantRulesTest.php`; `backend/tests/Feature/Scheduling/CollectiveMembershipTest.php` (RF-005.1–3).

## Funciones puras e interfaces

- `ParticipantRules::isActiveAt(attendee, anchor): bool`: alta `created_at <= anchor`, sin baja hasta después de ancla; participante inscrito válido (RF-005.2/3).
- `ParticipantRules::canJoinAfterBooking(member, session, ownSessions, now): JoinDecision`: conflicto si la sesión propia no está `canceled` ni «no completada» y sus intervalos [inicio, fin) intersectan; si conflictiva, resultado `excluded_for_session`, no reversión futura (RF-005.1, caso 41).
- `ParticipantRules::participantsAt(session, attendees, endAt, now): list<UserId>`: solamente participantes registrados a la sesión y activos en `end_at`, con excepción permanente aplicada, más mentor (RF-005.2/3).
- `SessionParticipantSyncService::onMembershipChange(source, user, change, now)` es interfaz con IO: serializa por source/usuario, agrega una fila de participante si elegible; en baja pone `left_at=now`; nunca elimina/edita asistencia (RF-005.1/2).
- El contrato de consumidores devuelve un único conjunto de IDs y no autoriza exponer datos de membresía. 006 consume el snapshot anclado al fin; 008 resuelve destinatarios actuales desde las filas y exclusiones 005.

## Algoritmo

```text
membershipAdded(source, user, now):
  lock source, then user (mismo orden que booking)
  para cada sesión colectiva de source que aún no alcanzó end_at:
    si hay sesión propia activa (no canceled/no_completed) que solapa:
       registrar exclusión permanente para (session,user); no añadir attendee
    si no:
       insertar/upsert attendee, conservando created_at del ingreso válido

membershipRemoved(source, user, now):
  lock source, then user
  para cada attendee vigente de ese source:
    si left_at es null: fijar left_at=now
  no eliminar filas, confirmaciones ni eventos

participantsAtEnd(session, attendees, now):
  ancla = session.end_at
  return filas incorporadas a la sesión a la ancla y no dadas de baja antes/en ancla,
         excluyendo cualquier marca permanente de caso 41, más mentor
```

Cambios posteriores al fin no cambian el conjunto anclado. Los procesos deben ser repetibles: alta/baja duplicada no crea filas ni intervalos de pertenencia contradictorios.

## Decisiones y alternativas descartadas

1. **Guardar alta/baja y exclusión en filas relacionadas, sin snapshot paralelo JSON:** permite preservar asistencia y aplicar el ancla `end_at`; se descarta borrar asistentes al retirar miembro, que perdería evidencia.
2. **Exclusión del caso 41 queda ligada a esa sesión aun si el conflicto desaparece:** lo exige RF-005.1; se descarta incorporar retroactivamente por cambio ulterior en agenda personal.
3. **Observers llaman servicio de sincronización transaccional idempotente:** sigue cambios de membresía existentes; se descarta recalcular la membresía en 006/008, que produciría resultados distintos a través del tiempo.
4. **Locks source y usuario en orden compartido con 004:** cierra carrera alta-reserva; se descarta sincronización asíncrona eventual que deje una ventana de participantes incorrecta.

## Tests y cierre

- Unit: alta sin conflicto / con conflicto, sesión de solape semiabierto, sesiones personales canceladas/no completadas ignoradas, exclusión persistente y conjunto al `end_at` con `now` fijo (RF-005.1–3; CA-13.2–13.4).
- Feature: altas para tres tipos colectivos, baja antes/después del fin, bajas posteriores a confirmar, reintentos de observer, prohibición de introducir miembro en sesión pasada (CA-13.2–13.4; caso 35).
- Regresión contractual del caso 41: alta colectiva continúa válida, sesión no se revalida ni cancela, no existe asistencia ni recordatorio para excluido (la ejecución de recordatorios se verifica como contrato con 008, no se implementa aquí).
- Acceso/privacidad: un usuario de otro colectivo no puede consultar la pertenencia; no se cambian responsables ni membresías (RNF-005.2).

## Dependencias contractuales

- **De 004:** asistentes de inscripción inicial y relación/session collective ya determinados; sincronización no reproduce creación ni rechazo de reserva.
- **A 006:** conjunto anclado a fin, excluido el miembro caso 41; sin fila no puede confirmar ni recibir inicialización de asistencia.
- **A 008:** destinatarios ordinarios e inmediatos requieren pertenencia vigente, baja efectiva y exclusión caso 41; el responsable no miembro solo aplica al aviso de cambio según 008.
- **A 009:** lista de participantes autorizada debe derivarse de la misma fuente y no de lectura ad hoc de miembros activos.
