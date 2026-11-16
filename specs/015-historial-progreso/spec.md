# Spec 015 — Historial verificable de progreso

Estado: implementada

**Spec padre aprobada:** `specs/001-progress-tracking-dashboard/spec.md`, v0.5.1 (2026-10-13). Esta spec desglosa RF-19 y sus eventos relacionados ya aprobados en RF-17, RF-18 y RF-20; no crea eventos ausentes ni analítica de tendencias.

## Contexto y objetivo

El estado actual del panel no cuenta por sí solo cómo se llegó a él. Una cronología unificada permite consultar cambios y evidencias verificables de objetivos, actividades/rutas y competencias con las fechas disponibles, sin reconstruir datos ausentes ni inferir causalidad. La retención de los eventos mínimos de eliminación de resultados está bloqueada hasta que se documente la política general de conservación/eliminación.

## Usuarios

- **Participante titular:** consulta su historial individual.
- **Mentor asignado y autorizado:** consulta el historial de los mentees dentro del acceso vigente.
- **Admin con permiso explícito de soporte y auditoría:** consulta solo dentro del permiso vigente.
- **Auditor autorizado:** identifica, exclusivamente mediante la referencia seudonimizada autorizada, al actor de eventos de eliminación de resultados.
- **Miembro o responsable de colectivo:** no accede a historiales individuales a través de agregados.

## Historias de usuario

- HU-1. Como participante, quiero revisar eventos verificables de mi proceso para comprender los cambios y evidencias que constan.
- HU-2. Como mentor autorizado, quiero consultar el historial del sujeto asignado para dar seguimiento informado.
- HU-3. Como usuario autorizado, quiero reconocer eventos incompletos o sin fecha sin que el sistema invente información.

## Definiciones

- **Evento verificable:** existe un registro fuente que acredita el evento; su ausencia no prueba que el evento no ocurrió ni autoriza a reconstruirlo.
- **Evento sin fecha:** registro acreditado sin fecha; se presenta en grupo separado «fecha no disponible» y marcado incompleto.
- **Evento mínimo de eliminación:** representación aprobada en CA-19.7 de la Spec 001: fecha, tipo, metadatos mínimos del vínculo/tipo y actor seudonimizado identificable solo por auditores autorizados; sin respuestas, puntuaciones ni resultado fuente eliminado.

## Requisitos funcionales

- **RF-015.1 (trazabilidad RF-19):** EL SISTEMA DEBE ofrecer una cronología individual unificada de eventos registrados de objetivos, actividades/rutas y competencias, fechas y evidencias disponibles, para el titular y usuarios autorizados conforme a RF-9/RF-12 de la Spec 001. La cronología es un registro de eventos, no un indicador agregado ni serie temporal.
- **RF-015.2 (trazabilidad RF-19):** EL SISTEMA DEBE incluir eventos verificables de objetivos: creación, primer inicio (`en progreso`), cambios de estado —incluidos logrado y abandonado— y evidencias asociadas, anteriores o posteriores a la propuesta solo cuando las fuentes acrediten esos datos.
- **RF-015.3 (trazabilidad RF-19):** EL SISTEMA DEBE incluir eventos verificables de actividades/rutas: inicio, finalización, cambios de estado, evidencias disponibles y creación, cambio o retiro explícito del vínculo reto-módulo. La transición automática de completitud de módulo y su revocación reflejan actor sistema y usuario/acción desencadenantes conforme a RF-17.
- **RF-015.4 (trazabilidad RF-19):** EL SISTEMA DEBE incluir cambios de nivel de competencia efectivamente registrados; evaluaciones vinculadas explícitamente; creación, cambio y retiro de vínculos resultado-competencia; y cambios de estado reales de resultados vinculados mientras la relación está activa, con actor, fecha y estados anterior/resultante cuando estén registrados. Debe respetar cardinalidad de vínculos y no atribuir causalidad ni cambios automáticos de nivel.
- **RF-015.5 (trazabilidad RF-19):** CUANDO un vínculo resultado-competencia se cree, cambie o retire, EL SISTEMA DEBE registrar el evento con actor, fecha, relación anterior y resultante, incluyendo la ausencia para creación/retiro. Un retiro o pérdida de estado completado no borra la relación/evento histórico.
- **RF-015.6 (trazabilidad RF-19):** CUANDO la fuente registre un cambio real de estado de un resultado mientras exista un vínculo activo, EL SISTEMA DEBE registrar un evento inmutable con actor, fecha y estado anterior/resultante, conservando la relación histórica y evitando presentar como vigente un resultado incompleto o eliminado. SI la fuente no permite la transición, ENTONCES EL SISTEMA NO DEBE afirmar que ocurrió ni inventar el evento.
- **RF-015.7 (trazabilidad RF-19):** CUANDO un evento tenga fecha disponible, EL SISTEMA DEBE ordenarlo cronológicamente; SI un registro acredita el evento pero carece de fecha, ENTONCES EL SISTEMA DEBE presentarlo en grupo separado «fecha no disponible» e identificarlo como incompleto, sin asignarle posición temporal inventada.
- **RF-015.8 (trazabilidad RF-19):** SI no existe registro fuente que acredite un evento, ENTONCES EL SISTEMA NO DEBE crear una entrada, afirmar que ocurrió ni afirmar que falta. SI existe el registro pero faltan campos, EL SISTEMA DEBE señalar los campos incompletos y no reconstruir autor, fecha, estado o evidencia.
- **RF-015.9 (trazabilidad RF-19/CA-19.7):** CUANDO una eliminación de resultado afecte la traza, EL SISTEMA DEBE conservar únicamente el evento mínimo aprobado, sin respuestas, puntuaciones ni el resultado fuente. La vista normal no revela identidad del actor; solo un auditor autorizado puede identificarlo mediante la referencia seudonimizada. La retención solo puede implementarse tras identificar y documentar la política general de conservación/eliminación; no se fija plazo.
- **RF-015.10 (trazabilidad RF-19):** EL SISTEMA DEBE autorizar en servidor el historial individual solo para el titular, mentor autorizado o admin con permiso explícito de soporte y auditoría. Ninguna vista agregada de colectivo DEBE revelar eventos, historial o evidencias individuales.

## Requisitos no funcionales

- **RNF-015.1 (P3/P4):** cada consulta de historial valida acceso en servidor; el agregado no permite acceso lateral a datos individuales y el evento de eliminación aplica minimización de datos aprobada.
- **RNF-015.2 (P5/P6/P11):** los eventos relevantes son trazables e inmutables frente a operaciones ordinarias; cada evento se deriva de su registro fuente, distingue faltantes y no se usa para alterar métricas históricas ni generar estimaciones de tendencia.
- **RNF-015.3 (P16):** cronología, grupo sin fecha, incompletitud, estados y errores se presentan de forma comprensible y accesible, con navegación por teclado, foco visible y distinción no dependiente solo del color.
- **RNF-015.4 (rendimiento/responsive):** cuando la consulta de historial forme parte de la lectura interactiva del panel/mapa, rige RNF-5 padre: p95 de respuesta API ≤2 s bajo 50 consultas simultáneas, medido desde recepción en servidor hasta completar respuesta. La interfaz aplicable cumple RNF-7 padre: viewport mínimo 320 px, sin recorte ni scroll horizontal y controles operables con teclado y toque.
- **RNF-015.5 (P19):** pruebas automatizadas verifican eventos presentes/ausentes, orden, fechas faltantes, minimización, permisos y ausencia de filtraciones en agregados.
- **RNF-015.6 (idioma):** el texto nuevo de la interfaz DEBE respetar RF-15 de la Spec 001: idioma activo es/en/pt y fallback al español sin mostrar claves crudas.

## Casos límite

- No hay registro del evento: no se crea entrada ni se declara que falta.
- Existe registro sin fecha: grupo separado «fecha no disponible», incompleto y fuera del orden cronológico.
- Registro con campos incompletos: se señalan faltantes, sin completar autor, fecha, estado o evidencia por inferencia.
- Vínculo de evaluación retirado: desaparece como relación vigente, pero persiste su evento histórico.
- Resultado vinculado incompleto: relación se conserva como incompleta y no es evidencia vigente.
- Fuente no permite cambio de estado: no se inventa transición ni evento.
- Resultado eliminado: solo evento mínimo; sin respuestas, puntuaciones o fuente; identidad oculta en vista normal y reconocible solo por auditor autorizado.
- Política general de conservación/eliminación aún no documentada: no implementar la retención del evento mínimo.
- Historial consultado en vista colectiva: nunca devuelve cronologías ni detalles individuales; sigue vigente la supresión uniforme de agregados.
- Evento de vínculo reto-módulo o completitud/revocación automática: actor y acción desencadenante se representan según la traza de RF-17/RF-20, sin crear asociaciones inferidas.

## Fuera de alcance

- Crear o reconstruir eventos que las fuentes no acreditan; posicionar temporalmente registros sin fecha.
- Tendencias, series temporales, comparación entre periodos, predicción, notificaciones o exportaciones.
- Cambiar reglas de objetivos, retos, competencias, vínculos o permisos definidos por las Specs 012 y 014 y la Spec 001.
- Definir plazo o política de retención; el prerrequisito documental aprobado debe resolverse antes de implementar la retención del evento mínimo.

## Criterios de finalización

- [ ] RF-015.1–RF-015.10 se verifican para tipos de evento, datos incompletos, fechas, autorizaciones y vistas colectivas.
- [ ] Solo se muestran eventos acreditados; fechas ausentes quedan separadas y ningún campo se inventa.
- [ ] Cambios de vínculos y estados relacionados se reflejan con actor, momento y transición disponible conforme a las decisiones padre.
- [ ] Retención de evento de eliminación permanece bloqueada hasta que la política general esté identificada y documentada; cuando se implemente, cumple minimización y acceso audit-only.
- [ ] Se preservan P3/P4/P5/P6/P11/P16 y los permisos de §3.1 sin cambios.
- [ ] Se valida accesibilidad y el criterio responsive de 320 px; el p95 del padre se verifica en las lecturas de panel/mapa que incluyan el historial.

## Trazabilidad RF padre → RF hija

| Spec 001 v0.5.1 | Requisitos de esta spec |
|---|---|
| RF-19, CA-19.1–19.8 | RF-015.1–RF-015.10 |
| RF-17, CA-17.4/17.13/17.14 (eventos de completitud, transiciones y revocación) | RF-015.3, RF-015.10 |
| RF-18, CA-18.6–18.7 (cambios y estado histórico de vínculos) | RF-015.4–RF-015.6, RF-015.9 |
| RF-20, CA-20.4 (traza del vínculo reto-módulo) | RF-015.3, RF-015.10 |

## Dudas abiertas

- La política general de conservación/eliminación debe identificarse y documentarse antes de implementar la retención del evento mínimo de eliminación. Es un prerrequisito aprobado pendiente, no una decisión delegada a implementación ni una duda que esta spec pueda resolver.
  - **Resuelta (2026-11-15):** `docs/politica-retencion.md` v1.0.0 está vigente y la Spec 014 (T014.10–T014.13) ya implementa la retención del evento mínimo. Esta spec solo lo presenta (plan §1 y §3).
