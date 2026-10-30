# Propuesta de desglose — Spec 002

**Estado:** propuesta de desglose; Spec 002 v0.4.2 aprobada; planes y tareas de 002, 008 y 010 aprobados. No implica aprobación integral de este desglose ni de otras hijas.
**Fuente normativa:** `specs/002-mentorship-scheduling/spec.md` v0.4.2 (aprobada).
**Base de planificación:** `plan.md` v1.2.0 y `tasks.md` de la Spec 002, aprobados; planes y tareas de 008 y 010 también aprobados.

## Propósito y límites

La Spec 002 permanece intacta y es la única fuente normativa de producto. Las specs hijas propuestas la particionan por contratos compartidos, reglas de negocio y experiencias consumidoras; no amplían su alcance ni vuelven a decidir D1–D11. Cada hija está en **borrador** y debe pasar Clarificación con @reviewer y aprobación explícita antes de que se redacten su plan y sus tareas. No hay autorización para implementar.

Las ~43 unidades de 20–30 minutos estimadas en el plan padre no caben como tareas atómicas en una sola spec bajo el límite SDD de 10 tareas por spec. Se proponen ocho hijas, con estimaciones de tamaño —no tareas— inferiores a 10 por hija y una suma inicial de ~43 unidades; se ajustará durante la planificación aprobada, sin convertir estos números en backlog.

## Orden propuesto y trazabilidad

| Orden / spec propuesta | Alcance normativo asignado (RF y CA de Spec 002) | Dependencias de producto | Estimación revisada (unidades atómicas de 20–30 min; no tareas aprobadas) |
|---|---|---|---:|
| 003 `agenda-access-timezone` | RF-3 (CA-3.1–3.7); RF-10 (CA-10.1–10.7) | Spec 002 v0.4.2; definición de responsable de Spec 001 | 4 |
| 004 `availability-booking` | RF-1 (CA-1.1–1.6); RF-2 (CA-2.1–2.7); RF-13 (CA-13.1, CA-13.5) | 003; contrato compartido de zona y autorización | 7 |
| 005 `collective-participation` | RF-13 (CA-13.2–13.4) | 003 y 004; inscripción colectiva inicial de 004 | 4 |
| 006 `session-lifecycle-attendance` | RF-8 (CA-8.1–8.9); RF-11 (CA-11.1–11.6) | 003, 004 y 005; contrato de participantes y ancla de fin | 7 |
| 007 `session-reschedule-cancel` | RF-6 (CA-6.1–6.8); RF-7 (CA-7.1–7.6) | 003, 004 y 006; revalidación de reserva y estados | 5 |
| 008 `session-reminders-meet` | RF-5 (CA-5.1–5.8); RF-9 (CA-9.1–9.7) | 003–007; productor de avisos con `notification_id` estable; integración visible final por 010 | 6 + puerta de aceptación integrada en 010 |
| 009 `agenda-calendar` | RF-4 (CA-4.1–4.7) | 003–008; sesiones/estados vigentes y acceso colectivo | 4 |
| 010 `agenda-accessible-interface` | RF-12 (CA-12.1–12.4); RF-5/CA-5.2 vía RF-010.4 (presentación/upsert visible de `SessionReminder`, sin redefinir entrega) | 003–009; contratos funcionales aprobados; productor 008 y su ID estable | 7 |

La dependencia de 006→004/005 evita que el registro de asistencia vuelva a definir quién participa: CA-13.3 fija el ancla de hora de fin, y 005 resuelve qué miembros pertenecen a esa sesión, incluida la exclusión del caso 41. 006 no crea registro ni admite confirmación de asistencia para el miembro excluido. 008 usa el mismo conjunto resuelto por 005 al enviar recordatorios ordinarios e inmediatos; el caso 41 no recibe recordatorios de esa sesión. La dependencia 007→004 garantiza que crear y reprogramar compartan las mismas validaciones; la dependencia 008→007 garantiza que los avisos respondan a eventos reales de cambio. 009 y 010 son capas consumidoras, no fuentes paralelas de sesiones ni de reglas. En concreto, 010 consume el evento privado existente `SessionReminder` y upserta la presentación visible por `notification_id`; no crea canal, API, bandeja persistente ni prescribe un componente.

### Orden de implementación y puerta de aceptación cruzada 008→010

Se conserva el orden de implementación de las hijas: 003→004→005→006→007→008 (productor/estados/reintentos)→009 (calendario)→010 (consumidor/presentación accesible). **RF-008.2 no se acepta finalmente al terminar 008**: su garantía de un solo aviso lógico visible requiere la prueba integrada de 010 que redelivery del mismo `notification_id` reemplaza/actualiza un único aviso, mientras IDs distintos permanecen distintos. La relación 008→010 es una dependencia de validación posterior, no una arista de implementación que reordene las hijas ni un ciclo; no bloquea terminar 009 ni comenzar 010.

### Base de las estimaciones revisadas y límite de tareas

Las estimaciones revisadas de la tabla cuentan unidades de trabajo acotadas a 20–30 minutos, no son backlog ni autorización para planificar/implementar. Un desglose preliminar que cabe en el límite SDD de hasta 10 tareas por hija es: 003, 4 unidades (semántica de zona; lectura/autorización; operaciones de gestión; auditoría y pruebas de denegación); 004, 7 (datos mínimos/auditoría de creación; ventanas; excepciones día/rango; huecos y privacidad; solapes; participación/conflicto colectivo; duplicidad/concurrencia); 005, 4 (alta elegible; exclusión por conflicto del caso 41; bajas prospectivas; ancla de fin y regresión); 006, 7 (transiciones; idempotencia/concurrencia; cierre y terminales; confirmación propia; confirmación responsable y precedencia; registros de asistencia con contrato 005; compatibilidad y permisos); 007, 5 (reprogramación/auditoría; revalidación; eventos y avisos; cancelación/tardía; terminales y fallos externos); 008, 6 (resolución de destinatarios 005; horizontes/dedupe; cambios e inmediato; creación/backfill Meet; reintentos; actualización/eliminación y fallos); 009, 4 (consulta personal; navegación/detalle; estados vacío/error; agenda colectiva/soporte y autorización); 010, 7 (textos/estados i18n; interacción teclado/foco; tamaño/contraste; errores anunciables; reflow/calendario/formularios a 320/1280 px CSS; integración/upsert accesible de avisos; verificación WCAG 2.2 AA).

Este recuento revisado indica que cada hija puede desglosarse en no más de 10 tareas atómicas de 20–30 minutos con el alcance actual; ninguna requiere otra división antes de su plan/tareas. La hija 010 pasa de 6 a 7 unidades por integrar/probar la presentación de `SessionReminder`; la puerta de aceptación de 008 se resuelve con esa tarea y no añade otra unidad de implementación a 008. Al planificar, si la descomposición verificable de cualquier hija supera ese límite, esa hija deberá dividirse antes de redactar su `tasks.md`.

### Contratos compartidos que no se deben partir

- **Acceso y tiempo (003):** fija quién puede ver/gestionar cada recurso y la semántica de zona horaria usada por todas las hijas. Cada hija protege sus operaciones con ese contrato; no replica ni altera sus permisos.
- **Reserva (004):** validación de solapes, disponibilidad, duplicidad y cuota con participación colectiva; un conflicto de un miembro rechaza el hueco para todo el colectivo. Reprogramar reutiliza ese mismo resultado completo.
- **Participación (004→005→006→008):** alta inicial y conflicto de reserva (004), cambios prospectivos y contrato de exclusión del caso 41 (005), registro/asistencia anclados a la hora de fin —sin registro ni confirmación para el miembro excluido— (006), y destinatarios de recordatorios ordinarios e inmediatos resueltos conforme al mismo contrato (008). El caso 41 se decide en 005 y su exclusión se propaga como contrato compartido a 006/008.
- **Estado y cambio (006→007→008):** 006 define el grafo y terminales; 007 admite cambios solamente según RF-6/RF-7; 008 escucha los cambios sin reabrir estados ni inventar notificaciones.
- **Interfaz (009→010) y avisos (008→010):** 009 entrega calendario alimentado por las sesiones autorizadas; 010 asegura idioma, teclado, asistencia tecnológica y uso responsive de todas las superficies, además de consumir `SessionReminder` por el canal privado existente y upsertar presentación por ID. No cambia el backend.

## Matriz completa RF de la Spec 002 → hija

| RF padre | Hija responsable | Cobertura adicional / límite |
|---|---|---|
| RF-1 | 004 | Su uso al reprogramar se consume desde 007; no se duplica la especificación de reserva. |
| RF-2 | 004 | Disponibilidad solo del mentor; consultas de huecos respetan privacidad. |
| RF-3 | 003 | Se consume en todas las visualizaciones y en el evento Meet; no redefine el instante. |
| RF-4 | 009 | Calendario personal y agenda del responsable, sobre la fuente de sesiones existente. |
| RF-5 | 008 | 008 es propietario de despacho, estado y reintentos; 010 cubre la presentación/upsert visible (RF-010.4); aceptación final de RF-008.2 espera la prueba integrada. |
| RF-6 | 007 | Reprogramación enlaza con validación 004, estado/asistencia 006 e integración 008. |
| RF-7 | 007 | Cancelación enlaza con estado 006 e integración 008. |
| RF-8 | 006 | Compatibilidad de indicador con Spec 001, RNF-13. |
| RF-9 | 008 | Creación, actualización, cancelación, reintentos y eventos externos. |
| RF-10 | 003 | Es contrato transversal; las hijas posteriores dependen de él. |
| RF-11 | 006 | Incluye idempotencia de creación/transiciones que se materializa en 004/007. |
| RF-12 | 010 | Idioma y accesibilidad de la agenda completa. |
| RF-13 | 004 (CA-13.1, 13.5); 005 (CA-13.2–13.4) | 006/008 consumen participantes ya resueltos; no trasladan ni duplican RF-13. |

## Matriz de cobertura transversal

- **Constitución:** P1/P2/P17 → 004, 008, 009; P3/P4 → 003 y todas las operaciones hijas; P5 → 006/007 y soporte 003; P6 → 006; P8/P14 → 004–008; P16 → 010; P19 → cada plan futuro; P20 → 008.
- **RNF de Spec 002:** RNF-1 → 003 y operaciones 004–009; RNF-2 → 003 (auditoría de soporte/cambios de zona), 004 (autor y momento de creación ordinaria de sesión), 006 (asistencia) y 007 (reprogramación/cancelación); RNF-3 → 004, 006–008; RNF-4 → 006; RNF-5 → contratos de operaciones 003–009; RNF-6/7/8 → 010; RNF-9 → 003, 004 y 009; RNF-10 → 008; RNF-11 → pruebas de las ocho hijas; RNF-12 → 009; RNF-13 → 006.
- **Fuera de alcance padre:** permanecen fuera en todas las hijas la sincronización bidireccional con calendarios externos, recurrencia, disponibilidad del mentee, penalización económica, nuevos canales, proveedores adicionales, invitados externos, no-show automático, recomendación automática de huecos y zona organizacional (Spec 002 §8).

## Riesgos y temas para revisión

1. La interdependencia entre participación colectiva, asistencia y destinatarios de avisos es el principal riesgo de omisión; se hace explícita en los contratos anteriores y en las dependencias 004→005→006→008.
2. El límite de 10 tareas es por hija, no un permiso para comprimir trabajo en tareas grandes. Las estimaciones son límites de tamaño preliminares; el plan de cada hija deberá desglosar unidades de 20–30 minutos y, si excede 10, proponer otra división antes de tareas.
3. La superficie 010 depende de los contratos de 003–009; el consumo de `SessionReminder` por RF-010.4 ya fue asignado como cambio de alcance aprobado. No anticipa ni altera sus reglas de negocio, estados, autorización o entrega.
4. Las decisiones de diseño abiertas en el plan 002 v1.2.0 siguen siendo decisiones de planificación, no dudas nuevas de producto ni cambios a la Spec 002; deben mantenerse subordinadas a la spec aprobada.
5. Las únicas dudas abiertas en estos borradores son de clarificación/revisión de consistencia y frontera entre entregas; no reabren D1–D11 ni autorizan ampliar el alcance.

## Puerta de aprobación

Esta propuesta y sus ocho specs hijas son borradores. Solicitar revisión de @reviewer y aprobación explícita del usuario por spec (o aprobación explícita del conjunto, identificando sus ocho rutas). Solo después de aprobar una hija se redactarán su `plan.md` y `tasks.md`. Los documentos actuales `plan.md`/`tasks.md` de 002 se conservan sin cambios como propuesta de arquitectura y estimación, no como autorización de implementación.
