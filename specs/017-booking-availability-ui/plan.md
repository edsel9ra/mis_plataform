# Plan — Spec 017: Reserva de sesiones y editor de disponibilidad

**Estado:** implementado. Revisión final en `review.md`.
**Spec:** `specs/017-booking-availability-ui/spec.md` (implementada).
**Base:** reglas y endpoints de la 004 ya implementados (`AvailabilityController`, `SaveAvailabilityWindowRequest`, `SaveAvailabilityExceptionRequest`, `GetAvailabilitySlotsRequest`, `AvailabilitySlotsService`, `Rules/AvailabilityRules`, `SessionBookingService::book`, `SessionPolicy::create`/`manageAvailability`/`viewAvailabilitySlots`) y patrones de la 010/016 (`Dialog`, `FormErrorSummary`, `fieldErrorPresentation`, `announceError={false}`, región `role="status"`, helpers de zona en `lib/scheduling.ts`).

## Hechos comprobados en el código

- `GET /mentors/{mentor}/availability/slots?from&to&duration_minutes`: `from`/`to` se interpretan en la zona del perfil de quien consulta salvo que lleven desfase; devuelve `{start, end, timezone}` (ISO con desfase, zona del consultante). Huecos cada 30 min; sin ventanas cubren las 24 h. Solo descuenta la ocupación del mentor y de quien consulta. No hay límite de rango.
- Ventanas (`/availability/windows`): `day_of_week` 0–6, `start_time`/`end_time` `H:i`, `timezone`; el servidor rechaza `end_time <= start_time` (clave `end_time`) y el solape con otra ventana del mismo día (clave `day_of_week`). Respuesta `{id, day_of_week, start_time, end_time, timezone}`.
- Excepciones (`/availability/exceptions`): `mode` `block|open`, `start_date`, `end_date?`, `start_time?`/`end_time?` (juntas), `timezone`, `reason?`. Respuesta con `reason` (solo la ve el propio mentor). **Hoy no informa de las sesiones afectadas.**
- Solo el rol mentor accede a ventanas y excepciones, y solo a las propias (resto: 404).
- `POST /sessions`: `relationship_id`, `session_type` (debe coincidir con el tipo de la relación: personal→individual, familiar→family, grupal→group, empresa→corporate), `title`, `description?`, `scheduled_at`, `duration_minutes`, `timezone?`. 201 si crea, **200 si es un reintento idempotente** (misma sesión).
- `GET /relationships` lista las relaciones visibles para la persona, pero **no indica en cuáles puede programar** (`SessionPolicy::create` → mentor, cliente individual o responsable del colectivo).

## Trazabilidad RF

| RF | Cobertura |
|---|---|
| RF-017.1 | Página `/availability` (solo mentor) con ventanas por día y excepciones ordenadas; enlace en la navegación del mentor y en `/sessions`. |
| RF-017.2 | Diálogo de ventana (crear/editar) + confirmación de borrado; errores del servidor por campo. |
| RF-017.3 | Diálogo de excepción (crear/editar) + borrado; motivo marcado como privado. |
| RF-017.4 | Backend: `conflicting_sessions` en la respuesta de crear/editar una excepción bloqueante; frontend: aviso con enlaces al detalle (016). |
| RF-017.5 | La página solo se ofrece a mentores; el servidor sigue autorizando (404 para otros). |
| RF-017.6 | Backend: `GET /sessions/bookable-relationships`; frontend: botón «Reservar sesión» en `/sessions` (solo si la lista no está vacía) y selector de relación en `/sessions/new`. |
| RF-017.7 | Lista de huecos por semana navegable en la zona de quien consulta, con duración elegible. |
| RF-017.8 | Formulario de título y descripción + resumen + confirmación; `session_type` lo da el servidor. |
| RF-017.9 | Mapeo de rechazos 422 a campos o resumen, conserva los datos, refresca huecos; 200 = ya reservada. |
| RF-017.10 | Anuncio de éxito, enlace al detalle e invalidación de `['sessions']`. |
| RF-017.11–017.13 | i18n es/en/pt, teclado, foco, anuncios y zona visible; revisión final con evidencia. |
| RNF-017.1–017.4 | Revisión axe/teclado/reflow/idiomas; envíos con `loading` y deshabilitados. |

## Backend (dos cambios aditivos, sin tocar reglas)

### 1. Relaciones en las que se puede reservar
`GET /api/v1/sessions/bookable-relationships` en `SessionController` (o un controlador pequeño), con una clase Action o query reutilizable:
- Parte de `MentorshipRelationship::forUser($user)->active()` y filtra con `Gate::forUser($user)->inspect('create', [Session::class, $relationship])->allowed()` — la **misma** regla que aplica `POST /sessions`.
- Respuesta (API Resource nuevo `BookableRelationshipResource`): `{ id, type, session_type, is_collective, mentor: {id, full_name}, source: {type, name} }`. `session_type` sale de la misma correspondencia que usa `SessionBookingService::expectedSessionType` (se extrae a un método público estático o a una regla compartida, sin duplicarla).
- Los administradores no usan esta pantalla (tienen `admin/sessions`): para ellos el endpoint devuelve una lista vacía (`forUser` devolvería todas). El conjunto de partida es `forUser` más las relaciones cuyo origen la persona administra según `userCanManageSource` y que `forUser` no incluye (p. ej., cohortes creadas por ella sin ser miembro); la regla final sigue siendo `SessionPolicy::create`.
- Tests: mentor, cliente individual, responsable de familia/empresa/cohorte incluidos; miembro no responsable, relación pausada/finalizada y ajeno excluidos; `session_type` correcto por tipo; coherencia: toda relación devuelta acepta `POST /sessions` en un hueco válido y ninguna excluida lo acepta (403/404).

### 2. Sesiones afectadas por una excepción bloqueante
Al crear o editar una excepción con `mode=block`, la respuesta añade `conflicting_sessions: [{id, title, scheduled_at, duration_minutes}]`: sesiones del mentor (como mentor de la relación) con estado distinto de `canceled` y `not_completed`, futuras, cuyo intervalo queda bloqueado por **esa** excepción según `AvailabilityRules` (se reutiliza la comprobación de solape de excepción existente, por ejemplo `check([], [$exception], $interval, ...)` con resultado `blocked`, o se expone el predicado `exceptionOverlaps`; sin regla paralela). Para `mode=open` y en `GET`/listados, el campo no aparece. No se cancela ni modifica nada (RF-004.5).
- Tests: excepción de día completo, de rango y con franja; sesión dentro, contigua (no solapa), cancelada y `not_completed` (excluidas), pasada (excluida), de otro mentor (excluida); zona de la excepción distinta de la app; respuesta de `open` sin el campo; listados sin cambios.

## Frontend

### Datos (`hooks/use-availability.ts`, `hooks/use-booking.ts`, `lib/query-keys.ts`)
- Query keys: `availabilityWindows`, `availabilityExceptions` (raíz `['availability']`), `availabilitySlots(mentorId, from, to, duration)` = `['availability', 'slots', …]` (bajo la misma raíz), `bookableRelationships`.
- Hooks CRUD de ventanas y excepciones (invalidan `['availability']`, que incluye los huecos), `useAvailabilitySlots`, `useBookableRelationships`, `useBookSession` (invalida `['sessions']` y los huecos de ese mentor).

### Funciones puras (`lib/availability.ts`, `lib/booking.ts`, con Vitest)
- `groupWindowsByDay(windows, locale)`: lunes→domingo, ordenadas por inicio; nombres de día con `Intl`. **Comprobado en T017-03:** `day_of_week` es el `format('w')` de PHP en `AvailabilityRules::windowContains` — **0 = domingo, 1 = lunes, …, 6 = sábado** (la presentación agrupa lunes→domingo igualmente).
- `sortExceptions(exceptions, today)`: vigentes y futuras por fecha de inicio; las pasadas se ocultan.
- `exceptionSummary(exception, locale)`: texto del periodo y franja.
- `groupSlotsByDay(slots, viewerTimezone, locale)`: agrupa por día local de quien consulta.
- `bookingWeekRange(anchor, viewerTimezone)`: semana lunes→lunes reutilizando `CalendarRange.forPeriod`.
- Mapeo de errores del servidor a campos para ventana (`day_of_week`, `start_time`, `end_time`, `timezone`), excepción (todas sus claves) y reserva (`scheduled_at`/`session` → resumen con refresco de huecos; `title`, `duration_minutes` → campo; `relationship_id`/`session_type` → resumen).

### Esquemas Zod (`lib/schemas/availability.ts`, `lib/schemas/booking.ts`)
- Ventana: día 0–6, horas `HH:mm`, fin posterior al inicio (el servidor también lo valida), zona IANA.
- Excepción: modo, fecha de inicio, fin opcional ≥ inicio, franja opcional pero completa, zona, motivo opcional.
- Reserva: relación, duración 15–480 (constantes de la 016), título obligatorio (trim, máx. 200 como el servidor), descripción opcional.

### Pantallas
- **`/availability`** (`app/[locale]/availability/page.tsx`): solo mentores (otros roles: mensaje «no disponible» y enlace a la agenda; la ruta se añade a los prefijos protegidos del middleware). Dos secciones con h2: «Horario semanal» (ventanas por día, botones Editar/Eliminar con nombre contextual, «Añadir franja») y «Excepciones» (lista, Editar/Eliminar, «Añadir excepción»). Diálogos `AvailabilityWindowDialog`, `AvailabilityExceptionDialog` y confirmación de borrado. Tras guardar una excepción bloqueante con `conflicting_sessions`, aviso persistente (`role="status"`) con la lista y enlaces al detalle de cada sesión.
- **Navegación:** entrada «Disponibilidad» en el menú del mentor (`AppShell`), y enlace en `/sessions` para mentores.
- **`/sessions/new`** (`app/[locale]/sessions/new/page.tsx`): paso 1, relación (select) y duración (por defecto 60); paso 2, lista de huecos de la semana agrupados por día (botones con nombre «{día}, {hora} – {hora} ({zona})», `aria-pressed` en el elegido), con «Semana anterior/siguiente» y estado vacío; paso 3, título, descripción y resumen, con «Confirmar reserva». Los pasos son secciones de una misma página (no un asistente con estado oculto), así cada control es accesible y el foco se mueve al encabezado del paso siguiente.
- **Primera semana con huecos (RF-017.7b, cambio aprobado):** al abrir `/sessions/new` y al cambiar relación o duración (válida), se piden los huecos semana a semana desde la actual (con las mismas query keys por semana, para que queden en caché) y se muestra la primera con algún hueco; se detiene en cuanto encuentra una, como máximo 8 semanas (constante documentada `BOOKING_WEEK_SEARCH_LIMIT = 8`). Sin resultados, se queda en la semana actual con el mensaje de «sin huecos en las próximas 8 semanas». La búsqueda se cancela si cambian relación o duración a mitad. Descartado pedir un rango de 8 semanas en una sola llamada: sin ventanas del mentor serían miles de huecos y el endpoint no limita el rango (ver Riesgos). Helper puro `firstWeekWithSlots` testeado con un buscador simulado.
- **Entrada:** botón «Reservar sesión» en `/sessions`, visible solo si `useBookableRelationships` no está vacío.
- **Éxito:** anuncio (201 «Sesión reservada», 200 «La sesión ya estaba reservada»), enlace al detalle y botón para volver a la agenda; el calendario se actualiza por invalidación.

### i18n
Namespaces `availability` y `booking` en es/en/pt, sin romper el test de paridad.

## Decisiones y alternativas descartadas

1. **Endpoint dedicado de relaciones reservables.** Descartado añadir `can_schedule` a `GET /relationships` (cambia un contrato usado en varios sitios y evalúa la política en cada listado) y descartado ofrecer todas las relaciones activas dejando que el servidor rechace (incumple RF-017.6).
2. **Conflictos calculados por el servidor al guardar la excepción.** Descartado calcularlos en el cliente cruzando la agenda con la excepción (sería una regla paralela de solape de excepciones, contra RNF-017.2).
3. **Reserva en una página propia (`/sessions/new`) con pasos visibles.** Descartado un diálogo: la lista de huecos de una semana es larga y en 320 px un diálogo con scroll interno es peor para el reflow y el teclado.
4. **`session_type` lo da el servidor.** Descartado deducirlo en el cliente (duplicaría la correspondencia de `SessionBookingService`).
5. **Sin límite de rango nuevo en los huecos.** El cliente pide una semana; limitar el rango en el servidor cambiaría el contrato de la 004 y queda fuera de alcance (se anota como riesgo).

## Estrategia de tests

- **Pest feature:** endpoint de relaciones reservables (matriz de roles y estados + coherencia con `POST /sessions`); `conflicting_sessions` (casos de arriba); contratos sin cambios en listados y en `GET` de excepciones.
- **Vitest:** funciones puras, esquemas, mapeo de errores y contratos de hooks con `api` simulado (URL, método, cuerpo, invalidaciones), como en la 016.
- **Playwright desechable + axe:** mentor crea/edita/borra ventana y excepción con teclado, ve el aviso de sesiones afectadas; cliente individual y responsable reservan un hueco con teclado; rechazo por hueco ocupado entre medias (refresco de huecos y datos conservados); reintento idempotente; persona sin relaciones reservables; axe sin violaciones, reflow 320/1280 en es/en/pt. Evidencia en `/tmp/pw017/` y revisión en `specs/017-booking-availability-ui/review.md`; al terminar se cierra T010-04.

## Riesgos

- `GET .../availability/slots` no limita el rango; un cliente malicioso podría pedir rangos enormes. Fuera de alcance de esta spec; conviene una spec o tarea de endurecimiento aparte.
- En sesiones colectivas, un hueco ofrecido puede rechazarse porque otro miembro está ocupado (los huecos solo descuentan mentor y solicitante). La interfaz lo explica con el motivo del servidor (caso límite de la spec).
