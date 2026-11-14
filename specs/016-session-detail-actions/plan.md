# Plan — Spec 016: Acciones sobre una sesión desde su detalle

**Estado:** implementado (T016-01–10 completadas).
**Spec:** `specs/016-session-detail-actions/spec.md` (aprobada).
**Base:** reglas de 003/006/007 ya implementadas en `backend/app/Services/Scheduling/Rules/*`, acciones `RescheduleSessionAction`, `CancelSessionAction`, `CompleteSessionAction`, `ConfirmAttendanceAction`, autorización `SessionPolicy::manage`/`manageStatus`, y los patrones de accesibilidad de la 010 (`FormErrorSummary`, `fieldErrorPresentation`, `Dialog`, `announceError`).

## Trazabilidad RF

| RF | Cobertura |
|---|---|
| RF-016.1 | Diálogo de reprogramación + hook + esquema Zod + conversión de hora de pared a instante. |
| RF-016.2 | Diálogo de cancelación con motivo, aviso de cancelación tardía a partir de `available_actions.cancel.late`. |
| RF-016.3 / 016.4 | Panel de asistencia: entrada propia y por miembro a cargo, a partir de `available_actions.attendance`. |
| RF-016.5 | Diálogo de confirmación de completar. |
| RF-016.6 | `SessionActionAvailability` en el backend + `available_actions` en `GET /sessions/{id}` + presentación de motivos en el frontend. |
| RF-016.7 | Claves es/en/pt para motivos, diálogos y anuncios; traducción de los rechazos de `CompleteSessionAction`. |
| RF-016.8 | Gestión de foco en diálogos y tras el éxito; verificación con teclado. |
| RF-016.9 | Invalidación del detalle y del rango del calendario tras cada mutación. |
| RF-016.10 | Ante un rechazo 403/422 de una acción ofrecida: mostrar el error y volver a cargar el detalle. |
| RNF-016.1–016.4 | Revisión axe/teclado/reflow/idiomas con evidencia; botón deshabilitado y `loading` durante el envío. |

## Backend

### Reglas puras con motivo (sin cambiar su comportamiento)
Las reglas actuales devuelven booleanos o decisiones sin motivo. Se añaden variantes que devuelven el **código de motivo** y las acciones pasan a usarlas, para que la disponibilidad informada y la ejecución compartan la misma fuente:

- `ChangeRules::rescheduleBlocker(status, scheduledAt, now, minimumNoticeMinutes): ?string` → `null` | `terminal_status` | `already_started` | `insufficient_notice`. `canReschedule()` pasa a ser `rescheduleBlocker(...) === null`. `RescheduleSessionAction` usa el código para elegir el mensaje que ya emite (`sessions.reschedule_not_eligible` / `sessions.reschedule_notice`).
- `ChangeRules::cancelBlocker(status): ?string` → `null` | `terminal_status` (solo `scheduled` e `in_progress` se pueden cancelar). `CancelSessionAction` lo usa. `ChangeRules::isLate()` ya existe y alimenta `cancel.late`.
- `LifecycleRules::completeBlocker(status, startAt, endAt, now, closeAfterHours): ?string` → `null` | `terminal_status` | `not_ended` | `window_closed` | `invalid_schedule`. Reproduce exactamente las comprobaciones actuales de `CompleteSessionAction`, que pasa a usarla.
- `AttendanceRules::assess(...)` con la misma firma que `evaluate()`, que devuelve `[AttendanceDecision, ?string $reason]` con `not_registered` | `terminal_status` | `excluded` | `not_ended` | `window_closed` | `forbidden` | `not_chargeable` | `already_confirmed_by_member`. `evaluate()` pasa a ser `assess(...)[0]`, con lo que su comportamiento y sus tests no cambian. `Keep` se traduce en no disponible con motivo `already_confirmed_by_member` (RF-016.4).

Todas reciben `now` como parámetro y no hacen IO.

### Elegibilidad de asistencia compartida
`ConfirmAttendanceAction::actorRelation()` y `chargeableTargetIds()` se extraen a `App\Services\Scheduling\AttendanceEligibility` (métodos públicos `actorRelation(User, string $targetId, MentorshipRelationship)` y `chargeableTargetIds(MentorshipRelationship, Session)`). La acción y el servicio de disponibilidad los usan, de modo que no hay dos versiones de "miembros a cargo".

### Servicio de disponibilidad
`App\Services\Scheduling\SessionActionAvailability::for(Session $session, User $user, CarbonInterface $now): array`, una interfaz con IO que carga los asistentes, las exclusiones y la relación:

```json
{
  "reschedule": { "allowed": false, "reason": "insufficient_notice" },
  "cancel":     { "allowed": true,  "reason": null, "late": false },
  "complete":   { "allowed": false, "reason": "not_ended" },
  "attendance": [
    { "user_id": "…", "self": true,  "allowed": false, "reason": "not_ended", "current_status": "unconfirmed" },
    { "user_id": "…", "self": false, "allowed": true,  "reason": null,        "current_status": "unconfirmed" }
  ]
}
```

- `reschedule`/`cancel`: primero `Gate::forUser($user)->inspect('manage', $session)`; si se deniega → `forbidden`; si no, el bloqueante de la regla.
- `complete`: `inspect('manageStatus')` (solo el mentor) → `forbidden`; si no, `completeBlocker`.
- `attendance`: la entrada propia si la persona es asistente registrada, más una entrada por cada `chargeableTargetIds` si es responsable. Cada una se evalúa con `AttendanceRules::assess` y los datos que hoy usa la acción. `current_status` sale del registro del asistente. Las personas que no son asistentes ni responsables reciben `[]`.
- **No se evalúa** (y la spec no lo exige) la validación completa de reprogramación de la 004 (solapes, disponibilidad, cuota), porque depende de la fecha nueva; la rechaza el servidor al enviar (RF-016.1) y la interfaz muestra el motivo.

### Exposición
- Solo `GET /sessions/{id}` (`SessionController::show`) añade `available_actions`, mediante `SessionResource::withAvailableActions(array)` y `$this->when(...)`. Los listados (`GET /sessions`, calendario) no lo incluyen, para no añadir consultas por fila ni cambiar su contrato.
- Se actualiza el snapshot de contrato si cubre el detalle de sesión (cambio deliberado y testeado).
- **Enmienda aprobada (tras T016-07):** el detalle también expone `timezone` (columna existente de la sesión), con el mismo mecanismo que `available_actions` (solo en `GET /sessions/{id}`; listados, calendario y admin no cambian). Lo necesita el diálogo de reprogramar para prellenar fecha, hora y zona en la zona de la sesión (RF-016.1). Alternativas descartadas: exponerla en todas las respuestas de sesión (cambia más contratos) o usar la zona del perfil (incumple RF-016.1). Se implementa dentro de T016-09.

### Traducción de rechazos
`CompleteSessionAction` tiene literales en español: "Solo el mentor de la relación puede completar la sesión.", "La sesión está en un estado terminal…", etc. Pasan a claves `sessions.complete_*` en `lang/{es,en,pt}/sessions.php`. Se revisa que los demás rechazos de reprogramar, cancelar y asistencia ya usen `__()`.

## Frontend

- **Tipos y presentación pura** (`frontend/src/lib/session-actions.ts` + test):
  - Tipos `SessionAvailableActions`.
  - `actionReasonKey(action, reason): string`, que mapea a `session_actions.reasons.*` con fallback genérico.
  - `attendanceTargetsPresentation(...)`.
- **Zona horaria:** `wallTimeToInstant(date, time, timezone): string` (ISO UTC), pura y con tests de DST, salvo que ya exista un helper equivalente de la 003 en `lib/scheduling.ts`/`lib/datetime.ts`. Antes de implementar se comprueba cómo interpreta `SessionBookingService::validateReschedule` el par `scheduled_at` + `timezone`, y se envía el formato que espera.

  Comprobado: el servidor hace `Carbon::parse(scheduled_at)->setTimezone(app.timezone)` — si la cadena lleva offset o `Z`, ese instante se respeta; si no lleva offset, se lee como hora de `America/Mexico_City`. El campo `timezone` **no** se usa para interpretar `scheduled_at`; solo se persiste y alimenta las comprobaciones de ventana de disponibilidad. Por tanto el cliente convierte (fecha, hora, zona IANA) a un instante ISO UTC con `Z` y envía ese valor junto con `timezone`. `wallTimeToInstant` reutiliza `zonedTimeToInstant`; por CA-3.4 (reprogramar es un cambio explícito, nunca un desplazamiento silencioso) `isExistingWallTime` comprueba que la hora de pared exista — el esquema de reprogramación rechaza horas dentro del hueco DST con `session_actions.validation.time_nonexistent` y `toReschedulePayload` lanza `TypeError` como defensa en profundidad; los horarios ambiguos de otoño se resuelven a la primera ocurrencia. `instantToWallTime` devuelve `{date, time}` en la zona para prellenar el diálogo.
- **Esquemas Zod** (`frontend/src/lib/schemas/session-actions.ts`):
  - Reprogramar: fecha, hora, duración entera entre 15 y 480 (los valores de `config/scheduling.php`, que se exponen como constantes documentadas), zona IANA y motivo opcional.
  - Cancelar: motivo con `trim` no vacío.
  - Asistencia: `attended` | `absent`.
- **Hooks** (`frontend/src/hooks/use-sessions.ts`): `useRescheduleSession`, `useCancelSession`, `useCompleteSession`, `useConfirmAttendance`. En `onSettled` invalidan el detalle (`['sessions', id]`) y el rango del calendario. En los errores 403/422 el componente vuelve a cargar el detalle (RF-016.10).
- **Componentes** (`frontend/src/components/scheduling/session-actions/`):
  - `SessionActionsPanel`: botones para las acciones permitidas y texto con el motivo de las no permitidas.
  - `RescheduleSessionDialog`.
  - `CancelSessionDialog`, con aviso de cancelación tardía.
  - `CompleteSessionDialog`.
  - `AttendancePanel`: grupo "Asistió/No asistió" por persona, con su estado actual y su motivo.

  Todos usan `Dialog`, `Input`/`Select`/`Textarea` con `announceError={false}` + `FormErrorSummary`, `noValidate`, foco inicial en el primer campo, retorno al disparador, y anuncio del éxito en una región `role="status"`.
- **Integración:** `/sessions/[id]` monta el panel bajo la tarjeta del detalle. Tras un éxito, el foco va al encabezado de estado de la sesión (`tabIndex=-1`).
- **i18n:** namespace `session_actions` en es/en/pt, sin romper el test de paridad de la agenda.

## Decisiones y alternativas descartadas

1. **Motivos como códigos estables traducidos en el cliente.** Descartado enviar texto ya traducido: los códigos se pueden testear y el frontend ya traduce con fallback a es.
2. **Reglas con motivo reutilizadas por las acciones.** Descartado un servicio de disponibilidad con condiciones propias: sería una regla paralela, que RF-016.6 prohíbe.
3. **`available_actions` solo en el detalle.** Descartado en los listados por coste (N+1) y para no cambiar el contrato del calendario.
4. **Sin evaluar solapes ni cuota antes de enviar una reprogramación.** Dependen de la fecha nueva; se descarta una "previsualización" porque la spec no la pide.
5. **Refetch del detalle ante un rechazo** en lugar de actualizaciones optimistas: evita estados incoherentes (RF-016.10).

## Estrategia de tests

- **Pest unit:** cada `*Blocker`/`assess` con bordes exactos (inicio, fin, fin + 48 h, aviso de 2 h, estados terminales) y equivalencia `evaluate == assess[0]`. Los tests existentes de las reglas y acciones siguen en verde sin cambios.
- **Pest feature:**
  - Matriz de rol (mentor, cliente individual, responsable, miembro no responsable, ajeno, admin con y sin soporte) × estado/momento (futura con más de 2 h, futura con menos de 2 h, en curso, terminada dentro de la ventana, terminada fuera de la ventana, cancelada, completada) sobre `available_actions`.
  - **Test de coherencia:** en cada caso de la matriz, `allowed === true` ⇔ ejecutar la acción real (con datos válidos) no la rechaza por permiso ni por estado.
  - Traducción en/pt de los rechazos de completar.
  - Ajeno → 404 en el detalle (contrato vigente).
- **Vitest:** `actionReasonKey`, presentación de asistencia, esquemas Zod (motivo con solo espacios, duración fuera de rango) y `wallTimeToInstant` (DST y zonas con offset negativo).
- **Playwright desechable + axe** (como en la 010): flujos con teclado de las 4 acciones, rechazo con refresco (cancelar desde otra pestaña y luego intentar reprogramar), axe sin violaciones, reflow a 320/1280 en es/en/pt. Evidencia en `/tmp/audit/a11y/016-*`, y revisión en `specs/016-session-detail-actions/review.md`.
