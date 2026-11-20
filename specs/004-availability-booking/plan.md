# Plan — Spec 004: Disponibilidad y reserva de sesiones

**Estado:** implementado (cabecera puesta al día el 2026-11-20: todas las tareas de `tasks.md` están completadas)  
**Spec:** `specs/004-availability-booking/spec.md`; scope hijo aprobado.  
**Base:** Spec 002 v0.4.0 y `specs/002-mentorship-scheduling/plan.md` v1.2.0.  
**Dependencias:** 003 fija acceso/zona. La resolución inicial de miembros activos se apoya en relaciones y colectivos existentes. 005 heredará solo los cambios prospectivos posteriores a la reserva.

## Trazabilidad

| RF hija | Cobertura padre | Alcance de esta entrega |
|---|---|---|
| RF-004.1–004.3 | RF-1, CA-1.1–1.4; CA-11.4; RNF-2 | Alta y dedupe idempotente, validaciones/autor y momento. |
| RF-004.4–004.6 | RF-2, CA-2.1–2.7 | Ventanas/excepciones/huecos; privacidad del motivo. |
| RF-004.7 | RF-13, CA-13.1/13.5 | Conjunto inicial de participantes y rechazo indivisible por conflicto. |
| RNF-004.1–004.4 | RNF-1/2/3/9/11, P5/P8/P19 | Acceso 003, concurrencia, pruebas e integridad. |

Casos cubiertos: padre 1–7, 12, 30, 38; queda expresamente fuera el caso 41 (alta prospectiva, 005).

## Archivos y responsabilidades previstos

- `database/migrations/*mentor_availability_*`, `MentorAvailabilityWindow`, `MentorAvailabilityException`, enums/factories: persistencia UUID, índices/constraints y motivos privados (RF-004.4/5).
- `database/migrations/*add_scheduling_columns_to_sessions*`, `Session`, `SessionAttendee`, `SessionStatus`: persistir `end_at` y soporte de los participantes iniciales; la columna `timezone` pertenece a 003, conforme al orden de dependencias (RF-004.1/2/7).
- `app/Services/Scheduling/Rules/{OverlapDetector,DuplicateDetector,AvailabilityRules}.php`: núcleo puro sin IO (RF-004.2–004.6).
- `app/Services/Scheduling/SessionBookingService.php`, `app/Actions/CreateSessionAction.php`: validar/reutilizar cuota del plan, locks y crear participantes en transacción; la acción existente actual se modifica, no se duplica (RF-004.1–004.3/7).
- Form Requests/Resources/controladores/rutas: `AvailabilityController`, `SessionController`, requests `Availability/*` y `Session/StoreSessionRequest`, Resources de disponibilidad y sesión; autorización delegada a 003 (RF-004.1/4–6).
- `backend/routes/api.php`, `config/scheduling.php`, traducciones `backend/lang/{es,en,pt}/`: rutas de reservas y disponibilidad, límites de duración y textos de validación (RF-004.1–6).
- Pest unit `tests/Unit/Scheduling/{OverlapDetector,DuplicateDetector,AvailabilityRules}Test.php`; feature `tests/Feature/Scheduling/{SessionBooking,Availability,SessionConcurrency}Test.php` (RF-004.1–7).
- Frontera interfaz: exponer contrato de huecos; editor y accesibilidad de agenda no se implementan aquí (010).

## Funciones puras e interfaces

Todo predicado dependiente del tiempo recibe `now`; ninguna regla lee reloj, base, red ni configuración global. La capa de Action convierte instantes/zona de 003 antes de invocar reglas.

- `OverlapDetector::overlaps([start,end), [start,end)): bool`; `conflicts(candidate, existing, now): list<Conflict>` excluye `canceled` y «no completada» (CA-1.2/1.3/1.6).
- `DuplicateDetector::normalizeTitle(title): string`; `isDuplicate(candidate, existing, now): ?SessionRef` compara relación/inicio/duración/título normalizado (CA-1.4).
- `AvailabilityRules::check(windows, exceptions, interval, now, timezone): Decision`; exige que toda la sesión quepa, `block` prevalece, `open` puede habilitar y ausencia de ventanas exime solo filtro de ventanas, no blocks (CA-2.3/2.4/2.7).
- `AvailabilityRules::freeSlots(windows, exceptions, busy, range, duration, viewerTimezone, now): list<Slot>` devuelve únicamente huecos, nunca modo/motivo de excepción (CA-2.6).
- `SessionBookingService::validateAndCreate(input, actor, now)` es interfaz de aplicación transaccional, no función pura: autoriza vía contrato 003, bloquea relación/source/participantes en orden estable, carga conflictos, reglas y cuota y crea en un único commit.
- Interfaces API: CRUD propio de `/availability/windows` y `/availability/exceptions`; lectura `GET /mentors/{mentor}/availability/slots`; creación `POST /sessions`. Form Requests y Resources; `200` idempotente para duplicado y `201` para nueva sesión conforme a 002 (RF-004.1–6).

## Algoritmo

```text
book(input, actor, now):
  validar relación activa, tipo, fecha futura, duración 15..480 y zona (003)
  transacción:
    lock relación → source/colectivo → usuarios participantes ordenados por UUID
    resolver participantes iniciales: mentor + individuo o miembros activos
    releer sesiones de esos usuarios y disponibilidad del mentor
    si duplicate(normalize(input), existentes, now): devolver sesión existente
    conflicts = conflictos en intervalos [inicio, fin), omitiendo canceled/not_completed
    si conflicts: rechazar reserva (un miembro colectivo ocupado rechaza a todo el colectivo)
    aplicar ventana + excepciones con precedence block; sin ventanas, aún evaluar block
    validar cuota mensual vigente de plan excluyendo canceled/not_completed
    insertar sesión y asistentes iniciales con actor y created_at; commit
  devolver Resource filtrado; llamada externa no pertenece a esta entrega
```

Disponibilidad CRUD verifica propietario según 003; un fallo de validación no muta ventanas ni sesión. La inscripción posterior no se infiere por reevaluación: contrato 005.

## Decisiones y alternativas descartadas

1. **Un `SessionBookingService` común a crear y futura reprogramación:** conserva una sola validación de cuota/solape/disponibilidad; se descarta copiar la lógica dentro de controladores o de 007.
2. **Locks de relación, source y participantes ordenados más restricción única de respaldo:** cubre reservas que comparten persona entre relaciones; se descarta lock solo de relación, que no serializa esos conflictos, y confiar solo en validación previa.
3. **Reglas puras para disponibilidad/solape/duplicidad:** reproducibles y testeables; se descarta lógica con reloj/consultas dentro del controlador.
4. **Excepciones bloqueantes persisten aunque no haya ventanas; block vence a cualquier ventana:** respeta CA-2.4/2.7; no se usa un bypass que deje ignorar exceptions.
5. **Huecos sin motivo ni tipo de excepción:** preserva privacidad; se descarta exponer causa del bloqueo a quien consulta.

## Estrategia de tests y cierre

- Unit Pest: intervalos semiabiertos (solape de un minuto, contiguas), estados excluidos, título normalizado, ventana completa, `block` sin ventana, `block` vence, `open` y huecos privados; fijar `now` en cada regla (RF-004.2–6).
- Feature Pest: límites de datos, relación inactiva/tipo, mentor sin ventana, excepción sobre sesión ocupada sin borrar sesión, consulta como dueño/no dueño y campos no filtrados (RF-004.1/4–6; 003).
- Concurrencia/feature: duplicado concurrente devuelve un registro; reservas en relaciones distintas con participante común serializan; conflicto de miembro activo rechaza la sesión colectiva completa; límite mensual excluye canceled/not_completed (RF-004.2/3/7; CA-11.3/11.4).
- Compatibilidad/contrato: respuesta Resource contiene los asistentes iniciales que 005/006/008 consumirán; no afirmar altas posteriores, lifecycle, avisos, Meet o calendario.

## Dependencias contractuales

- **De 003:** actor autorizable y timezone explícita; reglas no replican acceso.
- **A 005:** fila de participante inicial del colectivo es la base sobre la que altas/bajas actúan prospectivamente; caso 41 no deshace reserva.
- **A 006/008:** provee participantes iniciales con ancla/fecha de alta y conflicto colectivo integral; no crea registros de asistencia ni resuelve destinatarios al enviar.
- **A 007:** `SessionBookingService` es la misma validación completa para reprogramar, excluida la propia sesión; deberá revalidar dentro de su transacción.
- **A 009/010:** contrato persistente/Resource y huecos consumibles; no añade superficie de calendario.
