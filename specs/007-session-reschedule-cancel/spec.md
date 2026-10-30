# Spec 007 — Reprogramación y cancelación de sesiones

Estado: aprobada

**Spec padre:** `specs/002-mentorship-scheduling/spec.md` v0.4.1, aprobada.
**Dependencias:** hijas 003 (acceso/zona), 004 (revalidación completa de reservas) y 006 (estados/transiciones terminales). 008 consume los eventos de cambio establecidos aquí.
**Alcance:** cambios de sesiones existentes mediante reprogramación elegible o cancelación trazable, incluidos sus efectos requeridos sobre Meet y notificaciones.
**Mapa RF padre → RF hija:** RF-6 → RF-007.1–RF-007.3; RF-7 → RF-007.4–RF-007.6.

## Contexto y objetivo

Los cambios de fecha y las cancelaciones afectan la agenda y la confianza de participantes. Esta spec asegura que la reprogramación solo ocurra antes del inicio tras revalidación completa, mientras una cancelación conserva la historia, libera la ocupación y admite un resultado tardío marcado.

## Usuarios

Mentor y mentee de la relación; responsable autorizado del colectivo; soporte administrativo autorizado.

## Historias de usuario

- HU-1. Como participante autorizado, quiero cambiar una sesión futura conservando sus valores anteriores para que todos conozcan qué cambió.
- HU-2. Como participante autorizado, quiero cancelar con un motivo y saber si fue tardía para liberar el hueco sin perder trazabilidad.

## Definiciones

- **Reprogramable:** sesión `scheduled` cuyo inicio aún es futuro y cuya nueva hora satisface la antelación mínima de dos horas.
- **Cancelación tardía:** realizada con menos de dos horas antes del inicio o después de iniciar.

## Requisitos funcionales

- **RF-007.1 (RF-6 padre; CA-6.1/6.6):** CUANDO un usuario autorizado reprograma una sesión elegible, EL SISTEMA DEBE registrar autor, momento, fecha anterior y nueva, zona anterior y nueva si cambia, duración anterior/nueva y motivo si se indicó; debe conservar identidad e historial de la misma sesión.
- **RF-007.2 (RF-6 padre; CA-6.2/6.3/6.7/6.8):** SI una reprogramación falla la validación completa de hija 004 (solapes de cualquier participante, disponibilidad y precedencia de excepciones, duplicidad, fecha futura, antelación mínima de dos horas y límite mensual para la nueva fecha), ENTONCES EL SISTEMA DEBE rechazarla y dejar intacta la sesión original. SI está en `completed`, `canceled` o «no completada», o ya está `in_progress` o pasó su inicio, DEBE rechazarla. Una reprogramación intentada con menos de dos horas de antelación se rechaza e informa la regla.
- **RF-007.3 (RF-6 padre; CA-6.4/6.5):** CUANDO una sesión se reprograma, EL SISTEMA DEBE actualizar fecha/hora y zona del evento externo asociado, cuando exista, recalcular recordatorios y generar la notificación única de cambio definida por RF-5 padre.
- **RF-007.4 (RF-7 padre; CA-7.1/7.2/7.4):** CUANDO un usuario autorizado cancela, EL SISTEMA DEBE registrar autor, momento y motivo y notificar conforme a RF-5 padre. El registro y el evento para el consumidor DEBEN contener solo los datos mínimos necesarios para auditoría y aviso —referencia de sesión, autor, momento, motivo y datos de tardanza cuando correspondan— y NO DEBEN incluir `meet_link`. SI ocurre a menos de dos horas del inicio o después de iniciada, ENTONCES DEBE aceptar y marcarla tardía con antelación real, sin penalización económica, notificando que fue tardía.
- **RF-007.5 (RF-7 padre; CA-7.3/7.5/7.6):** MIENTRAS la sesión esté `canceled`, EL SISTEMA DEBE excluirla de solapes, cuota mensual, recordatorios y cualquier registro/confirmación posterior de asistencia, conservarla para consulta y liberar el hueco. Una `in_progress` se puede cancelar con autorización, motivo y trazabilidad; impide confirmaciones posteriores. SI se intenta cancelar `completed`, ya `canceled` o «no completada», DEBE rechazarse como terminal. El borrado físico queda limitado a una operación administrativa excepcional y auditada.
- **RF-007.6 (RF-7 padre; CA-7.4):** CUANDO se cancela una sesión, EL SISTEMA DEBE conservar el valor persistido de `meet_link` como dato histórico y dejarlo inactivo en MIS: mientras el estado sea `canceled`, el API/recurso NO DEBE devolver ese valor (puede omitir el campo o devolverlo nulo). Si la sesión tiene evento Meet asociado, EL SISTEMA DEBE publicar el evento de cancelación para que la hija 008 gestione la eliminación externa best-effort. SI dicha eliminación falla, DEBE registrar el fallo y mantener la sesión cancelada y el enlace oculto, sin reactivarlo ni exponerlo, y sin bloquear ni revertir la cancelación.

## Requisitos no funcionales

- RNF-007.1 (RNF-1/2 padre): autorización de 003, registro inalterable por flujo ordinario de autor, momento y cambio, incluidos los cambios tardíos.
- RNF-007.2 (RNF-3/5 padre): los efectos externos no revierten una cancelación persistida; una operación rechazada no altera la sesión.
- RNF-007.3 (RNF-11 padre/P19): pruebas cubren revalidación, antelación, terminales y fallos de Meet/notificación.

## Casos límite

- Casos padre 8–11: conflicto deja sesión intacta; terminal rechaza; tardía aceptada y marcada; reprogramación bajo dos horas rechazada.
- Casos padre 15–18: fallo de eliminación Meet no revierte; avisos viejos no se envían; cancelada no recibe recordatorios; el aviso de cambio se envía aunque no hubiera recordatorio pendiente.
- Caso padre 42: cancelación conserva el valor de `meet_link` solo como dato histórico; éxito o fallo al borrar en Google no cambia su ocultación en API, y el evento de cancelación no incluye el enlace.
- Caso padre 36: cambio a mes que excede límite de plan se rechaza.
- Caso padre 40: cambio explícito de zona deja ambas zonas en el historial y actualiza Meet.

## Fuera de alcance

No se crean nuevas sesiones ni se editan estados fuera de las acciones definidas; no se redefine política de permisos, reserva, ciclo de vida, notificaciones o Meet. Los recordatorios y avisos se definen en 008; la creación de Meet en 008.

## Criterios de finalización

- RF-007.1–RF-007.6 cubren CA-6.1–6.8 y CA-7.1–7.6.
- La sesión original permanece intacta en todo rechazo; cambios aceptados actualizan la misma sesión y conservan historia.
- Los fallos externos de cancelación cumplen CA-7.4 sin revertir el resultado persistido.

## Dudas abiertas

- No hay dudas de producto; D2 y D10 permanecen cerradas. La revisión debe confirmar la frontera de eventos de cambio con 008.
