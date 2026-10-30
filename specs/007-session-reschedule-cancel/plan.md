# Plan — Spec 007: Reprogramación y cancelación

**Estado:** aprobado  
**Spec:** `specs/007-session-reschedule-cancel/spec.md`; alcance hijo aprobado.  
**Base:** Spec 002 v0.4.1 y plan 002 v1.2.0.
**Dependencias:** 003 autorización/zona, 004 revalidación de reserva, 006 estado/terminales. 008 consumirá eventos para avisos/Meet.

## Trazabilidad

| RF hija | Padre/CA exactos | Límite de responsabilidad |
|---|---|---|
| RF-007.1 | RF-6, CA-6.1/6.6 | Historial, identidad persistente y autor/momento. |
| RF-007.2 | RF-6, CA-6.2/6.3/6.7/6.8; casos 8/9/11/36/40 | Elegibilidad y revalidación usando servicio 004; rechazo deja intacta sesión. |
| RF-007.3 | RF-6, CA-6.4/6.5; RF-5 CA-5.7 | Emite un cambio persistido para consumidor 008; no implementa el envío ni Meet. |
| RF-007.4–007.6 | RF-7, CA-7.1–7.6; casos 10/15–18/42 | Persistencia de cancelación/tardía; retiene `meet_link` como dato histórico y lo oculta en API; evento sin enlace; borrado externo se delega a 008 sin revertir ni reexponer. |
| RNF-007.1–007.3 | RNF-1/2/3/5/11, P5/P14/P17 | Auditoría, externalidad best-effort e invariancia ante rechazo. |

## Archivos y responsabilidades previstos

- Migración `session_schedule_changes` (sesión, autor, momento, horario/duración/zona anterior/nueva, motivo); `SessionScheduleChange` y Resource (RF-007.1).
- Migración aditiva `sessions` para cancelación y auditoría; `Session`/`SessionStatus`; no reabrir estados 006 (RF-007.1/4/5).
- Rules `ChangeRules.php` para reprogramabilidad y cálculo tardío/lead con `now`; se invoca `OverlapDetector` y `SessionBookingService` de 004 (RF-007.2/4).
- Actions `RescheduleSessionAction.php` y `CancelSessionAction.php`: transacción, locks y auditoría; actions no incluyen lógica externa de 008 (RF-007.1/2/4/5).
- `SessionResource.php` y cualquier respuesta API que entregue Meet: si `status=canceled`, omite o devuelve nulo para `meet_link` aunque el atributo persista; 007 mantiene este contrato de ocultación y no limpia el dato histórico (RF-007.6, caso 42).
- Requests `RescheduleSessionRequest`/`CancelSessionRequest`, `SessionController`, `SessionPolicy` (contrato 003) y `routes/api.php`; mantener edición genérica incapaz de saltarse Actions (RF-007.1–6).
- Evento de dominio persistido post-commit, `SessionRescheduled`/`SessionCanceled` (o equivalente compatible): ID estable, referencia y datos mínimos autorizados; el de cancelación puede incluir autor, momento, motivo y tardanza cuando corresponda, pero nunca `meet_link`; 008 lo consume sin que 007 invoque Google (RF-007.3/4/6).
- Unit `ChangeRulesTest`; feature `SessionRescheduleTest`, `SessionCancelTest`, pruebas contractuales aisladas del consumidor 008 verifican persistencia del link, su omisión API tras cancelar y ausencia del link en el evento (RF-007.1–6, caso 42).

## Funciones puras e interfaces

- `ChangeRules::canReschedule(session, now, minimumNoticeMinutes): Decision`: solo `scheduled`, inicio futuro, mínimo 120 minutos y no terminal (CA-6.7/6.8).
- `ChangeRules::cancellationLead(startAt, now): int` y `isLate(startAt, now): bool`: tardía si menos de 120 min o inicio ya pasado; guarda minutos reales tal como se define en CA-7.2 (CA-7.2).
- `RescheduleSessionAction::execute(sessionId, input, actor, now)`: operación IO; autorización 003, eligibility 006, invoca validación completa 004 excluyendo la propia sesión, graba cambio/valores anterior-nuevo y emite evento tras commit (CA-6.1/2/4/5).
- `CancelSessionAction::execute(sessionId, reason, actor, now)`: operación IO; permite scheduled/in_progress conforme a 006, persiste status/campos/auditoría primero, emite evento estable post-commit (CA-7.1–7.6).
- Interfaz de eventos a 008: evento tiene `event_id` idempotente, `session_id`, tipo, `occurred_at` y snapshot mínimo de fecha anterior/nueva o motivo/late; el evento de cancelación no incluye `meet_link`; consumo externo aislado y no bloquea commit (RF-007.3–6).

## Algoritmo

```text
reschedule(id, input, actor, now):
  transaction + lock relationship → source → session → participant IDs ordenados
  validar policy 003 y ChangeRules(session, now, 120)
  candidate = nueva fecha/duración/zona (003)
  llamar SessionBookingService 004 en modo reschedule, excluir sesión actual
  ante cualquier error: rollback íntegro
  append session_schedule_changes(prev, new, zones, durations, actor, now, reason)
  actualizar misma sesión; commit
  publicar SessionRescheduled(event_id estable) después del commit

cancel(id, reason, actor, now):
  transaction + lock session; policy 003 y transición permitida por 006
  calcular late/lead; persistir canceled + actor/momento/motivo y transición auditada
  commit
   conservar meet_link/meet_event_id persistidos como dato histórico
   publicar SessionCanceled(event_id estable, referencia, autor, momento, motivo y tardanza/lead si aplican; sin meet_link)
   # 007/Resource omite o devuelve null para meet_link en toda respuesta API con status=canceled
   # consumidor 008 solicita deleteEvent best-effort; su fallo no limpia/reexpone el link ni revierte status
   # aviso único y eliminación externa pertenecen a 008
```

Un error de emisión externa nunca revierte cancelación/reprogramación persistida. La tolerancia y reintento de los efectos queda especificada por 008; el evento/outcome contractual no equivale a implementarlos en 007.

## Decisiones y alternativas descartadas

1. **Actions distintas y endpoint de operación dedicado:** separa revalidación y autorización del update menor; se descarta reusar un PUT genérico que permita cambiar fecha/estado sin reglas completas.
2. **Historial de agenda append-only:** permite inspeccionar anterior/nuevo y autor sin reconstruirlo de logs; se descarta reemplazar la misma fila sin historial.
3. **Reutilizar `SessionBookingService` de 004:** garantiza mismo cálculo que creación; se descarta duplicar solapes/cuota en 007.
4. **Evento post-commit con ID estable como frontera con 008:** impide enviar antes de persistir y permite dedupe; se descarta acoplar esta hija a cliente Google/canal de aviso (que corresponden a 008).
5. **Cancelación persiste antes de efectos externos y conserva el link histórico oculto:** el estado cancelado controla la exposición API; se descarta borrar el dato local, pues el historial de MIS debe conservarlo.
6. **Evento mínimo como frontera:** el evento de cancelación nunca lleva `meet_link`; se descarta transportar el enlace en auditoría/eventos porque el consumidor 008 necesita la referencia del evento remoto, no duplicar el dato.

## Tests y cierre

- Unit con `now` fijo: inicio futuro, justo 2h, menos de 2h, `in_progress`, terminales; cálculo de cancelación tardía y minutos reales (RF-007.2/4).
- Feature reprogramación: conflictos, disponibilidad/excepción, cuota y duplicidad devuelven rechazo sin cambio; éxito mantiene misma ID e historial completo incluidas zonas (CA-6.1–6.8).
- Feature cancelación: scheduled/in_progress, motivo requerido, tardía aceptada, terminal rechaza; fila y valor de `meet_link` se conservan, pero toda respuesta API omite/devuelve nulo para el link cancelado; evento de cancelación mínimo sin `meet_link`; hueco se libera para reserva futura (CA-7.1–7.6, caso 42).
- Contrato con 008: evento post-commit único lleva referencias/datos mínimos necesarios; prueba aislada verifica que un fallo del consumidor no altera estado ni valor persistido y no revela el enlace. La eliminación Google y su retry se prueban en 008, no se implementan aquí.
- Autorización cruzada 003 y estados 006: participante/responsable/soporte apropiados, ajeno 404, operación denegada 403, cambios tras inicio o estado terminal denegados (RF-007.1–5).

## Dependencias contractuales

- **De 003:** policy, visibilidad 404/403 y zona como parte del cambio explícito.
- **De 004:** validador transaccional reutilizable, cuota y participantes colectivos; rechazo preserva íntegra sesión.
- **De 006:** `scheduled` futura se reprograma; terminales no mutan; `in_progress` puede cancelarse; no reimplementar grafo.
- **A 008:** evento estable de reprogramación/cancelación y snapshot necesario; 008 implementa cambio/borrado Meet y aviso único, fallos registrados/no-revertidos.
- **A 009/010:** misma sesión conserva identidad e historia; calendario refleja el estado guardado, UI no recalcula elegibilidad.
