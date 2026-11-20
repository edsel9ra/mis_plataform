# Spec 004 — Disponibilidad y reserva de sesiones

Estado: implementada

**Spec padre:** `specs/002-mentorship-scheduling/spec.md` v0.4.0, aprobada.
**Dependencias:** hija 003 (`specs/003-agenda-access-timezone/spec.md`); definición de miembros activos según la relación/colectivo existente.
**Alcance:** disponibilidad de mentores, validación de reservas e inscripción/conflicto de participantes colectivos en programación inicial.
**Mapa RF padre → RF hija:** RF-1 → RF-004.1–RF-004.3; RF-2 → RF-004.4–RF-004.6; RF-13 (CA-13.1, CA-13.5) → RF-004.7.

## Contexto y objetivo

La reserva actual no impide solapes ni duplicados y no dispone de calendario de disponibilidad del mentor. Esta spec define las reglas para declarar disponibilidad, consultar huecos y reservar sesiones sin conflictos, manteniendo el hueco como decisión indivisible para todo colectivo.

## Usuarios

Mentor que declara disponibilidad; mentor, mentee o responsable autorizado que reserva; participantes cuya ocupación debe respetarse.

## Historias de usuario

- HU-1. Como mentor, quiero declarar ventanas y excepciones para controlar cuándo puedo atender.
- HU-2. Como persona que programa, quiero conocer huecos libres y evitar reservas incompatibles o duplicadas.
- HU-3. Como miembro de un colectivo, quiero que una sesión común no se reserve si alguien del colectivo ya está ocupado.

## Definiciones

- **Solapamiento:** intersección de intervalos semiabiertos [inicio, fin) de sesiones no canceladas para un mismo participante.
- **Duplicidad:** repetición de relación, inicio, duración y título equivalente tras normalizar mayúsculas/minúsculas y espacios repetidos.
- **Excepción:** disponibilidad para fecha concreta o rango; si bloquea un periodo, prevalece sobre una ventana recurrente.

## Requisitos funcionales

- **RF-004.1 (RF-1 padre; CA-1.1; RNF-2):** CUANDO se programa una sesión, EL SISTEMA DEBE exigir relación activa, título, fecha futura, duración entera entre 15 y 480 minutos, tipo coherente con la relación y zona horaria elegida conforme a hija 003; en sesiones colectivas DEBE inscribir los participantes conforme a RF-13 padre. DEBE conservar quién creó la sesión y cuándo se creó.
- **RF-004.2 (RF-1 padre; CA-1.2/1.3/1.6):** SI la reserva se solapa con una sesión no cancelada del mentor o de cualquier otro participante, ENTONCES EL SISTEMA DEBE rechazarla e indicar el conflicto. En sesiones colectivas, el conflicto de cualquier miembro activo rechaza el hueco para todo el colectivo. Para solape y límite mensual DEBE excluir `canceled` y «no completada», conservándolas para consulta/trazabilidad.
- **RF-004.3 (RF-1 padre; CA-1.4):** SI se repite una solicitud con la misma relación, inicio, duración y título equivalente normalizado, ENTONCES EL SISTEMA DEBE devolver la sesión ya creada y no crear otra.
- **RF-004.4 (RF-2 padre; CA-2.1/2.2):** EL SISTEMA DEBE permitir al mentor crear, modificar y eliminar sus ventanas recurrentes (día semanal, inicio/fin y zona) y excepciones para un día concreto o un rango de fechas. SI el fin de una ventana antecede al inicio o esta se solapa consigo misma de forma inconsistente, DEBE rechazarla con motivo.
- **RF-004.5 (RF-1/RF-2 padre; CA-1.5/2.3/2.4/2.5/2.7):** SI una sesión completa no cabe en ventana disponible ni está cubierta por excepción que la habilite, ENTONCES EL SISTEMA DEBE rechazarla con motivo. Si no hay ventanas definidas, no aplica el filtro de ventanas, pero los solapes y excepciones bloqueantes sí aplican. Una excepción bloqueante prevalece siempre sobre una ventana habitual. Una excepción en día ya ocupado conserva las sesiones existentes y permite solo reprogramarlas o cancelarlas, informando el conflicto.
- **RF-004.6 (RF-2 padre; CA-2.6):** CUANDO el mentor consulta su disponibilidad, EL SISTEMA DEBE mostrar ventanas y excepciones en su zona; CUANDO quien programa consulta huecos, DEBE devolver huecos libres en la zona del consultante sin exponer detalle ni motivo de las excepciones.
- **RF-004.7 (RF-13 padre; CA-13.1/13.5):** CUANDO se programa una sesión colectiva, EL SISTEMA DEBE inscribir a todos los miembros activos en ese momento y al mentor. SI cualquier miembro activo tiene otra sesión no cancelada solapada, ENTONCES EL SISTEMA DEBE rechazar la reserva para el colectivo completo e indicar el conflicto.

## Requisitos no funcionales

- RNF-004.1 (RNF-1/9 padre): crear sesión y administrar disponibilidad se autorizan en servidor según la hija 003; motivos y detalles de excepciones son privados al mentor.
- RNF-004.2 (RNF-3/11 padre): las reglas de reserva y disponibilidad tienen resultados verificables ante solicitudes repetidas, solapes y límites.
- RNF-004.3 (P8/P19): la integridad de la reserva debe sostenerse frente a solicitudes simultáneas.
- RNF-004.4 (RNF-2 padre/P5): la creación ordinaria de cada sesión conserva su autor y momento; el registro no se altera mediante operaciones ordinarias de negocio.

## Casos límite

- Casos padre 1–7: intersección de un minuto sí solapa; sesiones contiguas no; mismo participante en relaciones distintas; choque colectivo total; duplicidad/reintento; mentor sin ventanas con excepción bloqueante; excepción no elimina sesiones existentes y prevalece sobre ventana. Las excepciones pueden cubrir un día concreto o un rango de fechas.
- Caso padre 12: el mentee crea con las mismas validaciones que el mentor.
- Caso padre 30: una relación pausada o finalizada no admite sesiones nuevas; las sesiones existentes permanecen consultables.
- Caso padre 38: excepción bloqueante rechaza aunque la ventana cubra el horario.
- La cancelación/no completada no ocupa hueco ni cuota; no se borra su historial.

## Fuera de alcance

No se cubren cambios prospectivos de membresía posteriores a la reserva (hija 005), reprogramación/cancelación de sesiones ya creadas (hija 007), recordatorios, Meet ni presentación del calendario. No hay disponibilidad del mentee ni recomendación automática de horarios.

## Criterios de finalización

- RF-004.1–RF-004.7 cubren CA-1.1–1.6, CA-2.1–2.7 y CA-13.1/13.5 sin alterar el límite mensual vigente; la creación ordinaria conserva autor y momento conforme a RNF-2.
- Los casos límite de reserva, excepción, solape colectivo e idempotencia son verificables.
- El resultado colectivo es indivisible y consumible por las hijas 005–008.

## Dudas abiertas

- No hay dudas de producto; D7 y D11 permanecen cerradas. @reviewer debe confirmar que el límite de participación de esta hija frente a la 005 está completo.
