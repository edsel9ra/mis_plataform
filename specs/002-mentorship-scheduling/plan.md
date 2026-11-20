# Plan de implementación — Spec 002: Agenda y gestión de mentorías

**Estado:** implementado a través de las specs hijas 003–010 y 016–018 (cabecera puesta al día el 2026-11-20)
**Versión:** 1.2.0
**Fecha:** 2026-10-12
**Spec de referencia:** `specs/002-mentorship-scheduling/spec.md` (v0.4.2, aprobada)
**Constitución:** `docs/constitution.md` (v1.1.0, principios P1–P20 y decisiones §3.1)
**Ámbito:** decisiones de diseño posteriores a la spec (el CÓMO). No define todavía código ejecutable; los bloques marcados como *pseudocódigo* son lenguaje natural estructurado.

---

## 0. Resumen ejecutivo

La agenda se construye **sobre las sesiones ya existentes** (P2): no se crea una entidad paralela, sino que se endurecen `sessions` y `session_attendees` con **migraciones aditivas** (zona horaria, `end_at`, estados de ciclo de vida, trazabilidad de cancelación/reprogramación, confirmación de asistencia), se añaden dos tablas nuevas de **disponibilidad** y dos de **trazabilidad** (historial de cambios, eventos de asistencia), y se introduce un **núcleo de reglas puras** (`app/Services/Scheduling/Rules/*`) testeable sin base de datos.

La escritura pasa por **Actions** (crear, reprogramar, cancelar, completar, confirmar asistencia) que revalidan el conjunto completo de reglas; la autorización **se reutiliza** de `MentorshipRelationship::userCanAccessSource()`/`userCanManageSource()` y de `SessionPolicy` (ampliado). El ciclo de vida (`in_progress` automático, «no completada» a las 48 h), los recordatorios (24 h/2 h/1 h con dedupe por fecha vigente) y la **generación automática de Google Meet con reintento** viven en tres comandos artisans agendados en `routes/console.php`.

En frontend, una nueva ruta `dashboard/agenda` ofrece calendario día/semana/mes, detalle de sesión, formularios de crear/reprogramar/cancelar y el editor de disponibilidad del mentor, todo con react-query + Zod + `components/ui/*`, sin dependencias nuevas.

No se introduce ninguna tecnología nueva: Laravel, PostgreSQL, Redis (solo caché), Reverb (ya existente, para recordatorios/avisos) y Next.js. La capa frontend consume el evento privado `SessionReminder`/`user.{id}` existente; no añade canal ni API.

---

## 1. Principios de la constitución aplicables y cómo se cumplen

| Principio | Efecto en el diseño | Partes del plan |
|---|---|---|
| **P1** única fuente de verdad | Todo vive en `sessions`/`session_attendees`; Google es proveedor externo (su fallo nunca revierte una operación de agenda). | §6, §9.5, §12 |
| **P2** arquitectura existente primero | Se reutilizan sesiones, relaciones, `Notification`+dedupe, `GoogleMeetService`, Reverb y `components/ui/*`; sin dependencias nuevas. | §7, §11, §12 |
| **P3** autorización en cada operación | `SessionPolicy` ampliado (participante, responsable de colectivo, admin con permiso explícito); 404 para recursos ajenos; disponibilidad solo propia. | §9.8, §13 |
| **P4** privacidad desde el diseño | Motivos de excepciones no se exponen a terceros; recordatorios sin datos innecesarios; sin invitados en el evento externo. | §5.3, §13 |
| **P5** trazabilidad | Tabla `session_schedule_changes`, columnas de cancelación/cierre, `session_attendees.confirmed_by/at`, `session_attendance_events` append-only y activitylog para creación/transiciones/cierres y operaciones de soporte. | §6, §7, §9.1, §13 |
| **P6** progreso por evidencias | El cierre (`completed`) es testimonio exclusivo del mentor; la asistencia se confirma, nunca se infiere; registros iniciales = «no confirmada». | §9.1, §9.6 |
| **P8** agenda consistente | Estados explícitos con grafo validado, solapes por participante, duplicidades normalizadas, zona por sesión, idempotencia con índice único de respaldo. | §8, §9.1, §9.2 |
| **P14** automatizaciones idempotentes | Recordatorios y avisos con ID lógico estable; el backend conserva estado/reintentos y `SessionReminder` publica ese ID. El consumidor visible de 010 hace upsert; solo la confirmación del proveedor marca `sent`. Un reintento ambiguo puede repetir transporte, pero no añade otro aviso lógico visible. Meet idempotente con reintento. | §6.4, §9.4, §11.4, §15.2 |
| **P16** experiencia accesible | Estados con texto + color, errores junto al campo, `aria-live`, teclado en calendario y formularios. | §11.4 |
| **P17** contratos de API | Form Requests + API Resources; errores i18n sin trazas; timeouts en Google. | §5, §10 |
| **P18** cambios de datos seguros | Migraciones **aditivas** (nada se elimina ni renombra); `attended` y `status` conservan su significado. | §6 |
| **P19** calidad demostrable | Reglas puras unit-testeadas + feature tests de solapes, concurrencia, permisos y transiciones (lo que pide literalmente la verificación de P8). | §15 |
| **P20** observabilidad | `scheduling.meet_failed`, `scheduling.meet_delete_failed`, `scheduling.reminder_failed`, `scheduling.lifecycle_*` con solo ids; fallos externos diagnosticables sin enlace. | §13 |

---

## 2. Puntos que la spec deja abiertos y se resuelven aquí (supuestos de diseño)

La spec 002 no tiene dudas de producto (§10 cerrada), pero deja explícitamente decisiones «de diseño posterior». Se aplican por defecto; cada una tiene un único punto de cambio.

| # | Punto abierto en la spec | Decisión de diseño (default) | Punto de cambio |
|---|---|---|---|
| S1 | Representación del estado «no completada» (CA-11.6: «almacenada o derivada es decisión de diseño posterior») | **Quinto valor almacenado** `not_completed` en `sessions.status` (enum `SessionStatus::NotCompleted`) | `SessionStatus` + scopes |
| S2 | Entrega, ID lógico y dedupe visible (CA-5.2/5.6) | `notifications.id` es estable entre intentos; `status` (`pending`/`sent`/`failed`) + `sent_at` distinguen el resultado; solo confirmación del proveedor marca `sent`. `SessionReminder` transporta `notification_id`; 010 consume el evento privado existente y actualiza/reemplaza el aviso visible por ese ID. Una redelivery ambigua puede repetir transporte, no el aviso lógico visible. La aceptación final de RF-5/RF-008.2 queda condicionada al test integrado de 010. | persistencia `notifications` + productor 008 + consumidor de presentación 010 |
| S3 | Cadencia fija de reintentos de Meet (CA-9.2) | Comando `app:generate-meet-links` **cada 5 min** (`config/scheduling.php`) hasta la hora de inicio | `routes/console.php` |
| S4 | Qué es «menor» en familia/cohorte (CA-8.1) | `FamilyMember.age < 18` **con edad conocida**; sin edad → se trata como adulto (no confirma el responsable). En cohorte se reutiliza la edad de `FamilyMember` del mismo usuario si existe; si no, el coordinador no confirma por nadie (conservador, P6/P3) | `ParticipantRules::chargeableMembers()` |
| S5 | Calendario: ¿endpoint nuevo o extender el listado (RF-4/CA-4.6) | **Extender `GET /sessions`** con `from`/`to`/`include_collectives` (misma fuente, P1) | `SessionController@index` |
| S6 | ¿Tiempo real con Reverb para la agenda? | `SessionReminder` conserva su canal privado existente `user.{id}`; los avisos de cambio usan el evento/canal ya fijado por su contrato. El consumidor de 010 escucha el evento existente; el **calendario no** se suscribe a WebSockets (refetch en foco + invalidación tras mutación). No se añade un canal. | §11.4, §12 DT-14 |
| S7 | Ancla de «participantes registrados» (menor 4 de la revisión) | Filas `session_attendees` **nunca borradas** + columnas `joined` implícito (`created_at`) y `left_at` (baja) → ancla en `end_at` | §6.3, §9.3 |
| S8 | Zona de sesiones heredadas (caso 32, CA-3.3) | `sessions.timezone NOT NULL DEFAULT 'America/Mexico_City'` (= zona por defecto del sistema, `config/app.php`) | migración §6.1 |
| S9 | Rejilla de «huecos libres» (CA-2.6) | Pasos de **30 min** (`slot_step_minutes`) en la zona del consultante | `AvailabilityRules::freeSlots()` |
| S10 | Historial de reprogramación (CA-6.1/6.6) | Tabla propia `session_schedule_changes` (no activitylog) | §6.2 |
| S11 | Permisos explícitos de soporte (RF-10/CA-10.1–10.6) | Permisos `scheduling.view.support` y `scheduling.manage.support`; ser admin por sí solo no concede acceso a la agenda | Seeder de permisos + `SessionPolicy` |

---

## 3. Vista de arquitectura y flujo

```
Next.js  ·  dashboard/agenda (calendario, detalle, disponibilidad)
   │  react-query + Zod + components/ui/* + lib/scheduling.ts (puro)
   ▼
GET /api/v1/sessions?from&to  ·  POST /sessions/{id}/reschedule|cancel|complete|attendance ...
   ▼
Laravel: Controllers delgados
   ├── Form Requests (validación, P17)
   ├── SessionPolicy / MentorshipRelationship::userCanAccessSource|ManageSource (P3)
   ├── Actions: Create · Reschedule · Cancel · Complete · ConfirmAttendance  (escritura)
   │        └── SessionBookingService (revalidación completa) · SessionParticipantSyncService
   ├── Rules PURAS (sin IO): Overlap · Duplicate · Availability · Reminder ·
   │        Attendance · Lifecycle · Change · Participant          ← núcleo testeable (RNF-11)
   ├── Services de IO: SessionNotificationService · MeetAutomationService · SessionLifecycleService
   ├── API Resources (contrato estable)
   └── Comandos (routes/console.php, cada minuto / 5 min):
        app:advance-session-lifecycle · app:send-session-reminders · app:generate-meet-links
   ▼
PostgreSQL (sessions*, session_attendees*, mentor_availability_*,
            session_schedule_changes, session_attendance_events, notifications)
   + activitylog (P5) · Redis (caché, nunca verdad) · Reverb (broadcast) · Google Calendar (externo)
```

Regla de oro: **las Actions orquestan, las Rules no tocan IO, los comandos aplican tiempo, los controllers no calculan.**

---

## 4. Trazabilidad rápida RF → bloques del plan

| RF | Título | Dónde se resuelve |
|---|---|---|
| RF-1 | Programación con solapes y duplicidades | §5.2, §6, §8 (F1–F4), §9.2, §15.2 |
| RF-2 | Disponibilidad de mentores | §5.3, §6.2, §8 (F5/F6), §9.2, §11.2 |
| RF-3 | Zona horaria de la sesión | §6.1, §8 (F2), §9.2, §11.3 |
| RF-4 | Vista de calendario | §5.1 (index con rango), §11.1 |
| RF-5 | Recordatorios fiables | §6.4, §8 (F7–F9), §9.4, §11.4, §15.2 (productor 008 + consumidor 010, caso 43) |
| RF-6 | Reprogramación con trazabilidad | §6.3, §7 (RescheduleSessionAction), §9.7 |
| RF-7 | Cancelación con política de antelación | §6.1, §7 (CancelSessionAction), §8 (F14), §9.5/§9.7, §15.2 (caso 42) |
| RF-8 | Registro de asistencia | §6.3, §8 (F10/F11), §9.6, §15 |
| RF-9 | Google Meet automático | §7 (GoogleMeetService/MeetAutomationService), §9.5 |
| RF-10 | Autorización de la agenda | §9.8, §13, §15.2 |
| RF-11 | Estados e idempotencia | §8 (F12), §9.1, §6.5 (índice único), §15 |
| RF-12 | i18n y accesibilidad | §10, §11.4, §15.3 (incluida presentación accesible del aviso 008 según 010) |
| RF-13 | Participación colectiva dinámica | §8 (F15/F16), §9.3, §6.3 |
| RNF-1..13 | — | §13 (1–3, 9, 10), §15 (11), §16 (12), §9.6/§6 (13) |

La matriz de §15.4 enlaza cada RF con sus pruebas de backend y frontend. Las autorizaciones administrativas se desglosan en §5.1 y §9.8: `scheduling.view.support` habilita lectura y `scheduling.manage.support` habilita operaciones de soporte; el rol administrativo por sí solo no concede ninguna de las dos.

---

## 5. Contrato de API (P17)

Todos los endpoints bajo `/api/v1`, autenticados (`auth:sanctum` + middleware `active`). Respuestas con API Resources. Errores: `401` sin sesión · `403` operación no permitida **para quien sí ve el recurso** · `404` recurso no visible o inexistente (indistinguible, CA-10.5) · `422` validación con motivos i18n (nunca trazas). Los endpoints son **compatibles**: no se eliminan rutas existentes; se estrechan reglas (ver §5.4).

### 5.1 Sesiones (RF-1, RF-3, RF-4, RF-6, RF-7, RF-8, RF-9, RF-11)

| Método y ruta | Uso / RF | Form Request | Notas |
|---|---|---|---|
| `GET /sessions?page&per_page&from&to&include_collectives` | Lista **y calendario** (RF-4) | — | `from`/`to` acotan el rango; `include_collectives=1` añade las sesiones de los colectivos donde soy responsable (CA-4.7). Misma fuente que la lista (CA-4.6). Lectura autorizada a participantes, responsables y cuentas con `scheduling.view.support`. |
| `POST /sessions` | Crear (RF-1) | `StoreSessionRequest` (modificado: +`timezone`) | Autoriza mentor, mentee y responsable del colectivo de la relación (CA-10.2); soporte administrativo exige `scheduling.manage.support`. Duplicidad → **200 con la sesión existente**; creación nueva → 201 (CA-1.4). |
| `GET /sessions/{id}` | Detalle (RF-10) | — | `view` → participante, responsable o usuario con `scheduling.view.support`; si no, 404 (CA-10.5). `scheduling.manage.support` permite operar el recurso, no consultar listados/detalles generales. |
| `PUT /sessions/{id}` | Edición menor | `UpdateSessionRequest` (**deja de aceptar `scheduled_at`/`duration_minutes`**; van por `reschedule`) | 422 si se envían campos de agenda (evita reprogramar sin revalidar). |
| `PUT /sessions/{id}/status` | Solo notas | `UpdateSessionStatusRequest` (**`status` sale de aquí**) | El estado solo cambia vía `complete`/`cancel`/ciclo de vida (CA-11.1/11.2). |
| `POST /sessions/{id}/reschedule` | Reprogramar (RF-6) | `RescheduleSessionRequest` (`scheduled_at` ≥2 h, `duration_minutes?`, `timezone?`, `reason?`) | Revalidación completa (CA-6.2); registra historial; recalcula recordatorios; actualiza Meet. |
| `POST /sessions/{id}/cancel` | Cancelar (RF-7) | `CancelSessionRequest` (`reason` obligatorio) | Marca «tardía» si <2 h o ya iniciada (CA-7.2); persiste cancelación y publica evento mínimo para que 008 solicite el borrado Meet *best-effort* (CA-7.4); conserva el valor almacenado y lo oculta en toda respuesta API mientras esté cancelada; aviso único. |
| `POST /sessions/{id}/complete` | Cierre del mentor (RF-11) | `CompleteSessionRequest` (`mentor_notes?`) | **Solo el mentor de la relación** (CA-11.1: exclusiva; P6: atestigua). Promoción robusta `scheduled→in_progress` si hace falta; crea registros iniciales «no confirmada» (CA-8.3). |
| `PUT /sessions/{id}/attendance` | Confirmar asistencia (RF-8) | `ConfirmAttendanceRequest` (`status ∈ attended\|absent`, `attendee_user_id?`) | Por defecto uno mismo; `attendee_user_id` solo si soy responsable **a cargo** de esa persona (CA-8.1); mentor y admin de soporte por otros → 403. |
| `POST /sessions/{id}/meet-link` | Generación manual (RF-9) | — | Idempotente: si ya existe, devuelve el enlace (CA-9.3); queda disponible al llegar la hora de inicio; antes de esa hora reintenta automáticamente la tarea programada (CA-9.2). |
| `DELETE /sessions/{id}` | Borrado físico | — | **Solo cuenta administrativa con `scheduling.manage.support`**, excepcional y auditada (CA-7.6); ruta existente: se restringe. |

Las rutas de soporte bajo `/admin/sessions/*` quedan sujetas a los mismos permisos explícitos y Actions que `/sessions/*`: lectura requiere `scheduling.view.support`; cualquier escritura/borrado excepcional requiere `scheduling.manage.support`. El middleware de rol administrativo existente es una barrera adicional, no una autorización suficiente.

**Permisos de soporte:** `scheduling.view.support` permite consultar agenda/listados/detalles, sin capacidad de mutación; `scheduling.manage.support` permite operaciones de soporte y hace visible el recurso únicamente al autorizar esa operación. Para lectura general (incluida posterior a una mutación), se exige `scheduling.view.support`. Ambos permisos se asignan explícitamente en el seeder a `super_admin` y `admin`; se comprueban por permiso en Policy/controlador, no se infieren del rol ni del middleware `role:super_admin|admin`.

### 5.2 Disponibilidad (RF-2, RF-10)

| Método y ruta | Uso | Form Request |
|---|---|---|
| `GET /availability/windows` · `POST /availability/windows` · `PUT|DELETE /availability/windows/{id}` | Ventanas recurrentes **propias** (CA-2.1, CA-10.4: tercero → 403/404) | `AvailabilityWindowRequest` (`day_of_week 0–6`, `start_time < end_time`, `timezone`) — CA-2.2 |
| `GET|POST /availability/exceptions` · `PUT|DELETE /availability/exceptions/{id}` | Excepciones propias (`mode ∈ block\|open`, fecha o rango, horario opcional, `reason` **privado**) | `AvailabilityExceptionRequest` |
| `GET /mentors/{mentor}/availability/slots?from=&to=&duration_minutes=` | Huecos libres **para quien programa**, en su zona (CA-2.6) | — Devuelve `[{start, end, timezone}]` **sin** tipo/motivo de excepciones (RNF-9). |

### 5.3 Respuesta de sesión (API Resource)

`SessionResource` añade a los campos actuales: `timezone`, `end_at`, `meet_status` (`ready`|`pending` — CA-9.6), `cancellation` (`reason`, `at`, `by`, `late`, `lead_minutes`), `completed_at`/`completed_by`, `attendance` (mi estado; mentor/responsable y soporte autorizado: todos — CA-8.7/CA-10.1), `history` (cambios de agenda, solo para quien ve la sesión), y `permissions` (`can_manage`, `can_complete`, `can_confirm_own`, `can_confirm_others`) como **mejora progresiva** de la UI (la autorización real sigue en servidor, CA-12.3). El campo `meet_link` se omite o devuelve nulo mientras `status=canceled`, aunque su valor siga persistido; ninguna respuesta API que entregue el enlace (incluido el endpoint manual) lo devuelve para una sesión cancelada (CA-7.4/CA-9.5).

`SessionAttendeeResource` → `{user, attendance_status, attended, confirmed_by, confirmed_at, left_at}`; `attended` se mantiene por compatibilidad con la spec 001 (CA-8.9).

### 5.4 Cambios de API documentados (compatibilidad)

- `PUT /sessions/{id}` y `PUT /sessions/{id}/status` **dejan de aceptar** cambios de `scheduled_at`/`duration_minutes`/`status` (riesgo de saltarse la revalidación); rutas equivalentes en `/admin/sessions/*` pasan por las mismas Actions.
- `POST /sessions` acepta `timezone` (por defecto la del creador) y aplica las validaciones nuevas (solape/disponibilidad/duplicidad) → respuestas `422` que antes no existían.
- Ninguna columna se elimina ni cambia de significado (P18).

---

## 6. Modelo de datos (PostgreSQL) — migraciones **aditivas** (P18)

> **Compatibilidad exigida:** `sessions.meet_link`/`meet_event_id`/`status` y `session_attendees.attended` los consume la spec 001 (`SessionMetricsCalculator`, `ProgressPanelService`). **No se renombra ni elimina nada**; `attended` pasa a ser el **reflejo** de `attendance_status` sincronizado en un hook `saving` del modelo (CA-8.9).

### 6.1 `..._add_scheduling_columns_to_sessions_table` (RF-3, RF-7, RF-9, RF-11)
- `timezone` string(50) **NOT NULL default `'America/Mexico_City'`** (CA-3.1/CA-3.3; heredadas quedan con la zona del sistema — caso 32).
- `end_at` timestamp **nullable + backfill** (`scheduled_at + duration_minutes`) + índice; se mantiene en un hook `saving` (nunca se calcula en SQL disperso).
- `completed_at`, `completed_by` uuid null FK users (P5, CA-11.1).
- `canceled_at`, `canceled_by` uuid null FK users, `cancellation_reason` text null, `canceled_late` bool default false, `cancellation_lead_minutes` int null (CA-7.1/7.2).
- `meet_attempts` int default 0, `meet_last_attempt_at` timestamp null, `meet_last_error` string(255) null (sanitizado, sin secretos — RNF-10), `meet_stale` bool default false (CA-9.2/9.4).
- Índices: `(end_at)`, `(status, end_at)`.

### 6.2 Nuevas tablas de disponibilidad y trazabilidad (RF-2, RF-6, RF-8)
- **`mentor_availability_windows`**: `id` uuid, `user_id` FK users, `day_of_week` tinyint 0–6, `start_time` time, `end_time` time, `timezone` string(50), timestamps. UNIQUE `(user_id, day_of_week, start_time, end_time)`; índice `(user_id, day_of_week)`. CHECK `end_time > start_time` (CA-2.2).
- **`mentor_availability_exceptions`**: `id`, `user_id`, `mode` `block|open`, `start_date` date, `end_date` date (= `start_date` si null), `start_time`/`end_time` time null (null = todo el día), `timezone`, `reason` text null (**nunca expuesto a terceros**, RNF-9), timestamps. CHECK `end_date >= start_date` y ambos horarios juntos o ninguno.
- **`session_schedule_changes`** (RF-6): `id`, `session_id` FK, `changed_by` FK users, `previous_scheduled_at`/`new_scheduled_at`, `previous_timezone`/`new_timezone` null, `previous_duration_minutes`/`new_duration_minutes`, `reason` text null, `created_at` (CA-6.1).
- **`session_attendance_events`** (RF-8, P5): `id`, `session_id`, `attendee_user_id`, `event_type initialization|confirmation`, `status not_confirmed|attended|absent`, `recorded_by` FK users nullable (nulo solo para inicialización automática), `occurred_at`. **Append-only**: cuando el mentor completa la sesión se registra, para cada participante anclado a `end_at`, una inicialización auditada `not_confirmed` con origen sistema e instante efectivo `end_at`; si ya hubo confirmación entre `end_at` y el cierre, se conserva el estado efectivo confirmado y se registra la inicialización sin sobrescribirlo. Al pasar a `not_completed` se asegura la misma inicialización para cualquier participante aún no inicializado. Cada confirmación conserva actor e instante, incluso si la del miembro prevalece y la del responsable queda como antecedente sin efecto (CA-8.3/8.6). Índice único parcial `(session_id, attendee_user_id)` para `event_type='initialization'` hace idempotente la inicialización sin impedir múltiples confirmaciones.

### 6.3 `..._add_attendance_columns_to_session_attendees_table` (RF-8, RF-13)
- `attendance_status` string(20) NOT NULL default `'not_confirmed'` ∈ {`not_confirmed`,`attended`,`absent`} (§4: tres estados).
- `confirmed_by` uuid null FK users, `confirmed_at` timestamp null.
- `left_at` timestamp null — marca de **baja del colectivo sin borrar la fila** (CA-13.3; ancla «participantes registrados» = filas cuyo `created_at ≤ end_at` y (`left_at` null o `> end_at`) — menor 4 de la revisión).
- `attended` bool existente se mantiene sincronizado = (`attendance_status === 'attended'`).

### 6.4 `..._add_status_to_notifications_table` (RF-5)
- `status` string(12) NOT NULL default `'sent'` ∈ {`pending`,`sent`,`failed`}, `sent_at` timestamp null.
- `notifications.id` (UUID ya persistido) es el **ID lógico estable**, se reutiliza en el evento de transporte y no cambia en reintentos. `dedupe_key` identifica el aviso lógico y su fila persistente; el productor emite el mismo `notification_id` en `SessionReminder`; el consumidor visible asignado a 010 actualiza/reemplaza el elemento por ese ID, nunca anexa uno nuevo.
- Regla: solo la confirmación positiva del proveedor marca `sent` y consume la deduplicación; `failed` y resultado ambiguo siguen reintentables con la misma fila/ID (CA-5.2/5.6). El UNIQUE en `dedupe_key` evita filas lógicas duplicadas, no evita que el transporte se publique más de una vez si la cola vuelve a entregar después de que el proveedor aceptó pero antes de persistir `sent`.
- El backend prueba identidad y estado de entrega, pero no prueba por sí solo la deduplicación visible. El consumidor de 010 hace upsert por el `notification_id` del evento privado existente `SessionReminder`; la garantía comprobable es un aviso lógico visible por ID, no exactamente una publicación de transporte. La aceptación final de RF-008.2 se cierra solo después del test integrado de 010; no se presupone transporte exactly-once.

### 6.5 `..._add_unique_backstop_to_sessions_table` (RF-11, CA-11.3)
- UNIQUE parcial `(relationship_id, scheduled_at)` **WHERE `status IN ('scheduled','in_progress')` AND `deleted_at IS NULL`** → respaldo de concurrencia si el bloqueo transaccional se escapa. Excluye `canceled`/`not_completed`/`completed` para no impedir reutilizar un hueco liberado (CA-1.6).

### 6.6 Datos derivados (no persistidos)
- «Sesión confirmada» = `status === 'scheduled' && scheduled_at > now` (definición §4) — nunca columna.
- Los campos de instante (`scheduled_at`, `end_at`, `occurred_at`, etc.) se manejan como instantes UTC compatibles con la representación existente; `timezone` conserva la zona IANA para interpretación/visualización y el proveedor externo, no para reinterpretar el instante almacenado.
- «Fuera de ventana de asistencia» = `now >= end_at + 48 h` — derivado, límite inclusivo (CA-8.8).
- Fin de sesión consultado en SQL usa `end_at` (índice); el cálculo es único: `sessionEnd()`.

---

## 7. Archivos backend: creados / modificados y responsabilidad

### 7.1 Creados — dominio (RF-1, RF-2, RF-5, RF-8, RF-11)
| Archivo | Responsabilidad | RF |
|---|---|---|
| `app/Enums/AttendanceStatus.php` | `not_confirmed`/`attended`/`absent` + `label()` | RF-8 |
| `app/Enums/AvailabilityMode.php` | `block`/`open` | RF-2 |
| `app/Models/MentorAvailabilityWindow.php` | Ventana recurrente (UUID, casts time/date) | RF-2 |
| `app/Models/MentorAvailabilityException.php` | Excepción con modo y motivo privado | RF-2 |
| `app/Models/SessionScheduleChange.php` | Historial de reprogramaciones | RF-6 |
| `app/Models/SessionAttendanceEvent.php` | Confirmaciones append-only | RF-8 |
| `database/factories/MentorAvailabilityWindowFactory.php` · `MentorAvailabilityExceptionFactory.php` · `SessionAttendeeFactory.php` | Datos de prueba | RNF-11 |
| `config/scheduling.php` (nuevo) | `horizons_hours=[24,2,1]`, `min/max_duration_minutes=15/480`, `min_notice_minutes=120`, `attendance_window_hours=48`, `not_completed_after_hours=48`, `default_timezone`, `meet_retry_every_minutes=5`, `slot_step_minutes=30`, `minor_age=18` | RF-1, RF-5, RF-7, RF-8, RF-9, RF-11 |
| `lang/{es,en,pt}/scheduling.php` (+actualizar `sessions.php`) | Motivos de rechazo, estados y avisos en 3 idiomas | RF-12 |

### 7.2 Modificados — dominio (RF-1, RF-3, RF-8, RF-11)
| Archivo | Cambio |
|---|---|
| `app/Enums/SessionStatus.php` | +`NotCompleted`, `isTerminal()`, `canTransitionTo($to)` (grafo de CA-11.1). |
| `app/Models/Session.php` | `timezone`/`end_at` en fillable+casts; hook `saving` que calcula `end_at`; scopes `past` incluye `not_completed`, `upcoming` idem; relaciones `scheduleChanges()`, `attendanceEvents()`; helpers `end()`, `isConfirmed()`, `attendanceDeadline()`. |
| `app/Models/SessionAttendee.php` | +`attendance_status`/`confirmed_by`/`confirmed_at`/`left_at`; hook `saving` sincroniza `attended` (CA-8.9). |

### 7.3 Creados — núcleo puro (RF-1, RF-2, RF-5, RF-7, RF-8, RF-11, RF-13) — ver §8

`app/Services/Scheduling/Rules/`: `OverlapDetector`, `DuplicateDetector`, `AvailabilityRules`, `ReminderRules`, `AttendanceRules`, `LifecycleRules`, `ChangeRules`, `ParticipantRules`.

### 7.4 Creados/modificados — escritura (RF-1, RF-6, RF-7, RF-8, RF-11, RF-13)
| Archivo | Responsabilidad | RF |
|---|---|---|
| `app/Actions/CreateSessionAction` (modificado) | Revalidación completa + idempotencia + inscripción de participantes + disparo de Meet | RF-1, RF-13 |
| `app/Actions/RescheduleSessionAction` (nuevo) | Revalida todo, registra historial, aviso único, actualiza Meet, recalcula recordatorios | RF-6 |
| `app/Actions/CancelSessionAction` (nuevo) | Motivo, marca tardía, persiste antes de efectos externos, publica evento mínimo post-commit para 008, aviso único, detiene recordatorios y registra transición; no borra Meet ni limpia el valor histórico almacenado | RF-7 |
| `app/Actions/CompleteSessionAction` (nuevo) | Solo mentor; promoción robusta; registros iniciales «no confirmada» | RF-11, RF-8 |
| `app/Actions/ConfirmAttendanceAction` (nuevo) | Ventana 48 h, precedencia miembro > responsable, mentor nunca por otros, evento append-only + auditoría | RF-8, RF-10 |
| `app/Services/Scheduling/SessionBookingService.php` (nuevo) | Reutilizable por crear/reprogramar: conflictos, disponibilidad, duplicidad, cuota mensual (extraída de `CreateSessionAction`, hoy `private`) | RF-1, RF-2, RF-6 |
| `app/Services/Scheduling/SessionParticipantSyncService.php` (nuevo) | Efectos prospectivos de altas/bajas (RF-13) + **regla del caso 41** (alta con sesión propia solapando → no se inscribe en esa sesión y no se revalida) | RF-13 |
| `app/Services/Scheduling/SessionNotificationService.php` (nuevo) | Recordatorios y avisos de cambio: una fila/ID estable por aviso lógico (dedupe por fecha vigente/evento), estado de entrega confirmado por proveedor, y broadcast por el evento privado existente con el mismo ID para cada reintento. Provee el contrato de entrada para el consumidor de presentación 010; no afirma por sí solo dedupe visible. | RF-5 |
| Observadores en `FamilyMember`/`CohortMember`/`Employee` (nuevo) | `created`/`deleted`/`status` → `SessionParticipantSyncService` (miembros activos del colectivo) | RF-13 |

### 7.5 Creados — automatizaciones y Meet (RF-5, RF-9, RF-11)
| Archivo | Responsabilidad | RF |
|---|---|---|
| `app/Console/Commands/AdvanceSessionLifecycle.php` (`app:advance-session-lifecycle`) | `scheduled→in_progress` a la hora de inicio; `→not_completed` en `end_at + 48 h`; al cierre terminal asegura inicializaciones auditadas aún ausentes; idempotente con bloqueo | RF-11, RF-8 |
| `app/Console/Commands/SendSessionReminders` (modificado) | Horizontes en zona de sesión, dedupe con fecha vigente y estado `sent`, recordatorio inmediato (CA-5.8), participantes dinámicos, fallos → `failed` | RF-5, RF-13 |
| `app/Console/Commands/GenerateMeetLinks` (`app:generate-meet-links`) | Backfill + reintento cada 5 min de `scheduled` futuras sin enlace o con `meet_stale`; idempotente con bloqueo; para a la hora de inicio (después, manual) | RF-9 |
| `app/Services/Scheduling/MeetAutomationService.php` (nuevo) | Decide crear/actualizar/eliminar; lleva `meet_attempts`/`meet_last_error`, registra fallo de borrado sin incluir el enlace y nunca revierte una cancelación | RF-7, RF-9 |
| `app/Services/GoogleMeetService` (modificado) | `createMeetEvent(..., string $timezone)` (**fin de la zona fija**, CA-9.7), `updateEvent(...)` (CA-9.4), `deleteEvent(...)` para consumidor 008; timeouts ya existentes | RF-7, RF-9, RF-3 |

### 7.6 Creados — API (P17)
| Archivo | Responsabilidad | RF |
|---|---|---|
| `app/Http/Requests/Session/{RescheduleSessionRequest, CancelSessionRequest, CompleteSessionRequest, ConfirmAttendanceRequest}.php` | Validación de cada operación | RF-6, RF-7, RF-8, RF-11 |
| `app/Http/Requests/Session/{StoreSessionRequest, UpdateSessionRequest, UpdateSessionStatusRequest}` (modificados) | +`timezone`; sin cambio directo de `scheduled_at`/`status` | RF-1, RF-3, RF-11 |
| `app/Http/Requests/Availability/{AvailabilityWindowRequest, AvailabilityExceptionRequest}.php` | CA-2.2, CA-2.1 | RF-2 |
| `app/Http/Resources/{SessionResource(mod), SessionScheduleChangeResource, SessionAttendeeResource(mod), MentorAvailabilityWindowResource, MentorAvailabilityExceptionResource}.php` | Contrato de sesión con `meet_link` oculto cuando está cancelada, historial, asistencia y disponibilidad | RF-7, RF-9, RF-6, RF-8, RF-2 |
| `app/Http/Controllers/Api/SessionController` (modificado) | `index` con rango/collectives; `reschedule/cancel/complete/attendance`; auth ampliada | RF-1, RF-4, RF-6..RF-11 |
| `app/Http/Controllers/Api/AvailabilityController` (nuevo) | CRUD propio + `slots()` (huecos libres sin exponer excepciones) | RF-2, RF-10 |
| `app/Policies/SessionPolicy` (modificado) | `view`: participante **o responsable de colectivo** (`userCanManageSource`) **o** usuario con permiso explícito `scheduling.view.support`; `manage`: mentor **o mentee** o responsable o usuario con `scheduling.manage.support`; `complete`: **solo mentor**; primero resuelve visibilidad y después operación: recurso ajeno → 404, usuario que ve pero no puede ejecutar → 403 | RF-10 |
| `database/seeders/RolePermissionSeeder.php` (modificado) | Declara `scheduling.view.support` y `scheduling.manage.support`, los asigna explícitamente a `super_admin` y `admin`, y exige comprobar el permiso además del middleware de rol; el rol por sí solo no sustituye la comprobación | RF-10 |
| `backend/routes/api.php` (modificado) | Rutas §5.1 y §5.2 | RF-1..RF-11 |
| `backend/routes/console.php` (modificado) | `app:advance-session-lifecycle` (cada minuto), `app:send-session-reminders` (cada minuto, ya estaba), `app:generate-meet-links` (cada 5 min) | RF-5, RF-9, RF-11 |
| `app/Http/Controllers/Api/AdminController` (modificado) | `/admin/sessions/*` delega en las mismas Actions (sin escritura libre de `status`) | RF-10, RF-11 |

### 7.7 Modificados — documentación
`AGENTS.md` (entidades, rutas, config, comandos) · `MEMORY.md` (estado y decisiones DT-1..DT-16) · `specs/002-*/spec.md` (Estado → `implementada` al finalizar).

---

## 8. Funciones puras de lógica necesarias (RNF-11, P19)

Definición de «pura»: **sin base de datos, red, reloj global ni aleatoriedad**; reciben colecciones ya cargadas y `now`/`timezone` como parámetros. Todas en `app/Services/Scheduling/Rules/`.

| # | Función (método estático puro) | Entradas | Salida | Regla / CA | RF |
|---|---|---|---|---|---|
| F1 | `OverlapDetector::overlaps(a, b)` | dos intervalos `[inicio, fin)` | bool | Solape parcial = sí; tocarse = no (caso 1) | RF-1 |
| F2 | `LifecycleRules::sessionEnd(scheduledAt, durationMinutes)` | fecha + duración | instante | Fuente única de `end_at` (CA-8.8, CA-11.6) | RF-11 |
| F3 | `OverlapDetector::conflicts(candidate, existing)` | candidato `{id?, start, end, participants[]}` + sesiones existentes no canceladas con sus participantes | `[{session_id, user_id}]` | CA-1.2, CA-1.3, CA-1.6 (excluye `canceled`/`not_completed`), CA-13.5 | RF-1, RF-13 |
| F4 | `DuplicateDetector::normalizeTitle()` + `isDuplicate(candidate, existing)` | título/relación/inicio/duración | bool | Título normalizado (minúsculas + colapso de espacios) — CA-1.4 | RF-1 |
| F5 | `AvailabilityRules::check(windows, exceptions, interval, now)` | ventanas y excepciones ya cargadas | `{allowed, reason}` | Sin ventanas → solo se exime el filtro de ventanas y **las excepciones bloqueantes siguen aplicando** (CA-2.4 menor 1); excepción gana (CA-2.7); sesión **entera** dentro de una ventana (CA-2.3) | RF-2 |
| F6 | `AvailabilityRules::freeSlots(windows, exceptions, busy, range, duration, tz)` | colecciones | `[{start,end}]` | Rejilla de 30 min en la zona del consultante; no revela motivos (CA-2.6) | RF-2 |
| F7 | `ReminderRules::dueHorizons(scheduledAt, now)` · `isImminent(scheduledAt, now)` | fechas | `[24,2,1]` / bool | Cadencia (CA-5.1); inmediato si <24 h (CA-5.8) | RF-5 |
| F8 | `ReminderRules::reminderKey(session, userId, horizon)` | sesión + usuario | string | **Incluye el `scheduled_at` vigente** → reprogramar reactiva horizontes (CA-5.2/5.3) | RF-5 |
| F9 | `ReminderRules::changeKey(session, eventKey, userId)` | id de evento de cambio | string | Un aviso por evento de negocio (CA-5.7) | RF-5 |
| F10 | `AttendanceRules::windowEnd(endAt)` · `canConfirm(session, attendee, actor, target, now)` | datos ya cargados | instante / `{ok, reason}` | Abre en la hora de fin, cierra a +48 h; rechaza `canceled`/`not_completed`, antes de fin o fuera de plazo (CA-8.2, CA-8.8) | RF-8 |
| F11 | `AttendanceRules::applyPrecedence(current, incoming, actorRelation)` | estados | nuevo estado | Miembro prevalece sobre responsable **en cualquier orden**; mentor nunca por otros; `attended` bool = `attended` status (CA-8.6, CA-8.1, CA-8.9) | RF-8 |
| F12 | `LifecycleRules::next(status, startAt, endAt, now)` · `isTerminal(status)` | estado + fechas | `?status` | Grafo CA-11.1 + robustez temporal + terminales; «no completada» a 48 h (CA-11.6) | RF-11 |
| F13 | `ChangeRules::canReschedule(session, now, minNotice)` | sesión + `config` | `{ok, reason}` | Solo `scheduled` futura y ≥2 h (CA-6.7, CA-6.8) | RF-6 |
| F14 | `ChangeRules::cancellationLead(startAt, now)` · `isLate(startAt, now)` | fechas | minutos / bool | Tardía = <2 h o ya iniciada (CA-7.2) | RF-7 |
| F15 | `ParticipantRules::isActiveAt(attendee, anchor)` · `participantsAt(session, attendees, anchor)` | filas + ancla | bool / ids | **Ancla `end_at`** (menor 4): `created_at ≤ anchor` y (`left_at` null o `> anchor`) | RF-13 |
| F16 | `ParticipantRules::chargeableMembers(members, ageIndex)` | miembros del colectivo + edades conocidas | ids | «A cargo» por tipo: familia → menores (<18 con edad conocida); empresa → empleados; cohorte → solo menores si hay edad (menor 2 de la revisión) | RF-8, RF-13 |

---

## 9. Algoritmo en pseudocódigo

### 9.1 Ciclo de vida de estados (RF-11, RF-8)

```
TRANSICIONES (CA-11.1):
  scheduled    → in_progress   # automática, en startAt
  in_progress  → completed     # manual, SOLO mentor, requiere now >= endAt
  scheduled|in_progress → canceled   # manual, autorizados (CA-10.3), con motivo
  scheduled|in_progress → not_completed  # automática, en endAt + 48 h sin completar
  completed | canceled | not_completed = TERMINALES (no se reabren ni reprograman)

FUNCTION next(status, startAt, endAt, now):
  IF status IN TERMINALS: RETURN null
  IF now >= endAt + 48h:   RETURN not_completed        # inclusivo en el límite (CA-8.8/11.6)
  IF status == scheduled AND now >= startAt: RETURN in_progress
  RETURN null

COMMAND app:advance-session-lifecycle (cada minuto, IDEMPOTENTE):      # routes/console.php
  now = clock()
  FOR id IN sessions WHERE (status='scheduled' AND scheduled_at <= now)
                     OR (status IN ['scheduled','in_progress'] AND end_at + 48h <= now):
      DB.transaction:
         s = lockForUpdate(session)
         target = next(s.status, s.scheduled_at, s.end_at, now)
         IF target != null AND s.status != target:
              IF target == not_completed:
                   ensureAttendanceRecords(s, activeParticipantsAt(s, anchor=end_at))   # inicialización auditada idempotente para el cierre terminal
              previous = s.status
              s.status = target
              activitylog.append(event='scheduling.status_transition', subject=s,
                                 actor=null, properties={previous, target, occurred_at=now}) # P5
              broadcast cambio

COMPLETE (manual, CA-11.1 / menor 4):
FUNCTION CompleteSessionAction(session, actor, now):
  DB.transaction:
  session = lockForUpdate(session); re-read status y hora
  authorize actor == relationship.mentor        # exclusiva del mentor (P6)
  IF session.status NOT IN ['scheduled','in_progress']: REJECT(estado terminal)
  IF now >= session.end_at + 48h: REJECT(estado terminal; límite inclusivo, CA-8.8/11.6)
  IF now < session.end_at: REJECT("aún no ha terminado")
  originalStatus = session.status
  IF session.status == 'scheduled': session.status = in_progress   # robustez temporal
  session.status = completed; completed_at/by = now/actor
  # Activitylog append-only: si venía scheduled registra también la promoción
  # intermedia scheduled→in_progress antes de in_progress→completed (CA-11.1/P5).
  appendStatusTransitions(originalStatus, intermediateIfNeeded, completed, actor, now)
  ensureAttendanceRecords(session, activeParticipantsAt(end_at))   # CA-8.3: evento de inicialización por participante, occurred_at=end_at
  # Inserta una sola inicialización not_confirmed por participante anclado a end_at.
  # No sobrescribe attendance_status/attended/confirmed_by/confirmed_at si ya hubo confirmación.
  # Una confirmación anterior al cierre se conserva como evento posterior efectivo;
  # la inicialización es idempotente y no se vuelve a emitir al reintentar complete.
```

### 9.2 Validación de reserva — crear y reprogramar (RF-1, RF-2, RF-6, RF-13)

```
FUNCTION SessionBookingService.validate(candidate, relationship, actor, mode):   # mode: create|reschedule; caller owns transaction
  1. resolver relationship/source; lockForUpdate(relationship, source) en orden estable
     # cambios de membresía sincronizan bajo el mismo lock del source
     # orden global de locks: relationship → source → session existente (si reschedule) → users por UUID
  2. autorización: actor es mentor, mentee, responsable del colectivo o tiene `scheduling.manage.support` # CA-10.2, D2/D9
  3. relación activa + tipo coherente + fecha futura + 15..480 min + zona          # CA-1.1
  4. IF mode==reschedule:
        ChangeRules.canReschedule(session, now, 2h)         # CA-6.7/6.8 → REJECT si no
        excluir la propia sesión de los conflictos
  5. participantes = mentor + (individual: mentee | colectivo: ParticipantRules.activeMembersNow + mentor)
     # RF-13 CA-13.1; determinar pertenencias bajo lock de source
  6. lockForUpdate(User rows de todos los participantes, ordenados por UUID)
     # cualquier creación que comparta participante se serializa aunque sea de otra relación
  7. releer sesiones/conflictos y disponibilidad dentro de la transacción, tras los locks
  8. duplicidad: dup = DuplicateDetector(candidate, existing of relationship)     # CA-1.4
     IF dup AND mode==create: RETURN {existing: dup}                              # 200 idempotente
     IF dup AND mode==reschedule: REJECT(duplicidad)
  9. conflictos = OverlapDetector.conflicts(candidate, sessions)
         - consulta: sesiones NO canceladas y NO not_completed (CA-1.6)
         - compara contra TODOS los participantes (CA-1.2/1.3)
         - colectivo: cualquier miembro ocupado → RECHAZA EL HUECO PARA TODO EL COLECTIVO (CA-13.5)
  10. disponibilidad (solo del mentor):
        windows  = load(mentor.windows)
        exceptions = load(mentor.exceptions)
        r = AvailabilityRules.check(...)        # CA-2.3, CA-2.7, CA-2.4 (menor 1: sin ventanas
                                               # solo se exime el filtro de ventanas; las
                                               # excepciones bloqueantes SIGUEN rechazando)
        IF NOT r.allowed: REJECT(r.reason)
  11. cuota mensual del plan para la NUEVA fecha, excluyendo canceled y not_completed (CA-1.6, CA-6.2)
  RETURN validación y participantes; no libera los locks ni confirma la transacción

CreateSessionAction:
  DB.transaction:
    validar(...) ; INSERT session y attendee rows bajo los locks
    activitylog.append(event='scheduling.session_created', actor=actor, subject=session,
                       properties={session_id, source='user|support', occurred_at=now}) # P5/CA-10.6
    # índice único de respaldo (§6.5); en conflicto exacto, recuperar existente para create
  AFTER COMMIT (best-effort): MeetAutomation.ensure(session) + recordatorio inmediato si <24 h

RescheduleSessionAction (también dentro de una única DB.transaction):
  resolver relationship; lockForUpdate(relationship) → source → session → usuarios participantes por UUID
  validar(candidate, mode=reschedule) bajo los mismos locks
  insertar session_schedule_changes ; actualizar inicio/duración/zona/end_at
  COMMIT
  AFTER COMMIT (best-effort): actualizar Meet y emitir un aviso de cambio;
  cualquier rechazo deja intacta la sesión original
```

### 9.3 Participación colectiva dinámica (RF-13, casos 41/35/39, menores 3–4)

```
ON FamilyMember|CohortMember|Employee created (alta)  → sync(source, user):
  lockForUpdate(source)   # misma serialización que SessionBookingService
  lockForUpdate(user)     # serializa la comprobación del caso 41 frente a reservas propias
  FOR s IN sessions de relaciones sobre `source`
          WHERE status IN ['scheduled','in_progress'] AND end_at > now:   # antes de que se celebre
      busy = alguna sesión no cancelada de `user` que solape s   # CASO 41
      IF busy:
          SKIP    # alta válida; NO se revalida la sesión colectiva; el miembro queda
                  # COMO NO PARTICIPANTE de ESA sesión (no recibe recordatorios ni
                  # confirma asistencia) — coherente con RF-13 y CA-13.2/13.5
      ELSE: upsert(session_attendees: session_id, user_id)       # CA-13.2 → recibirá los
                                                                 # recordatorios pendientes
ON baja (deleted|status!=active) → sync(source, user):
  lockForUpdate(source)
  lockForUpdate(user)
  FOR attendee IN filas del usuario para sesiones del colectivo con left_at IS NULL:
      attendee.left_at = now     # CA-13.3: corta recordatorios/confirmaciones desde ahora
      # nunca borrar filas ni cambiar estados/eventos de asistencia ya registrados
      # si left_at > end_at, sigue dentro del conjunto anclado a end_at (RF-13/CA-13.4)

ANCLA «participantes registrados» (menor 4):
  activeParticipantsAt(s, anchor = s.end_at) =
      attendees WHERE created_at <= anchor AND (left_at IS NULL OR left_at > anchor)
      → es el conjunto al que CompleteSessionAction crea los registros iniciales
        y al que se cierra la ventana a las 48 h. Una baja posterior (left_at > anchor
        sería el caso contrario) no borra registros ya creados.
```

### 9.4 Recordatorios y avisos de cambio (RF-5)

```
COMMAND app:send-session-reminders (cada minuto):                        # MODIFICADO
  now = clock()
  FOR s IN sessions WHERE status='scheduled' AND scheduled_at > now:
     tz = s.timezone                                        # CA-5.1: antelación en zona de sesión
     targets = mentor + attendees activos HOY (ParticipantRules)   # RF-13 «en el momento de enviar»
                # el responsable NO miembro NO recibe recordatorios (menor 2 → CA-5.1)
     FOR h IN ReminderRules.dueHorizons(s.scheduled_at, now):        # 24h, 2h, 1h
         FOR u IN targets:
             key = ReminderRules::reminderKey(s, u, h)               # incluye scheduled_at VIGENTE
             IF ReminderRules.isImminent(s, now) AND h == 24:        # CA-5.8: aviso inmediato único
                 key = reminderKey(s, u, 'immediate')                # (creación <24 h de antelación)
             row = Notification.where(dedupe_key=key).first()
              IF row?->status == 'sent': CONTINUE                     # proveedor confirmó; consume (CA-5.2)
              IF row == null: row = INSERT(pending, dedupe_key=key)   # UNIQUE evita dobles
               TRY: publicar el evento privado existente SessionReminder(notification_id=row.id)
                   # ConfirmSessionReminderDelivery marca sent/sent_at solo tras confirmación del proveedor.
                    # El consumidor 010 hace upsert/reemplazo por notification_id, no append.
              CATCH e: row.status='failed'; log('scheduling.reminder_failed', ids)      # CA-5.6
                       # → reintentar con la misma fila e ID; no se consume el dedupe
              # Si el proveedor aceptó y la cola redelivera antes de persistir sent,
               # puede repetirse la publicación de transporte; 010 mantiene un único aviso lógico visible.

AVISO DE CAMBIO (CA-5.7) — ejecutado por consumidor 008 del evento post-commit de Reschedule/CancelAction:
  eventKey = schedule_change_id (reprogramar) | event_id estable de SessionCanceled (cancelar)
  FOR u IN participantes vigentes + responsable del colectivo (AUNQUE NO SEA MIEMBRO):
       key = ReminderRules::changeKey(s, eventKey, u)      # un aviso lógico por evento
       row = recuperar/crear Notification por dedupe_key   # conserva row.id en todo intento
        publicar con notification_id=row.id; consumidor 010 upsert por ese ID
  # reprogramación: los recordatorios de la fecha vieja clavan por la key con el
  # scheduled_at antiguo (nunca se envían: el comando solo mira la fecha vigente) CA-5.3
  # cancelación: s.status='canceled' → sale del filtro del comando (CA-5.4)
```

### 9.5 Google Meet con reintento (RF-9)

```
FUNCTION MeetAutomation.ensure(session, mode = create|update):     # IDEMPOTENTE
  IF session.meet_link AND mode==create: RETURN existing          # CA-9.3 (un solo evento)
  lock(session)  →  recheck                                            # concurrencia
  TRY:
     IF session.meet_event_id AND session.meet_stale:
         GoogleMeetService.updateEvent(id, title, start, duration, session.timezone)   # CA-9.4
     ELSE IF session.meet_event_id:
         RETURN existing
     ELSE:
         r = createMeetEvent(title, start, duration, session.timezone)   # CA-9.7: NUNCA zona fija,
                                                                         # SIN invitados (CA-9.7/§8)
     save(meet_link, meet_event_id, meet_attempts=0, meet_stale=false, meet_last_error=null)
  CATCH e:
     meet_attempts++; meet_last_attempt_at=now; meet_last_error=sanitize(e)   # sin secretos (RNF-10)
     log('scheduling.meet_failed', {session_id, attempts})
     # NUNCA se revierte la creación/reprogramación (P1/P17, CA-9.2)

COMMAND app:generate-meet-links (cada 5 min):                       # routes/console.php (cadencia fija)
  FOR s IN sessions WHERE status='scheduled' AND scheduled_at > now
                    AND (meet_link IS NULL OR meet_stale):
      MeetAutomation.ensure(s, s.meet_event_id ? update : create)
  # backfill: las `scheduled` futuras sin enlace existentes al activarse entran aquí (CA-9.1)
  # pasada la hora de inicio → el comando no actúa; queda la generación MANUAL (CA-9.2)

Las transiciones de estado y la creación inicial `scheduled` se registran en activitylog
con `session_id`, estado anterior/nuevo, actor o sistema y hora; la actualización ordinaria
de negocio no puede editar esos eventos (P5). El historial de reprogramación y de asistencia
se conserva en sus tablas append-only específicas.

CANCEL (RF-7):
  1. persistir canceled (motivo, autor, late, lead_minutes) — PRIMERO (P1)
  2. conservar `meet_link` y `meet_event_id` en MIS como datos históricos; no limpiar la columna
  3. publicar evento mínimo post-commit (referencia de sesión, autor, momento, motivo y tardanza si aplica), sin `meet_link`
  4. 008 consume el evento y solicita el borrado Meet externo *best-effort*; fallo registrado, sin revertir el estado ni revelar el enlace (CA-7.4)
  5. Resource y demás respuestas API omiten/devuelven nulo para `meet_link` mientras `status=canceled` (CA-7.4/CA-9.5)
```

### 9.6 Registro de asistencia (RF-8)

```
FUNCTION ConfirmAttendanceAction(session, actor, targetUser, status, now):
  DB.transaction:
  session = lockForUpdate(session); revalidar estado terminal/cancelación
  attendee = row(session, targetUser)                      # debe existir y pertenecer al conjunto anclado a end_at
  attendee = lockForUpdate(attendee)                       # serializa confirmaciones concurrentes
  IF attendee.left_at IS NOT NULL AND attendee.left_at <= now: REJECT("ya no pertenece") # CA-13.3
  r = AttendanceRules.canConfirm(session, attendee, actor, targetUser, now)
  IF NOT r.ok: REJECT(r.reason)        # antes de end_at | canceled | not_completed | now >= end_at+48h (CA-8.2/8.8)

  # ¿QUIÉN puede confirmar por QUIÉN (menor 2 → CA-8.1)?
  IF actor == targetUser:   OK (propia)
  ELIF actor es responsable del colectivo:
        chargeable = ParticipantRules::chargeableMembers(... )     # familia: menores (<18);
                                                                   # empresa: empleados;
                                                                   # cohorte: solo menores
        SI targetUser NOT IN chargeable: 403
  # La confirmación propia del miembro es siempre la efectiva, sin importar el orden.
  # Si el miembro confirmó primero: insertar igualmente el evento del responsable,
  # conservarlo como antecedente sin efecto y no alterar sus columnas efectivas.
  # Si el responsable confirmó primero: aplicar su estado provisional; la posterior
  # confirmación propia lo sustituye en columnas efectivas, pero conserva ambos eventos.
  ELIF actor es mentor: 403    # NUNCA por otros (CA-8.1)
  ELIF actor es admin con permiso de soporte: 403 (no confirma asistencia; solo consulta)
  ELSE: 404/403

  INSERT session_attendance_events(event_type=confirmation, status, recorded_by=actor, occurred_at=now) # append-only, en ambos órdenes
  IF actor == targetUser OR attendee.confirmed_by IS NULL:
      attendee.attendance_status = status ; confirmed_by/at = actor/now
      attendee.attended = (status == 'attended')      # hook mantiene el bool de la spec 001 (CA-8.9)
  # CA-8.6: en cualquier orden ambos eventos se conservan; la confirmación propia
  # prevalece sobre la del responsable y no cambia ante un intento posterior suyo.

CIERRE DEL PLAZO (CA-8.8): derivado — now >= end_at + 48h ⇒ sin confirmación posible (inclusivo);
  el estado efectivo sigue siendo «no confirmada» (nunca se infiere, P6).
```

### 9.7 Reprogramación y cancelación (RF-6, RF-7)

```
FUNCTION RescheduleSessionAction(session, data, actor, now):
  authorize manage (mentor|mentee|responsable|scheduling.manage.support) # CA-10.3
  r = SessionBookingService.validate(candidate, ..., mode=reschedule)   # §9.2 COMPLETO
  IF NOT r.ok: REJECT  → la sesión queda INTACTA con su fecha original (CA-6.2)
  INSERT session_schedule_changes(prev, new, tz_prev, tz_new, duration, reason, actor)  # CA-6.1
  session.update(scheduled_at/duration/timezone)   # el hook recalcula end_at
  MeetAutomation.ensure(mode=update)               # o meet_stale=true si falla (CA-9.4)
   publicar SessionRescheduled(event_id estable, datos mínimos de fecha anterior/nueva)
   # 008 consume el evento y emite changeNotice (CA-6.5 → CA-5.7)
  # recordatorios: se recalculan solos porque la key lleva scheduled_at vigente (CA-6.4)

FUNCTION CancelSessionAction(session, actor, reason, now):
  DB.transaction:
  session = lockForUpdate(session); re-read estado y política
  authorize manage (mentor|mentee|responsable|scheduling.manage.support) ; IF terminal: REJECT (CA-7.5)
  late = ChangeRules.isLate(start, now) ; lead = ChangeRules.cancellationLead(...)
  previous = session.status
  persist canceled(+late, +lead, +reason, +actor, +at)     # ANTES que cualquier llamada externa
  activitylog.append(event='scheduling.status_transition', subject=session,
                     actor=actor, properties={previous, canceled, occurred_at=now})
  COMMIT
  AFTER COMMIT (best-effort):
   conservar meet_link/meet_event_id persistidos como dato histórico; no limpiarlos
   publicar SessionCanceled(event_id estable, referencia de sesión, autor, momento,
                            motivo y tardanza/lead si aplican; SIN meet_link)
   # 008 solicita deleteEvent best-effort y registra fallo sin revertir ni exponer link
   # SessionResource y respuestas API omiten/devuelven null para el link cancelado
   # 008 consume SessionCanceled y emite changeNotice (CA-7.1 → CA-5.7)
  # los recordatorios dejan de salir por el filtro de estado (CA-5.4)
```

### 9.8 Autorización (RF-10, P3)

```
FUNCTION authorizeSession(session, actor, operation):
  baseVisible = actor es participante (involvesUser)
                OR userCanManageSource(actor, relationship.source) # responsable de colectivo (D9)
  supportVisible = actor.can('scheduling.view.support')
                   OR (operation mutante AND actor.can('scheduling.manage.support'))
  IF NOT (baseVisible OR supportVisible): 404  # ajeno = inexistente (CA-10.5)
  SWITCH operation:
    'view'      → OK
    'manage'    → mentor | mentee | responsable | actor.can('scheduling.manage.support')  # CA-10.2/10.3
    'complete'  → SOLO relationship.mentor                          # CA-11.1 (P6)
    'confirm_attendance' → §9.6
  IF usuario ya visible pero NO permitido para esa operación: 403 con motivo claro (CA-10.5)

Disponibilidad: cualquier tercero → 404/403 (CA-10.4); huecos libres legítimos sin detalle (CA-2.6).
Auditoría: operaciones realizadas bajo `scheduling.manage.support` + confirmaciones
del responsable sobre otras personas (CA-10.6) → activitylog. `scheduling.view.support`
no autoriza mutaciones; `scheduling.manage.support` no autoriza leer listados/detalles
fuera de una operación que gestione, salvo que también tenga `scheduling.view.support`.
```

---

## 10. i18n (RF-12)

- **Backend:** `lang/{es,en,pt}/scheduling.php` (motivos de rechazo de solape/disponibilidad/antelación, estados `scheduled|in_progress|completed|canceled|not_completed`, `attended|absent|not_confirmed`, avisos de recordatorio/cambio) + claves nuevas en `sessions.php`/`meet.php`. El middleware `SetRequestLocale` ya selecciona idioma por cabecera.
- **Frontend:** namespace `scheduling` en `frontend/src/i18n/messages/{es,en,pt}/common.json` (patrón del namespace `progress` de la spec 001). Ningún texto visible con claves crudas (CA-12.2); fallback a `es`.
- El título/descripción de la sesión es texto del autor y **no** se traduce (mismo criterio que la spec 001).

---

## 11. Cómo se pinta en la interfaz (RF-4, RF-12, RNF-6, RNF-7)

### 11.1 Páginas (`frontend/src/app/[locale]/sessions/`)
| Archivo | Responsabilidad | RF |
|---|---|---|
| `sessions/page.tsx` (página existente) | Evoluciona la lista paginada a calendario día/semana/mes alimentado por `GET /sessions?from&to`, conservando el acceso `/sessions` y `AppShell` | RF-4 |
| `sessions/loading.tsx` · `sessions/error.tsx` (si se necesitan para el segmento) | Estado de carga y error recuperable conforme al patrón existente | RF-4 |
| `sessions/[id]/page.tsx` | Detalle: estado, hora **con zona**, Meet (o «pendiente de enlace»), historial, acciones según `permissions`, asistencia | RF-3, RF-6..RF-9 |
| `sessions/disponibilidad/page.tsx` | Solo mentor: ventanas y excepciones en su zona (CA-2.1, CA-10.4) | RF-2 |

### 11.2 Componentes (`frontend/src/components/scheduling/`)
`CalendarMonthGrid` · `CalendarWeekView` · `CalendarDayView` (navegación con teclado, chips con **texto + color**, vacío guiado) · `SessionForm` (crear; selector de zona horaria por defecto la propia) · `RescheduleForm` · `CancelSessionDialog` (motivo obligatorio) · `CompleteSessionButton` (solo mentor) · `AttendancePanel` (mi estado + acciones del responsable) · `MeetLinkSection` (enlace o «pendiente de enlace» CA-9.6) · `SessionStatusBadge` · `AvailabilityWindowsEditor` · `AvailabilityExceptionsEditor` (el motivo se muestra solo a su dueño) · `FreeSlotsPicker` · `SessionChangeHistory`.

### 11.3 Cómo se ve
- **Calendario**: periodos día/semana/mes; cada chip muestra hora convertida a la zona del espectador + etiqueta de zona si difiere (CA-3.2); estados distinguibles **por texto + icono + color** (CA-4.2); vacío guiado con CTA «Programar sesión» (CA-4.4).
- **Detalle**: bloque de Meet con estado explícito; asistencia propia en primer plano; historial de cambios con autor/fecha/valores anterior-nuevo (CA-6.1); si soy responsable del colectivo, la agenda de mi colectivo aparece identificada como tal (CA-4.7).
- **Acciones**: los botones se ocultan según `permissions` **solo como mejora progresiva**; el servidor es quien decide (CA-12.3/RNF-1).
- **Errores**: `422` mapeados al campo con `role="alert"` + región anunciante (CA-12.4).

### 11.4 Capa de datos y tipos (frontend)
| Archivo | Responsabilidad | RF |
|---|---|---|
| `src/types/scheduling.ts` | Tipos del contrato (`SessionDetail`, `Attendance`, `AvailabilityWindow`, `FreeSlot`, …) | todos |
| `src/lib/scheduling.ts` (+ `scheduling.test.ts`) | **Funciones puras**: mapa estado→UI (badge/color/texto), formateo de hora **con conversión de zona** (`Intl`), etiquetas de horizonte, `isLateCancellation` → texto, mensaje de conflicto por código de error, `slotGrid` | RF-3, RF-4, RF-7, RF-12 |
| `src/lib/schemas/session.ts` | Zod: crear/reprogramar (fecha futura, 15–480, zona), cancelar (motivo), asistencia | RF-1, RF-6, RF-7, RF-8 |
| `src/hooks/use-scheduling.ts` | `sessionsRange(from,to)`, `session(id)`, mutaciones `reschedule/cancel/complete/confirmAttendance` con invalidación de `queryKeys.sessionsRoot` | RF-1, RF-4, RF-6..RF-8 |
| `src/hooks/use-availability.ts` | CRUD ventanas/excepciones + `freeSlots` | RF-2 |
| `src/hooks/use-session-reminders.ts` | Consumir `session.reminder` en el canal privado existente `user.{id}` y mantener la presentación transitoria keyed por `notification_id`; no persiste una bandeja ni redefine despacho/reintentos | RF-5, RF-12 (validación final con 010) |
| `src/lib/session-reminders.ts` (+ tests) | Función pura `upsertReminderNotice(current, incoming)`: mismo ID reemplaza/actualiza el elemento visible, IDs distintos permanecen distintos | RF-5, RF-12 (validación final con 010) |
| `src/lib/query-keys.ts` (modificar) | `sessionsRange`, `session(id)`, `availability`, `freeSlots(mentor,range)` | RF-2, RF-4 |
| `src/components/layout/AppShell.tsx` (revisar) | Conservar el enlace existente `/sessions`; ajustar etiqueta de «Sesiones» a «Agenda» si la navegación lo requiere, usando `localePath()` | RF-4 |
| `src/i18n/messages/{es,en,pt}/common.json` (modificar) | Namespace `scheduling` | RF-12 |

> **Sin tests de componentes** (no hay Testing Library, P2): la lógica vive en `lib/scheduling.ts` y se testea con Vitest, igual que `lib/progress.ts`.

---

## 12. Decisiones técnicas justificadas y alternativa descartada

| # | Decisión | Por qué | Alternativa descartada | Principio |
|---|---|---|---|---|
| DT-1 | **«No completada» como 5.º estado almacenado** | P8 pide estados explícitos; un solo punto de exclusión en consultas (recordatorios, solapes, cuota) y en resources | Estado derivado (`flag` calculado) → tocaría cada query/resource/UI con lógica dispersa y riesgo de olvido | P8, RF-11 |
| DT-2 | **`end_at` persistido + hook `saving`** | Ventana de 48 h e índice para rangos; cálculo único `sessionEnd()` | Expresión SQL `scheduled_at + interval` → sin índice, lógica duplicada en cada consulta | P8, RNF-12 |
| DT-3 | **`timezone NOT NULL DEFAULT` sistema** | CA-3.1 exige zona en toda sesión; heredadas cumplen sin backfill arriesgado | Columna nullable + fallback en código → cada lectura debe acordarse del fallback | P8, caso 32 |
| DT-4 | **Dos tablas relacionales de disponibilidad** | Consultas por día de semana y rangos con índice; validación CHECK (CA-2.2) | JSONB por usuario → difícil de validar/indexar y de exponer por huecos libres | P18, RF-2 |
| DT-5 | **Excepciones `block`/`open` con precedencia block > open > ventana** | D11/CA-2.7 exigen que la excepción gana; `open` cubre el caso «la excepción habilita» de CA-2.3 | Excepciones solo bloqueadoras → no se podría modelar una ventana especial (festivo ampliado) | RF-2 |
| DT-6 | **Asistencia: columnas efectivas + `session_attendance_events` apéndice** | CA-8.6 exige conservar AMBAS confirmaciones con autor y momento; lecturas baratas | Sobrescribir «última que llegue» → prohibido por CA-8.6; solo activitylog → no consultable como dominio | P5, RF-8 |
| DT-7 | **`attended` como reflejo sincronizado de `attendance_status`** | La spec 001 lee el bool (`SessionMetricsCalculator`); se mantiene su significado (CA-8.9) | Eliminar el bool o recalcularlo en consultas → rompe indicadores publicados | P1, RNF-13 |
| DT-8 | **Reservas transaccionales: locks de relación, source y participantes ordenados + índice único parcial** | Locks de participantes serializan solapes concurrentes incluso entre relaciones distintas; relationship/source protegen cuota y cambios de membresía; el índice parcial cubre creación exacta como último respaldo | Solo lock de relación (no protege al mismo usuario en relaciones distintas) o solo índice (no representa solapes por intervalo/participante) | P8, CA-11.3/11.4 |
| DT-9 | **Duplicidad en aplicación, no en índice de BD** | El título se compara *normalizado* (mayúsculas/espacios) — no expresable como UNIQUE | UNIQUE sobre título normalizado → imposible sin columna derivada y con falsos positivos | CA-1.4 |
| DT-10 | **Reprogramación como endpoint propio + tabla de historial** | Revalida **todo** (CA-6.2) y debe mostrar previo/nuevo (CA-6.1); consultarla es barato | Reusar `PUT /sessions/{id}` → mezclaría ediciones menores con revalidación total (además ese PUT ya existía sin validación) | P5, P17 |
| DT-11 | **Ciclo de vida en comando artisans cada minuto** | Determinista, testeable con `Carbon::setTestNow`, idempotente y visible en `routes/console.php` | Observadores (solo se disparan al tocar la sesión) o cálculo lazy al leer (el estado visible sería mentira: CA-11.1 pide transición *a la hora de inicio*) | P14, RF-11 |
| DT-12 | **Recordatorios: clave por `scheduled_at` vigente, fila/ID estable y upsert visible asignado a 010** | La clave nueva reactiva horizontes al reprogramar (CA-5.3); el mismo `notifications.id`/`notification_id` se conserva en reintentos; solo confirmación del proveedor marca `sent`; el consumidor de 010 actualiza/reemplaza el aviso visible por ese ID aunque se repita transporte ambiguo (CA-5.2/5.6, caso 43). La aceptación de RF-008.2 espera esa prueba integrada. | Clave sin fecha (huecos al reprogramar), ID nuevo por intento (duplica avisos lógicos), o asumir exactly-once del transporte (no garantizable) | P14, RF-5 |
| DT-13 | **Meet: comando de barrido cada 5 min con contenedor de intentos** | «Cadencia fija y periódica» (CA-9.2) explícita en config; backfill de futuras sin enlace (CA-9.1) | Cola de jobs con backoff → menos visible, más moving parts; reintento desde el usuario → prohibido por CA-9.2 | P14, P2 |
| DT-14 | **Reverb solo para recordatorios/avisos; calendario sin WebSockets** | La spec no exige tiempo real en la agenda; `SessionReminder` publica por el canal privado existente `user.{id}` y el consumidor de 010 reutiliza ese contrato; el calendario se refresca con react-query (foco + invalidación) | Nuevo canal/protocolo para la presentación de avisos o tiempo real del calendario → coste y complejidad sin requisito (P2) | P2, RF-5 |
| DT-15 | **`complete` solo mentor (admin NO)** | «Exclusiva del mentor» (CA-11.1) y P6: cerrar *atestigua* que ocurrió; el admin no estuvo | Admin también cierra → permitiría atestiguar sin ser parte, contra P6 (el admin conserva consulta y soporte, CA-10.1) | P6, RF-11 |
| DT-16 | **Participantes = filas `session_attendees` nunca borradas + `left_at`** | Ancla en `end_at` (menor 4), altas/bajas prospectivas (RF-13) y «no reescribir celebradas» (CA-13.4) sin historia de membresías | Snapshot JSON en la sesión (habría que recomputar igual) o borrar filas en la baja (perdería registros, P5) | P5, RF-13 |
| DT-17 | **Calendario sobre `GET /sessions` con rango** | Una única verdad (CA-4.6/P1) y reutiliza la paginación/autorización existentes | Endpoint `/calendar` paralelo → duplicaría consultas y autorización | P1 |
| DT-18 | **Calendario propio con `components/ui/*`** | Sin dependencias nuevas; control total de i18n y accesibilidad (teclado, texto+color) | FullCalendar/u/big calendar → dependencia nueva, peso de bundle, i18n y a11y ajenos (P2, P16) | P2, RF-12 |
| DT-19 | **404 para recursos ajenos, 403 solo a quien los ve** | CA-10.5: no revelar existencia; patrón ya usado por `MentorWorkspaceAccessService` | 403 con detalle en todos → filtra existencia de sesiones ajenas | P3, RF-10 |
| DT-20 | **Cuota mensual extraída de `CreateSessionAction` a `SessionBookingService`** | Reutilizar en revalidación de reprogramación (CA-6.2) sin duplicar | Copiar los privados a `RescheduleSessionAction` → dos verdades de la misma regla | P1 |

---

## 13. Autorización, privacidad y observabilidad (RF-10, RNF-1/2/9/10)

- **RNF-1:** toda ruta bajo `auth:sanctum` + `active` y policy/autorización de recurso; la UI nunca decide (los `permissions` del resource son mejora progresiva). Ver matriz de permisos en §9.8.
- **RNF-9:** motivos de excepciones solo para su mentor (CA-2.6); los huecos libres no filtran su origen; recordatorios con `session_id`, título, hora y enlace (los mismos datos de siempre); sin invitados en Google (§8 de la spec).
- **RNF-10:** logs estructurados `scheduling.meet_failed`, `scheduling.meet_delete_failed`, `scheduling.reminder_failed`, `scheduling.lifecycle_transition`, `scheduling.access_denied` — solo ids, estado y código de error **sin** secretos, tokens ni `meet_link` (P20, CA-7.4).
- **RNF-2/RNF-3:** historial de cambios y eventos de asistencia append-only; transiciones de estado y soporte auditadas en activitylog; ninguna operación ordinaria los edita; los reintentos no duplican efectos (dedupe + transacciones + índices).
- **Admin:** operaciones de soporte auditadas con activitylog; el borrado físico de sesiones queda restringido a admin y auditado (CA-7.6).

---

## 14. Casos límite de la spec §7 y su cobertura

| # | Caso | Punto del plan |
|---|---|---|
| 1 | Solape en un minuto / contigüas | F1 `[inicio, fin)`, §9.2 |
| 2 | Mismo participante en dos relaciones (ocupación sí, ventanas no) | F3, F5 (solo mentor tiene ventanas) |
| 3 | Miembro colectivo ocupado → rechazo para todo | F3, §9.2 paso 6 |
| 4–5 | Doble clic / peticiones concurrentes | §9.2 paso 9 (lock + índice único), DT-8 |
| 6 | Mentor sin ventanas: solo solapes + **excepciones siguen aplicando** | F5 (CA-2.4 menor 1) |
| 7 | Excepción sobre día ocupado / gana a la ventana | F5, §9.2 paso 7 |
| 8–9, 36 | Reprogramación que choca / terminal / fuera de límite | §9.7, F13 |
| 10–11 | Cancelación tardía / <2 h | F14, §9.7 |
| 12 | El mentee crea con las mismas validaciones | §9.2 paso 1 (autorización) |
| 13–15 | Fallos de Google / reintento / cancelación con fallo | §9.5, DT-13 |
| 16–18 | Recordatorios tras reprogramar/cancelar/sin pendiente | §9.4 (clave con fecha vigente, filtro de estado, cambioNotice) |
| 19–23 | Asistencia: iniciales «no confirmada», 48 h, heredados, contradictoria, mixta | §9.6, F10/F11, DT-6 |
| 24 | Responsable: miembros a su cargo por tipo | F16 (menor 2) |
| 25–27 | Zona por defecto / convertida / medianoche y DST | DT-3, `lib/scheduling.ts`, F2 |
| 28, 31, 37 | Calendario vacío / permisos / IDOR | §11.2, §9.8, tests §15.2 |
| 29–30 | Meet sin credenciales / relación pausada | §9.5 (backfill + manual), §9.2 paso 2 |
| 32 | Datos heredados | DT-3, DT-7 (backfill `end_at`, zona por defecto) |
| 33 | Sesión no cerrada en 48 h | DT-1, §9.1 |
| 34 | Creada con <24 h | F7 (inmediato), §9.4 |
| 35 | Baja tras confirmar | DT-16, §9.3 (`left_at` no borra) |
| 38 | Excepción sobre ventana habitual | F5 |
| 39 | Miembro nuevo poco antes | §9.3 (alta → recordatorios pendientes) |
| 40 | Reprogramación con cambio de zona | §9.7 (historial con ambas zonas) |
| **41** | **Alta con sesión propia que solapa una colectiva** | **§9.3 (SKIP: alta válida, sin revalidación, queda no participante de ESA sesión)** |
| **42** | **Cancelación conserva Meet histórico y no lo expone, aunque falle el borrado remoto; evento mínimo sin enlace** | **§9.5, §9.7, `SessionResource`, `SessionCancelTest`/contrato con 008 (CA-7.4/CA-9.5)** |
| **43** | **Proveedor puede aceptar aviso sin confirmación persistida y la cola lo reentrega** | **§6.4, §9.4, §11.4, §15.2: productor conserva el ID; 010 upserta visible por ID; transporte repetible; nunca exigir exactly-once** |

---

## 15. Estrategia de tests (RNF-11, P19)

### 15.1 Unit backend (Pest, `tests/Unit/Scheduling/`) — sobre las Rules, con `now` inyectado y **sin DB**
- `OverlapDetectorTest` — F1/F3: solape parcial, contiguas no solapan, excluye `canceled`/`not_completed`, colectivo por cualquier miembro.
- `DuplicateDetectorTest` — F4: título con mayúsculas/espacios repetidos = duplicado; distinta duración no lo es.
- `AvailabilityRulesTest` — F5/F6: sesión enteramente en ventana; **sin ventanas → solo excepciones aplican (menor 1)**; `block` gana sobre ventana; `open` cubre; fin<inicio rechazado.
- `ReminderRulesTest` — F7/F8/F9: horizontes 24/2/1 según reloj; inmediato <24 h; la clave **cambia** al reprogramar; clave de aviso de cambio estable por evento. La deduplicación visible se verifica mediante contrato integrado con el consumidor T010, no desde esta prueba unitaria.
- `AttendanceRulesTest` — F10/F11: abre en `end_at`, cierra a +48 h; rechaza `canceled`/`not_completed`/antes de fin; **precedencia miembro > responsable en ambos órdenes**; mentor → denegado.
- `LifecycleRulesTest` — F12: `next()` devuelve los 4 caminos, robustez `scheduled→in_progress→completed`, terminales → null, prioridad de «no completada».
- `ChangeRulesTest` — F13/F14: reprogramación <2 h rechazada, `in_progress` rechazada; cancelación tardía con antelación real.
- `ParticipantRulesTest` — F15/F16: ancla `end_at` (alta posterior no entra, baja anterior sale, baja posterior no borra); «a cargo» por tipo de colectivo (menor 2), edad desconocida → adulto.

### 15.2 Feature backend (Pest, `tests/Feature/Scheduling/`)
- `SessionBookingTest` — **RF-1/RF-2/RF-13:** creación válida; rechazo por solape de mentor/participante/**colectivo completo**; disponibilidad con excepción que gana; mentor sin ventanas pero con excepción bloqueante rechaza (menor 1); duplicidad devuelve **200 con la existente**; límite mensual; tipo/relación.
- `SessionConcurrencyTest` — **RF-1/RF-11:** dos envíos idénticos simultáneos → una sesión; reintentos devuelven la misma (CA-11.3/11.4); dos reservas simultáneas incompatibles en relaciones distintas pero con un participante común → solo una se confirma; dos transiciones concurrentes de la misma sesión → un estado final y una secuencia auditada; valida locks por participante/estado y el índice único de respaldo de §6.5.
- `SessionLifecycleTest` — **RF-11:** con `Carbon::setTestNow`: `in_progress` automático; `complete` solo mentor (403 admin/mentee); robustez temporal con ambas transiciones auditadas; `not_completed` en el límite inclusivo `now >= end_at + 48 h` con inicializaciones auditadas «no confirmada»; terminales rechazan reprogramar/cancelar/completar; reintento del comando no duplica eventos.
- `SessionRescheduleTest` — **RF-6/RF-3/RF-9:** revalidación completa (choca → 422 y fecha intacta); historial con ambas zonas; aviso único (dedupe); Meet actualizado con `Http::fake()`; <2 h → 422.
- `SessionCancelTest` — **RF-7/RF-9:** marca tardía con antelación real; cancelar conserva `meet_link`/`meet_event_id` en MIS, `SessionResource` y demás respuestas API no exponen el enlace con `status=canceled`, y el evento mínimo no contiene `meet_link`; prueba de contrato con consumidor 008 confirma que un fallo de borrado remoto no altera status ni valor almacenado (caso 42, CA-7.4/CA-9.5); recordatorios detenidos; aviso único.
- `SessionAttendanceTest` — **RF-8:** asistencia desde `end_at` hasta antes de `end_at + 48 h`; exactamente en el límite inclusivo → 422; responsable confirma **solo a cargo** por tipo (familia menores sí / empresa empleados sí / cohorte adultos **403**); mentor por otros → 403; prueba ambos órdenes responsable→miembro y miembro→responsable conservando **ambos** eventos y el estado efectivo del miembro; inicialización auditada idempotente al completar (incluso cierre tardío); `attended` bool coherente (spec 001).
- `SessionAuthorizationTest` — **RF-10:** ajeno manipulando id → **404**; participante en operación no permitida → **403**; mentee crea/reprograma/cancela; responsable del colectivo gestiona; disponibilidad ajena → 403/404; `admin`/`super_admin` sin permiso explícito denegados, con `view.support` solo lectura, con `manage.support` mutaciones; soporte auditado.
- `CollectiveMembershipTest` — **RF-13:** alta → entra en recordatorios y confirmación; baja → deja de recibirlas sin borrar registros; celebradas intactas; **caso 41** (alta con sesión propia solapando: alta válida, la colectiva no se revalida/cancela, y esa persona no recibe recordatorios ni confirma de ESA sesión); ancla `end_at` (menor 4).
- `MeetAutomationTest` — **RF-9:** `Http::fake()` éxito → enlace creado con la **zona de la sesión** y sin invitados; fallo → sesión creada igualmente, `meet_attempts++`, comando reintenta y luego tiene éxito; idempotente (segunda llamada devuelve el mismo); backfill de futuras sin enlace (CA-9.1).
- `ReminderTest` — **RF-5/RF-13:** confirmación del proveedor marca `sent`; fallo y resultado ambiguo conservan la misma fila/ID y permiten reintento; simular aceptación del proveedor seguida de redelivery antes de persistir `sent`, comprobar publicaciones repetidas con el mismo `notification_id`; esta prueba de backend no acredita deduplicación visible. T010 de 010 verifica la integración del consumidor por el mismo ID antes de aceptar RF-008.2 (caso 43). No afirmar exactamente una publicación de transporte. Además: reprogramación reactiva horizontes y no envía la fecha vieja; cancelada no recuerda; <24 h → inmediato único; responsable no miembro no recibe recordatorio pero sí aviso de cambio.

### 15.3 Frontend (Vitest, `frontend/src/lib/scheduling.test.ts`)
- Mapa estado→UI (5 estados + etiquetas), distinción por texto no solo color.
- Conversión y etiqueta de zona horaria (dos zonas, límite de día).
- `isLateCancellation` → etiqueta; `slotGrid` con paso 30 min; mensaje de conflicto por código 422; fallback i18n a `es`.
- QA manual de RF-12/RNF-6/7: teclado completo (incluido foco visible), lectores de pantalla/labels y errores anunciables, contraste/estados sin depender solo del color y uso en viewport móvil; no se añade Testing Library ni otro framework en esta spec.

### 15.4 Matriz RF → test

| RF | Unit | Feature | Vitest |
|---|---|---|---|
| RF-1 | Overlap, Duplicate | Booking, Concurrency | conflictos |
| RF-2 | Availability | Booking, Authorization | slots |
| RF-3 | Lifecycle (`sessionEnd`) | Reschedule (zona en historial/Meet) | formato zona |
| RF-4 | — | Booking (rango/collectives) | calendario UI |
| RF-5 | Reminder (ID estable/reintento ambiguo) | Reminder: productor; integración visible obligatoria en T010 (caso 43) | horizontes + upsert visible en T010 |
| RF-6 | Change | Reschedule | — |
| RF-7 | Change | Cancel + contrato 008 (caso 42) | tardía / link cancelado oculto |
| RF-8 | Attendance | Attendance, Lifecycle | — |
| RF-9 | — | Meet, Reschedule, Cancel | — |
| RF-10 | — | Authorization | — |
| RF-11 | Lifecycle | Lifecycle, Concurrency | estados |
| RF-12 | — | (mensajes es/en/pt) | fallback |
| RF-13 | Participant | CollectiveMembership, Reminder, Booking | — |

**Comandos:** `make test-backend` (Pest), focalizados con `docker compose exec backend php artisan test tests/Feature/Scheduling/...`; `make test-frontend`, `pnpm typecheck`, `pnpm lint`. Nada se considera listo si fallan (P19). `Http::fake()` para Google, `Carbon::setTestNow()` para el tiempo, `Broadcast::fake()` para Reverb.

---

## 16. Rendimiento (RNF-12)

- Consultas del calendario acotadas por rango con el índice `(scheduled_at)` existente + `(status, end_at)` nuevo; `with(['relationship.mentor','attendees.user'])` para evitar N+1.
- Disponibilidad y huecos libres: dos lecturas indexadas por `user_id` más sesiones ocupadas del mentor por rango; sin caché necesaria en v1 (volumen bajo); si se necesitara, Redis con TTL corto **sin** ser fuente de verdad (P1).
- Objetivo y comprobación: calendario ≤2 s p95 (RNF-12), medido con un conjunto representativo de sesiones/participantes y un benchmark de 30 cargas tras calentamiento; el test de contrato además verifica que el conteo de consultas no crezca por sesión (evitar N+1). La inspección de queries por sí sola no sustituye la comprobación del umbral temporal.

---

## 17. Fuera de alcance y excepciones formales

- Tal cual la spec §8: sincronización bidireccional con calendarios externos, sesiones recurrentes, disponibilidad del mentee, penalizaciones, otros canales de aviso, otros proveedores de videollamada, invitados en el evento externo, no-show automático, sugerencia de huecos y zona por organización.
- **Sin dependencias nuevas** (P2): sin librería de calendario, sin cola de trabajos nueva, sin librería de fechas (se usa `Carbon` en backend e `Intl` en frontend).
- Ninguna excepción formal a la constitución (§5) es necesaria: no se omite ningún principio aplicable.

---

## 18. Actualizaciones de documentación al finalizar

- `AGENTS.md`: entidades (`MentorAvailability*`, `SessionScheduleChange`, `SessionAttendanceEvent`), rutas nuevas (`/sessions/{id}/reschedule|cancel|complete|attendance`, `/availability/*`, `/mentors/{id}/availability/slots`), `config/scheduling.php`, comandos (`app:advance-session-lifecycle`, `app:generate-meet-links`), 5.º estado `not_completed`.
- `MEMORY.md`: estado de la feature, decisiones DT-1..DT-20 y gotchas (p. ej. «`attended` es reflejo de `attendance_status`», «la clave de recordatorio lleva la fecha vigente»).
- `specs/002-mentorship-scheduling/spec.md`: Estado → `implementada` (v0.5.0) al cierre.

---

## 19. Checklist de criterios obligatorios (constitución §4)

- [x] Cumple los principios aplicables (P1–P20 mapeados en §1).
- [x] Respeta la arquitectura existente; sin dependencias nuevas (P2, DT-18).
- [x] Implementa autorización en el servidor y protección de datos (§9.8, §13).
- [x] Define reglas de negocio y criterios de aceptación (§8, §9, §14).
- [x] Incluye pruebas automatizadas de comportamientos críticos (§15 — literalmente las que pide la verificación de P8: solapes, concurrencia, cambios de horario e idempotencia).
- [x] Conserva integridad y trazabilidad de datos (§6, append-only + activitylog).
- [x] Documenta migraciones, dependencias y cambios de API (§5.4, §6, §18).
- [x] Permite diagnosticar fallos sin exponer información sensible (§13).

---

## 20. Entregas y orden de implementación (todavía sin código)

El plan estima **~43 unidades de trabajo de 20–30 minutos** (30 en backend + 13 en frontend/cierre), organizadas explícitamente en **dos entregas**:

- **Entrega A — backend completo (fases 0–5, 30 tareas):** preparación · dominio/migraciones · núcleo puro · escritura (Actions) · lectura (requests/resources/controllers/rutas) · automatizaciones (ciclo de vida, recordatorios, Meet). Al terminar, **toda la regla de negocio es verificable por API** (`make test-backend` en verde) sin tocar frontend.
- **Entrega B — frontend + cierre (fases 6–8, 13 tareas):** contrato (tipos/Zod/lib/hooks) · UI+i18n · documentación y verificación final (`pnpm typecheck`, `pnpm lint`, `make test-frontend`).

Orden por fase dentro de cada entrega (cada unidad cierra con sus tests en rojo→verde):

1. **Fase 0 — preparación:** baseline y carpetas.
2. **Fase 1 — dominio/migraciones:** config+lang, enums, migraciones aditivas, modelos+factories, `SessionPolicy`.
3. **Fase 2 — núcleo puro:** las 8 Rules con sus unit tests.
4. **Fase 3 — escritura:** Create/Reschedule/Cancel/Complete/ConfirmAttendance + `SessionBookingService` + sync de participantes/disponibilidad.
5. **Fase 4 — lectura:** Form Requests, Resources, controllers y rutas, tests de booking/concurrencia y de autorización.
6. **Fase 5 — automatizaciones:** ciclo de vida, recordatorios, avisos de cambio, GoogleMeetService y Meet con reintento (+ sus tests feature).
7. **Fase 6 — contrato frontend:** tipos, `lib/scheduling.ts` + Vitest, schemas Zod, query-keys, hooks, i18n.
8. **Fase 7 — UI:** calendario, detalle, formularios, disponibilidad, integración en `AppShell`, accesibilidad/responsive.
9. **Fase 8 — cierre:** documentación (`AGENTS.md`/`MEMORY.md`), verificación final y Estado → `implementada`.

**Recomendación SDD sobre tamaño:** ~43 tareas atómicas supera el máximo recomendado de 10 por spec. Se recomienda mantener la Spec 002 (ahora v0.4.2 con cambio propuesto pendiente de aprobación) como fuente de alcance y desglosar la implementación en specs hijas aprobadas (por ejemplo, backend de dominio/API; automatizaciones; frontend), cada una con un backlog de hasta 10 tareas atómicas; no se altera el alcance ni se crea ninguna hija sin aprobación. `tasks.md` de esta propuesta enumera 10 paquetes de trabajo de alto nivel por el límite de entregables del planner, no pretende hacer pasar esos paquetes por 43 tareas pequeñas; se debe aceptar el desglose propuesto antes de implementar.
