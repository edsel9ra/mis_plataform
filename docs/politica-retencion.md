# Política general de conservación y eliminación de información

**Ruta:** `docs/politica-retencion.md`  
**Versión:** 1.0.0  
**Estado:** Vigente  
**Aprobada por:** rol `super_admin` de MIS  
**Entrada en vigor:** 2026-11-15  
**Ámbito:** Información persistente, evidencias y registros de auditoría de MIS.  
**Documento relacionado:** `docs/constitution.md`, Constitución Técnica y Funcional de MIS, P4 y P5.  
**Especificaciones relacionadas:** Spec 014 — Evidencia de evaluaciones y competencias; Spec 015 — Historial de progreso.

---

## 1. Propósito

Establecer los criterios que MIS debe aplicar para conservar, minimizar, anonimizar, seudonimizar y eliminar información durante su ciclo de vida.

La política busca conciliar tres necesidades:

- Proteger la privacidad de los participantes.
- Conservar la trazabilidad de las operaciones relevantes.
- Evitar la conservación indefinida de información que ya no sea necesaria.

Esta política desarrolla los principios P4 (Privacidad desde el diseño) y P5 (Trazabilidad de las decisiones) de la Constitución de MIS.

## 2. Principios de conservación y eliminación

### 2.1. Finalidad y necesidad

MIS solo debe conservar información que tenga una finalidad funcional, operativa, contractual, de seguridad o de auditoría identificada.

La existencia de un registro en la base de datos no constituye por sí sola una justificación para conservarlo indefinidamente.

### 2.2. Minimización

Cuando una finalidad pueda cumplirse mediante información reducida, MIS debe conservar la representación mínima necesaria.

La eliminación de un resultado de evaluación no autoriza conservar sus respuestas, puntuaciones o contenido mediante una copia alternativa en el historial.

### 2.3. Trazabilidad

Las operaciones relevantes deben permitir conocer quién las realizó, cuándo ocurrieron y qué cambió, conforme a P5.

La información necesaria para auditoría debe mantenerse separada de la información que se presenta en las vistas ordinarias del participante.

### 2.4. Conservación limitada

Cada categoría de información debe tener:

- Una finalidad de conservación.
- Un responsable.
- Un acontecimiento que inicia el cómputo del plazo.
- Un periodo de conservación aprobado.
- Una acción al finalizar dicho periodo.
- Una regla para las excepciones justificadas.

No se permite establecer una conservación indefinida por defecto.

## 3. Clasificación de la información

MIS debe diferenciar, como mínimo, las siguientes categorías:

| Categoría | Descripción | Tratamiento |
|---|---|---|
| Datos operativos | Usuarios, mentorías, objetivos, sesiones, actividades y registros necesarios para prestar el servicio. | Conservar mientras exista una finalidad legítima y un periodo aprobado aplicable. |
| Resultados de evaluaciones | Resultados individuales, respuestas, puntuaciones e información relacionada. | Aplicar las reglas de conservación y eliminación de resultados. |
| Registros de auditoría | Eventos que documentan actores, fechas y cambios relevantes. | Proteger su integridad y limitar su acceso. |
| Eventos mínimos de eliminación | Representaciones reducidas de operaciones de eliminación que deban aparecer en historiales. | Aplicar minimización, seudonimización y el plazo específico de esta política. |
| Correspondencias de seudónimos | Información que permite relacionar un identificador seudonimizado con un actor real. | Mantener separada y restringida, con el mismo plazo del evento al que corresponde. |
| Copias de respaldo | Copias temporales de información persistente. | Aplicar el ciclo de vida documentado de respaldos y evitar que una restauración reactive eliminaciones ya ejecutadas. |

La implementación de plazos para categorías distintas del evento mínimo requiere que se complete y apruebe el inventario de conservación correspondiente. No deben inferirse plazos para datos que esta propuesta no haya definido.

## 4. Evento mínimo por eliminación de resultados de evaluación

Esta sección establece la regla específica requerida por RF-014.7 y RF-014.8 de la Spec 014.

### 4.1. Información que puede conservarse

Cuando se elimina un resultado de evaluación que tenía vínculos con competencias, MIS podrá conservar exclusivamente un evento mínimo con:

- Fecha y hora de la operación.
- Tipo de evento y cambio realizado.
- Tipo de fuente afectada (`AssessmentResult` o `PersonalityAssessment`).
- Metadatos mínimos del vínculo y de la competencia necesarios para interpretar el acontecimiento.
- Identificador seudonimizado del actor responsable.

El evento no debe conservar el contenido del resultado eliminado.

Alcance concreto de esos campos:

- **Metadatos mínimos:** tipo de fuente, tipo de competencia y su clave (identificador de la etiqueta de skill o letra del factor OCEAN) y tipo de cambio. No incluyen el identificador del resultado eliminado.
- **Participante:** el evento guarda internamente la referencia al participante para poder mostrarse en su propio historial y aplicar el control de acceso. Esa referencia nunca se presenta en la vista ordinaria.
- **Eliminación del participante:** si se elimina la cuenta del participante, sus eventos mínimos y las correspondencias de seudónimo asociadas se eliminan con ella.

### 4.2. Información que debe excluirse

El evento mínimo no debe contener:

- Respuestas de la evaluación.
- Puntuaciones ni resultados calculados.
- Comentarios o contenido detallado de la evaluación.
- Copias o snapshots del resultado eliminado.
- Identificadores directos del participante o del actor en la vista ordinaria.
- Referencias que permitan recuperar el resultado eliminado.

### 4.3. Seudonimización del actor

La identidad real del actor debe mantenerse separada del evento histórico.

La correspondencia entre el seudónimo y la identidad real:

- Solo puede consultarse por auditores expresamente autorizados.
- Debe estar protegida por controles de acceso del servidor.
- Debe registrar cada consulta de identificación para auditoría.
- No debe exponerse a participantes, mentores ni usuarios de la vista ordinaria del historial.
- Debe eliminarse cuando venza el plazo de conservación del evento asociado, salvo una excepción formal aprobada.

La seudonimización no equivale a anonimización irreversible. Por tanto, el evento debe tratarse como información protegida mientras exista una correspondencia que permita identificar al actor.

### 4.4. Auditores autorizados

La identificación del actor se reserva a un permiso dedicado de auditoría, asignado a los roles `super_admin` y `admin`. Este permiso es independiente de los permisos de lectura de soporte del panel de progreso y no se concede a participantes, mentores ni otros roles.

### 4.5. Controles de la correspondencia de seudónimos

- Cada evento recibe un seudónimo aleatorio propio. No se usa un valor derivado de forma determinista de la identidad del actor, de modo que dos eventos del mismo actor no pueden relacionarse entre sí sin consultar la correspondencia.
- La correspondencia seudónimo → actor se guarda separada del evento, en un almacenamiento al que solo accede el servidor mediante el permiso de auditoría.
- Cada consulta de identificación registra quién la hizo, cuándo y sobre qué evento, sin reproducir el contenido del evento.
- El evento y su correspondencia expiran juntos (§5.2).

## 5. Plazo de conservación del evento mínimo

### 5.1. Plazo aprobado

El evento mínimo de eliminación se conserva durante **doce (12) meses**, contados desde la fecha y hora en que se eliminó el resultado de evaluación.

Este plazo es una decisión de producto aprobada expresamente el 2026-11-15. No representa un plazo legal universal ni se deriva de un periodo establecido en la Constitución.

### 5.2. Correspondencia del actor

La correspondencia que permite identificar al actor seudonimizado debe conservarse durante el mismo periodo del evento asociado.

Al vencer el plazo:

- Se elimina el evento mínimo.
- Se elimina la correspondencia de identidad.
- Se comprueba que el evento ya no pueda consultarse mediante el historial ordinario ni mediante la función de auditoría.
- Se registra el resultado de la operación de expiración sin reproducir los datos eliminados.

### 5.3. Ausencia de plazo aprobado

Si una revisión futura retirara o dejara sin efecto el plazo aprobado, MIS debe dejar de crear nuevos eventos mínimos hasta que se apruebe un plazo sustituto.

El plazo de §5.1 fue aprobado en la versión 1.0.0, lo que habilita la implementación del mecanismo de retención del evento mínimo conforme a esta política.

## 6. Separación del registro de auditoría operativo

El evento mínimo de eliminación no sustituye los registros de auditoría de las operaciones ordinarias.

El registro operativo y el evento mínimo cumplen finalidades diferentes:

- El registro operativo conserva la trazabilidad de la operación conforme a P5 y a sus propios controles de acceso y retención.
- El evento mínimo permite representar el acontecimiento en el historial sin conservar el resultado eliminado ni exponer la identidad del actor en la vista ordinaria.

No deben duplicarse respuestas, puntuaciones ni snapshots del resultado eliminado en el evento mínimo.

**Conciliación con CA-19.7 (Spec 001).** La condición de conservar «únicamente» el evento mínimo se aplica a la representación del resultado eliminado en el historial. El registro operativo es una categoría distinta (§3): conserva al actor real y la relación retirada por exigencia de P5, tiene acceso restringido y nunca contiene respuestas, puntuaciones ni snapshots del resultado. Ambos registros coexisten.

Los plazos de los registros operativos deben establecerse en el inventario de conservación y no deben deducirse automáticamente del plazo de doce meses aquí aprobado. Mientras ese inventario no exista, el registro operativo no tiene purga programada.

## 7. Eliminación y respaldos

La eliminación debe contemplar los repositorios persistentes y las representaciones derivadas que puedan conservar información personal.

Las copias de respaldo deben tener un ciclo de vida documentado. Una restauración no debe reintroducir silenciosamente resultados o eventos cuya eliminación ya se haya ejecutado.

Los detalles operativos de purga, expiración de respaldos y recuperación deben documentarse antes de habilitar los procesos automáticos correspondientes.

**Excepción aprobada:** la expiración automática del evento mínimo y de su correspondencia de seudónimo a los doce meses (§5.2) queda habilitada sin esperar a la documentación del ciclo de respaldos. Esa documentación sigue pendiente y no se habilita ningún otro proceso automático de purga hasta completarla.

## 8. Excepciones de conservación

Una conservación superior al plazo ordinario solo puede admitirse cuando exista una justificación documentada, por ejemplo, una obligación legal, contractual o una investigación formal.

Toda excepción debe registrar:

1. La categoría de información afectada.
2. La justificación y finalidad.
3. El responsable que la aprueba.
4. El alcance de la información que se conserva.
5. Los controles de acceso aplicables.
6. La fecha de revisión y, cuando corresponda, de expiración.

Una excepción no autoriza conservar respuestas, puntuaciones ni el resultado eliminado dentro del evento mínimo de RF-014.7.

## 9. Responsabilidades

| Responsabilidad | Asignación |
|---|---|
| Aprobar los plazos de conservación | Rol `super_admin` |
| Administrar su cumplimiento | Rol `super_admin` |
| Ejercer funciones de auditoría (identificación del actor, §4.4) | Roles `super_admin` y `admin`, mediante el permiso dedicado de auditoría |
| Revisar excepciones y solicitudes de conservación adicional | Rol `super_admin` |

## 10. Revisión de la política

La política debe revisarse cuando:

- Cambien las finalidades de tratamiento de información.
- Se incorporen nuevas categorías de datos.
- Cambien las obligaciones legales o contractuales aplicables.
- Se detecten riesgos de privacidad o trazabilidad.
- Se modifiquen los mecanismos de auditoría, eliminación o respaldo.

Cada revisión debe registrar versión, fecha, cambios y responsable de aprobación.

## 11. Condiciones de aprobación

| Condición | Estado (v1.0.0) |
|---|---|
| Aprobación expresa del responsable | Cumplida: rol `super_admin`, 2026-11-15 |
| Validación del plazo de doce meses para el evento mínimo | Cumplida (§5.1) |
| Responsables de conservación y auditoría identificados | Cumplida (§9) |
| Controles de la correspondencia de seudónimos documentados | Cumplida (§4.5) |
| Coherencia del tratamiento de resultados, registros operativos y respaldos con las obligaciones aplicables | Parcial: resultados y registros operativos conciliados (§4, §6); ciclo de respaldos pendiente, con la excepción aprobada en §7 |
| Versión aprobada y fecha de entrada en vigor registradas | Cumplida (encabezado y §13) |

Con estas condiciones, queda satisfecho el prerrequisito documental de RF-014.8 (Spec 014) y RF-015.9 (Spec 015) para implementar la retención del evento mínimo. Esto no habilita plazos para otras categorías de §3.

## 12. Relación con las especificaciones

| Documento | Relación |
|---|---|
| Constitución, P4 | Privacidad desde el diseño y políticas documentadas de conservación y eliminación. |
| Constitución, P5 | Trazabilidad de actores, fechas y cambios, con protección de los registros de auditoría. |
| Spec 014, RF-014.7 | Contenido mínimo y seudonimización del evento de eliminación. |
| Spec 014, RF-014.8 | Prerrequisito documental para implementar la retención del evento mínimo. |
| Spec 015 | Aplicación del evento mínimo en la cronología, respetando las mismas restricciones de retención y privacidad. |

## 13. Registro de versiones

| Versión | Fecha | Cambios | Aprobación |
|---|---|---|---|
| 0.1.0 | 2026-11-15 | Propuesta inicial. | — |
| 1.0.0 | 2026-11-15 | Aprobado el plazo de 12 meses. Se definen el alcance de los metadatos mínimos (§4.1), el permiso de auditoría (§4.4), los controles de seudónimos (§4.5), la conciliación con CA-19.7 (§6), la excepción de purga del evento mínimo (§7) y los responsables (§9). | Rol `super_admin` |

---

**Fin del documento.**