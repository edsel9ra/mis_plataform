# Spec 006 — Ciclo de vida y asistencia de sesiones

Estado: aprobada

**Spec padre:** `specs/002-mentorship-scheduling/spec.md` v0.4.0, aprobada.
**Dependencias:** hijas 003 (acceso/tiempo), 004 (sesión creada y tipos de participante) y 005 (participación prospectiva y ancla de hora de fin); `specs/001-progress-tracking-dashboard/spec.md` §3.1.3 para la regla vinculante de sesión realizada.
**Alcance:** transiciones y terminales de sesión, cierre del mentor y confirmación individual/de responsable dentro de la ventana aprobada.
**Mapa RF padre → RF hija:** RF-8 → RF-006.5–RF-006.8; RF-11 → RF-006.1–RF-006.4.

## Contexto y objetivo

El estado actual no define cuándo empieza o termina una sesión y el dato de asistencia carece de confirmación trazable. Esta spec define un ciclo de vida único y el registro individual de asistencia necesario para que una sesión cuente como realizada solo cuando se cumplen las condiciones aprobadas.

## Usuarios

Mentor que atestigua el cierre; participantes que confirman su asistencia; responsable que puede confirmar por miembros a su cargo; lector autorizado de progreso.

## Historias de usuario

- HU-1. Como mentor, quiero cerrar una sesión celebrada para dejar constancia de que ocurrió.
- HU-2. Como participante, quiero confirmar mi propia asistencia dentro del plazo para que el progreso refleje evidencia.
- HU-3. Como responsable autorizado, quiero confirmar por los miembros a mi cargo mientras ellos no lo hayan hecho.

## Definiciones

- **Estados terminales:** `completed`, `canceled` y «no completada»; no se reabren ni reprograman.
- **Ventana de confirmación:** desde la hora de fin programada hasta antes de cumplirse 48 horas; exactamente en el límite el plazo ya se cerró, según CA-8.8.
- **Confirmación efectiva:** la del propio miembro prevalece siempre sobre la hecha por el responsable, sin borrar ningún evento.
- **Realizada:** `completed` y asistencia individual confirmada como «asistió», con el significado existente de `attended`.

## Requisitos funcionales

- **RF-006.1 (RF-11 padre; CA-11.1):** EL SISTEMA DEBE admitir únicamente estas transiciones: `scheduled→in_progress` automática a la hora de inicio; `in_progress→completed` manual, solo por el mentor de la relación y después de la hora de fin; `scheduled|in_progress→canceled` manual por usuarios autorizados, con motivo y trazabilidad; `scheduled|in_progress→no completada` automática a las 48 horas de la hora de fin si el mentor no completó. `completed`, `canceled` y «no completada» son terminales. SI el sistema debe completar mientras sigue `scheduled` aunque ya empezó, ENTONCES primero la promueve a `in_progress` y luego permite el cierre.
- **RF-006.2 (RF-11 padre; CA-11.2/11.3/11.5):** SI se solicita transición no permitida, ENTONCES EL SISTEMA DEBE rechazarla e informar el estado actual. SI dos solicitudes simultáneas cambian estado de una sesión o crean la misma sesión, ENTONCES solo una debe aplicarse, sin duplicar sesión ni producir estado contradictorio. EL SISTEMA DEBE mantener coherencia entre estado, calendario, avisos, Meet e indicadores, con un efecto por evento.
- **RF-006.3 (RF-11 padre; CA-11.4):** SI se reintenta una misma solicitud de creación, ENTONCES EL SISTEMA DEBE devolver la sesión existente y no crear otra. La ejecución repetida de cambios de estado tampoco duplica sus efectos.
- **RF-006.4 (RF-11 padre; CA-11.6):** CUANDO una sesión no cancelada alcanza 48 horas desde su fin programado sin que el mentor la haya completado, EL SISTEMA DEBE hacerla visible como «no completada» a participantes, mentor y responsable si es colectiva. Es terminal, no permite completar ni confirmar asistencia posteriormente, no cuenta como realizada y no reescribe registros previos.
- **RF-006.5 (RF-8 padre; CA-8.1/8.2/8.8; contrato hija 005):** CUANDO llega la hora de fin programada, EL SISTEMA DEBE permitir a cada participante registrado según RF-005.1–RF-005.3 confirmar su propia asistencia como «asistió» o «no asistió» hasta antes del límite de 48 horas, registrando autor y momento. El responsable puede confirmar por miembros a su cargo solo mientras estos no hayan confirmado; la elegibilidad es: familia, menores; empresa, empleados; cohorte, menores pero no adultos. El mentor solo puede confirmar la propia. SI se intenta confirmar antes del fin, en `canceled`/«no completada», una vez cerrada la ventana o por un miembro excluido de esa sesión conforme al caso padre 41, EL SISTEMA DEBE rechazarlo y no crear un registro de asistencia para ese miembro. Al vencer las 48 horas sin confirmación mantiene «no confirmada», visible a las partes, sin inferir asistencia y sin admitir luego asistencia ni completación.
- **RF-006.6 (RF-8 padre; CA-8.3/8.5; contrato hija 005):** CUANDO el mentor marca una sesión como `completed`, EL SISTEMA DEBE asegurar un registro inicial «no confirmada» de cada participante registrado según RF-005.1–RF-005.3 a la hora de fin programada, más el mentor, con autor/momento identificados, sin sobrescribir confirmaciones ya realizadas. DEBE registrar la asistencia por participante, permitiendo resultados distintos dentro de una misma sesión. NO DEBE crear registro de asistencia para un miembro excluido de esa sesión por el caso padre 41, aunque esté activo a la hora de fin programada.
- **RF-006.7 (RF-8 padre; CA-8.4/8.6):** MIENTRAS el estado individual no sea «asistió» confirmado, la sesión NO DEBE contar como realizada para ese participante. SI confirma primero el responsable y luego el miembro, ENTONCES prevalece el miembro; si confirma primero el miembro, la confirmación posterior del responsable no la sustituye. En ambos órdenes EL SISTEMA DEBE conservar ambas entradas con autor y momento.
- **RF-006.8 (RF-8 padre; CA-8.7/8.9):** EL SISTEMA DEBE mostrar a cada participante su propia asistencia y al mentor/responsable la de los participantes de sus sesiones, incluido «no confirmada», sin exponer datos ajenos. «Asistió» equivale a `attended=true`; «no asistió» y «no confirmada» equivalen a `false`, sin alterar retroactivamente indicadores publicados.

## Requisitos no funcionales

- RNF-006.1 (RNF-2/3/4 padre): transiciones y confirmaciones conservan actor, momento, secuencia y estados sustituidos; operaciones repetidas son idempotentes y la validación de estado ocurre en servidor.
- RNF-006.2 (RNF-11 padre/P19): las transiciones, condiciones de plazo, concurrencia y precedencia miembro/responsable tienen pruebas automatizadas.
- RNF-006.3 (RNF-13 padre/P6): la asistencia mantiene compatibilidad con Spec 001; su fecha contable es el inicio interpretado en zona del sujeto, aunque cierre o confirmación ocurran después.

## Casos límite

- Casos padre 19–24: completada con asistencia sin confirmar; plazo vencido; registro heredado; confirmación contradictoria; asistencia mixta; alcance de «miembros a cargo» por colectivo.
- Caso padre 32: sesiones heredadas sin zona usan la zona por defecto y sin asistencia confirmada se consideran «no confirmada», manteniendo compatibilidad con el panel de progreso.
- Caso padre 33: cierre automático como «no completada», terminal y no realizado.
- Exactamente a hora de fin se abre la confirmación; exactamente al cumplirse fin+48 h se cierra.
- El cierre tardío del mentor no extiende la ventana, que se mide desde hora de fin programada.
- Una sesión cancelada o «no completada» no admite asistencia ulterior.

## Fuera de alcance

No se reprograman ni cancelan sesiones en esta spec (007), no se resuelve membresía prospectiva (005), no se envían recordatorios ni se crea Meet (008), y no cambia la métrica de Spec 001. No se infiere un no-show ni se marca una sesión realizada por silencio.

## Criterios de finalización

- RF-006.1–RF-006.8 verifican CA-8.1–8.9 y CA-11.1–11.6.
- El contrato de participante respeta el ancla de hora de fin de 005 y excluye al miembro del caso padre 41 tanto de confirmaciones como de registros de asistencia; prevalencia, plazos, terminales y compatibilidad con `attended` son demostrables.
- Una sesión solo se cuenta como realizada conforme a Spec 001 §3.1.3.

## Dudas abiertas

- No hay dudas de producto; D3, D6 y D8 permanecen cerradas. @reviewer debe revisar consistencia de límites temporales con CA-8.8/CA-11.6.
