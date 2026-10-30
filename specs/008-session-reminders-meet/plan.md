# Plan — Spec 008: Recordatorios y Google Meet

**Estado:** aprobado  
**Spec:** `specs/008-session-reminders-meet/spec.md`; scope hijo aprobado.  
**Base:** Spec 002 v0.4.2 aprobada y plan 002 v1.2.0 aprobado.
**Dependencias:** 003 zona/privacidad; 004 inscripción; 005 participantes y caso 41; 006 estados; 007 eventos de cambio. Consumidores: 009 calendario/detalle; 010 presentación accesible y consumidor/upsert visible de `SessionReminder` por `notification_id` (RF-010.4). La implementación de 009→010 conserva el orden; la aceptación final de RF-008.2 espera la integración de 010.

## Trazabilidad

| RF hija | Padre/CA exactos | Contrato |
|---|---|---|
| RF-008.1 | RF-5, CA-5.1/5.5; contrato 005 | Destinatarios vigentes según 005, horas de sesión y enlace/pendiente. |
| RF-008.2 | RF-5, CA-5.2/5.3/5.6; casos 15–16/43 | 008 conserva ID lógico y estado de proveedor; 010 consume `SessionReminder` y upserta por ID. Puede repetirse transporte, no el aviso lógico visible. La aceptación final espera prueba integrada T010. |
| RF-008.3 | RF-5, CA-5.4/5.7/5.8; CA-6.5/7.1; caso 41 | Cambios únicos, inmediato <24h, cese al cancelar; excluir no participante. |
| RF-008.4–008.6 | RF-9, CA-9.1–9.7; CA-3.1/3.7; CA-6.4/7.4 | Automatización, backfill, actualización/borrado, zona y sin invitados; la exposición API del enlace cancelado queda bajo contrato de 007. |
| RF-008.7 | RF-7, CA-7.4; caso 42 | Consumir cancelación, borrar Meet best-effort, registrar fallo; conservar valor local y mantenerlo oculto; evento/log sin enlace. |
| RNF-008.1–008.3 | RNF-3/5/9/10; P1/P4/P14/P17/P20 | Persistir primero, idempotencia, errores seguros y diagnóstico. |

## Archivos y responsabilidades previstos

- Migración aditiva de estado de entrega/dedupe de `notifications`; config `config/scheduling.php`; traducciones `lang/{es,en,pt}/scheduling.php`/`meet.php` (RF-008.1–5).
- `ReminderRules.php`, `ParticipantRules.php` (consumir contrato 005), `SessionNotificationService.php`: pureza de fechas/dedupe separada de broadcast; una fila `Notification` por aviso lógico, cuyo UUID se mantiene como ID estable (`notification_id`) a través de reintentos (RF-008.1–3).
- `SendSessionReminders.php` existente y `routes/console.php`: búsqueda de sesiones programadas, participantes y envío con reintento (RF-008.1–3).
- Consumidor de `SessionRescheduled`/`SessionCanceled` de 007; no modificar ni reemplazar las Actions propietarias de eventos. El de cancelación resuelve `meet_event_id` desde MIS y solicita borrado best-effort de manera idempotente; no recibe ni requiere `meet_link` en el evento y nunca limpia su valor persistido (RF-008.3/7).
- `MeetAutomationService.php`, `GenerateMeetLinks.php`, `GoogleMeetService.php` existente: creación, backfill, update/delete, status/attempts y timeout; sin invitados y timezone de sesión. En cancelación se conserva `meet_link`/`meet_event_id`; `SessionResource`/otras respuestas API mantienen la regla de 007 que oculta el link cuando `status=canceled` (RF-008.4–7).
- Tests Pest unit `ReminderRulesTest`; feature `ReminderTest`, `MeetAutomationTest`, pruebas de contrato de eventos 007 y `Http::fake`/`Event::fake` verifican caso 42 y productor/reintento del caso 43. Estas pruebas de backend solo demuestran ID estable en los intentos y estados de proveedor; la integración frontend T010 prueba el upsert visible y es requisito para aceptar RF-008.2 (RF-008.1–7).

### Dependencia del destino de notificaciones (RF-008.2, caso 43)

`SessionReminder` publica el mismo UUID como `notification_id` en el evento privado existente de usuario. No se encontró consumidor frontend de `session.reminder`. El productor 008 conserva el identificador, persistencia y estado del proveedor, pero **no prueba por sí solo la deduplicación visible**. La Spec 010 es propietaria de consumir ese evento y upsert por `notification_id`; la prueba integrada de 010 reemplaza la validación de destino que 008 no puede demostrar de forma aislada. No se añade canal, API, bandeja persistente ni prescripción de componente. La aceptación de RF-008.2 queda explícitamente condicionada a que esa prueba pase después de implementar 010; 008 puede completar su productor y sus pruebas backend antes, sin dar por aceptada la garantía visible.

## Funciones puras e interfaces

- `ReminderRules::dueHorizons(scheduledAt, now): list<int>` usa `now` y cadencia [24,2,1] en zona de sesión; entrega en intervalo temporal definido por tarea cada minuto (CA-5.1).
- `ReminderRules::isImminent(scheduledAt, now): bool`: inicio a menos de 24h para recordatorio inmediato único (CA-5.8).
- `ReminderRules::reminderKey(sessionId, userId, horizon, scheduledAt): string`: incorpora fecha vigente; `changeKey(sessionId, eventId, userId): string`: un efecto por evento estable (CA-5.2/3/7).
- `MeetAutomationRules::eligible(session, now): bool` y `shouldUpdate(session, event): decision` reciben `now`; únicamente scheduled futura, estado stale/update y política ya aprobada (CA-9.1/2/4).
- IO: `SessionNotificationService` registra pending, confirma sent solo ante entrega del provider, deja failed reintentable; la operación de entrega debe preservar exclusión 005 y responsable no miembro solo en cambio (CA-5.1–8).
- IO: `MeetAutomationService::ensure(session, mode, now)` y `delete(session, eventId, now)` son idempotentes, con timeout y error sanitizado. Creación usa start/end/timezone de 003, sin guest; operación de sesión no revierte por fallo externo (CA-9.2–9.7; CA-7.4).
- Comandos: barrido de recordatorios según scheduler existente, Meet retry cadencia fija del plan 002; manual Meet después del inicio conserva el endpoint actual bajo autorización 003 (CA-9.2).

## Algoritmo

```text
sendReminders(now):
  para cada sesión scheduled futura:
    targets = ParticipantRules de 005 al instante de envío, más mentor
    excluir exclusión caso 41; responsable no miembro no está en targets
    por horizonte debido (o immediate único si fecha creada/reprogramada <24h):
      key = session + participant + horizon + scheduled_at vigente
  si key ya sent: continuar; si pending concurrente: no duplicar
  recuperar o crear una sola fila pending; conservar su UUID como ID lógico
  entregar mensaje con fecha vigente + meet_link o texto pendiente e `notification_id=UUID`
  confirmar sent/sent_at solo al éxito confirmado del proveedor; ante error/ambigüedad
    registrar resultado, dejar reintentable con el mismo UUID
  consumidor de presentación 010: upsert/reemplazar por UUID, nunca anexar un segundo aviso lógico
  cola puede redeliverar tras aceptación previa a persistir sent: transporte repetido permitido;
  el consumidor de 010 conserva un único aviso visible (caso 43)

handleChange(event 007, now):
  idempotencia por event_id + participante vigente 005 + responsable colectivo
  recuperar/crear la misma fila e ID para cada aviso lógico de cambio; publicar con ese ID
  consumidor de 010 upsert por ID; replay puede repetir transporte pero no agrega otro aviso visible

handleCancellation(event 007, now):
  idempotencia por event_id
  si event.meet_event_id existe (resuelto desde MIS): solicitar deleteEvent best-effort
  no limpiar `sessions.meet_link` ni `meet_event_id`; 007 mantiene oculto el link por status=canceled
  éxito: registrar resultado mínimo; fallo: registrar error sanitizado sin link
  nunca reactivar sesión ni exponer el link; evento/log no incluyen `meet_link`

ensureMeet(session, mode, now):
  si enlace existente y create: devolver existente
  si scheduled futura sin enlace o stale: lock y recheck; crear/actualizar en Google con zona de sesión, sin guests
  al éxito guardar IDs/link y limpiar error; al fallo guardar intento/error sanitizado sin revertir sesión
  después del inicio, barrido automático para; generación manual autorizada permanece disponible
```

La cancelación persistida por 007 conserva el `meet_link` local como dato histórico y la capa API lo oculta según el estado. 008 solicita la eliminación externa best-effort; un fallo se registra sin limpiar el dato local, reactivar la sesión ni exponer el enlace (CA-7.4/caso 42). El evento y los logs de cancelación no incluyen `meet_link`. El barrido/backfill no altera sesiones pasadas o canceladas.

## Decisiones y alternativas descartadas

1. **Dedupe por fecha vigente y estado de entrega:** una reprogramación habilita nuevos horizontes, intentos fallidos no consumen clave; se descarta dedupe solo por sesión/usuario que bloquearía avisos futuros.
2. **Barridos programados idempotentes en Laravel y servicio Google existente:** reutiliza scheduler y fuente persistida; se descarta reintento manual obligatorio o sistema/cola nuevo.
3. **Evento post-commit 007 como única entrada de avisos de cambio:** evita notificar estado no persistido; se descarta repetir lógica en controlador/cliente.
4. **Proveedor Google best-effort; MIS sigue autoridad y conserva historia:** cancelación/reprogramación persistidas no se revierten; la cancelación oculta el link por estado y mantiene su valor almacenado, se descarta borrarlo localmente o que indisponibilidad externa impida operar agenda.
5. **No agregar invitados; pasar timezone explícita:** minimiza datos externos y conserva hora local; se descarta la zona fija o enviar contactos a Google.
6. **Un mismo UUID persistido como ID lógico, con upsert de presentación asignado a 010:** permite reconocer el mismo aviso cuando la cola redelivera tras un resultado ambiguo y evita afirmar exactly-once en el transporte. Se descarta asignar un ID nuevo por intento (generaría avisos lógicos distintos) y descartar reintentos ambiguos (el proveedor pudo no haber entregado). El upsert usa el evento privado existente y no introduce canal/API/bandeja persistente ni prescribe un componente.

## Tests y cierre

- Unit con `now`: fronteras de 24/2/1 h, inmediato menor que 24, claves cambian por fecha vigente, event_id dedupe; destinatario 005 excluido y responsable no miembro fuera de envío ordinario (RF-008.1–3).
- Feature scheduler: una fila/ID estable por horizonte/fecha; provider success marca sent; error/ambigüedad retryable con el mismo ID. Reproducir aceptación del proveedor seguida de redelivery antes de persistir `sent`: validar que la publicación puede repetirse y todas llevan el mismo ID (caso 43; no probar exactly-once del transporte). La prueba de backend no sustituye la integración de T010: su test de consumidor debe confirmar un único aviso visible actualizado con el mismo ID y elementos distintos para IDs diferentes. Hasta que pase, RF-008.2 queda sin aceptación final aunque T008-01–06 conserven sus marcas históricas.
- Además: cancelada detiene recordatorios, reprogramación invalida fecha antigua y mantiene nuevas, aviso de cambio sin reminder pendiente; incluye alta válida/exclusión caso 41 de 005 (CA-5.1–5.8).
- Meet fake HTTP: éxito zona explícita y sin attendees; error de credenciales/timeout no revierte booking; backfill scheduled futuro; retry repetido conserva evento; stale actualiza; cancelación elimina externamente best-effort. Tanto con borrado exitoso como fallido, `meet_link` persiste sin exponerse por API en `canceled`; fallo queda registrado, sin `meet_link` en evento/log y sin cambio de estado (RF-008.6–.7, CA-7.4/CA-9.5, caso 42).
- Diagnóstico sin secretos/datos innecesarios, tests repetidos y run scheduler múltiples (RNF-008.1, P14/P20).
- Contract test: 007 emite una vez un evento sin `meet_link`; 008 consume sin cambiar estado y conserva el valor histórico, mientras las respuestas de 009/API siguen ocultándolo en canceladas. 009 recibe el link solo cuando la sesión no está cancelada y el estado pending sin fuente externa paralela (RF-008.6–.7, caso 42).

## Dependencias contractuales

- **De 003:** instante y timezone persistidos, privacidad y autorización manual.
- **De 004/005:** participantes de alta inicial y modificaciones prospectivas; destinatarios se consultan conforme al set vigente. Excluidos del caso 41 no reciben reminder, avisos del colectivo ni Meet invite.
- **De 006:** solo sesiones `scheduled` generan/reintentan; terminales cesan recordatorios/automatización.
- **De 007:** eventos post-commit idempotentes para cambios; 008 no reabre, cancela ni vuelve a persistir la operación de negocio salvo sus metadatos propios.
- **Del consumidor de presentación 010:** recibe `SessionReminder` en su canal privado existente, utiliza `notification_id` para upsert visible y verifica IDs repetidos/distintos con idioma y accesibilidad. No añade canal/API/bandeja persistente. Esta dependencia condiciona la aceptación final de RF-008.2, no el orden de implementación de 009 antes de 010.
- **A 009/010:** un mismo `meet_link`/estado pendiente se presenta en recordatorio, detalle y calendario; presentación/i18n accesible corresponde a 010.
