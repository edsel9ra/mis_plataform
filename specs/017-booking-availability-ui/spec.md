# Spec 017 — Reserva de sesiones y editor de disponibilidad

Estado: implementada

**Spec padre:** `specs/002-mentorship-scheduling/spec.md` v0.4.2, aprobada. Cubre la parte de interfaz de usuario de la tarea padre T009 («formularios/acciones y editor de disponibilidad») para reservar sesiones nuevas y administrar la disponibilidad del mentor. Completa lo que la spec 016 dejó fuera de alcance.
**Dependencias:** hijas 003 (autorización y zona), 004 (disponibilidad y reserva), 005 (participación colectiva), 009 (calendario y detalle), 010 (idioma, accesibilidad y responsive) y 016 (patrones de diálogo, errores y anuncios). Esta spec **no redefine** reglas de negocio, validaciones, permisos ni límites de esas hijas: solo los hace operables por las personas usuarias.
**Relación con 010:** al cumplirse, junto con la 016, permite cerrar T010-04 (reserva y disponibilidad son las superficies que faltan).

## Contexto y objetivo

El servidor ya permite reservar sesiones con todas las validaciones de la 004 (relación activa, duración, solapes, duplicados, disponibilidad del mentor, límite mensual e inscripción colectiva), consultar los huecos libres de un mentor y administrar sus ventanas recurrentes y excepciones. Ninguna de estas operaciones tiene pantalla: hoy solo un administrador puede crear sesiones, desde `admin/sessions`. El objetivo es que quien puede programar una sesión la reserve eligiendo un hueco libre, y que cada mentor declare y mantenga su disponibilidad, en su idioma, en su zona horaria y con teclado o tecnologías de asistencia.

## Usuarios

- Mentor: administra su disponibilidad y puede reservar sesiones en sus relaciones.
- Cliente de una relación individual: reserva sesiones con su mentor.
- Responsable de un colectivo (familia, empresa o cohorte): reserva sesiones colectivas.

## Historias de usuario

- HU-1. Como mentor, quiero ver, crear, modificar y eliminar mis ventanas semanales de disponibilidad para que solo me reserven cuando puedo atender.
- HU-2. Como mentor, quiero declarar excepciones (días o rangos bloqueados, o franjas abiertas fuera de mi horario habitual) para reflejar vacaciones o cambios puntuales.
- HU-3. Como persona que programa, quiero ver los huecos libres del mentor de una relación en mi zona horaria y reservar uno, para no proponer horarios imposibles.
- HU-4. Como persona que programa, quiero entender por qué una reserva fue rechazada (solape, duplicado, disponibilidad, límite del plan), para elegir otra opción.
- HU-5. Como responsable de un colectivo, quiero saber que la sesión se reservará para todo el colectivo y que un conflicto de cualquier miembro impide el hueco.

## Definiciones

- **Ventana:** franja semanal recurrente del mentor (día de la semana, inicio, fin y zona horaria) (RF-004.4).
- **Excepción:** disponibilidad para un día concreto o un rango de fechas, que bloquea (`block`) o abre (`open`) un periodo, opcionalmente con franja horaria y motivo privado; una excepción bloqueante prevalece sobre una ventana (RF-004.5).
- **Hueco libre:** intervalo que el servidor devuelve como reservable para una duración dada, ya filtrado por ventanas, excepciones y ocupación (RF-004.6).
- **Persona que programa:** quien está autorizado a crear sesiones en una relación según la hija 003: el mentor, el cliente de una relación individual o el responsable del colectivo.

## Requisitos funcionales

### Editor de disponibilidad

- **RF-017.1 (RF-004.4/004.6):** EL SISTEMA DEBE ofrecer la disponibilidad del mentor en una página propia, enlazada desde la navegación del mentor y desde su agenda. CUANDO un mentor abre su disponibilidad, EL SISTEMA DEBE mostrar sus ventanas agrupadas por día de la semana y sus excepciones vigentes y futuras ordenadas por fecha, con horas expresadas en la zona de cada ventana o excepción e indicando esa zona.
- **RF-017.2 (RF-004.4):** EL SISTEMA DEBE permitir al mentor crear, modificar y eliminar ventanas indicando día de la semana, hora de inicio, hora de fin y zona horaria (por defecto la de su perfil). SI el servidor rechaza la ventana (por ejemplo, fin anterior al inicio o solape inconsistente), ENTONCES EL SISTEMA DEBE mostrar el motivo junto al campo afectado y no modificar la lista.
- **RF-017.3 (RF-004.4/004.5):** EL SISTEMA DEBE permitir al mentor crear, modificar y eliminar excepciones indicando tipo (bloquear o abrir), fecha de inicio, fecha de fin opcional, franja horaria opcional (si se indica, inicio y fin), zona horaria y motivo opcional. El motivo DEBE presentarse como privado del mentor.
- **RF-017.4 (RF-004.5):** SI el mentor bloquea un periodo en el que ya tiene sesiones, ENTONCES EL SISTEMA DEBE informarle de que esas sesiones se conservan y solo pueden reprogramarse o cancelarse (desde su detalle, spec 016), sin cancelarlas automáticamente.
- **RF-017.5 (RNF-004.1):** EL SISTEMA DEBE ofrecer el editor solo a usuarios con rol de mentor y solo sobre su propia disponibilidad; la autorización la decide el servidor.

### Reserva

- **RF-017.6 (RF-004.1; hija 003):** EL SISTEMA DEBE ofrecer el inicio de la reserva desde la agenda (`/sessions`) a las personas que pueden programar en al menos una relación activa. CUANDO una persona que programa inicia una reserva, EL SISTEMA DEBE permitirle elegir una de sus relaciones activas en las que puede programar, y no DEBE ofrecer relaciones pausadas o finalizadas.
- **RF-017.7 (RF-004.6):** CUANDO se ha elegido la relación y una duración (entera entre los límites que admite el servidor), EL SISTEMA DEBE mostrar los huecos libres del mentor para un periodo navegable, en la zona de quien consulta, sin revelar el detalle ni el motivo de las excepciones del mentor.
- **RF-017.7b (RF-004.6; cambio aprobado por el usuario, 2026-11-13):** CUANDO se abre la reserva o se cambia la relación o la duración, EL SISTEMA DEBE mostrar directamente la primera semana, a partir de la actual, que tenga algún hueco libre, buscando como máximo en las 8 semanas siguientes. SI no hay huecos en ese plazo, ENTONCES EL SISTEMA DEBE mostrar la semana actual e indicar que no hay huecos en las próximas 8 semanas. La navegación manual entre semanas no salta semanas vacías.
- **RF-017.8 (RF-004.1):** La reserva DEBE hacerse eligiendo uno de los huecos libres que ofrece el servidor; no se introduce fecha ni hora libre. CUANDO la persona elige un hueco, EL SISTEMA DEBE pedir título obligatorio y descripción opcional, mostrar un resumen (relación, fecha y hora en su zona, duración y, si es colectiva, que se reservará para todo el colectivo) y enviar la reserva tras una confirmación explícita. El tipo de sesión DEBE ser el que corresponde a la relación, sin pedírselo a la persona.
- **RF-017.9 (RF-004.2/004.3/004.5/004.7):** SI el servidor rechaza la reserva (solape del mentor o de cualquier participante, conflicto de un miembro del colectivo, hueco fuera de disponibilidad, límite mensual del plan, relación inactiva o datos inválidos), ENTONCES EL SISTEMA DEBE mostrar el motivo traducido junto al campo afectado o en el resumen, conservar los datos introducidos y volver a consultar los huecos libres. SI el servidor indica que la reserva ya existía (reintento idempotente), ENTONCES EL SISTEMA DEBE tratarla como reservada y no crear otra.
- **RF-017.10 (RF-004.1; hija 009):** CUANDO la reserva se acepta, EL SISTEMA DEBE anunciar el éxito, mostrar la sesión creada (enlace a su detalle) y reflejarla en el calendario sin recargar la página manualmente.

### Transversales

- **RF-017.11 (RF-010.1–010.3):** EL SISTEMA DEBE presentar todos los textos, estados vacíos, confirmaciones, errores y rechazos de estas pantallas en es/en/pt con recurso al español. Los errores de validación DEBEN mostrarse junto al campo afectado y en una región que las tecnologías de asistencia anuncien, y el éxito de cada operación DEBE anunciarse.
- **RF-017.12 (RF-010.2):** EL SISTEMA DEBE permitir realizar todas las operaciones de esta spec solo con teclado, con foco visible. La lista de huecos DEBE recorrerse y elegirse con teclado. Al abrir un formulario o confirmación, el foco DEBE entrar en él; al cerrarlo, DEBE volver al control que lo abrió; tras un éxito, DEBE quedar en un lugar coherente.
- **RF-017.13 (hija 003):** EL SISTEMA DEBE mostrar siempre la zona horaria en la que se expresan las horas, y las horas que no existen o se repiten por cambio de horario DEBEN tratarse sin desplazamientos silenciosos (CA-3.4).

## Requisitos no funcionales

- RNF-017.1 (RNF-010.1): las superficies cumplen WCAG 2.2 AA en los criterios aplicables, incluidos contraste 1.4.3, reflow 1.4.10 a 320 píxeles CSS, presentación a 1280 píxeles CSS y tamaño de objetivo 2.5.8 (24 × 24 píxeles CSS o excepción documentada).
- RNF-017.2 (RNF-004.1; hija 003): autorización, validación y cálculo de huecos siguen en el servidor; la interfaz no calcula disponibilidad, solapes ni límites por su cuenta. Los huecos mostrados son orientativos: el servidor revalida al reservar.
- RNF-017.3: una operación en curso indica que se está procesando y no puede enviarse dos veces mientras tanto.
- RNF-017.4 (RNF-010.2): la verificación incluye pruebas automatizadas de la lógica de presentación y una revisión con teclado, accesibilidad automatizada, reflow e idiomas en las superficies nuevas, con evidencia guardada.

## Casos límite

- Mentor sin ventanas: el editor lo indica con un estado vacío que invita a crear la primera; los huecos siguen limitados por solapes y excepciones bloqueantes (RF-004.5).
- Ventana con fin anterior al inicio, o que cruza medianoche: se muestra el rechazo del servidor.
- Excepción de un solo día sin franja (bloquea el día entero) frente a excepción de rango con franja.
- Excepción bloqueante sobre un día con sesiones ya reservadas: se informa y las sesiones se conservan.
- Periodo sin huecos libres: estado vacío que invita a cambiar de periodo o de duración.
- El hueco elegido deja de estar libre antes de confirmar (otra persona lo reserva): se muestra el rechazo, se conservan título y descripción y se refrescan los huecos.
- Reserva colectiva en la que un miembro tiene otra sesión: se rechaza el hueco para todo el colectivo con el motivo. Los huecos ofrecidos solo descuentan la ocupación del mentor y de quien consulta; la de los demás miembros la comprueba el servidor al reservar, así que un hueco ofrecido puede rechazarse por este motivo.
- Límite mensual del plan alcanzado: se muestra el motivo traducido.
- Reintento tras pérdida de conexión: no se duplica la sesión (RF-004.3).
- Hueco que cae en un cambio de horario de la zona de quien consulta: se muestra con su hora real y su zona.
- Persona sin relaciones activas en las que pueda programar: estado vacío explicativo, sin formulario.

## Fuera de alcance

- Cambios en reglas, validaciones, permisos, límites o notificaciones de las hijas 003–008 y de la 016.
- Reprogramar, cancelar, completar y asistencia (spec 016).
- Disponibilidad del mentee y recomendación automática de horarios (excluidas por la 004).
- Pantallas de administración (`admin/sessions` ya permite crear sesiones).
- Sincronización con calendarios externos.
- Iniciar la reserva desde cada relación o desde los paneles del cliente o del mentor.
- Reservar a una fecha y hora libres que no sean un hueco ofrecido.

## Criterios de finalización

- Un mentor puede administrar ventanas y excepciones, y una persona que programa puede reservar un hueco libre, con los rechazos del servidor traducidos en es/en/pt y sin claves crudas.
- Los huecos se muestran en la zona de quien consulta y nunca revelan detalles de excepciones del mentor.
- Las operaciones son operables con teclado, con foco gestionado, errores y éxitos anunciados, objetivos de 24 × 24 píxeles CSS y reflow correcto a 320 y 1280 píxeles CSS, con evidencia guardada.
- La reserva aceptada aparece en el calendario sin recargar.
- Con esta spec y la 016 terminadas, T010-04 puede cerrarse.

## Dudas abiertas

- **Resuelta (usuario, 2026-11-13) — Punto de entrada de la reserva.** Opción (a): botón «Reservar sesión» en la agenda `/sessions`, con selector de relación (RF-017.6). Iniciar la reserva desde cada relación o panel queda fuera de alcance.
- **Resuelta (usuario, 2026-11-13) — Reserva sin elegir un hueco ofrecido.** Opción (a): solo huecos ofrecidos por el servidor (RF-017.8). Comprobado: los huecos se generan cada 30 minutos (inicios en :00 y :30) y, si el mentor no tiene ventanas, cubren las 24 horas salvo solapes y excepciones bloqueantes; no se puede reservar, por ejemplo, a las 10:15.
- **Resuelta (usuario, 2026-11-13) — Ubicación del editor de disponibilidad.** Opción (a): página propia enlazada desde la navegación del mentor y desde su agenda (RF-017.1).
