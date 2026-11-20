# Spec 009 — Calendario y consulta de agenda

Estado: implementada

**Spec padre:** `specs/002-mentorship-scheduling/spec.md` v0.4.2, aprobada.
**Dependencias:** hijas 003–008; definición de acceso y zona de 003, estados de 006, enlace Meet de 008.
**Alcance:** consulta de calendario por día/semana/mes de las sesiones MIS a las que se tiene acceso.
**Mapa RF padre → RF hija:** RF-4 → RF-009.1–RF-009.4; consulta administrativa de soporte según RF-10/CA-10.1 → RF-009.5.

## Contexto y objetivo

La aplicación ofrece una lista paginada pero no una vista de calendario integrada. Esta spec añade la consulta temporal de sesiones existentes y autorizadas, incluida la agenda del colectivo para su responsable, evitando una fuente paralela de citas.

## Usuarios

Participante de sesiones; responsable autorizado de colectivo; usuario con permiso explícito de consulta de soporte.

## Historias de usuario

- HU-1. Como participante, quiero consultar día, semana o mes con hora, participantes y estado para organizar mi agenda.
- HU-2. Como responsable, quiero consultar sesiones de mi colectivo identificadas como tales, sin acceder a colectivos ajenos.

## Definiciones

- **Periodo calendario:** rango consultado por día, semana o mes.
- **Fuente de agenda:** las sesiones de MIS; eventos externos no se importan.

## Requisitos funcionales

- **RF-009.1 (RF-4 padre; CA-4.1/4.6):** EL SISTEMA DEBE mostrar en calendario sesiones de la fuente de sesiones de MIS en que participa la persona autenticada, con título, hora y zona según hija 003, duración, participantes y estado. No debe poblarse con una fuente paralela.
- **RF-009.2 (RF-4 padre; CA-4.2/4.3):** EL SISTEMA DEBE distinguir `scheduled`, `in_progress`, `completed`, `canceled` y «no completada» con etiqueta textual además de cualquier señal visual; debe permitir navegar día, semana y mes y abrir el detalle de sesión. CUANDO se consulta la vista semanal, EL SISTEMA DEBE iniciar el periodo en lunes en todos los locales disponibles (es, en y pt), independientemente del idioma activo; sus límites DEBEN determinarse en la zona horaria del espectador conforme a RF-003.1, teniendo en cuenta el desplazamiento UTC y los cambios de horario aplicables.
- **RF-009.3 (RF-4 padre; CA-4.4/4.5):** SI el periodo no tiene sesiones, ENTONCES EL SISTEMA DEBE mostrar estado vacío explícito con opción de programar, no una pantalla en blanco. SI falla la carga, DEBE mostrar error comprensible y opción de reintento.
- **RF-009.4 (RF-4 padre; CA-4.7):** CUANDO una persona es responsable autorizado de un colectivo, EL SISTEMA DEBE incluir sesiones futuras y pasadas de ese colectivo, identificarlas como tales y no exponer información de colectivos ajenos.
- **RF-009.5 (RF-10 padre; CA-10.1; contrato hija 003):** CUANDO un admin de soporte consulta el calendario, EL SISTEMA DEBE permitir la consulta solo si cuenta con el permiso explícito de lectura definido por la hija 003; sin ese permiso DEBE denegarla y no devolver sesiones. Con permiso, DEBE limitar los resultados al alcance autorizado y no conceder acceso por el solo rol administrativo.

## Requisitos no funcionales

- RNF-009.1 (RNF-1/9 padre): la consulta aplica autorización de hija 003 y respeta privacidad de participante/colectivo.
- RNF-009.2 (RNF-12 padre): el contenido de calendario carga en 2 segundos o menos en percentil 95 bajo condiciones normales de uso, sin recomputar datos idénticos redundantes.
- RNF-009.3 (P1/P16): fuente única MIS, estados comprensibles y recuperación de error.

## Casos límite

- Caso padre 28: calendario vacío ofrece estado guiado.
- Casos padre 25–27: una sesión sin zona del usuario usa la zona por defecto; se convierte a la zona del espectador y no aparece en el día equivocado al cruzar medianoche o cambio horario.
- Casos padre 31/37: sesión ajena no queda visible mediante identificador manipulado.
- Caso padre 31: admin sin permiso explícito de lectura no obtiene sesiones; la consulta con permiso explícito respeta el alcance autorizado conforme a RF-003.2 y CA-10.1.
- Una sesión colectiva se incluye solo en la agenda del responsable autorizado o de sus participantes conforme al contrato de 003.
- Al cruzar un cambio de horario, los límites de la semana siguen correspondiendo a lunes a las 00:00 y al lunes siguiente a las 00:00 en la zona horaria del espectador, según RF-003.1.

## Criterios de aceptación

- **CA-009.2.1 (RF-009.2; CA-4.3 padre):** Al consultar la misma semana y zona horaria del espectador en es, en y pt, el periodo semanal comienza el lunes y conserva los mismos límites locales; cambiar el idioma activo no cambia el primer día ni los límites temporales. Los límites corresponden a lunes 00:00 y al lunes siguiente 00:00 en la zona del espectador, convertidos a instantes absolutos considerando el desplazamiento UTC y los cambios de horario conforme a RF-003.1.

## Fuera de alcance

No incluye sincronización/importación de calendarios externos, recomendación de huecos, suscripción en tiempo real ni edición de reglas de sesión. Las acciones disponibles consumen las hijas funcionales y no cambian sus condiciones.

## Criterios de finalización

- RF-009.1–RF-009.4 cubren CA-4.1–4.7; RF-009.5 permite verificar denegación sin permiso explícito de consulta y resultados limitados al alcance autorizado con permiso, conforme a CA-10.1.
- Día/semana/mes, detalle, estados, vacío/error y agenda de colectivo se verifican con acceso correcto.
- [ ] CA-009.2.1 se verifica en es, en y pt, incluyendo límites semanales en zonas horarias con distintos desplazamientos UTC y un cambio de horario, sin alterar el lunes como inicio de semana.
- Se verifica el umbral de rendimiento establecido en RNF-009.2.

## Dudas abiertas

- No hay dudas de producto; D9 permanece cerrada. El alcance de consulta de soporte se rige por RF-009.5 y el contrato de acceso de 003.

## Historial de cambios

| Revisión | Fecha | Cambio | Estado |
|---|---|---|---|
| Revisión aprobada | 2026-10-12 | Se aprueba iniciar la semana en lunes de forma uniforme en es/en/pt y fijar los límites semanales en la zona horaria del espectador, con trazabilidad a RF-009.2/CA-4.3 y RF-003.1. | Aprobada explícitamente por el usuario. |
