# Spec 008 — Recordatorios y Google Meet de sesiones

Estado: aprobada

**Spec padre:** `specs/002-mentorship-scheduling/spec.md` v0.4.2, aprobada.
**Dependencias:** hijas 003 (zona/privacidad), 004 y 005 (participantes), 006 (estados), 007 (eventos de reprogramación y cancelación).
**Alcance:** recordatorios, avisos de cambio y ciclo de generación/actualización/eliminación del enlace Meet.
**Mapa RF padre → RF hija:** RF-5 → RF-008.1–RF-008.3; RF-9 → RF-008.4–RF-008.6; RF-7/CA-7.4 → RF-008.7.

## Contexto y objetivo

Los recordatorios actuales no se adaptan bien a cambios de fecha y Meet requiere creación manual. Esta spec define avisos con información vigente e idempotencia, así como un enlace externo automático que nunca debe ser requisito para persistir la sesión.

## Usuarios

Participantes vigentes de la sesión; responsable colectivo como destinatario de avisos de cambio; usuario autorizado que puede solicitar generación manual de Meet después del inicio.

## Historias de usuario

- HU-1. Como participante, quiero recordatorios con la fecha y el enlace vigentes y avisos de cambios para no usar datos caducados.
- HU-2. Como participante, quiero que Meet esté disponible automáticamente sin que un fallo externo impida mi sesión.

## Definiciones

- **Aviso lógico:** notificación identificada de forma estable para un participante y un evento concreto (recordatorio por sesión/horizonte/fecha vigente, o aviso de cambio por evento). Sus reintentos conservan el mismo identificador.
- **Entregado:** confirmado por el proveedor de notificación; solo esta confirmación permite marcar el envío como entregado. Un fallo o resultado ambiguo permanece reintentable y no consume la deduplicación.
- **Deduplicación visible:** el destino de notificaciones actualiza o reemplaza el mismo elemento para un identificador estable, en lugar de añadir otro aviso lógico. Un reintento con resultado ambiguo puede repetir la publicación por transporte; no se garantiza transporte exactamente una vez.
- **Sesión confirmada:** `scheduled` con inicio futuro, tanto al crearse como al reprogramarse.
- **Participante vigente para recordatorio:** participante que cumple el contrato compartido de RF-005.1–RF-005.3 de la hija 005 en el instante de envío; el miembro excluido de una sesión por el caso padre 41 no es destinatario de esa sesión. El responsable no miembro solo recibe aviso de cambio.

## Requisitos funcionales

- **RF-008.1 (RF-5 padre; CA-5.1/5.5; contrato hija 005):** MIENTRAS una sesión esté `scheduled`, EL SISTEMA DEBE recordar a sus participantes vigentes conforme a RF-005.1–RF-005.3 de la hija 005 a 24, 2 y 1 hora, con antelación expresada en zona de sesión. DEBE excluir de destinatarios al miembro del caso padre 41 que fue excluido de esa sesión; que siga activo al envío o a la hora de fin no lo reincorpora. Debe incluir enlace Meet cuando exista e indicar explícitamente cuando aún no existe. Responsable de colectivo que no es miembro no recibe recordatorios ordinarios.
- **RF-008.2 (RF-5 padre; CA-5.2/5.3/5.6; caso padre 43):** EL SISTEMA DEBE producir como máximo un recordatorio lógico visible por participante, sesión, horizonte y fecha vigente, con un identificador estable que no cambie entre intentos. El destino DEBE actualizar o reemplazar el mismo aviso para ese identificador, no añadir otro. Una reprogramación habilita los horizontes de la fecha nueva e invalida los de la anterior. SI el envío falla o su resultado es ambiguo, ENTONCES EL SISTEMA DEBE registrar el intento, dejarlo reintentable con el mismo identificador y marcarlo entregado solo ante confirmación de éxito del proveedor. El reintento de un resultado ambiguo puede repetir la publicación de transporte; no se garantiza transporte exactamente una vez, pero la persona destinataria ve un único aviso lógico.
- **RF-008.3 (RF-5 padre; CA-5.4/5.7/5.8; contrato hija 005):** CUANDO se cancela una sesión, EL SISTEMA DEBE detener sus recordatorios y emitir un único aviso de cancelación. CUANDO se reprograma o cancela, DEBE avisar una sola vez por evento a participantes vigentes conforme a RF-005.1–RF-005.3 y, si es colectiva, a su responsable, con fecha anterior y nueva o motivo, respectivamente, aunque no hubiera recordatorio pendiente; el miembro excluido por el caso padre 41 no pertenece a esos destinatarios para esa sesión. CUANDO se crea o reprograma con menos de 24 horas de antelación, DEBE enviar de inmediato un recordatorio único con fecha/enlace vigentes a participantes elegibles conforme a 005 que no tengan entrega para esa fecha, manteniendo los horizontes 2 h/1 h que aún venzan; el miembro del caso 41 queda excluido también de este envío inmediato.
- **RF-008.4 (RF-9 padre; CA-9.1):** CUANDO una sesión queda confirmada, EL SISTEMA DEBE generar Meet automáticamente. Las sesiones ya `scheduled` y futuras sin enlace al activar la funcionalidad deben incorporarse al mismo proceso; las celebradas o canceladas no se reescriben.
- **RF-008.5 (RF-9 padre; CA-9.2/9.3):** SI generación automática falla por credenciales, timeout o error externo, ENTONCES EL SISTEMA DEBE completar creación/reprogramación sin enlace, registrar el fallo y reintentar automáticamente con cadencia fija y periódica mientras siga `scheduled`, futura y sin enlace. Agotados reintentos o llegada el inicio, queda disponible generación manual posterior. Reintentar si ya existe enlace devuelve el existente sin duplicar evento.
- **RF-008.6 (RF-9 padre; CA-9.4/9.5/9.6/9.7):** CUANDO se reprograma con evento Meet asociado, EL SISTEMA DEBE actualizarlo o recrearlo dejando uno solo activo, con fecha, duración y zona de sesión. DEBE exponer el mismo enlace a participantes en recordatorio, calendario y detalle cuando la sesión no esté `canceled`; mientras no exista, mostrar «pendiente de enlace». El evento se crea/actualiza con fecha-hora local y zona de sesión, nunca zona fija, y sin añadir participantes como invitados del proveedor.
- **RF-008.7 (RF-7 padre; CA-7.4):** CUANDO el sistema consume un evento de cancelación con evento Meet asociado, DEBE solicitar al proveedor su eliminación como efecto externo best-effort, sin bloquear ni revertir la cancelación. DEBE conservar en MIS el valor persistido de `meet_link` como dato histórico y mantenerlo oculto de las respuestas API/recursos mientras la sesión esté `canceled`. SI la eliminación externa falla, ENTONCES DEBE registrar el fallo y mantener tanto el estado cancelado como la ocultación del enlace, sin reactivarlo ni exponerlo. El evento de cancelación y los registros asociados no incluyen `meet_link`.

## Requisitos no funcionales

- RNF-008.1 (RNF-3/10 padre; P14/P20): automatizaciones toleran repetición/fallos, registran fallos diagnosticables sin secretos ni datos personales innecesarios.
- RNF-008.2 (RNF-5 padre/P17): error del proveedor no tumba ni revierte operaciones de negocio; el resultado sin enlace es explícito.
- RNF-008.3 (RNF-9 padre/P4): no se exponen detalles innecesarios y participantes no se agregan al evento externo como invitados.

## Casos límite

- Casos padre 13–18: Meet no configurado o caído, reintento sin duplicar, cancelación con fallo de borrado, recordatorio de fecha antigua, cancelación sin recordatorio pendiente.
- Caso padre 42: tras cancelar, el enlace persiste solo como dato histórico y no se expone por API aunque el borrado remoto falle; el evento de cancelación no contiene `meet_link`.
- Caso padre 29: credenciales ausentes no impiden usar la agenda y el estado es pendiente.
- Casos padre 32/34/39/41: backfill de futuras existentes; creación con menos de 24 horas; alta de miembro elegible con avisos pendientes; alta con sesión propia solapada no recibe recordatorios de esa colectiva.
- Caso padre 43: proveedor acepta potencialmente el aviso, pero se reintenta antes de confirmar la entrega; se conserva el identificador, el destino reemplaza el aviso lógico visible y puede repetirse la publicación de transporte.
- Responsable de colectivo no miembro recibe cambio, pero no recordatorios regulares (CA-5.1/5.7).

## Fuera de alcance

No se sincronizan calendarios externos bidireccionalmente, no se añaden invitados, no se agregan canales de aviso ni proveedores de videollamada, y no se altera el ciclo de vida ni el significado de participantes. No se implementa sugerencia automática de horarios.

## Criterios de finalización

- RF-008.1–RF-008.6 cubren CA-5.1–5.8 y CA-9.1–9.7; RF-008.7 cubre CA-7.4.
- Los avisos usan fecha vigente y el contrato de participantes de 005 (incluida la exclusión del caso padre 41); los fallos/resultados ambiguos no consumen dedupe, conservan el mismo identificador y el destino presenta un único aviso lógico visible aunque se repita la publicación de transporte.
- El enlace se genera/reintenta sin bloquear la agenda y respeta zona y ausencia de invitados; en sesiones canceladas su valor histórico no se expone y el borrado externo best-effort no altera estado ni visibilidad.

## Dudas abiertas

- No hay dudas de producto; D4 y D5 permanecen cerradas. @reviewer debe verificar que CA-5.7 sea el único contrato de avisos de cambio.
