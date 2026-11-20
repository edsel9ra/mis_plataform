# Spec 002 — Agenda y gestión de mentorías

**Estado:** implementada a través de sus specs hijas 003–010 (`decomposition.md`) y de 016–018, que cubrieron los huecos del desglose (cabecera puesta al día el 2026-11-20)
**Versión:** 0.4.2
**Fecha:** 2026-10-13
**Historial:** 0.1.0 (2026-10-12) borrador inicial con las dudas D1–D5; 0.2.0 (2026-10-12) las 5 dudas se resolvieron con decisiones vinculantes y quedan convertidas en requisitos (§10); sin dudas abiertas. 0.3.0 (2026-10-12) revisión QA de la v0.2.0: los 8 hallazgos bloqueantes se resolvieron con decisiones de producto D6–D11 (§10) —ciclo de vida de estados, participación colectiva, ocupación colectiva, confirmación de asistencia, agenda del responsable de colectivo, reprogramación, precedencia de excepciones de disponibilidad y zona horaria del evento externo— y se incorporan las correcciones de los hallazgos menores y sugerencias de la revisión (verificabilidad, dedupe, terminología unificada de asistencia, umbrales, matriz HU↔RF↔CA). **0.4.0 (2026-10-12) APROBACIÓN del cliente** de la v0.3.0 tras la 2ª pasada de revisión, sin reabrir ninguna decisión: se aplican los 4 menores y las 3 sugerencias no bloqueantes del reviewer —CA-2.4 (las excepciones bloqueantes siguen aplicando sin ventanas), CA-8.1/caso 24 («miembros a su cargo» por tipo de colectivo), nuevo caso límite 41 (alta de miembro con sesión propia solapando una colectiva), CA-8.3/CA-13.3 («participantes registrados» anclados a la hora de fin programada), CA-5.1 (el responsable no miembro no recibe recordatorios), nueva definición de «sesión confirmada» (§4) y RNF-13 (atribución del cálculo de la spec 001)—. **Estado: aprobada; pasa a fase de plan y tareas.** 0.4.1 (2026-10-13) aclaración de la conservación histórica y ocultación en API del enlace Meet al cancelar, el borrado externo best-effort a cargo de 008 y la minimización del evento de cancelación; aprobada explícitamente por el usuario. 0.4.2 (2026-10-13) **aprobado explícitamente por el usuario:** delimita la idempotencia de recordatorios a la notificación lógica visible: los reintentos ambiguos conservan un ID estable y el destino reemplaza/actualiza el mismo aviso, aunque pueda repetirse su publicación de transporte; solo una confirmación del proveedor marca el envío como entregado.
**Ámbito:** nueva funcionalidad de producto (el QUÉ y el POR QUÉ; sin decisiones de implementación)
**Referencias:** `docs/constitution.md` (principios P1–P20; los aplicables se citan en el texto), regla de módulo §3 «Agenda y gestión de mentorías» y decisión de producto §3.1.3; `specs/001-progress-tracking-dashboard/spec.md` (de cuya regla de «sesiones realizadas» depende el registro de asistencia de esta spec, y de cuya §3.1 D10 procede la definición de responsable de colectivo).
**Naturaleza:** esta spec describe **qué** debe hacer la funcionalidad y **por qué**. No define endpoints, esquemas de base de datos ni componentes concretos; esos son decisiones de diseño posteriores.

---

## 1. Contexto y objetivo

### 1.1 Contexto (de dónde partimos)

MIS ya trabaja con sesiones, pero la agenda es incompleta y poco consistente:

- **Sesiones:** existen con estados (`scheduled`, `in_progress`, `completed`, `canceled`), fecha (`scheduled_at`), duración, tipo (`individual`, `family`, `group`, `corporate`) y campos de Meet (`meet_link`, `meet_event_id`). Se agrupan bajo una relación de mentoría polimórfica. **No hay transiciones automáticas definidas**: `in_progress` y `completed` se escriben sin regla productiva clara, y no existe ningún estado ni marca para una sesión que el mentor nunca cierra.
- **Participantes:** existen `SessionAttendee` con un indicador `attended`, pero **no hay ningún flujo que lo escriba**: hoy la asistencia solo la siembra el seeder de demo. No hay registro de quién confirmó ni cuándo. En sesiones colectivas tampoco está definido quién es participante ni cómo varía eso en el tiempo.
- **Creación de sesiones:** la pueden hacer hoy el mentor de la relación o un admin; se valida relación activa, tipo coherente con la relación y límite mensual de sesiones del plan, pero **no se comprueba solapamiento con otras sesiones ni duplicidades** (dos peticiones iguales crean dos sesiones).
- **Disponibilidad de mentores:** **no existe** el concepto en el sistema (sin ventanas, sin excepciones, sin comprobación al reservar).
- **Zona horaria:** el usuario tiene campo de zona (`America/Mexico_City` por defecto), pero **la sesión no identifica su zona** (P8 la exige) y el evento de Google se crea con una zona fija; las métricas de la spec 001 ya calculan en la zona del sujeto.
- **Recordatorios:** existe la tarea `app:send-session-reminders` que corre cada minuto y avisa a 24 h, 2 h y 1 h, con clave de deduplicación (ya idempotente). No reacciona a reprogramaciones ni cancelaciones, su deduplicación no considera la fecha vigente de la sesión y no cubre sesiones creadas con poca antelación.
- **Google Meet:** existe el servicio y un endpoint manual `POST /sessions/{id}/meet-link`; la creación es opcional y manual, y al eliminar la sesión se borra el evento de Google.
- **Autorización:** «ver» = participar en la sesión; «gestionar» = ser admin o mentor de la relación. Hoy **solo el mentor/admin puede reprogramar o cancelar**; el mentee no (con esta spec pasan a gestionar **ambas partes**, y el responsable de colectivo gestiona las de su colectivo — RF-10, decisiones D2 y D9).
- **Interfaz:** existe una página de sesiones en **lista** (próximas / pasadas) con paginación; **no hay vista de calendario** ni gestión de disponibilidad.

### 1.2 Objetivo (qué queremos y por qué)

Construir la **agenda de mentorías**: programación de sesiones con control de solapamientos y duplicidades, disponibilidad explícita de los mentores, un ciclo de vida de estados con cierre trazable, recordatorios fiables, reprogramaciones y cancelaciones trazables, registro de asistencia, una vista de calendario integrada con las sesiones de la API y la generación **automática** del enlace de Google Meet cuando corresponda.

**Por qué:** la fila «Agenda y gestión de mentorías» de la constitución (§3) exige que **se controlen disponibilidad, estados, zona horaria y duplicidades**. Sin eso, dos personas pueden ocupar el mismo hueco, un reintento duplica sesiones, los recordatorios apuntan a fechas viejas y la asistencia —que es la que hace contar una sesión como realizada según §3.1.3— no se registra con fiabilidad. Esta spec es, además, el **alimentador de la regla de «sesiones realizadas» de la spec 001**.

### 1.3 Principios rectores de esta spec

- **P8 (agenda consistente y sin duplicidades):** estados y reglas de disponibilidad explícitos; toda sesión identifica participantes, fecha, duración, **zona horaria** y estado; se impiden reservas incompatibles para un mismo participante; cancelaciones y reprogramaciones actualizan el calendario y conservan trazabilidad; los reintentos no crean sesiones duplicadas.
- **P5 (trazabilidad):** quién reprogramó, quién canceló, quién confirmó la asistencia y cuándo, con los valores anteriores y nuevos; las confirmaciones sustituidas no se pierden.
- **P14 (automatizaciones idempotentes):** recordatorios, avisos de cambio y generación de Meet toleran reintentos y no producen efectos duplicados (un solo efecto por evento de negocio).
- **P3 (autorización en cada operación):** la agenda se autoriza en el servidor reutilizando la autorización existente de relaciones y sesiones; ocultar un botón no autoriza nada; una sesión ajena no se revela manipulando identificadores.
- **P6 (progreso basado en evidencias):** una sesión programada no cuenta como realizada por sí sola; el mentor que cierra una sesión **atestigua** que ocurrió, y la asistencia individual se confirma por cada participante, nunca se infiere.
- **P16 (experiencia accesible y consistente):** formularios con validación y errores claros, estados de carga/éxito/error, operables por teclado; el calendario distingue estados visualmente.
- **P17 (contratos de API explícitos):** entradas, salidas, errores y reglas de autorización definidos; integración con Google con timeouts y errores controlados.
- **P1 (fuente única de verdad) y P2 (arquitectura existente):** la agenda se apoya en las sesiones, relaciones y recordatorios ya existentes; Google es un proveedor externo, nunca la fuente de verdad.
- **P19 (calidad demostrable):** pruebas de solapamiento, concurrencia, cambios de horario e idempotencia (la propia constitución las cita en la verificación de P8).

### 1.4 Alcance de la v1 (resumen)

Incluye: ciclo de vida de estados explícito con transición automática a `in_progress`, cierre manual exclusivo del mentor y estado «no completada» a las 48 h de la hora de fin (D6); validación de solapamientos y duplicidades al programar, con participación colectiva dinámica (todos los miembros activos del colectivo; D7) y rechazo del hueco para todo el colectivo si algún miembro choca; disponibilidad de mentores (ventanas y excepciones, con precedencia de la excepción — D11) comprobada al reservar; zona horaria por sesión, elegida por quien la programa (por defecto la suya), mostrada convertida a la zona de cada participante; vista de calendario/agenda sobre las sesiones de la API, incluida la del colectivo para su responsable; recordatorios con la cadencia 24 h / 2 h / 1 h, robustos ante cambios y ante sesiones creadas con poca antelación, más un aviso único de reprogramación y cancelación; reprogramación solo de sesiones `scheduled` futuras con revalidación completa (D10) y cancelación con política de antelación de 2 horas (las cancelaciones tardías quedan marcadas); confirmación de asistencia por participante (con precedencia de la confirmación propia sobre la del responsable y el mentor nunca confirmando por otros — D8) con plazo de 48 h desde la hora de fin, que alimenta la regla §3.1.3; generación automática (e idempotente) del enlace de Google Meet con la zona horaria de la sesión; autorización del responsable de colectivo sobre la agenda de su colectivo (D9), i18n y accesibilidad.

Excluye (ver §8): sincronización bidireccional con calendarios externos, sesiones recurrentes, disponibilidad del mentee, penalizaciones económicas, otros canales de aviso, otros proveedores de videollamada e invitados en el evento externo.

### 1.5 Coherencia con decisiones existentes

- **§3.1.3 (vinculante):** «una sesión cuenta como realizada únicamente cuando su estado es `completed` **y** su asistencia está confirmada». El registro de asistencia de esta spec (RF-8) es **el que hace posible esa regla**: sin asistencia confirmada, la sesión no se contará como realizada en el panel de progreso (spec 001, RF-5).
- **Equivalencia con el dato existente:** el indicador de asistencia existente que consume la spec 001 (el booleano `attended` de `SessionAttendee`) mantiene su significado: «asistió» equivale a verdadero; «no asistió» y «no confirmada» equivalen a falso (CA-8.9). Ningún estado nuevo altera el significado de ese dato ni los indicadores ya publicados.
- **Límite mensual de sesiones del plan:** se conserva tal cual está; esta spec no lo cambia (solo asegura que las canceladas no lo consuman, comportamiento ya existente, y que se revalide al reprogramar — CA-6.2).
- **Responsables de colectivo:** se reutiliza la definición ya decidida en la spec 001 (§3.1 D10): `FamilyGroup.head_user_id`, `Cohort.created_by`, `Company.admin_id`, comprobada con la autorización existente de colectivos (`userCanManageSource`).

---

## 2. Usuarios

| Usuario | Descripción | Qué hace en la agenda | Por qué (necesidad) |
|---|---|---|---|
| **Mentee** | Persona en proceso (rol `mentee`, o `employee` con proceso asignado). | Ve su calendario, crea, reprograma y cancela sesiones de su relación, recibe recordatorios y confirma su propia asistencia (la de su grupo/familia solo si es responsable de él). | Organizar su tiempo, decidir sobre su agenda y saber cuándo y cómo se une a cada sesión. |
| **Mentor** | Acompaña a uno o varios mentees (rol `mentor`). | Define su disponibilidad, crea sesiones, reprograma y cancela, cierra (`completed`) las sesiones que atiende, confirma su propia asistencia, consulta la asistencia de todas sus sesiones y gestiona el Meet. | Proteger su tiempo, evitar choques y dejar constancia de lo ocurrido. |
| **Responsable de colectivo** | `company_admin`, cabeza de familia o coordinador de cohorte (spec 001, §3.1 D10). | **Consulta la agenda de su colectivo** y **programa, reprograma y cancela sesiones del mismo** (como si fuera su parte gestora) y confirma la asistencia de los miembros a su cargo —según el tipo de colectivo: familia → menores; empresa → empleados; cohorte → solo menores (CA-8.1)— cuando ellos no la han confirmado. | Organizar el acompañamiento del colectivo y ver su actividad programada sin fiscalizar ni depender de cada persona. |
| **Admin de plataforma** | `super_admin` / `admin`. | Gestiona la agenda como soporte, con registro de auditoría (P3/P5). | Diagnóstico y resolución de incidencias. |

---

## 3. Historias de usuario

- **HU-1 (Mentor):** como mentor, quiero reservar una sesión sabiendo si choca con otra mía o con otra de los participantes, para no crear conflictos de agenda.
- **HU-2 (Mentor):** como mentor, quiero definir mis ventanas de disponibilidad (días y horas) y sus excepciones, para que solo me ofrezcan huecos en los que realmente puedo.
- **HU-3 (Mentor/mentee/responsable):** como quien programa una sesión, quiero comprobar la disponibilidad del mentor (sus huecos libres), para no recibir un rechazo tardío o una hora imposible.
- **HU-4 (Mentee):** como mentee, quiero ver mi agenda en un calendario con mis próximas sesiones y su estado, para organizar mi semana de un vistazo.
- **HU-5 (Todos):** como usuario, quiero ver cada sesión con su hora **y su zona horaria** (elegida al programarla), para unirme a la hora correcta esté donde esté.
- **HU-6 (Todos):** como participante, quiero recibir recordatorios de mis sesiones siempre con la fecha y el enlace correctos, además de un aviso cuando la sesión se reprograma o se cancela, para no quedarme con datos viejos.
- **HU-7 (Mentor/mentee/responsable):** como participante, quiero reprogramar una sesión **aún no iniciada** dejando constancia de la fecha anterior, la nueva, quién lo hizo y por qué, para que nadie se pierda sin saber qué pasó.
- **HU-8 (Mentor/mentee/responsable):** como participante, quiero cancelar una sesión con motivo y con la antelación mínima de 2 h, para liberar el hueco y que quede registrado —también cuando la cancelación es tardía—.
- **HU-9 (Todos los participantes):** como participante, quiero confirmar **mi propia** asistencia tras cada sesión; y si soy responsable de un colectivo, quiero poder confirmar también la de los miembros a mi cargo, sabiendo que la confirmación de cada miembro prevalece sobre la mía, para que el progreso cuente solo lo realmente realizado.
- **HU-10 (Mentee):** como participante, quiero confirmar que asistí a mi sesión dentro de las 48 h siguientes a su hora de fin, para que mi registro sea fiel aunque el mentor tarde en cerrar la sesión; y quiero que, si nadie confirma en ese plazo, quede constancia de «no confirmada» en lugar de asumida.
- **HU-11 (Todos):** como participante, quiero que el enlace de Google Meet exista automáticamente cuando la sesión está confirmada, para no depender de que alguien lo genere a mano.
- **HU-12 (Mentor/mentee):** como usuario, quiero que un doble clic, un reintento o una caída de red no creen dos sesiones ni dos eventos de Meet, para fiarme de la agenda.
- **HU-13 (Todos):** como usuario, quiero usar toda la agenda con teclado, en mi idioma (es/en/pt) y bien en móvil, para no depender del ratón ni del español.
- **HU-14 (Responsable de colectivo):** como responsable de un colectivo (familia, cohorte o empresa), quiero consultar la agenda de mi colectivo y programar, reprogramar y cancelar sesiones del mismo, para organizar el acompañamiento sin depender de que cada miembro lo haga.
- **HU-15 (Admin de plataforma):** como admin de plataforma, quiero operar la agenda como soporte (consulta, ajustes puntuales y resolución de incidencias) con registro de auditoría, para ayudar sin romper la trazabilidad.

---

## 4. Definiciones

- **Sesión:** cita de mentoría con participantes, fecha/hora de inicio, duración, **zona horaria**, tipo y estado (grafo de estados en RF-11); pertenece a una relación de mentoría.
- **Sesión confirmada:** sesión en estado **`scheduled` con hora de inicio futura** (futura respecto al instante evaluado), ya sea al crearse o al reprogramarse; es la condición que dispara la generación automática del enlace de Meet (CA-9.1) y la que admite recordatorios (CA-5.1). Al llegar su hora de inicio pasa a `in_progress` (CA-11.1) y deja de ser «confirmada».
- **Ventana de disponibilidad:** franja recurrente (día de semana + hora de inicio/fin + zona) en la que un mentor declara poder atender sesiones.
- **Excepción de disponibilidad:** día concreto (o rango) en que el mentor bloquea o amplía sus ventanas, p. ej. festivos o vacaciones. **La excepción tiene precedencia sobre la ventana** (CA-2.7).
- **Solapamiento:** dos sesiones no canceladas cuyos intervalos [inicio, fin) se intersectan **para un mismo participante** (el mentor o cualquiera de los asistentes). En sesiones colectivas, el conjunto de participantes es el definido en RF-13.
- **Duplicidad:** solicitud de creación idéntica (misma relación, mismo inicio, misma duración y título equivalente) repetida, ya sea por error humano o por reintento técnico. El título se compara **normalizado**: sin distinguir mayúsculas/minúsculas y colapsando espacios repetidos.
- **Reprogramación:** cambio de fecha/hora (y/o duración y zona horaria) de una sesión **`scheduled` con hora de inicio futura** que conserva su identidad y su historial; exige revalidación completa (RF-6).
- **Cancelación:** cierre de una sesión sin celebración; conserva el registro y el motivo, y libera el hueco. Puede aplicarse a una sesión `scheduled` o `in_progress` (RF-7).
- **Asistencia confirmada:** registro **por participante —hecho por el propio participante o, para los miembros de un colectivo a cargo de un responsable, por ese responsable mientras el miembro no lo haga él mismo—** de que asistió (o no) a la sesión, con quién lo confirmó y cuándo, dentro de la ventana de confirmación. **La confirmación del propio miembro prevalece siempre sobre la del responsable; el mentor nunca confirma asistencia por otros.** Es el dato que exige §3.1.3 para contar la sesión como realizada. Cada registro adopta uno de tres estados: **«asistió», «no asistió» o «no confirmada»**.
- **Plazo (ventana) de confirmación de asistencia:** las 48 h siguientes a la hora de **finalización** programada de la sesión; se abre cuando llega esa hora de fin y se cierra al cumplirse las 48 h. Transcurrido ese plazo sin confirmación, la asistencia del participante queda fijada como «no confirmada» (no se infiere por silencio).
- **Estado «no completada»:** condición terminal de una sesión no cancelada que alcanza las 48 h de su hora de fin sin que el mentor la haya marcado `completed`. Es visible para ambas partes (participantes, mentor y, si es colectiva, el responsable); no admite ni completación ni confirmación de asistencia posteriores; no cuenta como realizada (§3.1.3). Su representación interna (almacenada o derivada) es un detalle de diseño posterior; lo vinculante es su comportamiento visible (CA-11.6).
- **Cancelación tardía:** cancelación hecha con menos de 2 horas de antelación a la hora de inicio programada (o después de la hora de inicio, en una sesión en curso); queda marcada como tal en su registro (dato de trazabilidad, sin penalización económica en v1).
- **Registro de asistencia:** el conjunto de asistencias por participante de una sesión, con su estado, autor y momento.
- **Participante (de una sesión colectiva):** todo **miembro activo del colectivo** (grupo familiar, cohorte o empresa) —junto con el mentor de la relación—, evaluado dinámicamente: se determina al programar y se ajusta con altas y bajas **de forma prospectiva** (RF-13).
- **Evento de Meet:** el evento de calendario externo creado para una sesión, del que procede el enlace de videollamada; se crea **con la fecha-hora y la zona horaria de la sesión** y **sin añadir participantes como invitados** del proveedor (CA-9.7).
- **Zona horaria de la sesión:** la zona elegida por quien programa la sesión (por defecto la zona propia del creador, con opción de elegir otra); se conserva junto a la sesión para poder mostrarla siempre de forma inequívoca, convertida a la zona del espectador; solo cambia como parte de un cambio explícito y registrado (CA-3.7).
- **Idempotencia de avisos:** cada aviso lógico tiene un identificador estable que no cambia entre intentos. El destino de notificaciones actualiza o reemplaza el mismo elemento visible para ese identificador, en vez de añadir otro aviso lógico. Un reintento tras un resultado ambiguo puede volver a publicar el aviso por transporte; la garantía es un único aviso lógico visible, no entrega exactamente una vez por transporte. Solo una confirmación de éxito del proveedor permite marcar el aviso como **entregado**; fallos y resultados ambiguos permanecen reintentables con el mismo identificador (CA-5.2, CA-5.6).

---

## 5. Requisitos funcionales (RF)

**Notación EARS usada (patrones en español):**

- **Ubicuo:** «El sistema DEBE …»
- **Dirigido por evento:** «CUANDO <disparador>, el sistema DEBE …»
- **Dirigido por estado:** «MIENTRAS <estado>, el sistema DEBE …»
- **Característica opcional:** «DONDE <característica>, el sistema DEBE …»
- **Comportamiento no deseado:** «SI <condición>, ENTONCES el sistema DEBE …»

---

### RF-1 — Programación de sesiones con control de solapamientos y duplicidades

El sistema DEBE permitir programar sesiones sobre una relación de mentoría activa, exigiendo los datos mínimos (título, inicio, duración dentro del rango vigente, tipo coherente con la relación y zona horaria) y DEBE rechazar reservas incompatibles o duplicadas. En sesiones colectivas, los participantes son los definidos en RF-13.

**Por qué:** P8 exige impedir reservas incompatibles para un mismo participante y que los reintentos no creen sesiones duplicadas; sin esta validación la agenda no es fiable (regla de módulo §3: «se controlan … duplicidades»).

**Criterios de aceptación:**

- **CA-1.1 (Ubicuo):** El sistema DEBE exigir, al programar, relación activa, título, fecha futura, **duración entera dentro del rango vigente de la plataforma (entre 15 y 480 minutos)**, tipo coherente con el tipo de relación y zona horaria (elegida por quien crea la sesión; CA-3.6); y en sesiones colectivas DEBE inscribir a los participantes según RF-13.
- **CA-1.2 (No deseado):** SI el intervalo de la nueva sesión se solapa con otra sesión no cancelada del **mismo mentor**, ENTONCES el sistema DEBE rechazar la reserva e indicar la sesión en conflicto.
- **CA-1.3 (No deseado):** SI el intervalo se solapa con otra sesión no cancelada de **cualquier otro participante** de la sesión (mentee, asistentes o, en lo colectivo, **cualquier miembro activo del colectivo — RF-13**), ENTONCES el sistema DEBE rechazar la reserva e indicar el conflicto; en una sesión colectiva esto equivale a rechazar el hueco **para todo el colectivo** (no existe reserva parcial).
- **CA-1.4 (No deseado):** SI se recibe una solicitud repetida con los mismos datos de relación, inicio, duración y título **equivalente (comparado normalizado: mayúsculas/minúsculas y espacios repetidos sin importar)** (duplicidad explícita o reintento), ENTONCES el sistema DEBE devolver la sesión ya creada en lugar de crear una segunda.
- **CA-1.5 (No deseado):** SI la sesión propuesta **no cabe enteramente dentro de ninguna ventana disponible** del mentor ni la cubre ninguna excepción (RF-2), ENTONCES el sistema DEBE rechazarla con un motivo comprensible.
- **CA-1.6 (Ubicuo):** El sistema DEBE descartar de la comprobación de solapes y de límite mensual las sesiones `canceled` y las «no completadas» (CA-11.6), conservándolas para trazabilidad.

### RF-2 — Disponibilidad de los mentores

El sistema DEBE permitir al mentor declarar sus ventanas de disponibilidad recurrentes y sus excepciones, y DEBE comprobarlas al programar o reprogramar una sesión, **con precedencia de la excepción sobre la ventana**.

**Por qué:** sin disponibilidad explícita no es posible «controlar disponibilidad» (§3) ni ofrecer huecos realistas; además evita el coste de crear y rechazar sesiones a última hora.

**Criterios de aceptación:**

- **CA-2.1 (Ubicuo):** El sistema DEBE permitir al mentor crear, modificar y eliminar sus ventanas de disponibilidad (día de semana, hora de inicio y fin, con su zona horaria) y sus excepciones de días concretos.
- **CA-2.2 (No deseado):** SI una ventana tiene fin anterior a su inicio o se solapa consigo misma de forma inconsistente, ENTONCES el sistema DEBE rechazarla e indicar el motivo.
- **CA-2.3 (No deseado):** SI una sesión propuesta (creación o reprogramación) **no cabe enteramente dentro de ninguna ventana disponible** del mentor y no la cubre ninguna excepción, ENTONCES el sistema DEBE rechazarla con motivo explícito.
- **CA-2.4 (Estado):** MIENTRAS un mentor no tenga ninguna ventana definida, el sistema DEBE permitir reservar con él sin aplicar el filtro de ventanas (compatibilidad con los mentores y datos existentes), sin que esto invalide la comprobación de solapes de RF-1 **ni el filtro de excepciones bloqueantes: si el mentor tiene excepciones bloqueantes, estas SIGUEN aplicando y el sistema DEBE rechazar toda reserva que las involucre, aunque no exista ninguna ventana (solo se exime el filtro de ventanas; RF-2, CA-2.7)**.
- **CA-2.5 (No deseado):** SI una excepción bloquea un día ya ocupado por sesiones existentes, ENTONCES el sistema DEBE conservar esas sesiones (no las elimina) y permitir solo su reprogramación o cancelación, informando del conflicto.
- **CA-2.6 (Ubicuo):** El sistema DEBE mostrar al mentor su disponibilidad y sus excepciones en su propia zona horaria, y DEBE permitir **a quien programa una sesión consultar los huecos libres resultantes** de la disponibilidad del mentor (en la zona del consultante), **sin exponer el detalle ni el motivo de las excepciones** (RNF-9).
- **CA-2.7 (No deseado):** SI una excepción de disponibilidad bloquea una fecha y hora, ENTONCES el sistema DEBE **rechazar toda reserva que las involucre aunque caigan dentro de una ventana habitual** (la excepción tiene precedencia sobre la ventana — decisión D11, §10).

### RF-3 — Zona horaria de la sesión

Toda sesión DEBE identificar su zona horaria (P8), **definida por quien la crea al programarla**, y el sistema DEBE interpretar y mostrar las horas convertidas a la zona de cada espectador de forma consistente; el evento externo DEBE heredar esa zona (CA-9.7).

**Por qué:** sin zona inequívoca, «martes 10:00» significa cosas distintas para el mentor y el mentee; y las métricas de la spec 001 ya dependen de interpretar la hora en la zona correcta.

**Criterios de aceptación:**

- **CA-3.1 (Ubicuo):** El sistema DEBE almacenar en cada sesión su zona horaria junto a su fecha de inicio.
- **CA-3.2 (Ubicuo):** El sistema DEBE mostrar cada sesión en la zona del usuario que la consulta e indicar de forma visible la zona de la sesión cuando sea distinta de la del espectador.
- **CA-3.3 (No deseado):** SI el usuario no tiene zona configurada, ENTONCES el sistema DEBE recurrir a la zona por defecto del sistema sin fallar ni mostrar horas vacías.
- **CA-3.4 (Ubicuo):** El sistema DEBE conservar el instante absoluto de la sesión ante cambios de horario local (horario de verano): reprogramar es un cambio explícito de fecha, nunca un desplazamiento silencioso.
- **CA-3.5 (Ubicuo):** El sistema DEBE calcular recordatorios, ventana de próximas sesiones y calendario en la zona correcta, de modo que una sesión cercana al cambio de día no aparezca en el día equivocado.
- **CA-3.6 (Evento):** CUANDO un usuario crea una sesión, el sistema DEBE guardar la zona horaria que él indique, tomando por defecto la de su propio perfil y permitiéndole elegir otra; esa zona queda identificada con la sesión de forma inequívoca (P8) y se conserva con ella hasta un cambio explícito posterior.
- **CA-3.7 (No deseado):** SI un usuario no autorizado para reprogramar intenta cambiar la zona horaria de una sesión, ENTONCES el sistema DEBE rechazarlo (CA-10.3); los usuarios autorizados DEBEN poder cambiarla **solo como parte de un cambio explícito de fecha/hora registrado** (con la zona anterior conservada en el historial — P5); la zona **nunca** cambia automáticamente.

### RF-4 — Vista de calendario / agenda integrada

El sistema DEBE ofrecer una vista de calendario que muestre **las sesiones de la API** en las que el usuario participa —y, si es responsable de colectivo, las de su colectivo—, con sus estados.

**Por qué:** hoy solo hay una lista paginada; el usuario no puede «ver su semana». El calendario es la cara visible del control de agenda (§3, P8) y debe integrarse con las sesiones ya existentes, no con una fuente paralela (P1).

**Criterios de aceptación:**

- **CA-4.1 (Ubicuo):** El sistema DEBE mostrar en el calendario las sesiones del usuario autenticado como participante, con título, hora (con zona), duración, participantes y estado.
- **CA-4.2 (Ubicuo):** El sistema DEBE distinguir visualmente los estados (`scheduled`, `in_progress`, `completed`, `canceled` y «no completada» — RF-11) sin depender solo del color (etiqueta textual).
- **CA-4.3 (Ubicuo):** El sistema DEBE permitir navegar por los periodos **día, semana y mes** y ver el detalle de una sesión desde el calendario.
- **CA-4.4 (No deseado):** SI el usuario no tiene sesiones en el periodo mostrado, ENTONCES el sistema DEBE mostrar un estado vacío explícito con opción a programar, no una pantalla en blanco.
- **CA-4.5 (No deseado):** SI la carga falla, ENTONCES el sistema DEBE mostrar un error comprensible con opción de reintentar (P16).
- **CA-4.6 (Ubicuo):** El sistema DEBE alimentar el calendario desde la misma fuente de sesiones que el resto de la aplicación (una única verdad, P1).
- **CA-4.7 (Ubicuo):** SI un usuario es responsable autorizado de un colectivo, ENTONCES su vista de agenda DEBE incluir las sesiones futuras y pasadas de ese colectivo, identificadas como tales, sin exponer datos de colectivos ajenos (RNF-9, CA-10.7).

### RF-5 — Recordatorios fiables

El sistema DEBE avisar a todos los participantes de cada sesión próxima, con la fecha y el enlace **vigentes**, DEBE enviar un único aviso lógico visible cuando la sesión se reprograme o se cancele, y DEBE tolerar reintentos sin duplicar avisos visibles.

**Por qué:** los recordatorios existentes (24 h, 2 h y 1 h) son correctos pero no reaccionan a cambios ni cubren bien las sesiones creadas de urgencia; un aviso con la fecha antigua tras una reprogramación es peor que ningún aviso (P14, P8).

**Criterios de aceptación:**

- **CA-5.1 (Ubicuo):** El sistema DEBE recordar cada sesión `scheduled` a sus participantes **(según RF-13 en el momento de enviar)** con avisos a 24 h, 2 h y 1 h de antelación (cadencia mantenida para la v1 por decisión D5, §10; antelación expresada en la zona de la sesión). **El responsable de un colectivo que NO es miembro del mismo NO recibe recordatorios** (no es participante): solo recibe el aviso de cambio de CA-5.7.
- **CA-5.2 (Ubicuo):** El sistema DEBE producir **como máximo un recordatorio lógico visible** por participante, sesión, horizonte de antelación y **fecha vigente** de la sesión. Cada aviso lógico DEBE conservar el mismo identificador estable en todos sus intentos; el destino de notificaciones DEBE actualizar o reemplazar el elemento de ese identificador, no añadir otro. Una reprogramación habilita nuevos avisos para la fecha nueva e invalida los de la fecha anterior (CA-5.3). Solo una confirmación de éxito del proveedor permite marcar el aviso como entregado; un fallo o resultado ambiguo no consume la deduplicación y permite reintentar con el mismo identificador. Ante una redelivery ambigua puede repetirse la publicación de transporte: no se garantiza transporte exactamente una vez, pero la persona destinataria ve un solo aviso lógico.
- **CA-5.3 (No deseado):** SI una sesión se reprograma, ENTONCES el sistema DEBE calcular los recordatorios pendientes a partir de la nueva fecha y DEBE retirar o invalidar los que correspondan a la fecha anterior.
- **CA-5.4 (No deseado):** SI una sesión se cancela, ENTONCES el sistema DEBE dejar de enviar sus recordatorios y DEBE notificar la cancelación (CA-5.7).
- **CA-5.5 (Ubicuo):** El sistema DEBE incluir en el recordatorio el enlace de Meet cuando exista e indicar explícitamente que aún no existe cuando no exista.
- **CA-5.6 (No deseado):** SI el envío de un recordatorio falla o su resultado es ambiguo (p. ej., el proveedor pudo aceptarlo, pero no se confirmó el estado), ENTONCES el sistema DEBE registrar el intento, mantenerlo reintentable con el mismo identificador estable y marcarlo **entregado** solo cuando el proveedor confirme éxito. El destino DEBE deduplicar/upsert por ese identificador para que no aparezcan dos avisos lógicos visibles; el sistema no garantiza una sola publicación de transporte cuando se reintenta un resultado ambiguo (P14, P20).
- **CA-5.7 (Evento):** CUANDO una sesión se reprograma o se cancela, el sistema DEBE enviar a todos los participantes vigentes (RF-13) y, si es colectiva, a su responsable, **un solo aviso por evento de cambio** —con la fecha anterior y la nueva (reprogramación) o el motivo (cancelación)—, **además** de los recordatorios y aunque no tengan recordatorio pendiente en ese horizonte (P14: un solo efecto por evento de negocio). Este es el requisito único de los avisos de cambio; las operaciones de reprogramación y cancelación lo ejecutan (CA-6.4, CA-7.1).
- **CA-5.8 (Evento):** CUANDO una sesión se crea o se reprograma con **menos antelación que la mayor cadencia (24 h)**, el sistema DEBE enviar **de inmediato un único recordatorio** con la fecha y el enlace vigentes a los participantes que no tengan ninguno entregado para esa fecha, y DEBE dejar los horizontes aún por venir (2 h y 1 h) para su hora; ningún horizonte se entrega más de una vez por fecha vigente (CA-5.2).

### RF-6 — Reprogramación con trazabilidad

El sistema DEBE permitir reprogramar una sesión **`scheduled` con hora de inicio futura** (cambiar fecha/hora, duración y/o zona horaria) revalidando la totalidad de las reglas de agenda —incluida la antelación mínima de 2 horas— y conservando el historial del cambio. Las sesiones en curso o pasadas **no** se reprograman (CA-6.8).

**Por qué:** P8: «las cancelaciones y reprogramaciones DEBEN actualizar el calendario y conservar la trazabilidad»; sin historial, nadie puede explicar por qué cambió una sesión (P5). Y reprogramar una sesión ya iniciada o pasada rompería la secuencia de estados (D6, D10; decisión §10).

**Criterios de aceptación:**

- **CA-6.1 (Evento):** CUANDO un usuario autorizado reprograma una sesión `scheduled` **con hora de inicio futura**, el sistema DEBE registrar quién lo hizo, cuándo, la fecha anterior, la nueva, la zona anterior y la nueva (si cambia) y el motivo (si se indicó).
- **CA-6.2 (No deseado):** SI el cambio no supera la **revalidación completa** de RF-1 y RF-2 —solape con cualquier participante (incluidos los miembros activos del colectivo), disponibilidad del mentor con precedencia de excepciones (CA-2.7), duplicidad (CA-1.4), fecha futura con antelación mínima (CA-6.7) y límite mensual de sesiones del plan para la nueva fecha—, ENTONCES el sistema DEBE rechazarlo y mantener la sesión intacta con su fecha original.
- **CA-6.3 (No deseado):** SI se intenta reprogramar una sesión `completed`, `canceled` o «no completada», ENTONCES el sistema DEBE rechazarlo (son estados terminales — CA-11.1).
- **CA-6.4 (Evento):** CUANDO cambia la fecha de una sesión, el sistema DEBE actualizar el evento de calendario externo asociado —con la nueva fecha/hora **y su zona horaria** (RF-9, CA-9.7)— y recalcular sus recordatorios (RF-5).
- **CA-6.5 (Evento):** CUANDO se reprograma, el sistema DEBE notificar a todos los participantes conforme a **CA-5.7** (un solo aviso por cambio, con fecha anterior y nueva).
- **CA-6.6 (Ubicuo):** El sistema DEBE conservar el identificador y el historial de la sesión tras reprogramarla (no se crea una sesión nueva).
- **CA-6.7 (No deseado):** SI se intenta reprogramar con menos de 2 horas de antelación a la hora de inicio programada de la sesión, ENTONCES el sistema DEBE rechazarlo e informar de la antelación mínima (la cancelación con menos de 2 h sí se admite, marcada como tardía — CA-7.2).
- **CA-6.8 (No deseado):** SI la sesión está `in_progress` o su hora de inicio ya pasó, ENTONCES el sistema DEBE **rechazar cualquier reprogramación** e informar de que, si procede, la sesión debe cancelarse (RF-7); la reprogramación solo es posible antes de su inicio (decisión D10, §10; elimina la contradicción de la v0.2.0 entre CA-6.1 y CA-6.7).

### RF-7 — Cancelación con trazabilidad y política de antelación

El sistema DEBE permitir cancelar una sesión `scheduled` o `in_progress` dejando registro del autor, el momento y el motivo, aplicando la política de antelación (antelación mínima de 2 horas; la cancelación tardía se admite y queda marcada) y liberando el hueco.

**Por qué:** cancelar sin registro rompe P5; cancelar sin reglas provoca abusos y huecos perdidos; el hueco debe liberarse para que RF-1 vuelva a permitir reservarlo (P8).

**Criterios de aceptación:**

- **CA-7.1 (Evento):** CUANDO un usuario autorizado cancela una sesión, el sistema DEBE registrar quién, cuándo y por qué, y notificarlo conforme a **CA-5.7**.
- **CA-7.2 (No deseado):** SI una sesión se cancela con menos de 2 horas de antelación a su hora de inicio programada **(o después de haberla iniciado)**, ENTONCES el sistema DEBE aceptar la cancelación y registrarla como **«tardía»** (autor, momento, motivo y antelación real) como dato de trazabilidad, sin penalización económica en v1, y notificar a los participantes que la cancelación fue tardía (decisión D2, §10); la cancelación NO DEBE bloquearse por llegar tarde.
- **CA-7.3 (Estado):** MIENTRAS una sesión está `canceled`, el sistema DEBE excluirla de solapes, de límites mensuales, de recordatorios y de **todo registro o confirmación de asistencia**, conservándola para consulta; una sesión `in_progress` **sí puede cancelarse** con los mismos requisitos de autorización, motivo y trazabilidad (CA-7.1), y su cancelación impide toda confirmación de asistencia posterior (la sesión nunca contará como realizada).
- **CA-7.4 (Evento):** CUANDO se cancela una sesión, el sistema DEBE conservar en MIS el valor persistido de `meet_link` como dato histórico, pero el enlace DEBE quedar inactivo en MIS: mientras `status=canceled`, el API/recurso NO DEBE devolver su valor (puede omitir el campo o devolverlo nulo). Si la sesión tiene un evento Meet asociado, el sistema DEBE solicitar su eliminación al proveedor externo; este efecto pertenece al ciclo Meet de RF-9 y es **best-effort**. SI la eliminación externa falla, ENTONCES el sistema DEBE registrar el fallo, mantener el estado cancelado y la ocultación del enlace sin reactivarlo ni exponerlo, y notificar el resultado **sin bloquear ni revertir la cancelación** (P1). El registro y el evento de cancelación DEBEN limitarse a los datos mínimos necesarios para auditoría y aviso —referencia de sesión, autor, momento, motivo y datos de tardanza cuando correspondan— y NO DEBEN incluir `meet_link`; ningún contrato vigente de cancelación requiere ese valor.
- **CA-7.5 (No deseado):** SI se intenta cancelar una sesión ya `completed`, ya `canceled` o «no completada», ENTONCES el sistema DEBE rechazarlo informando del estado terminal.
- **CA-7.6 (Ubicuo):** El sistema DEBE conservar la sesión cancelada y su historial; eliminar físicamente solo por operación administrativa excepcional y auditada.

### RF-8 — Registro de asistencia (alimenta la regla de sesiones realizadas)

El sistema DEBE registrar, por participante, si asistió a la sesión («asistió», «no asistió» o «no confirmada»), quién lo confirmó y cuándo; DEBE permitir que **cada participe confirme la suya propia** desde la hora de fin hasta 48 h después; DEBE permitir al **responsable de un colectivo** confirmar la de los miembros a su cargo (con la confirmación propia del miembro siempre prevalente) y DEBE **prohibir al mentor confirmar por otros**. El cierre de la sesión (`completed`) es una acción manual del mentor (RF-11), independiente de este registro.

**Por qué:** decisión §3.1.3 (vinculante): una sesión cuenta como realizada **solo** si está `completed` **y** su asistencia está confirmada. Sin este registro, el panel de progreso (spec 001, RF-5) no puede distinguir lo hecho de lo previsto (P6).

**Criterios de aceptación:**

- **CA-8.1 (Evento):** CUANDO ha pasado la hora de **finalización** programada de la sesión, el sistema DEBE permitir que cada participante confirme **su propia** asistencia («asistió» o «no asistió») durante las 48 h siguientes (CA-8.8), dejando constancia de quién lo confirmó y cuándo; el **responsable de un colectivo** (cabeza de familia, coordinador de cohorte o admin de empresa; reutilizando la autorización de responsabilidad de la spec 001, §3.1 D10) DEBE poder confirmar también la de los **miembros a su cargo** **mientras ese miembro no lo haya confirmado él mismo**; **el mentor NUNCA confirma asistencia por otros** (solo la suya propia) — decisión D8, §10. **«Miembros a su cargo» según el tipo de colectivo:** en una **familia**, la cabeza de familia sobre sus **menores**; en una **empresa**, el `company_admin` sobre sus **empleados**; en una **cohorte**, el coordinador **NO** confirma por los miembros **adultos** (cada cual confirma la suya propia), salvo por los **menores** de la cohorte.
- **CA-8.2 (No deseado):** SI se intenta registrar o confirmar asistencia **antes de la hora de fin programada**, en una sesión `canceled` o «no completada», o una vez cerrada la ventana de 48 h, ENTONCES el sistema DEBE rechazarlo (CA-8.8, CA-11.6).
- **CA-8.3 (Evento):** CUANDO el mentor marca una sesión como `completed` (tras su celebración), el sistema DEBE asegurar que **cada participante tenga su registro de asistencia con estado inicial «no confirmada»** —nunca «asistió» asumido—, dejando constancia de quién y cuándo; **«cada participante» se ancla a la hora de fin programada: los miembros activos del colectivo en ese instante (más el mentor), de modo que una baja posterior no borra registros ya creados**; las confirmaciones de los participantes se admiten dentro de la ventana de 48 h desde la hora de fin (CA-8.8), **también cuando la sesión se completó tarde**.
- **CA-8.4 (Estado):** MIENTRAS la asistencia de un participante no esté confirmada como «asistió», esa sesión NO DEBE contar como realizada para ese participante en los indicadores de progreso (§3.1.3, spec 001 RF-5).
- **CA-8.5 (Ubicuo):** El sistema DEBE registrar por separado la asistencia de **cada** participante (en una sesión grupal unos asisten y otros no).
- **CA-8.6 (No deseado):** SI el responsable de un colectivo confirma la asistencia de un miembro y **después el propio miembro confirma la suya**, ENTONCES el sistema DEBE **prevalecer la confirmación del miembro** (no «la última que llegue»); SI el responsable confirma después de que el miembro ya hubiera confirmado, ENTONCES su confirmación NO DEBE sustituir la del miembro. En ambos casos **ambos registros quedan conservados con su autor y momento** (auditoría, P5); el mentor nunca genera esta clase de conflictos porque no confirma por otros (CA-8.1).
- **CA-8.7 (Ubicuo):** El sistema DEBE mostrar a cada participante su propia asistencia, y al mentor y al responsable del colectivo la de los participantes de sus sesiones (—incluido el estado «no confirmada»—), sin exponer datos ajenos a quienes no participan.
- **CA-8.8 (No deseado):** SI pasan 48 h desde la hora de finalización programada de la sesión sin que un participante haya confirmado su asistencia, ENTONCES el sistema DEBE mantenerla fijada como **«no confirmada»**, hacerla visible para las partes correspondientes (participante, mentor y responsable si es colectiva), **cerrar la ventana de confirmación** de esa sesión (ni asistencia ni completación se admiten después — CA-8.2, CA-11.6) y **NO DEBE inferir asistencia por silencio** (P6; §3.1.3: solo cuenta como realizada con asistencia confirmada).
- **CA-8.9 (Ubicuo):** El sistema DEBE mantener la **equivalencia semántica con el indicador de asistencia existente** que consume la spec 001: «asistió» equivale a su booleano de asistencia en verdadero; «no asistió» y «no confirmada» equivalen a falso; ningún estado nuevo altera el significado de ese dato ni los indicadores ya publicados (RNF-13).

### RF-9 — Generación automática del enlace de Google Meet

El sistema DEBE crear (y mantener) el enlace de videollamada de una sesión cuando esta queda confirmada, de forma automática, idempotente y sin bloquear la operación de agenda si el proveedor externo falla.

**Por qué:** el usuario pide que el enlace se genere «cuando corresponda» y no a mano; pero Google es un servicio externo (P17) y su fallo no puede impedir reservar (P1, P2).

**Criterios de aceptación:**

- **CA-9.1 (Evento):** CUANDO una sesión queda confirmada —estado `scheduled` con fecha futura, ya sea al crearse o al reprogramarse—, el sistema DEBE generar su enlace de Meet automáticamente (decisión D4, §10). Esto incluye a las **sesiones `scheduled` futuras sin enlace existentes al activarse esta funcionalidad**: entran en el mismo proceso de generación y reintento (las celebradas o canceladas no se reescriben).
- **CA-9.2 (No deseado):** SI la generación automática falla (credenciales ausentes, timeout, error del proveedor), ENTONCES el sistema DEBE completar la reserva o la reprogramación igualmente **sin enlace**, DEBE registrar el fallo y **reintentarlo automáticamente —responsabilidad del sistema (tarea programada), no del usuario—, con una cadencia fija y de forma periódica mientras la sesión siga `scheduled`, sin enlace y con hora futura**; agotados los reintentos automáticos o llegada la hora de inicio, queda disponible la **generación manual** posterior (responsable: el usuario). El fallo NO DEBE revertir la operación de agenda (P14/P17).
- **CA-9.3 (No deseado):** SI se reintenta la generación para una sesión que ya tiene enlace (automática o manualmente), ENTONCES el sistema DEBE devolver el enlace existente sin crear un segundo evento (P14).
- **CA-9.4 (Evento):** CUANDO se reprograma una sesión con evento asociado, el sistema DEBE actualizar el evento externo con la nueva fecha, duración **y zona horaria** (CA-9.7), o recrearlo dejando uno solo activo.
- **CA-9.5 (Ubicuo):** El sistema DEBE exponer a los participantes el enlace de Meet de su sesión cuando exista y la sesión no esté `canceled`, de forma que el recordatorio (CA-5.5), el calendario (RF-4) y el detalle de la sesión lo muestren siempre igual. La cancelación aplica la ocultación de CA-7.4 con independencia de que la eliminación externa haya tenido éxito.
- **CA-9.6 (Estado):** MIENTRAS una sesión no tiene enlace, el sistema DEBE mostrarlo como «pendiente de enlace» y no como un enlace vacío o roto.
- **CA-9.7 (Ubicuo):** El sistema DEBE crear y actualizar el evento externo **con la fecha-hora local de la sesión y su zona horaria** (CA-3.1), **nunca con una zona horaria fija**, de modo que el evento aparezca a la hora local correcta en el calendario externo (RF-3, P8); y DEBE crearlo **sin añadir a los participantes como invitados** del proveedor en v1 (la notificación de la sesión la hace MIS — P4; §8).

### RF-10 — Autorización de la agenda

El sistema DEBE validar identidad, rol, permisos y pertenencia en cada operación de la agenda, reutilizando la autorización existente de relaciones, sesiones y colectivos.

**Por qué:** P3; y no duplicar lógica de pertenencia (la de `MentorshipRelationship`, la política de sesiones y la autorización de colectivos ya existentes). Quién puede hacer qué es una decisión de producto, ya resuelta (D2 y D9, §10): **ambas partes gestionan** sus sesiones y el **responsable de colectivo** gestiona las de su colectivo.

**Criterios de aceptación:**

- **CA-10.1 (Ubicuo):** El sistema DEBE permitir **consultar** una sesión o el calendario solo a quien participa en ella, a su responsable de colectivo (CA-4.7) o a un admin con permiso explícito.
- **CA-10.2 (Ubicuo):** El sistema DEBE permitir **crear** sesiones en una relación al mentor **y al mentee** de esa relación, **y al responsable del colectivo cuando la relación recae sobre su colectivo**, y a los perfiles con permiso administrativo equivalente, validándolo en el servidor (decisión D2 y D9, §10).
- **CA-10.3 (Ubicuo):** El sistema DEBE permitir **reprogramar y cancelar** a ambas partes de la relación (mentor y mentee), **al responsable del colectivo** y a los perfiles con permiso administrativo, sujetándolo a la política de antelación de 2 h (CA-6.7, CA-7.2) y a las restricciones de estado (CA-6.8), denegando en el servidor al resto.
- **CA-10.4 (Ubicuo):** El sistema DEBE permitir **definir, modificar y eliminar la disponibilidad únicamente al propio mentor, sobre su propia agenda**; ningún tercero la modifica (los demás usuarios solo pueden consultar los huecos libres resultantes — CA-2.6).
- **CA-10.5 (No deseado):** SI un usuario sin pertenencia a la sesión solicita o modifica una sesión ajena **manipulando identificadores**, ENTONCES el sistema DEBE responder **como si el recurso no existiera** (sin revelar su existencia ni sus datos — P3); SI el usuario sí conoce el recurso (participa o es su responsable) pero la operación concreta no le está permitida, ENTONCES el sistema DEBE responder denegado (**403**) con motivo claro.
- **CA-10.6 (Ubicuo):** El sistema DEBE registrar en auditoría las operaciones relevantes de agenda realizadas por perfiles administrativos **y las que un responsable de colectivo realiza sobre participantes que no son él mismo** (p. ej. confirmar asistencia de sus miembros) (P5).
- **CA-10.7 (Ubicuo):** SI un usuario es el **responsable autorizado de un colectivo** (según la definición de la spec 001, §3.1 D10, con la autorización de colectivos ya existente), ENTONCES el sistema DEBE permitirle **consultar la agenda de ese colectivo** (CA-4.7) y **programar, reprogramar y cancelar sesiones del mismo** (CA-10.2, CA-10.3), validándolo en el servidor; el resto de miembros NO DEBE tener ese acceso por el solo hecho de pertenecer (decisión D9, §10).

### RF-11 — Estados de sesión consistentes, idempotencia y bloqueo de duplicados

El sistema DEBE gestionar las sesiones mediante un conjunto explícito de estados con transiciones permitidas y **con un disparador definido para cada transición**, y DEBE hacer idempotentes las operaciones de creación y de cambio de estado.

**Por qué:** P8 (estados explícitos, reintentos sin duplicados) y P14 (una misma operación lógica no produce efectos duplicados). La verificación de P8 pide literalmente pruebas de solapamiento, concurrencia, cambios de horario e idempotencia. Y sin un cierre trazable, una sesión que el mentor nunca completa queda «en el aire» indefinidamente (decisión D6, §10).

**Criterios de aceptación:**

- **CA-11.1 (Ubicuo):** El sistema DEBE admitir únicamente las siguientes transiciones, con su disparador:
  - `scheduled → in_progress`: **automática, del sistema**, a la hora de inicio programada de la sesión.
  - `in_progress → completed`: **manual y exclusiva del mentor** de la relación, que con ella atestigua que la sesión se celebró (P6); solo admisible **una vez pasada la hora de fin programada**.
  - `scheduled → canceled` e `in_progress → canceled`: **manual**, por los usuarios autorizados (CA-10.3), con motivo y trazabilidad (RF-7).
  - `scheduled → «no completada»` e `in_progress → «no completada»`: **automática, del sistema**, a las 48 h de la hora de fin sin que el mentor la haya completado (CA-11.6).
  - `completed`, `canceled` y «no completada» son **terminales** (no se reabren ni se reprograman).
  - **Robustez temporal:** SI al momento de completar la sesión su hora de inicio ya pasó pero constara aún `scheduled` (p. ej. por una incidencia que impidió la transición automática), ENTONCES el sistema DEBE promoverla primero a `in_progress` y después permitir el cierre: la secuencia temporal no se salta.
- **CA-11.2 (No deseado):** SI se solicita una transición no permitida (p. ej. `canceled → scheduled` o `in_progress → scheduled`), ENTONCES el sistema DEBE rechazarla e informar del estado actual.
- **CA-11.3 (No deseado):** SI dos peticiones simultáneas intentan crear la misma sesión, o cambiar el estado de una misma sesión, ENTONCES el sistema DEBE aplicar solo la primera y no debe producirse ni una sesión duplicada ni un estado contradictorio (concurrencia).
- **CA-11.4 (No deseado):** SI una misma solicitud de creación se reintenta (doble clic, timeout, reenvío), ENTONCES el sistema DEBE devolver la sesión existente en lugar de crear otra (clave de idempotencia).
- **CA-11.5 (Ubicuo):** El sistema DEBE mantener la coherencia entre estado, calendario, recordatorios y evento de Meet en cada transición (un solo efecto por evento).
- **CA-11.6 (Evento):** CUANDO pasan 48 h desde la hora de fin programada de una sesión no cancelada sin que el mentor la haya marcado `completed`, el sistema DEBE dejarla en el estado **«no completada»**, **visible para ambas partes** (participantes y mentor; y el responsable si es colectiva). Ese estado es terminal: no admite completación ni confirmación de asistencia posteriores (CA-8.2, CA-7.5), **no cuenta como sesión realizada** (§3.1.3, CA-8.4) y no reescribe registros anteriores (P5/P6). Su representación interna (estado almacenado o derivado) es decisión de diseño posterior; lo vinculante es este comportamiento visible (decisión D6, §10).

### RF-12 — Idioma y accesibilidad de la agenda

El sistema DEBE presentar toda la agenda en el idioma activo (es/en/pt) y DEBE ser usable con teclado y tecnologías de asistencia, en móvil y escritorio.

**Por qué:** P16 y convención i18n del proyecto; el calendario es una interfaz densa donde la accesibilidad es fácil de perder.

**Criterios de aceptación:**

- **CA-12.1 (Ubicuo):** El sistema DEBE mostrar en es/en/pt los textos, estados, motivos de rechazo y notificaciones de la agenda (los mensajes generados por el sistema, p. ej. motivos de validación, en los 3 idiomas), incluidas las etiquetas de los estados de sesión y de asistencia («asistió», «no asistió», «no confirmada», «no completada»).
- **CA-12.2 (No deseado):** SI falta una traducción, ENTONCES el sistema DEBE recurrir al idioma por defecto (es) sin mostrar claves crudas.
- **CA-12.3 (Ubicuo):** El sistema DEBE permitir programar, reprogramar, cancelar, marcar asistencia y navegar el calendario **solo con teclado**, con foco visible y objetivos táctiles suficientes.
- **CA-12.4 (Ubicuo):** El sistema DEBE comunicar los errores de validación junto al campo afectado y en una región anunciable (P16: «los formularios DEBEN validar los datos y comunicar los errores de forma clara»).

### RF-13 — Participación en sesiones colectivas

En las sesiones de tipo familiar, grupal o empresarial, el sistema DEBE considerar participantes a **todos los miembros activos del colectivo** (grupo familiar, cohorte o empresa) **en el momento de evaluarlos**, junto con el mentor de la relación, y DEBE aplicar las altas y bajas de membresía de forma **prospectiva**, sin reescribir sesiones ya celebradas.

**Por qué:** en una sesión familiar, grupal o empresarial no participa «un asistente» aislado: participa el colectivo. Sin definir esto, los recordatorios, la confirmación de asistencia y la comprobación de solapes no saben a quién afectan (decisión D7, §10; regla de módulo §3: «toda sesión DEBE identificar participantes»).

**Criterios de aceptación:**

- **CA-13.1 (Evento):** CUANDO se programa una sesión colectiva, el sistema DEBE inscribir como participantes a **todos los miembros activos del colectivo en ese momento** (más el mentor de la relación).
- **CA-13.2 (Evento):** CUANDO un miembro **se da de alta** en el colectivo después de programada la sesión y **antes de celebrarse**, el sistema DEBE incluirlo, **desde ese momento**, en los recordatorios pendientes de la fecha vigente (CA-5.1, CA-5.8) y DEBE permitirle confirmar su asistencia cuando se abra su ventana (CA-8.1) —**salvo** si ya tiene una sesión propia que solape esa sesión colectiva, en cuyo caso se aplica el caso límite 41 (no participa de esa sesión): alta válida, sin revalidación retroactiva—.
- **CA-13.3 (Evento):** CUANDO un miembro **deja el colectivo**, el sistema DEBE dejar de enviarle recordatorios y de permitirle confirmar asistencia **desde ese momento**; sus registros de asistencia ya hechos se conservan íntegramente (P5) y su pertenencia pasada a sesiones celebradas no se reescribe. **Definición anclada:** los «participantes registrados» de una sesión colectiva son los **miembros activos del colectivo en su hora de fin programada** (más el mentor); una baja posterior **no borra registros ya creados** ni altera esa participación registrada.
- **CA-13.4 (Estado):** MIENTRAS una sesión colectiva ya se ha celebrado (llegó su hora de fin), los cambios posteriores de membresía **NO DEBEN alterar** sus participantes registrados ni sus registros de asistencia: la baja de un miembro no borra su asistencia y el alta de otro no lo añade a una sesión pasada.
- **CA-13.5 (No deseado):** SI **cualquier miembro activo** del colectivo tiene otra sesión no cancelada cuyo intervalo se solapa con el hueco propuesto, ENTONCES el sistema DEBE **rechazar el hueco para todo el colectivo** (consistencia con CA-1.3; decisión D7, §10), indicando el conflicto.

---

## 6. Requisitos no funcionales (RNF)

- **RNF-1 (Autorización — P3):** toda operación de la agenda se autoriza en el servidor (crear, reprogramar, cancelar, asistencia, disponibilidad, consulta de calendario, agenda del responsable de colectivo). Ocultar elementos en el frontend no constituye autorización.
- **RNF-2 (Trazabilidad — P5):** creaciones, reprogramaciones, cancelaciones (incluida la marcación de «tardía») y registros de asistencia conservan autor, momento y valores anterior/nuevo; las confirmaciones de asistencia sustituidas se conservan; no se alteran mediante operaciones ordinarias de negocio ni se almacenan secretos.
- **RNF-3 (Idempotencia — P8/P14):** reintentos de creación y generación de Meet no producen efectos duplicados; para recordatorios y avisos de cambio, los reintentos no producen más de un elemento lógico visible por identificador estable en el destino. Un resultado ambiguo puede causar publicaciones repetidas por transporte y no se considera una entrega confirmada; se reintenta con el mismo identificador, y solo la confirmación del proveedor marca el envío como entregado (CA-5.2/5.6). Las automatizaciones toleran fallos con reintentos controlados.
- **RNF-4 (Consistencia de estados — P8):** los estados y transiciones de sesión son únicos, validados en el servidor y con disparador definido (RF-11); agenda, recordatorios, Meet e indicadores se derivan del mismo estado.
- **RNF-5 (Contratos de API — P17):** entradas y salidas de la agenda están validadas; los errores no exponen trazas ni datos internos; la integración con Google declara timeouts y maneja errores del proveedor sin tumbar la operación de negocio.
- **RNF-6 (Accesibilidad — P16):** navegación por teclado, foco visible, contraste suficiente, estados comunicados por texto además de color, mensajes de error anunciables.
- **RNF-7 (Responsive):** calendario y formularios de agenda se usan bien en móvil.
- **RNF-8 (i18n):** textos y motivos de error en es/en/pt con el mecanismo de traducciones existente.
- **RNF-9 (Privacidad — P4):** la agenda solo expone sesiones a sus participantes, a su responsable de colectivo y a perfiles con permiso explícito; el detalle y los motivos de las excepciones de disponibilidad no se exponen a terceros (CA-2.6); los recordatorios y notificaciones no incluyen datos innecesarios.
- **RNF-10 (Observabilidad — P20):** los fallos de recordatorios y de la integración con Google generan registros estructurados y útiles, sin secretos ni datos personales innecesarios, y son diagnosticables (incluidos los reintentos agotados de Meet — CA-9.2).
- **RNF-11 (Calidad demostrable — P19):** pruebas automatizadas de solapamiento, disponibilidad (incluida la precedencia de excepciones), duplicados, concurrencia, transiciones de estado (incluidas las automáticas y el estado «no completada»), reprogramación/cancelación con trazabilidad y antelación mínima de 2 h (incluida la cancelación tardía), asistencia con plazo de 48 h y precedencia miembro/responsable, participación colectiva (altas y bajas) y permisos (permitido y denegado), citadas expresamente por la verificación de P8.
- **RNF-12 (Rendimiento):** la vista de calendario carga su contenido en **2 segundos o menos (percentil 95) en condiciones normales de uso**, sin recomputar datos idénticos de forma redundante.
- **RNF-13 (Coherencia con la spec 001):** el registro de asistencia es compatible con la regla §3.1.3: sin asistencia confirmada no hay «sesión realizada», y los cambios aquí no alteran retroactivamente indicadores ya publicados. **La fecha de referencia para contabilizar una sesión realizada en un mes es la de su fecha de inicio, interpretada en la zona del sujeto** —igual que el **cálculo ya implementado** en la spec 001 (esta regla procede de ese cálculo, no del texto literal de esa spec)—, con independencia de cuándo se marque `completed` o se confirme la asistencia; «asistió» equivale al booleano de asistencia existente (CA-8.9).

---

## 7. Casos límite

1. **Solape parcial:** dos sesiones que se intersectan en un solo minuto → rechazo (RF-1); el intervalo es [inicio, fin), una sesión que termina justo cuando otra empieza **no** es solape.
2. **Mismo participante en dos relaciones:** un mentee con dos mentores → también se comprueba su **ocupación** (que sus sesiones no canceladas no se solapan, CA-1.2/CA-1.3); **no** se le aplican ventanas de disponibilidad (solo los mentores las declaran — §8, CA-2.4).
3. **Sesión colectiva con algún miembro ocupado:** si **cualquier** miembro activo del colectivo tiene otra sesión que solapa el hueco, la reserva se rechaza **para todo el colectivo** (RF-13, CA-13.5, CA-1.3). No existe reserva «por asistentes» parcial.
4. **Doble clic / reintento de creación:** se devuelve la sesión existente, nunca dos (CA-1.4, CA-11.4).
5. **Dos peticiones concurrentes:** solo una gana; no hay sesión duplicada ni estado contradictorio (CA-11.3).
6. **Mentor sin disponibilidad definida:** no se aplica el filtro de ventanas, pero sí el de solapes (CA-2.4); las excepciones bloqueantes que el mentor sí tenga definidas siguen aplicando y rechazan la reserva en sus fechas (CA-2.4, CA-2.7).
7. **Excepción sobre día ya ocupado:** no se borran las sesiones existentes; solo se bloquean nuevas (CA-2.5); y **la excepción gana sobre la ventana habitual** (CA-2.7).
8. **Reprogramación que choca:** se rechaza y la sesión queda intacta con su fecha original (CA-6.2).
9. **Reprogramar o cancelar una sesión terminal** (`completed`, `canceled` o «no completada»): rechazado con motivo (CA-6.3, CA-7.5).
10. **Cancelación tardía (menos de 2 h o después del inicio):** se acepta y queda marcada como «tardía» con su antelación real, sin penalización económica en v1; los recordatorios ya enviados no se recuperan (CA-7.2).
11. **Reprogramación con menos de 2 h de antelación:** se rechaza informando de la antelación mínima (CA-6.7).
12. **El mentee crea una sesión en su relación:** se admite con exactamente las mismas validaciones que si la crea el mentor (solape, disponibilidad, duplicados, límite del plan: CA-10.2, CA-1.2–CA-1.6).
13. **Fallo de Google al crear o regenerar Meet:** la sesión o la reprogramación se completa igualmente sin enlace; estado «pendiente», reintentos automáticos del sistema hasta la hora de inicio y, después, reintento manual; sin bloquear (CA-9.2).
14. **Reintento de generación de Meet:** devuelve el enlace existente; un solo evento externo (CA-9.3).
15. **Cancelación con fallo de Google:** la cancelación se persiste primero (P1); el evento externo **no se pudo eliminar**: se registra el fallo, el valor histórico de `meet_link` se conserva en MIS pero no se devuelve por API/recurso, no se reactiva ni se expone y no se revierte la cancelación (CA-7.4).
16. **Recordatorio tras reprogramar:** los avisos de la fecha vieja no se envían; se recalculan con la fecha nueva y la clave de dedupe considera la fecha vigente (CA-5.2, CA-5.3).
17. **Recordatorio de sesión cancelada:** no se envía; en su lugar se notifica la cancelación (CA-5.4, CA-5.7).
18. **Reprogramación/cancelación sin recordatorio pendiente en el horizonte:** el aviso único del cambio se envía igualmente a todos los participantes vigentes (CA-5.7).
19. **Sesión `completed` con asistencia aún sin confirmar:** al cerrarla, cada participante tiene su registro en **«no confirmada»** (nunca «asistió» asumido); las confirmaciones se admiten hasta las 48 h desde la hora de fin (CA-8.3, CA-8.8).
20. **Nadie confirma en 48 h:** la asistencia queda «no confirmada», visible para las partes, con la ventana cerrada; nunca se infiere por silencio y la sesión no cuenta como realizada (CA-8.8, CA-8.4, §3.1.3).
21. **Sesión `completed` sin asistencia registrada (dato heredado):** no cuenta como realizada (CA-8.4).
22. **Asistencias contradictorias (responsable y miembro):** **prevalece la del propio miembro** sobre la que haya confirmado el responsable por él (aunque llegue después); ambos registros quedan conservados con autor y momento en auditoría; el mentor nunca confirma por otros (CA-8.6, CA-8.1).
23. **Sesión grupal con asistencia mixta:** cada participante cuenta por sí mismo (CA-8.5).
24. **Responsable de colectivo:** confirma la asistencia de los **miembros a su cargo según el tipo de colectivo** —familia: la cabeza de familia sobre sus **menores**; empresa: el `company_admin` sobre sus **empleados**; cohorte: el coordinador **no** confirma por los miembros **adultos** (cada cual confirma la suya), salvo por los **menores** de la cohorte—, y **solo mientras ellos no la hayan confirmado**; el resto de participantes confirma la suya propia (CA-8.1).
25. **Usuario sin zona horaria:** se usa la zona por defecto sin fallos (CA-3.3).
26. **Sesión creada en una zona distinta a la del participante que la ve:** se muestra convertida a la zona del espectador y se indica la zona de la sesión (CA-3.2, CA-3.6); el evento externo se crea con esa misma zona, no con una fija (CA-9.7).
27. **Sesión que cruza la medianoche o el cambio de horario local:** el instante absoluto se conserva; solo un cambio explícito la mueve (CA-3.4, CA-3.5).
28. **Calendario vacío:** estado vacío guiado, no pantalla en blanco (CA-4.4).
29. **Proveedor de Meet no configurado (sin credenciales):** la agenda funciona; Meet queda como pendiente con motivo, reintentos registrados y generación manual disponible (CA-9.2).
30. **Relación de mentoría pausada o finalizada:** no admite nuevas sesiones (comportamiento existente) y la agenda existente se conserva en consulta.
31. **Participante sin permiso:** acceso denegado en el servidor sin filtrar datos de la sesión; para quien no participa ni gestiona, la respuesta es idéntica a la de un recurso inexistente (CA-10.5).
32. **Datos heredados:** sesiones creadas antes de esta spec (sin zona o sin asistencia) se muestran con la zona por defecto y asistencia «no confirmada», sin romper el panel de progreso (RNF-13); las `scheduled` futuras sin enlace entran en la generación automática al activarse (CA-9.1).
33. **Sesión que el mentor no cierra en 48 h:** queda **«no completada»**, visible para ambas partes, terminal; no admite completación ni confirmación de asistencia posteriores y no cuenta como realizada (CA-11.6).
34. **Sesión creada con menos de 24 h de antelación:** se envía de inmediato un único recordatorio con la fecha vigente y los horizontes aún por venir (2 h, 1 h) a su hora; ninguno se entrega dos veces (CA-5.8, CA-5.2).
35. **Miembro que se da de baja tras confirmar su asistencia:** su registro se conserva íntegro; la baja no lo borra ni altera la sesión celebrada (CA-13.3, CA-13.4).
36. **Reprogramación hacia un mes donde el plan ya alcanzó su límite mensual:** rechazada en la revalidación completa (CA-6.2).
37. **Usuario externo a la sesión consulta o manipula su identificador:** respuesta idéntica a recurso inexistente, sin revelar datos (CA-10.5).
38. **Excepción de disponibilidad sobre una ventana habitual:** la excepción gana; la reserva en esa fecha/hora se rechaza aunque la ventana la cubra (CA-2.7).
39. **Miembro nuevo en el colectivo poco antes de la sesión:** desde su alta recibe los recordatorios pendientes de la fecha vigente (o el aviso inmediato si ya vencieron los horizontes) y podrá confirmar su asistencia (CA-13.2, CA-5.8).
40. **Reprogramación que cambia la zona horaria:** se admite solo para usuarios autorizados, queda registrada con la zona anterior y la nueva, y el evento externo se actualiza con la zona nueva (CA-3.7, CA-6.1, CA-9.4).
41. **Alta de un miembro que ya tiene una sesión propia solapando una sesión colectiva existente:** el alta en el colectivo es **válida** y tiene efecto **prospectivo** (CA-13.2), pero **no se revalida retroactivamente** la sesión colectiva ya programada: esta **no se revalida ni se cancela** (coherente con RF-13 y con CA-13.2/CA-13.5, que evalúan el hueco al programar, no al alta). Ese miembro queda como **no participante de ESA sesión**: **no recibe sus recordatorios ni confirma su asistencia** de ella; su propia sesión no cancelada se mantiene y la sesión colectiva no se ve afectada (CA-1.3 no se aplica de forma retroactiva).
42. **Eliminación de Meet de una sesión cancelada:** tanto si Google confirma como si falla la eliminación, MIS conserva el valor histórico de `meet_link`, pero no lo devuelve en ningún API/recurso mientras la sesión esté `canceled`; el fallo queda registrado y no reactiva ni revela el enlace. El evento de cancelación contiene solo datos mínimos de auditoría/aviso y no incluye el enlace (CA-7.4, CA-9.5).
43. **Publicación de aviso con resultado ambiguo:** el proveedor pudo aceptar una notificación, pero el proceso no confirmó el estado de entrega antes de ser reintentado. El reintento conserva el mismo identificador estable y el destino actualiza/reemplaza el aviso lógico existente; la persona ve un solo aviso, aunque el transporte haya publicado más de una vez. El aviso solo se marca como entregado tras confirmación del proveedor (CA-5.2, CA-5.6).

---

## 8. Fuera de alcance (v1)

No forma parte de esta versión:

- **Sincronización bidireccional con calendarios externos:** no se importan eventos de Google Calendar ni de otros calendarios de terceros; solo se crean/actualizan/eliminan los eventos propios de MIS (la agenda de MIS no se puebla con citas externas).
- **Sesiones recurrentes / series:** sin plantillas periódicas ni «cada martes»; cada sesión se crea de forma explícita.
- **Disponibilidad del mentee:** solo los mentores declaran ventanas en v1; la ocupación del mentee se deduce de sus propias sesiones (RF-1).
- **Penalizaciones por cancelación tardía:** la cancelación con menos de 2 h de antelación (o tras el inicio) se admite y se marca como «tardía» solo como dato de trazabilidad; sin cargos ni consecuencias económicas en v1 (los pagos quedan fuera).
- **Canales de aviso adicionales:** sin SMS, WhatsApp ni notificaciones push móviles nuevas; se usan los canales de notificación existentes.
- **Otros proveedores de videollamada:** sin Zoom, Teams ni salas físicas; solo el proveedor ya integrado.
- **Invitados en el evento externo:** los participantes **no** se añaden como invitados del proveedor de calendario; la notificación de la sesión la hace MIS (CA-9.7), evitando compartir correos con el proveedor (P4).
- **Auto-cancelación por ausencia (no-show automático):** la asistencia la registran personas; el sistema no cancela sesiones por inasistencia detectada ni registra ausencias automáticas. El estado «no completada» (CA-11.6) refleja la **falta de cierre por el mentor** en el plazo, no una inasistencia detectada.
- **Sugerencia automática de huecos / matchmaking de agenda:** sin algoritmo que proponga horarios; la comprobación es de validación (y la consulta de huecos libres, CA-2.6), no de recomendación.
- **Gestión de varias zonas por organización:** sin configuración de zona a nivel de empresa/colectivo (la zona es de la sesión y del usuario).

---

## 9. Criterios de finalización

La funcionalidad se considera terminada cuando:

- [ ] **Cumple los principios aplicables** de `docs/constitution.md`, en especial P3, P5, P6, P8, P14, P16, P17, P19 y P20, y la condición de módulo §3 «Se controlan disponibilidad, estados, zona horaria y duplicidades».
- [ ] **Respeta la decisión §3.1.3:** la asistencia registrada aquí es la que permite contar sesiones como realizadas en la spec 001, con equivalencia semántica al booleano de asistencia existente.
- [ ] **Gestiona el ciclo de vida completo de estados** con disparador definido: `in_progress` automático a la hora de inicio, `completed` solo manual del mentor tras la sesión, cancelación por autorizados, y «no completada» a las 48 h de la hora de fin, visible para ambas partes y terminal (RF-11).
- [ ] **Valida solapamientos y duplicidades** al programar y reprogramar (por participante, con título normalizado), rechazando el hueco para todo el colectivo si algún miembro choca, con pruebas de concurrencia e idempotencia.
- [ ] **Comprueba la disponibilidad del mentor** en la creación y la reprogramación (sesión completa dentro de ventana, con precedencia de la excepción y comportamiento explícito cuando no hay ventanas definidas).
- [ ] **Gestiona la participación colectiva dinámica:** todos los miembros activos del colectivo, con altas y bajas prospectivas que no reescriben sesiones celebradas (RF-13).
- [ ] **Identifica y muestra la zona horaria** de cada sesión: elegida por quien la crea (por defecto la suya), cambiada solo por usuarios autorizados con registro, mostrada convertida a la zona de cada participante y **heredada por el evento externo**.
- [ ] **Ofrece vista de calendario** (día, semana y mes) alimentada por las sesiones de la API —incluidas las del colectivo para su responsable—, con estados distinguibles, vacío guiado y errores recuperables.
- [ ] **Mantiene recordatorios fiables** con la cadencia 24 h / 2 h / 1 h, deduplicados por sesión+usuario+horizonte+fecha vigente (intentado ≠ entregado), cubriendo las sesiones creadas con poca antelación, recalculados ante reprogramaciones, retirados ante cancelaciones y con **un único aviso de cambio** por reprogramación/cancelación, idempotentes y con fallos registrados.
- [ ] **Registra reprogramaciones y cancelaciones** con autor, momento, valores anterior/nuevo y motivo: solo sesiones `scheduled` futuras se reprograman (con revalidación completa), las `in_progress` o pasadas no; la antelación mínima de 2 h se aplica (reprogramación rechazada sin ella; cancelación tardía marcada) y el evento externo se elimina (o su fallo queda registrado sin bloquear).
- [ ] **Registra asistencia por participante** («asistió»/«no asistió»/«no confirmada») con autor y momento: cada participante confirma la suya desde la hora de fin durante 48 h; el responsable del colectivo confirma la de sus miembros **con la del miembro siempre prevalente**; el mentor nunca confirma por otros; los conflictos quedan auditados; pasado el plazo queda «no confirmada» y no se cierra ninguna sesión con asistencia desconocida.
- [ ] **Genera el enlace de Meet automáticamente** (incluidas las sesiones futuras existentes sin enlace) con la fecha-hora y zona de la sesión, sin invitados del proveedor, de forma idempotente, con reintentos automáticos del sistema con cadencia fija y manual posterior, y sin bloquear la agenda si el proveedor falla (fallo registrado).
- [ ] **Implementa autorización en el servidor** para cada operación —mentor y mentee gestionan sus sesiones; el responsable de colectivo consulta y gestiona las de su colectivo; cada mentor su propia disponibilidad—, reutilizando la autorización existente (sin duplicar checks de pertenencia) y respondiendo como «inexistente» ante consultas ajenas manipulando identificadores.
- [ ] **Aplica idempotencia** a creaciones, generación de Meet, recordatorios y avisos de cambio (sin efectos duplicados).
- [ ] **Incluye pruebas automatizadas** de las reglas críticas: solapes (incluida la colectiva), disponibilidad y precedencia de excepciones, duplicados, concurrencia, transiciones (incluidas automáticas y «no completada»), trazabilidad, antelación de 2 h (reprogramación y cancelación tardía), asistencia con plazo de 48 h y precedencia miembro/responsable, participación colectiva (alta/baja) y permisos (permitido y denegado).
- [ ] **Es accesible, responsive y está traducida** (es/en/pt), con errores de formulario claros y anunciables.
- [ ] **Documenta migraciones y cambios de API** de la agenda.
- [ ] **`AGENTS.md`, `MEMORY.md` y la documentación relevante** quedan actualizados.

---

## 10. Dudas cerradas (decisiones vinculantes)

**No quedan dudas abiertas.** Las cinco dudas del borrador 0.1.0 se resolvieron con el cliente el 2026-10-12 (D1–D5) y la revisión QA de la v0.2.0 resolvió los 8 hallazgos bloqueantes con nuevas decisiones de producto (D6–D11); todas quedan convertidas en requisitos:

> **Aprobación (v0.4.0, 2026-10-12):** el cliente aprobó la spec tras la 2ª pasada de revisión **sin reabrir ninguna decisión D1–D11**. Se aplicaron de paso los 4 menores y las 3 sugerencias no bloqueantes del reviewer: CA-2.4 (las excepciones bloqueantes aplican también sin ventanas), CA-8.1/caso 24/D8/§2 («miembros a su cargo» por tipo de colectivo), nuevo caso 41 (alta de miembro con sesión propia que solapa una colectiva: alta válida, sin revalidación retroactiva, no participa de esa sesión), CA-8.3/CA-13.3 («participantes registrados» anclados a la hora de fin programada), CA-5.1 (el responsable no miembro no recibe recordatorios), nueva definición de «sesión confirmada» (§4) y RNF-13 (atribución del cálculo de la spec 001).

| Duda | Decisión vinculante | Dónde se plasma |
|---|---|---|
| **D1 — Zona horaria** | **Por sesión:** la define quien la crea al programarla (opción de elegir; por defecto la suya) y el sistema muestra a cada participante las horas convertidas a su propia zona. | RF-3 (CA-3.2, CA-3.6, CA-3.7), CA-1.1, §4 |
| **D2 — Permisos y antelación** | **Ambas partes gestionan:** mentor y mentee crean, reprograman y cancelan sesiones de su relación. Antelación mínima de **2 h** para reprogramar (sin ella se rechaza) y para cancelar; las cancelaciones con menos de 2 h (o tras el inicio) se aceptan **marcadas como «tardías»** (trazabilidad, sin penalización económica en v1). | RF-6 (CA-6.7, CA-6.8), RF-7 (CA-7.2), RF-10 (CA-10.2, CA-10.3), §4, §8 |
| **D3 — Asistencia** | **Ambas partes confirman, plazo 48 h:** cada participante confirma su propia asistencia; pasadas 48 h desde la hora de fin sin confirmación queda **«no confirmada»**, visible para las partes, sin inferir asistencia por silencio (P6/§3.1.3: realizada = `completed` + asistencia confirmada). Ampliado y precisado por D8. | RF-8 (CA-8.1, CA-8.3, CA-8.7, CA-8.8), §4 |
| **D4 — Meet automático** | **Al confirmarse y al reprogramarse:** el enlace se genera automáticamente cuando la sesión queda confirmada y se actualiza si se reprograma; si el proveedor falla, la reserva se completa sin enlace y el fallo queda registrado para reintento (no bloquea, P14/P17). Precisado por D11 y CA-9.7 (zona de la sesión). | RF-9 (CA-9.1, CA-9.2, CA-9.4, CA-9.7) |
| **D5 — Recordatorios** | **Se mantiene 24 h / 2 h / 1 h** en la zona de la sesión; además se envía **un único aviso por cambio** a los participantes cuando la sesión se reprograma o se cancela. | RF-5 (CA-5.1, CA-5.2, CA-5.7, CA-5.8), RF-6 (CA-6.4, CA-6.5), RF-7 (CA-7.1) |
| **D6 — Ciclo de vida de estados** (rev. QA, bloqueante 2) | `scheduled → in_progress` **automático** a la hora de inicio; `completed` **solo manual del mentor** tras la sesión (atestigua que ocurrió — P6); a las **48 h de la hora de fin** sin completar, la sesión queda **«no completada»**, estado **terminal visible para ambas partes** que no cuenta como realizada. Grafo completo y terminales en CA-11.1/CA-11.6. | RF-11, RF-8, §4, §7 (casos 33) |
| **D7 — Participación colectiva** (rev. QA, bloqueantes 3 y 5) | En sesiones familiares/grupales/empresariales, los participantes son **todos los miembros activos del colectivo** en cada momento; las **altas y bajas tienen efecto prospectivo** (recordatorios y capacidad de confirmar asistencia desde ese momento) y **no reescriben sesiones ya celebradas**; si **algún miembro** tiene otra sesión que solapa, el hueco se **rechaza para todo el colectivo**. | RF-13, CA-1.3, CA-1.6, CA-13.1–CA-13.5, §7 (casos 3, 35, 39) |
| **D8 — Confirmación de asistencia** (rev. QA, bloqueante 6) | **Cada participante confirma la suya propia**; el **responsable del colectivo** (familia/cohorte/empresa; reutilizando la autorización de responsables de la spec 001, §3.1 D10) puede confirmar la de sus **miembros a su cargo según el tipo de colectivo — familia: menores; empresa: empleados; cohorte: solo menores (CA-8.1)**; si el propio miembro confirma la suya, **prevalece la suya**; **el mentor nunca confirma por otros**; los conflictos quedan **registrados en auditoría** con autor y momento. | RF-8 (CA-8.1, CA-8.6), RF-10 (CA-10.6), §4, §7 (casos 22, 24) |
| **D9 — Agenda del responsable de colectivo** (rev. QA, bloqueante 7) | El responsable de colectivo **consulta la agenda de su colectivo y puede programar, reprogramar y cancelar sesiones del mismo**, con autorización explícita reutilizando la definición de responsable de la spec 001 (§3.1 D10) y la autorización de colectivos existente. | HU-14, RF-4 (CA-4.7), RF-10 (CA-10.2, CA-10.3, CA-10.7), §2 |
| **D10 — Reprogramación** (rev. QA, bloqueante 1) | Solo se reprograman sesiones **`scheduled` con hora de inicio futura**, con **revalidación completa** (solape, disponibilidad con precedencia de excepción, duplicidad, fecha futura, antelación mínima y límite de plan); una sesión `in_progress` o pasada **no se reprograma** —se cancela si procede—. Elimina la contradicción de la v0.2.0 (CA-6.1 vs CA-6.7). | RF-6 (CA-6.1, CA-6.2, CA-6.8), §4, §7 (caso 36) |
| **D11 — Precedencia de excepciones** (rev. QA, bloqueante 4) | Si una **excepción de disponibilidad** bloquea una fecha/hora, **se rechaza toda reserva en ella aunque caiga dentro de una ventana habitual** (la excepción gana). | RF-2 (CA-2.7), RF-1 (CA-1.5), CA-2.3, §7 (caso 38) |

---

## Anexo A — Matriz HU ↔ RF ↔ CA

| HU | RF principales | CA clave |
|---|---|---|
| HU-1 (reservar sin choques) | RF-1, RF-2, RF-13 | CA-1.2, CA-1.3, CA-13.5 |
| HU-2 (disponibilidad del mentor) | RF-2 | CA-2.1, CA-2.2, CA-2.7 |
| HU-3 (consultar huecos libres) | RF-1, RF-2 | CA-1.5, CA-2.3, CA-2.6 |
| HU-4 (calendario personal) | RF-4 | CA-4.1–CA-4.6 |
| HU-5 (zona horaria visible) | RF-3 | CA-3.1, CA-3.2, CA-3.6, CA-3.7 |
| HU-6 (recordatorios fiables) | RF-5 | CA-5.1–CA-5.8 |
| HU-7 (reprogramar) | RF-6 | CA-6.1, CA-6.7, CA-6.8 |
| HU-8 (cancelar) | RF-7 | CA-7.1, CA-7.2, CA-7.3 |
| HU-9 (confirmar asistencia) | RF-8 | CA-8.1, CA-8.6 |
| HU-10 (48 h y «no confirmada») | RF-8 | CA-8.3, CA-8.7, CA-8.8 |
| HU-11 (Meet automático) | RF-9 | CA-9.1, CA-9.3, CA-9.7 |
| HU-12 (sin duplicados) | RF-1, RF-11 | CA-1.4, CA-11.3, CA-11.4 |
| HU-13 (accesibilidad/i18n) | RF-12 | CA-12.1–CA-12.4 |
| HU-14 (agenda del responsable) | RF-4, RF-10 | CA-4.7, CA-10.2, CA-10.3, CA-10.7 |
| HU-15 (soporte de admin) | RF-10 | CA-10.1, CA-10.5, CA-10.6 |
| — (ciclo de vida de estados, soporte de todas) | RF-11 | CA-11.1, CA-11.2, CA-11.5, CA-11.6 |
