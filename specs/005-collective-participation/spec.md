# Spec 005 — Participación dinámica en sesiones colectivas

Estado: implementada

**Spec padre:** `specs/002-mentorship-scheduling/spec.md` v0.4.0, aprobada.
**Dependencias:** hijas 003 (`agenda-access-timezone`) y 004 (`availability-booking`), especialmente la inscripción inicial colectiva y el concepto de miembro activo.
**Alcance:** efectos prospectivos de alta/baja colectiva sobre sesiones que ya estén programadas o celebradas.
**Mapa RF padre → RF hija:** RF-13 (CA-13.2–CA-13.4) → RF-005.1–RF-005.3.

## Contexto y objetivo

Las altas y bajas de un colectivo pueden ocurrir después de programar una sesión. Esta spec determina su efecto prospectivo en la participación para que los avisos y la confirmación de asistencia reflejen la pertenencia vigente sin reescribir sesiones ya celebradas.

## Usuarios

Miembros de familias, cohortes y empresas; responsables y mentores que consultan los participantes de sus sesiones.

## Historias de usuario

- HU-1. Como miembro que se incorpora antes de la sesión, quiero quedar incluido desde mi alta cuando no exista un conflicto de ocupación.
- HU-2. Como miembro que deja el colectivo, quiero dejar de participar prospectivamente sin que se borren mis registros anteriores.

## Definiciones

- **Cambio prospectivo:** afecta participación desde el instante de alta/baja, sin revalidar retroactivamente la sesión colectiva ya existente.
- **Participante registrado:** miembro que pertenece a la sesión colectiva según el contrato de alta inicial y cambios prospectivos y que sigue activo a la hora de fin programada, más el mentor. Un miembro incorporado después de programar la sesión que ya tiene una sesión propia no cancelada ni «no completada» solapada queda excluido de esa sesión concreta conforme al caso padre 41, aunque esté activo a la hora de fin; no se le incorpora retroactivamente si desaparece después el conflicto.

## Requisitos funcionales

- **RF-005.1 (RF-13 padre; CA-13.2 y caso 41):** CUANDO un miembro se da de alta después de programarse una sesión colectiva y antes de celebrarse, EL SISTEMA DEBE comprobar si ese miembro ya tiene una sesión propia no cancelada ni «no completada» cuyo intervalo se solapa con la sesión colectiva. SI no existe ese conflicto, DEBE incluirlo desde el alta en la participación de esa sesión y en los recordatorios pendientes de su fecha vigente, y permitirle confirmar asistencia al abrirse su ventana. SI existe el conflicto, ENTONCES el alta en el colectivo sigue siendo válida y la sesión colectiva no se revalida ni cancela, pero el miembro queda excluido de esa sesión concreta: no se incorpora a su lista de participantes, no recibe sus recordatorios y no puede confirmar asistencia en ella, aunque siga activo a la hora de fin programada. La exclusión de esa sesión no se revierte por cambios posteriores en la sesión propia.
- **RF-005.2 (RF-13 padre; CA-13.3):** CUANDO un miembro deja el colectivo, EL SISTEMA DEBE dejar de enviarle recordatorios y de permitirle confirmar asistencia desde ese momento, conservando íntegramente sus registros existentes. Para la sesión colectiva celebrada, los participantes registrados son los miembros que siguen activos a la hora de fin programada y pertenecen a esa sesión según RF-005.1, más el mentor; una baja posterior no borra esos registros ni altera la pertenencia ya registrada.
- **RF-005.3 (RF-13 padre; CA-13.3/13.4):** MIENTRAS una sesión colectiva haya alcanzado su hora de fin, EL SISTEMA DEBE mantener inalterados sus participantes registrados —anclados a la hora de fin programada y sujetos a la exclusión específica de RF-005.1— y registros de asistencia ante altas o bajas posteriores; una baja no elimina la asistencia y un alta no se incorpora a la sesión pasada.

## Requisitos no funcionales

- RNF-005.1 (RNF-2 padre/P5): cambios de membresía no eliminan ni reescriben trazabilidad o confirmaciones anteriores.
- RNF-005.2 (RNF-1/9 padre): solo las relaciones y miembros autorizados reciben efectos de participación; no se expone información de otros colectivos.
- RNF-005.3 (RNF-11 padre): altas, bajas y reintentos deben poder comprobarse sin alterar la sesión histórica.

## Casos límite

- Caso padre 35: baja posterior a confirmar conserva el registro íntegro.
- Caso padre 39: alta cercana a la sesión incluye avisos pendientes o el aviso inmediato definido por RF-5 padre.
- Caso padre 41: alta con sesión propia que no está `canceled` ni «no completada» y se solapa con la colectiva es válida; no cancela ni revalida la sesión colectiva existente y excluye al miembro de esa sesión de su lista de participantes, recordatorios y confirmación de asistencia, incluso si está activo al llegar la hora de fin.
- Baja antes de la hora de fin y baja posterior a la hora de fin tienen efectos distintos conforme a CA-13.3/13.4.

## Fuera de alcance

No crea ni cancela sesiones, no administra membresías del colectivo ni determina quién puede confirmar por terceros. La inscripción original y el rechazo de reservas con ocupación colectiva pertenecen a 004; la ventana, precedencia y permisos de asistencia a 006; la resolución de destinatarios al momento de envío a 008.

## Criterios de finalización

- RF-005.1–RF-005.3 cubren CA-13.2–13.4, incluidos el ancla a hora de fin y el caso 41.
- Altas y bajas no alteran retroactivamente sesiones ya celebradas y su participación se puede consumir sin redefinirla por 006/008.

## Dudas abiertas

- No hay dudas de producto. Las hijas 006 y 008 consumen el conjunto de participantes registrado conforme a RF-005.1–RF-005.3, incluida la exclusión del caso 41; D7 y el caso 41 no se reabren.
