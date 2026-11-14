# Spec 016 — Acciones sobre una sesión desde su detalle

Estado: implementada

**Spec padre:** `specs/002-mentorship-scheduling/spec.md` v0.4.2, aprobada. Cubre la parte de interfaz de usuario de la tarea padre T009 («formularios/acciones») para las operaciones sobre una sesión ya existente.
**Dependencias:** hijas 003 (autorización y zona), 006 (ciclo de vida y asistencia), 007 (reprogramación y cancelación), 009 (calendario y detalle) y 010 (idioma, accesibilidad y responsive). Esta spec **no redefine** reglas de negocio, estados, plazos ni permisos de esas hijas: solo los hace operables por las personas usuarias.
**Relación con 010:** al cumplirse, permite cerrar T010-04 para reprogramación, cancelación y asistencia. Reserva y disponibilidad quedan para la spec 017.

## Contexto y objetivo

Las reglas para reprogramar, cancelar, completar y confirmar asistencia ya existen y se aplican en el servidor (hijas 006 y 007), pero hoy solo un administrador puede actuar sobre una sesión, y solo para cancelarla. Mentores, participantes y responsables pueden ver el detalle de una sesión, pero no realizar sobre ella las operaciones que las reglas ya les permiten. El objetivo es que cada persona autorizada pueda realizar esas operaciones desde el detalle de la sesión, entendiendo por qué una operación no está disponible o fue rechazada, en su idioma y con teclado o tecnologías de asistencia.

## Usuarios

- Mentor de la relación.
- Participante de la sesión (individual o miembro de un colectivo).
- Responsable de un colectivo (familia, empresa o cohorte) respecto a los miembros a su cargo.

## Historias de usuario

- HU-1. Como persona autorizada, quiero reprogramar una sesión futura indicando nueva fecha, hora y, si aplica, zona y motivo, para adaptarla a un cambio de planes sin crear otra sesión.
- HU-2. Como persona autorizada, quiero cancelar una sesión indicando el motivo, para que los demás participantes sean avisados.
- HU-3. Como participante, quiero confirmar si asistí o no a una sesión terminada, para que mi progreso sea correcto.
- HU-4. Como responsable de un colectivo, quiero confirmar la asistencia de los miembros a mi cargo que aún no lo hayan hecho.
- HU-5. Como mentor, quiero marcar como completada una sesión que ya terminó.
- HU-6. Como cualquier usuario del detalle, quiero entender por qué una acción no está disponible o fue rechazada, para saber qué puedo hacer.

## Definiciones

- **Acción disponible:** operación que la persona puede intentar desde el detalle según el estado y los plazos vigentes de la sesión. La decisión final siempre la toma el servidor.
- **Rechazo de dominio:** respuesta del servidor que impide una operación por una regla de 006/007 (por ejemplo, antelación mínima, solape, estado terminal o ventana de asistencia cerrada).
- **Ventana de asistencia:** desde la hora de fin programada hasta 48 horas después (RF-006.5).

## Requisitos funcionales

- **RF-016.1 (RF-6 padre; RF-007.1/007.2):** CUANDO una persona autorizada solicita reprogramar una sesión desde su detalle, EL SISTEMA DEBE pedir nueva fecha y hora, duración (prellenada con la actual y modificable dentro de los valores que admite el servidor), zona horaria (por defecto la de la sesión) y motivo opcional, y enviar la solicitud. SI el servidor la rechaza, ENTONCES EL SISTEMA DEBE mostrar el motivo del rechazo sin modificar la sesión mostrada. CUANDO se acepta, EL SISTEMA DEBE mostrar la nueva fecha en la zona de quien la consulta.
- **RF-016.2 (RF-7 padre; RF-007.4/007.5):** CUANDO una persona autorizada solicita cancelar una sesión, EL SISTEMA DEBE pedir un motivo obligatorio (no vacío ni solo espacios) y una confirmación explícita antes de enviarla. SI faltan menos de dos horas para el inicio o la sesión ya empezó, ENTONCES EL SISTEMA DEBE advertir antes de confirmar que la cancelación quedará marcada como tardía. CUANDO se acepta, EL SISTEMA DEBE mostrar la sesión como cancelada, con su motivo, y dejar de mostrar el enlace de la reunión.
- **RF-016.3 (RF-8 padre; RF-006.5/006.7):** MIENTRAS la ventana de asistencia esté abierta y la sesión no esté cancelada ni «no completada», EL SISTEMA DEBE permitir a cada participante registrar su propia asistencia como «asistió» o «no asistió», y mostrar su estado actual, incluido «no confirmada».
- **RF-016.4 (RF-8 padre; RF-006.5/006.7):** MIENTRAS la ventana de asistencia esté abierta, EL SISTEMA DEBE permitir al responsable registrar la asistencia de cada miembro a su cargo que aún no haya confirmado. SI el miembro ya confirmó, ENTONCES EL SISTEMA DEBE mostrar su confirmación y no ofrecer sustituirla.
- **RF-016.5 (RF-11 padre; RF-006.1):** CUANDO el mentor de la relación solicita completar una sesión después de su hora de fin, EL SISTEMA DEBE pedir confirmación y enviarla. CUANDO se acepta, EL SISTEMA DEBE mostrar el nuevo estado y la asistencia resultante.
- **RF-016.6 (RF-6/7/8/11 padre; RNF-010.3):** CUANDO una persona consulta el detalle de una sesión, EL SISTEMA (servidor) DEBE informar, para esa persona y en ese momento, qué acciones de esta spec están disponibles (reprogramar, cancelar, completar, confirmar la propia asistencia y confirmar la de cada miembro a cargo) y, para cada acción no disponible que la persona podría esperar, un motivo identificable (por ejemplo, «sin permiso», «estado terminal», «aún no ha terminado», «ventana de asistencia cerrada», «antelación insuficiente» o «el miembro ya confirmó»). La disponibilidad DEBE calcularse con las mismas reglas que aplica el servidor al ejecutar la acción (hijas 003, 006 y 007), sin reglas paralelas. La interfaz DEBE ofrecer solo las acciones informadas como disponibles y explicar con texto traducido el motivo de las no disponibles; no DEBE deducir permisos ni elegibilidad por su cuenta.
- **RF-016.10 (RNF-010.3):** SI el servidor rechaza una acción que había informado como disponible (por ejemplo, porque el estado cambió entre medias), ENTONCES EL SISTEMA DEBE mostrar el rechazo, actualizar el detalle con el estado y las acciones vigentes y no aplicar ningún cambio parcial.
- **RF-016.7 (RF-010.1–010.3):** EL SISTEMA DEBE presentar todos los textos, confirmaciones, advertencias, errores y rechazos de dominio de estas acciones en es/en/pt con recurso al español. Los errores de validación DEBEN mostrarse junto al campo afectado y en una región que las tecnologías de asistencia anuncien, y el éxito de cada acción DEBE anunciarse.
- **RF-016.8 (RF-010.2):** EL SISTEMA DEBE permitir realizar todas las acciones de esta spec solo con teclado, con foco visible. Al abrir una confirmación o formulario, el foco DEBE entrar en él; al cerrarlo o cancelarlo, DEBE volver al control que lo abrió; tras un éxito, DEBE quedar en un lugar coherente del detalle.
- **RF-016.9 (RF-6/7/8 padre):** CUANDO una acción se acepta, EL SISTEMA DEBE reflejar el cambio en el detalle y en el calendario sin recargar la página manualmente.

## Requisitos no funcionales

- RNF-016.1 (RNF-010.1): las superficies cumplen WCAG 2.2 AA en los criterios aplicables, incluidos contraste 1.4.3, reflow 1.4.10 a 320 píxeles CSS, presentación a 1280 píxeles CSS y tamaño de objetivo 2.5.8 (24 × 24 píxeles CSS o excepción documentada).
- RNF-016.2 (RNF-010.3; hija 003): la autorización y la validación de reglas siguen en el servidor; la interfaz no calcula permisos ni elegibilidad que contradigan al servidor.
- RNF-016.3: una acción en curso indica que se está procesando y no puede enviarse dos veces mientras tanto.
- RNF-016.4 (RNF-010.2): la verificación incluye pruebas automatizadas de la lógica de presentación y una revisión con teclado, accesibilidad automatizada, reflow e idiomas en las superficies nuevas, con evidencia guardada.

## Casos límite

- Reprogramación rechazada por antelación menor de dos horas, solape, disponibilidad o límite mensual: se muestra el motivo y la sesión no cambia.
- Reprogramación con cambio de zona: la nueva hora se muestra en la zona de quien consulta.
- Cancelación con motivo vacío o solo espacios: se indica junto al campo y no se envía.
- Cancelación a menos de dos horas o ya iniciada: advertencia de cancelación tardía antes de confirmar; se acepta.
- Cancelar una sesión ya completada, cancelada o «no completada»: no se ofrece; si llega a intentarse, se muestra el rechazo.
- Asistencia antes de la hora de fin o tras cerrar la ventana: no se ofrece y se explica cuándo se puede o se pudo confirmar.
- El responsable confirma por un miembro que confirmó por su cuenta entre medias: el servidor conserva la del miembro y la interfaz muestra la vigente.
- Dos personas actúan a la vez sobre la misma sesión (p. ej., una cancela mientras otra reprograma): la segunda recibe el rechazo y ve el estado actual.
- Sesión colectiva: cada persona ve solo la asistencia que tiene permitido ver (RF-006.8).
- Pérdida de conexión durante el envío: se informa y se permite reintentar sin duplicar la operación.

## Fuera de alcance

- Reserva de sesiones nuevas y editor de disponibilidad del mentor (spec 017).
- Cambios en reglas, estados, plazos, permisos, notificaciones o integración con Meet (hijas 003–008). Exponer la disponibilidad de acciones (RF-016.6) reutiliza esas reglas y no las modifica.
- Pantallas de administración (ya cubiertas por `admin/sessions`).
- Edición de título, descripción o notas de la sesión.

## Criterios de finalización

- Mentor, participantes y responsables pueden reprogramar, cancelar, confirmar asistencia y completar desde el detalle cuando las reglas lo permiten, y ven el motivo cuando no.
- Todos los rechazos de dominio de 006/007 que pueden producirse en estas acciones se muestran traducidos en es/en/pt, sin claves crudas.
- Las acciones son operables con teclado, con foco gestionado, errores y éxitos anunciados, objetivos de 24 × 24 píxeles CSS, y reflow correcto a 320 y 1280 píxeles CSS, con evidencia guardada.
- El calendario y el detalle reflejan el cambio tras cada acción aceptada.
- La autorización permanece en el servidor: las pruebas muestran que la disponibilidad informada coincide con lo que el servidor acepta al ejecutar cada acción para cada rol y estado, y que un rechazo tras un cambio de estado se comunica y refresca el detalle.

## Dudas abiertas

- **Resuelta (usuario, 2026-11-13) — Cómo sabe la interfaz qué acciones ofrecer.** Opción (a): el servidor informa en el detalle las acciones disponibles y los motivos de las no disponibles para quien consulta (RF-016.6). Implica un cambio de backend dentro de esta spec, sin modificar reglas.
- **Resuelta — Quién puede reprogramar y cancelar.** Según la autorización vigente del servidor (hija 003), pueden reprogramar y cancelar: el mentor de la relación, el cliente en una relación individual y el responsable del colectivo (familia, empresa o cohorte). Los miembros de un colectivo que no son responsables pueden ver la sesión y confirmar su propia asistencia, pero no reprogramarla ni cancelarla. Completar queda reservado al mentor. Esta spec presenta ese comportamiento y no lo modifica.
- **Resuelta (usuario, 2026-11-13) — Reprogramación con duración distinta.** Se permite cambiar la duración al reprogramar, dentro de lo que admite el servidor (RF-016.1).
