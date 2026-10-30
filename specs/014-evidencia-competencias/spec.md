# Spec 014 — Evidencia de evaluaciones y competencias

Estado: aprobada

**Spec padre aprobada:** `specs/001-progress-tracking-dashboard/spec.md`, v0.5.1 (2026-10-13). Esta spec desglosa únicamente RF-18 y RF-21, incluidas sus precisiones aprobadas en v0.5.1. No añade relaciones, interpretaciones ni reglas de nivel.

## Contexto y objetivo

Una evaluación puede aportar contexto a una competencia cuando existe una relación explícita, pero no determina automáticamente el nivel registrado. Como los resultados de catálogo y los de personalidad/OCEAN provienen de fuentes diferentes, mostrarlos correctamente preserva la interpretación y la trazabilidad. La retención del evento mínimo tras la eliminación tiene un prerrequisito documental obligatorio aún pendiente.

## Usuarios

- **Participante titular:** consulta sus competencias y resultados vinculados y gestiona esos vínculos.
- **Mentor asignado y autorizado:** consulta el contexto permitido y gestiona vínculos dentro del mismo ámbito autorizado.
- **Auditor autorizado:** identifica al actor seudonimizado de un evento de eliminación; la vista ordinaria no revela su identidad.

## Historias de usuario

- HU-1. Como participante, quiero consultar evaluaciones relacionadas explícitamente con mis competencias para comprender su contexto sin que el nivel se cambie automáticamente.
- HU-2. Como titular o mentor asignado autorizado, quiero crear, cambiar o retirar esos vínculos con trazabilidad para mantener una relación correcta entre evidencia y competencia.
- HU-3. Como usuario autorizado, quiero distinguir una relación histórica incompleta de evidencia vigente.

## Definiciones

- **Resultado de catálogo:** resultado individual completado `AssessmentResult`, con su relación de origen `Assessment`.
- **Resultado de personalidad/OCEAN:** resultado individual completado `PersonalityAssessment`, con su `test_version` registrado; no requiere un `Assessment`.
- La relación resultado-competencia es explícita y muchos-a-muchos.
- **Retención de eliminación:** conservar exclusivamente el evento mínimo aprobado, sujeta a que antes se identifique y documente la política general de conservación/eliminación. No se fija plazo.

## Requisitos funcionales

- **RF-014.1 (trazabilidad RF-18/RF-21):** CUANDO exista una relación explícita entre una competencia y un resultado individual completado, EL SISTEMA DEBE mostrarlo como evidencia/contexto de esa competencia, con los datos contextuales y fecha disponibles, sin modificar automáticamente su nivel.
- **RF-014.2 (trazabilidad RF-21):** EL SISTEMA DEBE representar resultados de catálogo como `AssessmentResult` individual completado conservando su `Assessment`, y resultados de personalidad/OCEAN como `PersonalityAssessment` individual completado conservando `test_version`. EL SISTEMA NO DEBE inventar ni exigir un `Assessment` para un resultado OCEAN.
- **RF-014.3 (trazabilidad RF-18/RF-21):** EL SISTEMA DEBE permitir que un resultado individual completado se vincule con varias competencias y que cada competencia se vincule con varios resultados individuales completados, sin exclusividad.
- **RF-014.4 (trazabilidad RF-18):** CUANDO el participante titular o su mentor asignado y autorizado cree, cambie o retire un vínculo evaluación-competencia, EL SISTEMA DEBE validar en el servidor el ámbito y permisos vigentes, persistir la relación vigente y auditar el actor, fecha y relación anterior y resultante; creación y retiro registran explícitamente la relación ausente correspondiente.
- **RF-014.5 (trazabilidad RF-18/RF-21):** SI no existe un vínculo registrado, ENTONCES EL SISTEMA NO DEBE inferir ni presentar una evaluación como evidencia de una competencia. Completar o cambiar una evaluación NO DEBE modificar por sí mismo el nivel de competencia.
- **RF-014.6 (trazabilidad RF-18/RF-21):** MIENTRAS un resultado vinculado deja de estar completado, EL SISTEMA DEBE conservar la relación histórica, identificarla como incompleta y no presentarla como evidencia vigente.
- **RF-014.7 (trazabilidad RF-18/RF-21/CA-19.7):** CUANDO se elimina un resultado que afecta el historial, EL SISTEMA DEBE conservar únicamente el evento mínimo: fecha, tipo de cambio, metadatos mínimos del vínculo/tipo necesarios para auditoría y referencia seudonimizada del actor identificable solo por auditores autorizados. La vista normal NO DEBE revelar la identidad del actor ni conservar respuestas, puntuaciones o el resultado fuente eliminado ni presentarlo como evidencia vigente.
- **RF-014.8 (prerrequisito de retención):** SI la política general de conservación/eliminación no está identificada y documentada, ENTONCES EL SISTEMA NO DEBE implementar la retención del evento mínimo de eliminación. La implementación no fija ni decide un plazo. Esta condición bloquea únicamente la retención indicada, no autoriza una política alternativa ni cambia la respuesta funcional aprobada.
- **RF-014.9 (trazabilidad RF-18):** EL SISTEMA DEBE respetar autorización, historial individual y entitlements vigentes de competencias/evaluaciones de RF-7, RF-9 y RF-12 de la Spec 001; no se otorga acceso por esta relación.

## Requisitos no funcionales

- **RNF-014.1 (P3/P4):** cada consulta y mutación valida en servidor identidad, relación de mentoría y acceso al titular, resultado y competencia; respuestas y puntuaciones no se exponen fuera del ámbito autorizado ni se retienen en el evento mínimo de eliminación.
- **RNF-014.2 (P5/P6/P9/P11):** vínculos y cambios son auditables; el origen del resultado es explícito, reproducible según la fuente existente y no se atribuye causalidad ni cambio de nivel sin registro explícito.
- **RNF-014.3 (P16):** fuente, estado vigente/incompleto, ausencia de datos y errores se comunican accesiblemente; controles operables con teclado y toque, foco visible y estados distinguibles más allá del color.
- **RNF-014.4 (rendimiento/responsive):** cuando estos datos se consulten en lecturas interactivas del panel/mapa, rige el RNF-5 padre: p95 de respuesta API ≤2 s bajo 50 consultas simultáneas, con medición desde recepción en servidor hasta completar respuesta. La interfaz aplicable cumple RNF-7 padre: 320 px mínimo, sin recorte ni scroll horizontal y operable con teclado y toque.
- **RNF-014.5 (P19):** pruebas automatizadas cubren fuentes, cardinalidad, autorizaciones, historial incompleto y datos sensibles omitidos.
- **RNF-014.6 (idioma):** el texto nuevo de la interfaz DEBE respetar RF-15 de la Spec 001: idioma activo es/en/pt y fallback al español sin mostrar claves crudas.

## Casos límite

- Sin vínculo explícito: no se presenta evaluación como evidencia de la competencia.
- Resultado de catálogo: conserva referencia `Assessment`; resultado OCEAN: conserva `test_version`, sin inventar instrumento de catálogo.
- Un resultado se relaciona con varias competencias, o varias evaluaciones con una competencia, sin colapsar ni excluir vínculos.
- Resultado vinculado deja de estar completado: relación histórica incompleta, no evidencia vigente.
- Resultado eliminado: evento mínimo únicamente; actor seudonimizado solo identificable por auditor autorizado; vista ordinaria sin identidad, respuestas, puntuaciones ni resultado fuente.
- Retiro explícito del vínculo: elimina la relación vigente, no el evento histórico de la operación.
- Actor no titular ni mentor asignado autorizado: operación denegada y vínculo sin cambios.
- Cambio de evaluación: no modifica automáticamente el nivel de competencia.
- Política de retención no documentada: no implementar retención del evento mínimo; no inventar plazo.

## Fuera de alcance

- Cambiar niveles automáticamente, reinterpretar puntuaciones o inferir vínculos por tipo de evaluación, resultado o módulo.
- Crear nuevas fuentes de evaluación, instrumentos, competencias o permisos.
- Conservar respuestas, puntuaciones o el resultado eliminado en el evento mínimo.
- Definir la política general de conservación/eliminación o su plazo; su identificación y documentación es prerrequisito externo a esta spec.
- Historial cronológico unificado, que se especifica en RF-19 (Spec 015); aquí se establecen las relaciones, conducta y eventos que lo alimentan.

## Criterios de finalización

- [ ] RF-014.1–RF-014.9 se verifican para fuentes de catálogo y OCEAN, cardinalidades y casos autorizados/denegados.
- [ ] Un resultado incompleto nunca aparece como evidencia vigente ni actualiza por sí solo un nivel.
- [ ] Evento de eliminación, cuando su retención esté habilitada, cumple la minimización aprobada y la separación de identidad audit-only; la retención queda bloqueada hasta documentar la política general.
- [ ] Se preservan P3/P4/P5/P6/P9/P11/P16, permisos y entitlements de la Spec 001.
- [ ] Se verifica el criterio de responsive de 320 px y el p95 aplicable a lecturas de panel/mapa del padre.

## Trazabilidad RF padre → RF hija

| Spec 001 v0.5.1 | Requisitos de esta spec |
|---|---|
| RF-18, CA-18.1–18.7 | RF-014.1, RF-014.4–RF-014.9 |
| RF-21, CA-21.1–21.5 | RF-014.2–RF-014.3, RF-014.6–RF-014.8 |
| RF-19, CA-19.7 (solo política de minimización/retención que condiciona los eventos de resultados) | RF-014.7–RF-014.8; la cronología se cubre en Spec 015 |

## Dudas abiertas

- La política general de conservación/eliminación debe identificarse y documentarse antes de implementar la retención del evento mínimo. Es un prerrequisito aprobado pendiente, no una decisión delegada a implementación ni una duda que esta spec pueda resolver.
