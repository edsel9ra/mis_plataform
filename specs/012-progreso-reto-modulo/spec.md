# Spec 012 — Progreso verificable de retos y módulos

Estado: implementada

**Spec padre aprobada:** `specs/001-progress-tracking-dashboard/spec.md`, v0.5.1 (2026-10-13). Esta spec desglosa únicamente los requisitos aprobados RF-17 y RF-20; no altera el baseline implementado v0.3.0 ni añade decisiones de producto.

## Contexto y objetivo

El porcentaje general de progreso de una ruta, el campo de progreso de un reto y la asistencia no acreditan por sí mismos la completitud de actividades o módulos. Esta ampliación permite tasas conciliables y un estado individual de módulo sustentados exclusivamente en retos con vínculo explícito y estados verificables, y habilita gestionar ese vínculo con autorización y trazabilidad. El mapa visual completo y sus reglas de presentación corresponden a RF-16 de la Spec 001 y a la Spec 013.

## Usuarios

- **Participante titular:** consulta su progreso y gestiona vínculos de sus propios retos.
- **Mentor asignado:** consulta el progreso autorizado del mentee y gestiona los vínculos de sus retos dentro de ese ámbito.
- **Sistema:** registra automáticamente la completitud del módulo y su revocación según los retos vinculados.
- **Auditor autorizado:** consulta trazas conforme a los permisos vigentes; esta spec no amplía dichos permisos.

## Historias de usuario

- HU-1. Como participante, quiero que las tasas de actividad, módulo y ruta reflejen estados explícitos para distinguir logros comprobados de estimaciones.
- HU-2. Como participante o mentor asignado, quiero asociar, cambiar o retirar el vínculo de un reto con un módulo autorizado para que las tasas reflejen relaciones verificables.
- HU-3. Como usuario autorizado, quiero saber cuándo faltan datos para no confundir una tasa incompleta con cero o con completitud.

## Definiciones

- **Reto vinculado aplicable:** `Challenge` con vínculo explícito persistido a `PathModule`, excepto cuando su estado registrado sea `template`, `canceled` o `abandoned`. Un reto aplicable cuyo estado falte o no sea reconocible no se excluye: bloquea la métrica dependiente y se marca incompleto.
- **Reto elegible:** reto vinculado aplicable cuyo estado conocido sea `assigned`, `in_progress` o `completed`.
- **Módulo elegible:** módulo con al menos un reto vinculado elegible y estado conocido en todos sus retos vinculados aplicables. Un estado faltante o desconocido impide calcular la tasa del módulo y bloquea la tasa de ruta que dependa de él; no se omite para producir un resultado parcial.
- «Sin datos», cero e incompleto son resultados distintos: no se sustituye una fuente ausente o estado desconocido por cero.

## Requisitos funcionales

- **RF-012.1 (trazabilidad RF-17):** CUANDO se calcule la tasa de retos vinculados de un ámbito, EL SISTEMA DEBE calcular `retos completed ÷ retos elegibles × 100` únicamente si todos los retos vinculados aplicables de ese ámbito tienen un estado conocido y elegible (`assigned`, `in_progress` o `completed`), mostrando numerador y denominador conciliables; cada reto conserva su estado registrado y no recibe un porcentaje artificial. SI al menos un reto vinculado aplicable carece de estado o tiene un estado desconocido, ENTONCES EL SISTEMA NO DEBE mostrar tasa alguna para ese ámbito ni calcular una tasa parcial a partir de los retos con estado conocido, y DEBE marcar la métrica como incompleta. Los retos cuyo estado conocido sea `template`, `canceled` o `abandoned` se excluyen y no bloquean el cálculo.
- **RF-012.2 (trazabilidad RF-17):** CUANDO se determine la completitud de un módulo, EL SISTEMA DEBE registrarla automáticamente como `completed` solo si existe al menos un reto vinculado elegible y todos los retos vinculados aplicables tienen estado explícito `completed`; ante cualquier estado faltante o desconocido, no debe registrar ni mantener completado el módulo. La traza identifica al sistema y conserva el usuario y la acción desencadenantes.
- **RF-012.3 (trazabilidad RF-17):** CUANDO se calcule el porcentaje de una ruta, EL SISTEMA DEBE dividir los módulos elegibles con completitud individual automáticamente registrada y persistida entre los módulos elegibles, dando el mismo peso a cada módulo y mostrando numerador y denominador conciliables.
- **RF-012.4 (trazabilidad RF-17):** SI falta o es desconocido el estado de cualquier reto vinculado aplicable, ENTONCES EL SISTEMA DEBE marcar como incompleta cada métrica dependiente y no presentar una tasa parcial de actividad, módulo ni ruta; para cada métrica bloqueada no basta con excluir el reto sin estado y calcular con los restantes. EL SISTEMA NO DEBE considerar completado el módulo afectado. Los retos con estado conocido `template`, `canceled` o `abandoned` están expresamente excluidos y su estado no bloquea la métrica.
- **RF-012.5 (trazabilidad RF-17):** SI no hay retos vinculados elegibles en un módulo ni retos vinculados aplicables con estado ausente o desconocido, ENTONCES EL SISTEMA DEBE mostrar «sin datos» y no registrar completitud automática; si hay un estado aplicable ausente o desconocido, se aplica RF-012.4 y el dato se marca incompleto en vez de «sin datos». SI la ruta no tiene módulos elegibles y no hay estados faltantes o desconocidos aplicables, ENTONCES EL SISTEMA DEBE mostrar «sin datos» para la tasa de ruta.
- **RF-012.6 (trazabilidad RF-17):** SI la evidencia disponible se limita al porcentaje general estimado de ruta, al campo de progreso de una actividad o a la asistencia, ENTONCES EL SISTEMA NO DEBE usarla para acreditar completitud de reto o módulo. Los objetivos conservan sin cambios la fórmula de RF-4 de la Spec 001, y estas tasas no alteran el indicador general de actividades de RF-6.
- **RF-012.7 (trazabilidad RF-17):** CUANDO un reto transite a `abandoned`, EL SISTEMA DEBE permitirlo solo desde `assigned` o `in_progress`; CUANDO salga de `abandoned`, DEBE permitir únicamente `assigned` o `in_progress`. EL SISTEMA DEBE rechazar otras transiciones, auditar las permitidas con actor, fecha y estados anterior/resultante, y recalcular las tasas y completitud afectadas. Mientras esté `abandoned` queda excluido; al reactivarse vuelve al denominador y, si no está `completed`, deja de sostener la completitud del módulo dependiente.
- **RF-012.8 (trazabilidad RF-17/RF-20):** CUANDO el participante titular gestione el vínculo reto-módulo de un reto propio, EL SISTEMA DEBE permitir asociarlo, cambiarlo o retirarlo únicamente tras autorizar en el servidor al actor respecto del reto y módulo implicados; para asociar o cambiar, el módulo destino debe pertenecer a una ruta que ese participante pueda consultar por estar asignada o iniciada. Para retirar, antes de modificar debe autorizar también el módulo actualmente vinculado y su ruta actual bajo esas mismas reglas. EL SISTEMA DEBE persistir el resultado explícito.
- **RF-012.9 (trazabilidad RF-20):** CUANDO un mentor asignado gestione el vínculo de un reto de uno de sus mentees asignados, EL SISTEMA DEBE aplicar la autorización vigente tanto al reto como a la ruta autorizada del sujeto. Para asociar o cambiar, el módulo destino debe pertenecer a una ruta incluida en la vista autorizada del sujeto conforme a RF-9 y CA-16.7. Para retirar, antes de modificar debe autorizar también el módulo actualmente vinculado y su ruta actual bajo esas mismas reglas.
- **RF-012.10 (trazabilidad RF-20):** SI el actor no está autorizado respecto del reto, módulo destino o módulo/ruta actuales cuando corresponda, ENTONCES EL SISTEMA DEBE denegar la operación sin revelar ni alterar el vínculo. Toda asociación, cambio o retiro autorizado DEBE dejar traza auditable con actor, momento y vínculo anterior y resultante (o su ausencia).
- **RF-012.11 (trazabilidad RF-17/RF-20):** CUANDO se cree, cambie o retire un vínculo autorizado, EL SISTEMA DEBE recalcular elegibilidad, tasas y completitud afectadas con los vínculos explícitos persistidos. Si se revoca una completitud automática, la transición desde `completed` registra al sistema como actor y al usuario/acción que la desencadenó, con fecha y estados anterior/resultante. Si no quedan retos elegibles ni retos vinculados aplicables con estado ausente o desconocido, el módulo queda «sin datos»; si existe un estado aplicable ausente o desconocido, queda incompleto conforme a RF-012.4.
- **RF-012.12 (trazabilidad RF-17/RF-20):** EL SISTEMA NO DEBE inferir vínculos por título, asistencia, progreso estimado u otros datos indirectos; un reto sin vínculo explícito no participa en las tasas de módulo o ruta. Estos permisos no conceden administración de objetivos ni alteran sus permisos.

## Requisitos no funcionales

- **RNF-012.1 (P3):** cada consulta o mutación protegida valida en el servidor identidad, autorización y pertenencia al reto, participante, módulo y ruta correspondientes; los identificadores manipulados no permiten acceso ajeno.
- **RNF-012.2 (P5/P6/P11):** los cambios relevantes y transiciones automáticas son trazables; cada tasa tiene fuente y regla explícitas, es conciliable con los registros y marca faltantes sin convertirlos en cero.
- **RNF-012.3 (P16):** los estados, errores, ausencia de datos e incompletitud se distinguen de forma comprensible; controles operables con teclado y toque, foco visible y sin depender solo del color.
- **RNF-012.4 (rendimiento/responsive):** las consultas de lectura del panel/mapa que incluyan estas tasas cumplen el RNF-5 padre: p95 de respuesta API ≤2 s bajo 50 consultas interactivas simultáneas, medido desde la recepción en servidor hasta completar la respuesta. La interfaz aplicable cumple RNF-7: viewport mínimo 320 px, sin recorte ni scroll horizontal y controles operables con teclado y toque.
- **RNF-012.5 (P19):** las reglas, transiciones, autorización, denegaciones y estados incompletos se verifican con pruebas automatizadas, incluidos casos límite.
- **RNF-012.6 (idioma):** el texto nuevo de la interfaz DEBE respetar RF-15 de la Spec 001: idioma activo es/en/pt y fallback al español sin mostrar claves crudas.

## Casos límite

- Módulo sin retos vinculados elegibles y sin estado aplicable ausente o desconocido: «sin datos», no completado.
- Reto vinculado aplicable con estado ausente o desconocido: bloquea por completo la tasa de actividad, del módulo y de la ruta dependiente; no se omite para calcular un parcial y el módulo no puede completarse.
- Todos los retos vinculados están excluidos (`template`, `canceled` o `abandoned`): módulo «sin datos».
- Los retos con estado conocido `template`, `canceled` o `abandoned` no bloquean tasas; si todos los retos vinculados están en esos estados, el módulo queda «sin datos».
- Una tasa de ruta sin módulos elegibles y sin estados faltantes aplicables es «sin datos»; un estado ausente o desconocido que afecte cualquier módulo de la ruta impide la tasa de ruta completa, no solo la contribución de ese módulo, y se marca incompleto.
- Reto sin vínculo explícito: queda fuera de las tasas; no se asocia por coincidencia de título, porcentaje, asistencia ni otra señal indirecta.
- Vínculo cambia o se retira y revoca completitud: se registra la transición automática y su desencadenante; sin retos elegibles, el módulo vuelve a «sin datos».
- Un reto `abandoned` solo vuelve a `assigned` o `in_progress`; estos estados lo reincorporan a tasas y no sostienen módulo completado.
- Participante sobre reto ajeno, participante titular que intenta enlazarlo a una ruta no asignada ni iniciada, mentor sobre mentee no asignado o actor sin acceso a ruta/módulo: operación denegada sin alterar ni revelar la relación.
- Objetivos conservan denominador y permisos vigentes de RF-1/RF-4; esta spec no los modifica.

## Fuera de alcance

- Cambiar fórmulas, periodos, estados o permisos aprobados en la Spec 001, salvo el ciclo `abandoned` explícitamente aprobado en RF-17.
- Inferir o crear relaciones de objetivos u otras entidades; modificar objetivos; usar asistencia o progreso estimado como prueba.
- Definir la presentación/secuencia general del mapa (Spec 013), agregados colectivos nuevos (Spec 013), evaluaciones/competencias (Spec 014) o historial unificado (Spec 015), sin perjuicio de las trazas exigidas aquí.
- Notificaciones en tiempo real, exportación, tendencias o configuración de reglas.

## Criterios de finalización

- [ ] RF-012.1–RF-012.12 se verifican con casos normales, incompletos, excluidos, transición y denegación.
- [ ] El cálculo se concilia con retos y módulos elegibles; un estado ausente o desconocido bloquea la tasa completa dependiente (sin parcial), y los estados expresamente excluidos no la bloquean; las ausencias no producen ceros ni completitud.
- [ ] Las mutaciones y transiciones cumplen autorización P3 y trazabilidad P5, incluidas revocaciones automáticas y usuario desencadenante.
- [ ] Se preservan P6/P11, accesibilidad P16, umbral colectivo uniforme y decisiones de objetivos de §3.1 de la Constitución sin modificarlos.
- [ ] Se verifica el p95 aplicable del panel/mapa y el criterio responsive de 320 px del padre.

## Trazabilidad RF padre → RF hija

| Spec 001 v0.5.1 | Requisitos de esta spec |
|---|---|
| RF-17, CA-17.1–17.14 | RF-012.1–RF-012.7, RF-012.11–RF-012.12 |
| RF-20, CA-20.1–20.8 | RF-012.8–RF-012.10, RF-012.11–RF-012.12 |

## Dudas abiertas

- No hay dudas de producto abiertas para el alcance heredado de RF-17/RF-20.
