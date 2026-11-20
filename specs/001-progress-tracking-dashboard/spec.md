# Spec 001 — Panel de progreso y seguimiento

**Estado:** implementada
**Versión:** 0.5.1
**Baseline funcional previo:** v0.3.0 — implementada; ampliaciones aprobadas v0.4.0, v0.5.0 y v0.5.1 — pendientes de implementación
**Fecha de aprobación:** 2026-10-13
**Ámbito:** actualización aprobada de producto (el QUÉ y el POR QUÉ; sin decisiones de implementación)
**Referencias:** `docs/constitution.md` (principios P1–P20; los aplicables se citan en el texto) y sus **decisiones de producto §3.1** (doc v1.1.0)
**Naturaleza:** esta spec describe **qué** debe hacer la funcionalidad y **por qué**. No define endpoints, esquemas de base de datos ni componentes concretos; esos son decisiones de diseño posteriores.

---

## 1. Contexto y objetivo

### 1.1 Contexto (de dónde partimos)

El baseline v0.3.0 ya integra datos de varias fuentes. Persisten diferencias entre lo que las fuentes modelan hoy y lo que requiere el mapa propuesto:

- **Objetivos:** el baseline v0.3.0 ya los gestiona como entidades `Objective`, con evidencias y estados; también permanecen los campos de texto libre en relaciones y retos.
- **Sesiones:** existen con estado (`scheduled`, `in_progress`, `completed`, `canceled`) y fecha (`scheduled_at`).
- **Actividades y rutas:** existen retos (`Challenge`, con estado, `progress`, `due_at`, `completed_at`) y progreso por usuario/ruta (`UserLearningProgress`, con estado y porcentaje de ruta). Los módulos (`PathModule`) tienen orden y recursos, pero no estado individual persistido; los retos no tienen actualmente una relación persistida con rutas o módulos.
- **Competencias:** existen como skills con nivel (`UserSkill` + `SkillTag`, enum `SkillLevel`) y como dimensiones de personalidad OCEAN.
- **Evaluaciones:** los resultados de catálogo (`AssessmentResult`) se ligan a `Assessment`; los resultados de personalidad/OCEAN se guardan en `PersonalityAssessment` con `test_version`. En los modelos revisados no existe relación explícita entre una evaluación y una competencia.
- **Compromisos:** existen como sesiones futuras programadas y fechas límite de retos.
- **Colectivos:** existen `FamilyGroup`, `Cohort` y `Company`, cada uno con miembros y una relación de mentoría polimórfica (`source` → User | FamilyGroup | Cohort | Company).

El baseline v0.3.0 ya ofrece una vista unificada de progreso individual, de seguimiento autorizado del mentor y agregados colectivos. Aún no ofrece el mapa de secuencia de aprendizaje con completitud verificable por módulo, la presentación contextual de evaluaciones relacionadas con competencias ni el historial cronológico unificado propuestos aquí.

### 1.2 Objetivo (qué queremos y por qué)

Ampliar el **panel de progreso y seguimiento v0.3.0** para que, apoyándose en los datos ya registrados y sin duplicar las funciones existentes, presente de forma clara y verificable:

1. Lo ya incluido en v0.3.0: **objetivos** y su grado de logro; **sesiones realizadas**; **actividades pendientes/vencidas**; **competencias** (skills y perfil OCEAN); **próximos compromisos**; seguimiento autorizado y agregados de colectivo.
2. Lo aprobado en v0.4.0 y actualizado en esta revisión: **secuencia visual de rutas y módulos**, gestión explícita y auditable del vínculo reto-módulo, tasas verificables de actividad/módulo/ruta, evaluaciones relacionadas como contexto y un historial cronológico unificado.

El panel debe funcionar **por persona** y también como **indicador agregado de colectivo** (familia, grupo/grupo de formación, empresa).

**Por qué:** una mentoría solo es medible y confiable si el avance se deriva de evidencias registradas y no de una impresión. El panel convierte datos ya existentes en seguimiento accionable, da al mentor visibilidad de su cartera y da a los responsables de colectivos una lectura agregada de su organización, **sin** exponer información individual indebida.

### 1.3 Principios rectores de esta spec

- **P6 (progreso basado en evidencias):** cada indicador tiene definición, fuente y regla documentadas; una sesión programada **no** cuenta como realizada; el progreso no se manipula por razones visuales; los datos incompletos se identifican.
- **P11 (indicadores y reportes coherentes):** definiciones de métricas compartidas y conciliables con sus registros de origen.
- **P3 (autorización en cada operación):** la visibilidad se controla en el servidor; ocultar un botón no autoriza nada.
- **P4 (privacidad desde el diseño):** los agregados de colectivo no deben revelar datos individuales a otros miembros.
- **P5 (trazabilidad):** los cambios relevantes de objetivos y estados quedan registrados.
- **P16 (experiencia accesible):** estados de carga, error, ausencia de datos y navegación por teclado.

### 1.4 Alcance de la v1 (resumen)

Incluye (baseline v0.3.0): entidad de objetivo de primera clase; panel individual del mentee; panel del mentor sobre sus mentees; agregados de colectivo para responsables y miembros; cinco bloques de indicadores individuales más la vista agregada de colectivo; autorización y privacidad; i18n y accesibilidad. Los requisitos aprobados de v0.4.0 añaden un mapa de rutas de aprendizaje con porcentajes de actividad/módulo/ruta calculados desde estados verificables, evaluaciones relacionadas como contexto y un historial cronológico unificado. Esta actualización v0.5.0 modifica la política anterior de no crear relaciones para permitir gestionar explícitamente el vínculo reto-módulo, conservando los permisos y definiciones ya vigentes.

Excluye (ver §7): tiempo real, exportación de reportes, IA predictiva sobre progreso, analítica de tendencias/series temporales y configuración de reglas por el administrador. El historial de eventos trazables propuesto no constituye una serie temporal ni un análisis de tendencias.

### 1.5 Decisiones cerradas con el cliente (2026-10-11)

Decisiones **vinculantes**, registradas en `docs/constitution.md` §3.1 (doc v1.1.0); esta spec se alinea con ellas:

1. **Permisos de objetivos:** el mentor administra los objetivos individuales; el mentee propone objetivos y aporta evidencias; el responsable autorizado administra los objetivos colectivos.
2. **Ámbito de objetivos:** se admiten objetivos individuales y colectivos, con titular o colectivo responsable obligatorio según corresponda.
3. **Sesiones realizadas:** cuentan solo las de estado `completed` **con asistencia confirmada**.
4. **Periodos predeterminados:** próximos 7 días para compromisos; estado actual para objetivos; mes calendario actual para sesiones realizadas.
5. **Privacidad colectiva:** umbral inicial de 3 participantes elegibles para mostrar agregados, aplicado de forma uniforme a toda vista (miembro, responsable o mentor), con reglas explícitas de pertenencia y tratamiento explícito de datos faltantes.

### 1.6 Decisiones de producto aprobadas en v0.5.0 y v0.5.1

Las decisiones de esta sección se aprobaron en v0.5.0 y v0.5.1 el 2026-10-13 y no alteran las decisiones vinculantes anteriores ni `docs/constitution.md` §3.1. La precisión sobre las fuentes concretas de los resultados incluida en el punto 5 quedó aprobada en v0.5.1.

1. **Mapa:** presentar únicamente la secuencia de módulos existentes en el orden de la ruta; no crear etapas, divisiones ni cortes nuevos.
2. **Acceso y visibilidad del mapa:** la vista del mapa es independiente y se accede desde el panel de progreso. El participante ve rutas asignadas o en las que ya inició progreso; el mentor autorizado ve las rutas de la vista autorizada del sujeto. No se añaden supuestos de visibilidad para administradores fuera del permiso de soporte vigente.
3. **Relaciones del mapa:** incluir objetivos y actividades solo bajo la ruta/módulo con el que ya tengan una relación registrada; no inferir relaciones. Se permite crear, cambiar o retirar explícitamente el vínculo entre un reto y un módulo conforme a RF-20; esta capacidad no crea vínculos para otras entidades ni cambia la política de objetivos.
4. **Actividades y tasas de actividad/módulo/ruta:** para estas tasas, una actividad es un `Challenge` vinculado explícitamente a un `PathModule`; no se incluyen otros tipos de registro. Los estados elegibles son `assigned`, `in_progress` y `completed`; `template`, `canceled` y `abandoned` se excluyen. Se incorpora `abandoned` al ciclo de estados del reto: se permite transitar a él desde `assigned` o `in_progress`, y salir de él hacia `assigned` o `in_progress`. Cada transición permitida es trazable. Mientras está `abandoned` se excluye de las tasas; al salir hacia cualquiera de los dos estados elegibles vuelve al denominador, y el estado resultante determina el recálculo de tasas y completitud de módulo según RF-17. Solo se calculan tasas con estado conocido en todas las actividades aplicables; si falta alguno, no se presenta una tasa parcial y se marca incompletitud. El estado completado del módulo se registra automáticamente según RF-17; módulo sin actividades vinculadas elegibles = «sin datos».
  5. **Evaluaciones y competencias:** las evaluaciones del catálogo y las de personalidad/OCEAN pueden mostrarse como contexto únicamente cuando exista una relación explícita entre una competencia y un resultado individual completado. El participante titular o su mentor asignado y autorizado puede crear, cambiar o retirar cada vínculo con el mismo ámbito y autorización vigentes; cada operación se audita y se registra en el historial con actor, fecha y relación anterior/resultante. No se infiere la relación por el tipo, resultado o módulo, ni se modifica automáticamente el nivel de competencia. La relación es muchos-a-muchos y conserva representación histórica si el resultado deja de estar completado o se elimina. Si el resultado se elimina, se conserva un evento con fecha, tipo de cambio y solo los metadatos mínimos del vínculo/tipo necesarios para auditoría, sin respuestas, puntuaciones ni el resultado fuente. El actor se registra mediante una referencia seudonimizada identificable únicamente por auditores autorizados; la vista normal del historial no revela su identidad. Antes de implementar la retención del evento debe identificarse y documentarse la política general de conservación/eliminación, sin que la implementación decida un plazo.
6. **Historial:** cronología unificada de objetivos, actividades/rutas y competencias. Incluye eventos anteriores solo cuando fuentes existentes acrediten el evento; los registros sin fecha se agrupan aparte y se marcan incompletos. No reconstruye eventos ausentes.
7. **Agregados nuevos:** cada tasa colectiva de actividad/ruta es el promedio de los porcentajes individuales definidos para esa misma métrica. Las tasas de ruta solo se agrupan entre miembros con la misma ruta y versión. Se mantiene la base general de participantes elegibles vigente para el umbral uniforme 3; además, cada promedio nuevo requiere al menos 3 porcentajes individuales calculables que contribuyan. Quien carezca de métrica individual sigue contando en la base general, pero no suma cero ni participa en ese promedio. El agregado se suprime si cualquiera de las dos bases aplicables es inferior a 3.
  8. **Trazabilidad automática y solicitudes de eliminación:** la transición automática de módulo identifica al sistema como actor y conserva el usuario/acción que la disparó. Ante una solicitud de eliminación de un resultado que afecte el historial, se conserva únicamente un evento con fecha, tipo de cambio y metadatos mínimos del vínculo/tipo que permitan auditoría, sin respuestas, puntuaciones ni el resultado fuente. El actor queda referido por un seudónimo identificable únicamente por auditores autorizados; la vista normal del historial no revela su identidad. Antes de implementar la retención de ese evento, la política general de conservación/eliminación debe estar identificada y documentada; esta spec no fija un plazo y la implementación no puede decidirlo por sí misma.
9. **Rendimiento y responsive:** se define «carga normal» como 50 usuarios que realizan consultas interactivas simultáneamente. Bajo esa carga, las consultas interactivas de lectura del panel/mapa completan en ≤ 2 s (p95), medido desde la recepción de cada solicitud en el servidor hasta completar su respuesta de API. El viewport mínimo es 320 px; todos los controles permanecen visibles, sin recorte ni scroll horizontal, y son operables con teclado y toque.
10. **Vínculo reto-módulo:** el participante puede asociar, desasociar o cambiar el vínculo de sus propios retos; el mentor asignado puede hacerlo para retos de sus mentees asignados. El módulo destino debe pertenecer a una ruta que el usuario pueda consultar y que esté asignada o iniciada para el participante; al actuar el mentor, se aplica el ámbito de rutas autorizado del sujeto. Cada operación requiere autorización en servidor tanto sobre el reto como sobre el módulo destino; para retirar el vínculo, también se valida antes de modificarlo la autorización del actor sobre el reto y sobre el módulo actualmente vinculado y su ruta actual. La operación persiste el vínculo explícito y deja traza auditable. La relación ausente no se infiere y no contribuye a las métricas de módulo; un módulo sin retos vinculados elegibles permanece «sin datos».

**Precisión aprobada en v0.5.1:** a partir de la inspección de las fuentes existentes, los resultados de catálogo se representan con `AssessmentResult` y conservan su relación con `Assessment`, mientras que los resultados de personalidad/OCEAN se representan con `PersonalityAssessment` y conservan `test_version`. La relación es muchos-a-muchos. Si el resultado deja de estar completado, el vínculo se conserva como historial y se muestra incompleto, nunca como evidencia vigente. Si se elimina, se conserva únicamente un evento con fecha, tipo de cambio y metadatos mínimos del vínculo/tipo necesarios para auditar, sin respuestas, puntuaciones ni el resultado fuente. El actor se representa mediante un seudónimo identificable únicamente por auditores autorizados; el historial normal no revela la identidad. Antes de implementar su retención, la política general de conservación/eliminación debe estar identificada y documentada; no se fija un plazo y la implementación no puede decidirlo por sí misma. Estos detalles amplían, sin atribuirlos retroactivamente, la decisión aprobada en v0.5.0.

### 1.7 Definiciones para esta actualización aprobada

- **Estado verificable de actividad/módulo:** estado explícito y persistido por la fuente de esa unidad. Solo `completed` cuenta como completo; la ausencia de estado se identifica como dato incompleto y no equivale a cero ni a completitud.
- **Actividad elegible para las tasas de mapa:** un `Challenge` con vínculo explícito a un `PathModule` cuyo estado registrado es `assigned`, `in_progress` o `completed`. `template`, `canceled` y `abandoned` se excluyen del denominador y de la regla de completitud automática. Un reto vinculado cuyo estado no está disponible causa dato incompleto e impide calcular una tasa aplicable; no se calcula parcialmente. El estado `abandoned` forma parte del ciclo de estados propuesto: solo se permite transitar a él desde `assigned` o `in_progress`, y desde `abandoned` solo se permite volver a `assigned` o `in_progress`. Al salir de `abandoned`, el reto vuelve a ser elegible para las tasas y la regla de completitud automática.
- **Completitud automática de módulo:** estado `completed` individual persistido automáticamente cuando existe al menos un `Challenge` vinculado elegible y todos los retos vinculados no excluidos tienen estado explícito `completed`. Si no hay actividades vinculadas elegibles, el módulo queda «sin datos»; si algún estado aplicable falta, queda incompleto y no se registra como completado.
- **Porcentaje de actividades:** proporción de `Challenge` vinculados elegibles completados respecto de los retos vinculados elegibles en el ámbito calculado. Para calcularlo todos los retos aplicables deben tener estado conocido; si falta alguno, se marca incompleto y no se muestra una tasa parcial. Cada actividad se presenta con su estado registrado, no con un porcentaje artificial.
- **Historial cronológico unificado:** consulta conjunta de eventos registrados de objetivos, actividades/rutas y competencias, ordenados por su fecha; no es una visualización analítica de tendencias.
- **Evento histórico verificable:** existe un registro que acredita que el evento ocurrió. Si no existe registro, no se crea una entrada ni se afirma que ocurrió un evento faltante; si el registro existe pero le faltan campos, se identifica como incompleto el dato faltante. Los registros sin fecha van en un grupo separado «fecha no disponible» y se marcan incompletos; no se les asigna posición cronológica inventada.
- **Evento de eliminación con actor seudonimizado:** evento mínimo que conserva el tipo y fecha del cambio, los metadatos mínimos del vínculo/tipo necesarios para auditoría y una referencia seudonimizada del actor que solo puede identificar un auditor autorizado. La vista normal del historial nunca revela la identidad del actor. No conserva respuestas, puntuaciones ni el resultado fuente eliminado. La referencia permite cumplir P5 sin exponer la identidad en el historial ordinario; no constituye una excepción a P5.
- **Vínculo explícito reto-módulo:** asociación persistida entre un `Challenge` y un `PathModule`, creada o modificada mediante una operación autorizada. Si no existe, no se deduce de otros datos ni se incluye el reto en métricas de módulo/ruta.
- **Vínculo evaluación-competencia:** relación explícita entre una competencia y un resultado individual completado; la relación es muchos-a-muchos: un resultado puede vincularse a varias competencias y cada competencia a varios resultados. Los resultados de catálogo son `AssessmentResult` y conservan su `Assessment`; los de personalidad/OCEAN son `PersonalityAssessment` y conservan `test_version`. Si el resultado deja de estar completado, el vínculo se conserva como historial y se identifica como incompleto; si se elimina, solo se conserva el evento mínimo definido arriba: fecha, tipo de cambio y metadatos mínimos del vínculo/tipo necesarios para auditoría, además del actor seudonimizado identificable únicamente por auditores autorizados. El historial normal no revela la identidad. No se conservan respuestas, puntuaciones ni el resultado fuente eliminado; en ningún caso se presenta como evidencia vigente. Antes de implementar la retención del evento, la política general de conservación/eliminación debe estar identificada y documentada; no se fija plazo. La creación, cambio, retiro y los cambios de estado relevantes generan trazabilidad, sujeta a minimización de datos personales.

---

## 2. Usuarios

| Usuario | Descripción | Qué ve en el panel | Por qué (necesidad) |
|---|---|---|---|
| **Mentee** | Persona en proceso de mentoría (rol `mentee`, o `employee` con proceso asignado). **Propone objetivos individuales y aporta evidencias.** | Su panel individual completo. | Saber cómo avanza y qué le toca hacer. |
| **Mentor** | Acompaña a uno o varios mentees (rol `mentor`). **Administra los objetivos individuales de su cartera.** | El panel individual de cada mentee que tenga asignado. | Detectar bloqueos, pendientes y vencimientos de su cartera. |
| **Responsable de colectivo** | `company_admin` (empresa), responsable de grupo familiar (`FamilyGroup.head`) o coordinador de cohorte. **Administra los objetivos colectivos de su ámbito.** | Indicador **agregado** de su colectivo, sujeto al umbral de 3 participantes elegibles. | Ver el avance de su organización sin entrar en el detalle de cada persona. |
| **Miembro de colectivo** | Persona que pertenece a una familia, grupo o empresa. | Indicador **agregado** del colectivo al que pertenece, sujeto al umbral de 3 participantes elegibles. | Sentido de pertenencia y transparencia dentro del grupo. |
| **Admin de plataforma** | `super_admin` / `admin`. | Acceso de soporte/lectura mediante permiso explícito (P3), con registro de auditoría si aplica. | Diagnóstico y soporte operativo. |

**Nota de privacidad:** el agregado para miembros se limita a métricas colectivas; **no** expone indicadores individuales de otras personas (ver RNF-2). El **umbral de 3 participantes elegibles** se aplica de forma uniforme a toda vista (miembro, responsable o mentor): por debajo del umbral, el agregado se suprime también para el responsable del colectivo (decisión §3.1.5).

---

## 3. Historias de usuario

- **HU-1 (Mentee):** como mentee, quiero ver mis objetivos con su estado y mi porcentaje de objetivos logrados, para saber si avanzo según lo acordado.
- **HU-2 (Mentee):** como mentee, quiero ver cuántas sesiones he realizado de verdad y cuáles tengo programadas, para distinguir lo hecho de lo previsto.
- **HU-3 (Mentee):** como mentee, quiero ver mis actividades pendientes y vencidas, para priorizar mi trabajo entre sesiones.
- **HU-4 (Mentee):** como mentee, quiero ver mis competencias (skills y perfil OCEAN), para reconocer en qué he crecido.
- **HU-5 (Mentee):** como mentee, quiero ver mis próximos compromisos (sesiones y vencimientos), para organizarme.
- **HU-6 (Mentor):** como mentor, quiero ver el panel de cada mentee asignado, para dar seguimiento informado en la sesión.
- **HU-7 (Mentor):** como mentor, quiero identificar rápidamente a los mentees con pendientes vencidos o sin actividad, para intervenir a tiempo.
- **HU-8 (Responsable de colectivo):** como responsable, quiero ver el avance agregado de mi familia/grupo/empresa, incluidas las tasas colectivas de actividad/ruta cuando cumplan los umbrales de privacidad, para tomar decisiones de acompañamiento sin fiscalizar a cada persona.
- **HU-9 (Miembro):** como miembro, quiero ver el avance agregado de mi colectivo, incluidas las tasas colectivas de actividad/ruta cuando cumplan los umbrales de privacidad, para situarme en el proceso común sin acceder a datos individuales.
- **HU-10 (Todos):** como usuario autorizado, quiero que el panel distinga «sin datos» de «cero», para no interpretar mal lo que veo.
- **HU-11 (Todos):** como usuario, quiero ver el panel en mi idioma (es/en/pt) y con estados de carga/error claros, para usarlo con confianza.
- **HU-12 (Mentee):** como mentee, quiero proponer objetivos y aportar evidencias, para influir en lo acordado sin administrar el objetivo.
- **HU-13 (Mentor):** como mentor, quiero administrar (crear, ajustar y cerrar) los objetivos individuales de mis mentees, para conducir el proceso.
- **HU-14 (Responsable):** como responsable de colectivo, quiero administrar los objetivos colectivos, para orientar a la organización sin depender de cada persona.
- **HU-15 (Participante/Mentor):** como participante o mentor autorizado, quiero recorrer visualmente la secuencia de la ruta en su orden y reconocer sus módulos, actividades y logros relacionados de forma registrada, para entender dónde estoy y qué sigue.
- **HU-16 (Participante/Mentor):** como usuario autorizado, quiero ver el porcentaje de objetivos y tasas verificables de actividad/módulo/ruta con reglas explícitas, para distinguir avance comprobado de estimaciones.
- **HU-17 (Participante/Mentor):** como usuario autorizado, quiero consultar evaluaciones del catálogo o de personalidad/OCEAN relacionadas explícitamente con competencias como contexto, para interpretar la evidencia sin que cambie automáticamente el nivel registrado.
- **HU-18 (Participante/Mentor):** como usuario autorizado, quiero consultar una cronología de cambios y evidencias verificables de objetivos, actividades/rutas y competencias, incluidos eventos previos cuando sus fuentes acrediten los datos, para comprender cómo evolucionó el proceso.
- **HU-19 (Participante/Mentor):** como participante o mentor asignado, quiero asociar, cambiar o retirar explícitamente el vínculo entre un reto y un módulo dentro de mi ámbito autorizado, para que las tasas del mapa se basen en relaciones verificables y auditables.
- **HU-20 (Participante/Mentor):** como participante titular o mentor asignado y autorizado, quiero crear, cambiar o retirar el vínculo entre un resultado de evaluación y una competencia, para mantener contextualizada la evidencia y conservar la trazabilidad de cada modificación.

---

## 4. Requisitos funcionales (RF)

**Notación EARS usada (patrones en español):**

- **Ubicuo:** «El sistema DEBE …»
- **Dirigido por evento:** «CUANDO <disparador>, el sistema DEBE …»
- **Dirigido por estado:** «MIENTRAS <estado>, el sistema DEBE …»
- **Característica opcional:** «DONDE <característica>, el sistema DEBE …»
- **Comportamiento no deseado:** «SI <condición>, ENTONCES el sistema DEBE …»

---

### RF-1 — Objetivos como entidad de primera clase

El sistema DEBE gestionar **objetivos** como entidades propias (no como texto libre), cada uno con, al menos: título, descripción, criterio de logro medible, estado, fecha límite y responsable; y vinculados a una persona (objetivo **individual**, con titular) o a un colectivo (objetivo **colectivo**, con colectivo responsable).

**Permisos (decisión §3.1.1):** el **mentor administra** los objetivos individuales; el **mentee propone** objetivos individuales y **aporta evidencias**; el **responsable autorizado administra** los objetivos colectivos.

**Por qué:** sin una entidad con criterio de logro y estado, el progreso no puede derivarse de evidencias (P6) ni ser trazable (P5).

**Criterios de aceptación:**

- **CA-1.1 (Ubicuo):** El sistema DEBE almacenar cada objetivo con un criterio de logro explícito y verificable.
- **CA-1.2 (Ubicuo):** El sistema DEBE vincular cada objetivo a **un titular persona** (objetivo individual) o a **un colectivo responsable** (objetivo colectivo), de forma mutuamente excluyente.
- **CA-1.3 (Evento):** CUANDO un usuario autorizado crea un objetivo, el sistema DEBE exigir título, criterio de logro, titular/colectivo responsable y fecha límite.
- **CA-1.4 (No deseado):** SI se intenta crear un objetivo sin criterio de logro, o sin titular ni colectivo responsable, ENTONCES el sistema DEBE rechazarlo e indicar el motivo.
- **CA-1.5 (Evento):** CUANDO el mentee propone un objetivo individual o aporta evidencias, el sistema DEBE registrarlo sin otorgarle permisos de administración sobre el objetivo.
- **CA-1.6 (No deseado):** SI un usuario sin permiso (mentee, miembro o mentor ajeno) intenta administrar un objetivo, ENTONCES el sistema DEBE denegar la operación en el servidor (P3).

### RF-2 — Ciclo de vida y trazabilidad del objetivo

El sistema DEBE permitir que un objetivo transite por estados claros (al menos: `no iniciado`, `en progreso`, `logrado`, `abandonado`) y DEBE registrar los cambios relevantes.

**Por qué:** P5 exige saber quién cambió qué y cuándo; P6 exige que «logrado» sea un hecho registrado, no una estimación.

**Criterios de aceptación:**

- **CA-2.1 (Evento):** CUANDO un usuario autorizado cambia el estado de un objetivo, el sistema DEBE registrar autor, fecha y estado anterior y nuevo.
- **CA-2.2 (Estado):** MIENTRAS un objetivo está `logrado`, el sistema DEBE contabilizarlo como cumplido en los indicadores.
- **CA-2.3 (No deseado):** SI un objetivo `logrado` se revierte a otro estado, ENTONCES el sistema DEBE conservar el registro del cambio y reflejar el estado vigente en los indicadores.
- **CA-2.4 (Ubicuo):** El sistema DEBE tratar «vencido» como condición **derivada** de la fecha límite frente a la fecha actual, no como un estado del ciclo de vida; un objetivo `logrado` no se marca como vencido.

### RF-3 — Panel individual del mentee

El sistema DEBE ofrecer un panel individual que integre, en un solo lugar: objetivos, sesiones realizadas, actividades pendientes, competencias y próximos compromisos.

**Por qué:** hoy la información está dispersa; el valor está en la lectura unificada (aprovechar los dashboards existentes, no duplicarlos).

**Criterios de aceptación:**

- **CA-3.1 (Ubicuo):** El sistema DEBE mostrar los cinco bloques del panel al mentee autenticado para su propio proceso.
- **CA-3.2 (Estado):** MIENTRAS el panel se carga, el sistema DEBE mostrar un estado de carga visible.
- **CA-3.3 (No deseado):** SI la carga del panel falla, ENTONCES el sistema DEBE mostrar un error comprensible con opción de reintentar.

### RF-4 — Indicador «% de objetivos logrados»

El sistema DEBE calcular, por persona, un porcentaje simple y explicable sobre sus **objetivos individuales**: **objetivos individuales logrados ÷ objetivos individuales totales**. Los objetivos colectivos no entran en el porcentaje individual (se reflejan en el ámbito del colectivo, RF-10).

**Por qué:** el cliente eligió un número único sencillo y auditable; P6 prohíbe inflar indicadores.

**Criterios de aceptación:**

- **CA-4.1 (Ubicuo):** El sistema DEBE calcular el porcentaje como objetivos individuales `logrado` dividido entre el total de objetivos individuales del usuario.
- **CA-4.2 (No deseado):** SI el usuario no tiene objetivos individuales, ENTONCES el sistema DEBE mostrar «sin datos» (sin objetivos individuales) en lugar de `0 %` o un error.
- **CA-4.3 (Ubicuo):** El sistema DEBE permitir conciliar el porcentaje con el detalle de objetivos (numerador y denominador visibles).

### RF-5 — Indicador «sesiones realizadas»

El sistema DEBE mostrar el número de sesiones **realizadas** en el **mes calendario actual** (decisión §3.1.4), contando únicamente las de estado `completed` **con asistencia confirmada** (decisión §3.1.3).

**Por qué:** P6 es explícito: una sesión programada no cuenta como completada; y la asistencia individual se mide con la asistencia registrada, no con el mero estado.

**Criterios de aceptación:**

- **CA-5.1 (Ubicuo):** El sistema DEBE contar como realizada una sesión solo si su estado es `completed` **y** su asistencia está confirmada.
- **CA-5.2 (No deseado):** SI una sesión está `scheduled`, `in_progress` o `canceled`, o está `completed` sin asistencia confirmada, ENTONCES el sistema NO DEBE contarla como realizada.
- **CA-5.3 (Ubicuo):** El sistema DEBE distinguir en la interfaz las sesiones realizadas de las programadas.
- **CA-5.4 (Ubicuo):** El sistema DEBE contar por defecto las sesiones realizadas del **mes calendario actual** e indicarlo de forma visible (ver RF-14).

### RF-6 — Indicador «actividades pendientes»

El sistema DEBE mostrar las actividades **pendientes** de la persona (retos asignados no completados y módulos de ruta pendientes), distinguiendo las **vencidas**.

**Por qué:** el seguimiento útil es el de lo que falta; marcar lo vencido permite priorizar (aplica al mentee y al mentor).

**Criterios de aceptación:**

- **CA-6.1 (Ubicuo):** El sistema DEBE listar como pendientes los retos en estado `assigned` o `in_progress` y los módulos de ruta no completados.
- **CA-6.2 (Evento):** CUANDO la fecha límite (`due_at`) de una actividad pendiente ya pasó, el sistema DEBE marcarla como vencida.
- **CA-6.3 (No deseado):** SI una actividad no tiene fecha límite, ENTONCES el sistema NO DEBE clasificarla como vencida.
- **CA-6.4 (Ubicuo):** El sistema DEBE contabilizar en el indicador solo las actividades pendientes o vencidas, no las completadas.
- **CA-6.5 (Ubicuo):** El sistema DEBE excluir de «pendientes» y de «completadas» las actividades canceladas (`canceled`), abandonadas (`abandoned`) y las plantillas (`template`).

### RF-7 — Indicador «competencias desarrolladas»

El sistema DEBE mostrar las competencias de la persona desde **dos fuentes distinguibles**: skills (`UserSkill` + `SkillTag`, con su nivel `SkillLevel`) y dimensiones del perfil **OCEAN**.

**Por qué:** el cliente eligió ambas fuentes; el MIS mide tanto competencias declaradas como rasgos de personalidad, y deben leerse sin confundirse (P9/P15).

**Criterios de aceptación:**

- **CA-7.1 (Ubicuo):** El sistema DEBE mostrar las skills con su nivel, indicando la fuente «skills».
- **CA-7.2 (Ubicuo):** El sistema DEBE mostrar las dimensiones OCEAN indicando la fuente «personalidad» y su fecha de evaluación.
- **CA-7.3 (Opcional):** DONDE el plan del usuario no incluye el entitlement OCEAN, el sistema DEBE ocultar o bloquear la sección OCEAN con un motivo explícito.
- **CA-7.4 (No deseado):** SI el usuario no ha completado la evaluación de personalidad, ENTONCES el sistema DEBE mostrar la sección OCEAN como «sin datos», no como valores en cero.

### RF-8 — «Próximos compromisos»

El sistema DEBE mostrar los próximos compromisos de la persona dentro de una **ventana de 7 días** (decisión §3.1.4): sesiones programadas futuras y vencimientos próximos de actividades y objetivos.

**Por qué:** cierra el ciclo de seguimiento: qué viene, dónde continuar y qué deadline se acerca.

**Criterios de aceptación:**

- **CA-8.1 (Ubicuo):** El sistema DEBE listar las sesiones en estado `scheduled` con fecha futura.
- **CA-8.2 (Ubicuo):** El sistema DEBE listar las actividades y objetivos con fecha límite dentro de los próximos **7 días**.
- **CA-8.3 (Ubicuo):** El sistema DEBE mostrar los compromisos ordenados por fecha, con zona horaria reflejada de forma consistente (P8).
- **CA-8.4 (No deseado):** SI no hay compromisos próximos, ENTONCES el sistema DEBE mostrar un estado vacío explícito.

### RF-9 — Panel del mentor sobre sus mentees

El sistema DEBE permitir al mentor ver el panel individual de cada mentee que tenga asignado, respetando los permisos y los entitlements de plan vigentes.

**Por qué:** el mentor necesita seguimiento informado y debe apoyarse en la autorización ya existente del Mentor Workspace (no duplicar checks).

**Criterios de aceptación:**

- **CA-9.1 (Ubicuo):** El sistema DEBE permitir al mentor acceder al panel solo de mentees con asignación válida o con una autorización explícita de historial sobre un proceso cerrado.
- **CA-9.2 (Estado):** MIENTRAS el plan del mentee no habilita el espacio del mentor, el sistema DEBE bloquear el acceso con un motivo explícito.
- **CA-9.3 (No deseado):** SI un mentor solicita el panel de un mentee no asignado, ENTONCES el sistema DEBE denegar el acceso sin revelar datos.
- **CA-9.4 (Ubicuo):** El sistema DEBE permitir al mentor identificar de un vistazo los mentees con pendientes o vencimientos.

### RF-10 — Agregado de colectivo para el responsable

El sistema DEBE mostrar al responsable de un colectivo (familia, grupo/grupal o empresa) un indicador **agregado** de sus miembros, derivado de los indicadores individuales.

**Por qué:** el valor para la organización es la lectura agregada. Se conserva para objetivos la **suma** de logros/totales y la **media** del porcentaje individual; las nuevas tasas de actividad/ruta usan la media de porcentajes individuales calculables para cada métrica, con privacidad adicional cuando hay datos faltantes.

**Criterios de aceptación:**

- **CA-10.1 (Ubicuo):** El sistema DEBE calcular el agregado vigente como suma de logros/totales del colectivo y media del % de objetivos logrados de sus miembros.
- **CA-10.2 (Ubicuo):** El sistema DEBE permitir al responsable ver el agregado solo de los colectivos que le corresponden y sin exponer indicadores individuales.
- **CA-10.3 (No deseado):** SI el colectivo no tiene miembros con objetivos, ENTONCES el sistema DEBE indicar «sin datos» y no calcular una media engañosa.
- **CA-10.4 (Ubicuo):** EL SISTEMA DEBE distinguir el tamaño de la base de contribución del agregado de objetivos vigente (cuántos miembros contribuyen), como en el baseline. Para nuevos promedios de actividad/ruta, la base computable es la de quienes tienen un porcentaje individual definido y se aplican las supresiones de CA-10.6 y RNF-2.
- **CA-10.5 (No deseado):** SI la base de participantes elegibles es inferior al **umbral de 3** (decisión §3.1.5), ENTONCES el sistema DEBE suprimir el agregado también para el responsable y mostrar el motivo, igual que para los miembros (RNF-2).
- **CA-10.6 (Ubicuo):** EL SISTEMA DEBE incluir también tasas colectivas de actividades/rutas calculadas como promedio de los porcentajes individuales definidos para la misma métrica. Los porcentajes de ruta solo se agrupan para la misma ruta y versión. Los participantes sin ese porcentaje individual definido NO DEBEN convertirse en cero ni incluirse en dicho promedio, pero SÍ DEBEN permanecer en la base general de participantes elegibles vigente. Cada promedio nuevo DEBE contar con al menos **3 porcentajes individuales calculables**; si contribuyen menos de 3, EL SISTEMA DEBE suprimir ese promedio. También se mantiene la supresión si la base general de participantes elegibles es inferior a 3. Estas reglas no alteran el agregado de objetivos vigente.

### RF-11 — Agregado visible para el miembro del colectivo

El sistema DEBE mostrar a un miembro el agregado del colectivo al que pertenece, **sin** exponer indicadores individuales de otras personas.

**Por qué:** transparencia del grupo con privacidad desde el diseño (P4).

**Criterios de aceptación:**

- **CA-11.1 (Ubicuo):** El sistema DEBE mostrar a un miembro solo métricas agregadas de su colectivo, incluidas las tasas de actividad/ruta definidas en CA-10.6 (con tasas de ruta agrupadas solo para la misma ruta y versión), nunca los valores o mapas individuales.
- **CA-11.2 (No deseado):** SI un usuario no pertenece al colectivo, ENTONCES el sistema DEBE denegar el acceso a su agregado.
- **CA-11.3 (No deseado):** SI la base general de participantes elegibles del colectivo es inferior a **3**, ENTONCES EL SISTEMA DEBE suprimir el agregado y mostrar el motivo. Para cada nueva tasa de actividad/ruta, SI contribuyen menos de **3 porcentajes individuales calculables**, ENTONCES EL SISTEMA DEBE suprimir esa tasa aunque la base general alcance 3; en ninguno de los casos debe revelar la base inferior al umbral ni convertir métricas ausentes en cero (RNF-2).

### RF-12 — Autorización en cada operación

El sistema DEBE validar identidad, rol, permisos y pertenencia en **cada** consulta del panel, tanto individual como agregada.

**Por qué:** P3; ningún dato ajeno debe obtenerse manipulando identificadores.

**Criterios de aceptación:**

- **CA-12.1 (Ubicuo):** El sistema DEBE validar en el servidor el acceso a cada panel y agregado.
- **CA-12.2 (No deseado):** SI un usuario solicita el panel o agregado de un recurso al que no tiene acceso, ENTONCES el sistema DEBE responder denegado sin filtrar información.
- **CA-12.3 (Ubicuo):** El sistema DEBE tratar el dashboard y su visualización como presentación, nunca como control de acceso.

### RF-13 — Distinción entre «sin datos» y «cero»

El sistema DEBE distinguir explícitamente la ausencia de datos del valor cero en todos los indicadores.

**Por qué:** P6 y P11 exigen identificar datos incompletos; confundir «no hay datos» con «0» produce conclusiones falsas.

**Criterios de aceptación:**

- **CA-13.1 (Ubicuo):** El sistema DEBE mostrar «sin datos» cuando la fuente no tiene registros para el indicador.
- **CA-13.2 (Ubicuo):** El sistema DEBE mostrar `0` solo cuando existen registros y su valor es efectivamente cero.
- **CA-13.3 (No deseado):** SI un indicador depende de una fuente incompleta, ENTONCES el sistema DEBE señalarlo como dato incompleto.

### RF-14 — Periodo de los indicadores, definido por su naturaleza

El sistema DEBE aplicar a cada indicador el periodo que corresponde a su naturaleza (decisión §3.1.4), de forma consistente entre los indicadores y sus agregados.

**Por qué:** P11: los criterios temporales deben aplicarse consistentemente y los totales deben conciliarse con sus fuentes; forzar un único periodo sobre indicadores de naturaleza distinta los volvería engañosos.

**Criterios de aceptación:**

- **CA-14.1 (Ubicuo):** El sistema DEBE aplicar por defecto el **mes calendario actual** a «sesiones realizadas», el **estado actual** a objetivos y competencias, y los **próximos 7 días** a los compromisos.
- **CA-14.2 (Ubicuo):** El sistema DEBE indicar de forma visible el periodo o la referencia temporal vigente junto a cada indicador.
- **CA-14.3 (Ubicuo):** El sistema DEBE etiquetar como «estado actual» los indicadores de naturaleza puntual (objetivos y competencias), en lugar de ofrecer un filtro de periodo inexistente.
- **CA-14.4 (No deseado):** SI un indicador no admite cambio de periodo, ENTONCES el sistema DEBE indicarlo claramente y no aplicar en silencio un periodo distinto.

### RF-15 — Idioma e internacionalización del panel

El sistema DEBE presentar todo el texto del panel en el idioma activo (es/en/pt).

**Criterios de aceptación:**

- **CA-15.1 (Ubicuo):** El sistema DEBE usar los catálogos de traducción existentes para el texto de la interfaz.
- **CA-15.2 (No deseado):** SI falta una traducción, ENTONCES el sistema DEBE recurrir al idioma por defecto (es) sin mostrar claves crudas.

### RF-16 — Mapa visual de la ruta de aprendizaje

CUANDO un participante o un usuario autorizado consulta el mapa de aprendizaje, EL SISTEMA DEBE presentar las rutas y sus módulos en el orden definido para cada ruta, junto con objetivos, actividades y logros únicamente cuando la relación con esa ruta o módulo esté registrada de forma verificable. La consulta y representación del mapa no crean vínculos ni alteran estados por sí mismas; el vínculo explícito entre reto y módulo puede gestionarse conforme a RF-20, y la completitud automática del módulo se rige exclusivamente por RF-17.

**Por qué:** una lectura visual común ayuda a reconocer la secuencia de aprendizaje sin inferir relaciones o logros no respaldados por datos (P3, P6, P11).

**Criterios de aceptación:**

- **CA-16.1 (Evento):** CUANDO el usuario autorizado abre una ruta, EL SISTEMA DEBE presentar sus módulos en el orden vigente de esa ruta.
- **CA-16.2 (Estado):** MIENTRAS se representa un objetivo, actividad, módulo o logro, EL SISTEMA DEBE mostrar el estado registrado de su fuente y ubicarlo bajo una ruta o módulo solo si existe una relación explícita registrada. Para un módulo sin estado de completitud persistido, EL SISTEMA NO DEBE inventar otro estado: debe mostrar los estados de las actividades vinculadas y marcar incompletitud si falta alguno de los estados requeridos para el cálculo.
- **CA-16.3 (No deseado):** SI no existe relación registrada entre una entidad y una ruta/módulo, ENTONCES EL SISTEMA NO DEBE inferirla por coincidencia de título, asistencia, porcentaje estimado, mentoría u otro dato indirecto. Para retos y módulos, solo una operación explícita y autorizada conforme a RF-20 puede crear o cambiar el vínculo.
- **CA-16.4 (Ubicuo):** EL SISTEMA DEBE mantener las reglas de visibilidad de RF-9 y RF-12; el mapa de un participante no debe exponer el detalle de otra persona ni convertir un agregado colectivo en acceso individual.
- **CA-16.5 (Ubicuo):** EL SISTEMA DEBE presentar los módulos únicamente como secuencia ordenada según el orden registrado en la ruta, sin introducir etapas ni subdivisiones.
- **CA-16.6 (Ubicuo):** EL SISTEMA DEBE mostrar en vistas colectivas solo los nuevos porcentajes agregados autorizados conforme a RF-10/RF-11 y RNF-2; NO DEBE revelar mapas, historiales ni indicadores individuales. El agregado vigente de objetivos no cambia.
- **CA-16.7 (Ubicuo):** EL SISTEMA DEBE ofrecer el mapa en una vista independiente accesible desde el panel de progreso. El participante DEBE ver únicamente rutas asignadas o en las que haya iniciado progreso; el mentor autorizado DEBE ver las rutas incluidas en la vista autorizada del sujeto. Para administradores se mantienen exclusivamente los permisos de soporte vigentes; este requisito no amplía su acceso.

### RF-17 — Porcentajes verificables por objetivo, actividad/módulo y ruta

EL SISTEMA DEBE mostrar por separado el porcentaje de objetivos definido en RF-4, la tasa de completitud de los `Challenge` vinculados explícitamente a módulos y el porcentaje de ruta derivado de estados de módulo individuales explícitos. Los vínculos reto-módulo gestionados explícitamente conforme a RF-20 son persistidos y auditables. El mapa DEBE mostrar el estado registrado de cada reto; no DEBE presentar un porcentaje propio de actividad si la fuente no define tal métrica. El porcentaje de ruta DEBE dar el mismo peso a cada módulo elegible y no inferir completitud desde el porcentaje agregado general de progreso de ruta ni desde asistencia.

**Por qué:** un porcentaje transparente no debe convertir una estimación, una asistencia o datos sin estado en un logro (P6, P11).

**Criterios de aceptación:**

- **CA-17.1 (Ubicuo):** EL SISTEMA DEBE calcular el porcentaje de ruta como módulos completos cuyo estado individual `completed` está automáticamente registrado y persistido, dividido entre los módulos elegibles de esa ruta, multiplicado por 100; numerador y denominador DEBEN poder contrastarse con el detalle mostrado. Es elegible un módulo con al menos un reto vinculado elegible y estado conocido en todos los retos vinculados no excluidos.
- **CA-17.2 (Ubicuo):** EL SISTEMA DEBE considerar actividad elegible para el mapa y sus tasas únicamente un `Challenge` con vínculo explícito y persistido a un `PathModule` y estado `assigned`, `in_progress` o `completed`. EL SISTEMA DEBE excluir `template`, `canceled` y `abandoned` tanto del denominador de actividad/ruta como de la regla de completitud automática. Los retos sin vínculo explícito no participan en estas tasas; el vínculo puede establecerse, cambiarse o retirarse únicamente mediante RF-20.
- **CA-17.3 (Ubicuo):** EL SISTEMA DEBE calcular la tasa de completitud de actividades vinculadas como retos elegibles `completed` divididos entre todos los retos elegibles aplicables, multiplicado por 100, con numerador y denominador contrastables con el detalle. Cada actividad DEBE mostrar su estado registrado; no se le DEBE asignar artificialmente un porcentaje 100/0 cuando su fuente solo define un estado.
- **CA-17.4 (Evento):** CUANDO un módulo tiene al menos un `Challenge` vinculado elegible y todos los retos vinculados no excluidos tienen estado explícito `completed`, EL SISTEMA DEBE registrar y persistir automáticamente el estado individual completado del módulo. La traza DEBE identificar al sistema como actor y conservar el usuario y la acción que dispararon la transición, incluida la operación explícita sobre el vínculo cuando esta la origine.
- **CA-17.5 (No deseado):** SI algún `Challenge` vinculado no excluido tiene estado explícito distinto de `completed`, ENTONCES EL SISTEMA NO DEBE considerar vigente ni mantener registrado como completado el módulo. SI falta el estado de algún reto vinculado aplicable, ENTONCES EL SISTEMA DEBE marcar incompleto el dato y NO DEBE considerar completado el módulo. Los retos `canceled` y `abandoned` se excluyen; si todos los retos vinculados están excluidos, no se registra completitud.
- **CA-17.6 (No deseado):** SI un módulo no tiene actividades vinculadas elegibles, ENTONCES EL SISTEMA DEBE mostrar «sin datos» y NO DEBE registrarlo como completado por esta regla.
- **CA-17.7 (No deseado):** SI falta el estado explícito de un `Challenge` vinculado aplicable, ENTONCES EL SISTEMA DEBE marcarlo incompleto y NO DEBE mostrar una tasa parcial para la actividad, el módulo o la ruta que depende de él. La ausencia NO DEBE convertirse en cero ni en completitud.
- **CA-17.8 (No deseado):** SI la única señal disponible es el porcentaje estimado actual de la ruta, el campo de progreso de una actividad o la asistencia a una sesión, ENTONCES EL SISTEMA NO DEBE usarla para acreditar completitud de actividad o módulo.
- **CA-17.9 (No deseado):** SI una ruta no contiene módulos elegibles y no hay estados faltantes aplicables, ENTONCES EL SISTEMA DEBE mostrar «sin datos» para su porcentaje, no `0 %`. SI algún estado faltante de un reto vinculado afecta la determinación de módulos de esa ruta, ENTONCES EL SISTEMA DEBE marcar la tasa de ruta como incompleta y no mostrar un porcentaje parcial.
- **CA-17.10 (Ubicuo):** EL SISTEMA DEBE mantener para objetivos la fórmula vigente de RF-4: objetivos individuales logrados ÷ todos los objetivos individuales; los objetivos abandonados siguen en ese denominador y no se sustituyen por la regla de exclusión de actividad/módulo.
- **CA-17.11 (Ubicuo):** EL SISTEMA DEBE excluir plantillas, retos cancelados y abandonados según CA-17.2, y no incorporar a una tasa de actividad/ruta un reto sin relación explícita con el módulo/ruta calculado.
- **CA-17.12 (Ubicuo):** EL SISTEMA DEBE mantener separadas estas tasas del conteo de actividades pendientes/completadas de RF-6; la regla nueva no altera el baseline de dicho indicador.
- **CA-17.13 (Evento):** CUANDO un `Challenge` está en `abandoned`, EL SISTEMA DEBE permitir cambiar su estado a `assigned` o `in_progress`, y no a otros estados. CUANDO un `Challenge` está en `assigned` o `in_progress`, EL SISTEMA DEBE permitir cambiar su estado a `abandoned`; NO DEBE permitir esa transición desde `completed`, `canceled` ni `template`. Cada transición permitida en cualquiera de estos sentidos DEBE quedar trazable conforme a P5, con actor, fecha y estados anterior y resultante. Mientras el reto está `abandoned`, se excluye de las tasas y de la completitud automática; al transitar a `assigned` o `in_progress`, vuelve a ser elegible y EL SISTEMA DEBE recalcular las tasas afectadas. Si ese estado elegible no es `completed`, EL SISTEMA DEBE dejar de considerar completado el módulo que dependa de ese reto, conforme a CA-17.5.
- **CA-17.14 (Evento):** CUANDO se crea, cambia o retira un vínculo reto-módulo autorizado, EL SISTEMA DEBE recalcular la elegibilidad y completitud afectadas usando solo los vínculos explícitos persistidos, y dejar de considerar completado cualquier módulo que ya no cumpla la regla de CA-17.4. La operación y sus efectos en las tasas/completitud DEBEN quedar trazables; si no quedan retos vinculados elegibles, el módulo se presenta «sin datos» conforme a CA-17.6. La ausencia de vínculo no se sustituye por una asociación inferida. SI el cambio o retiro del vínculo revoca una completitud automática previamente registrada, ENTONCES EL SISTEMA DEBE registrar la transición desde el estado anterior `completed` hasta el estado resultante, identificando al sistema como actor y al usuario que originó la operación como autor/usuario desencadenante, junto con la acción, fecha y estados anterior y resultante.

### RF-18 — Evaluaciones relacionadas como evidencia de competencias

CUANDO exista una relación registrada entre un resultado individual y una competencia, EL SISTEMA DEBE mostrar el resultado como evidencia/contexto de esa competencia, incluida su fecha disponible, sin modificar automáticamente el nivel de competencia. Un resultado puede relacionarse con varias competencias y una competencia con varios resultados. Si un resultado vinculado deja de estar completado, EL SISTEMA DEBE conservar el vínculo en el historial y marcarlo como incompleto, sin presentarlo como evidencia vigente. Si el resultado se elimina, EL SISTEMA DEBE conservar únicamente un evento con fecha, tipo de cambio y metadatos mínimos del vínculo/tipo necesarios para auditoría, además de una referencia seudonimizada del actor identificable solo por auditores autorizados. La vista normal del historial NO DEBE revelar la identidad del actor. EL SISTEMA NO DEBE conservar respuestas, puntuaciones ni el resultado fuente eliminado ni presentarlo como evidencia vigente. La retención de ese evento solo puede implementarse después de identificar y documentar la política general de conservación/eliminación.

**Por qué:** las evaluaciones contextualizan la evidencia, pero no constituyen por sí mismas una regla de interpretación de nivel (P6, P9, P15).

**Criterios de aceptación:**

- **CA-18.1 (Estado):** MIENTRAS se muestran competencias, EL SISTEMA DEBE identificar evidencia vigente únicamente a partir de una relación registrada con un resultado individual completado: `AssessmentResult` para catálogo o `PersonalityAssessment` para personalidad/OCEAN. EL SISTEMA DEBE mostrar los datos contextuales disponibles y la fecha; para catálogo incluye la referencia al instrumento `Assessment` y para personalidad/OCEAN el `test_version` registrado. La multiplicidad de relaciones no está limitada a una por resultado ni a una por competencia.
- **CA-18.2 (No deseado):** SI no hay una relación registrada entre evaluación y competencia, ENTONCES EL SISTEMA NO DEBE inventarla ni presentar esa evaluación como evidencia de dicha competencia.
- **CA-18.3 (No deseado):** SI una evaluación se completa o cambia, ENTONCES EL SISTEMA NO DEBE modificar automáticamente el nivel registrado de una competencia; un cambio requiere el registro explícito correspondiente.
- **CA-18.4 (Ubicuo):** EL SISTEMA DEBE aplicar a evaluaciones y competencias la autorización y los entitlements vigentes de RF-7, RF-9 y RF-12.
- **CA-18.5 (Evento):** CUANDO el participante titular o su mentor asignado y autorizado registra una relación con una competencia, EL SISTEMA DEBE permitir vincular un `AssessmentResult` individual completado para evaluaciones del catálogo o un `PersonalityAssessment` individual completado para personalidad/OCEAN; DEBE conservar `Assessment` en el primer caso y `test_version` en el segundo, sujeto a la autorización vigente. Un resultado individual completado puede vincularse con varias competencias y cada competencia puede vincularse con varios resultados individuales completados. El tipo, resultado o módulo por sí solos no crean dicha relación.
- **CA-18.6 (Evento):** CUANDO el participante titular o su mentor asignado y autorizado cambia o retira una relación existente entre un resultado de evaluación y una competencia, EL SISTEMA DEBE permitir la operación con el mismo ámbito y autorización vigentes que para crearla, y DEBE auditarla y registrarla en el historial conforme a CA-19.8. El retiro elimina la relación vigente, pero no el evento histórico del vínculo; el cambio no modifica automáticamente el nivel de competencia.
- **CA-18.7 (Estado):** MIENTRAS un resultado vinculado deja de estar completado, EL SISTEMA DEBE conservar su relación histórica con cada competencia, mostrarlo como incompleto y NO DEBE presentarlo como evidencia vigente. CUANDO un resultado vinculado se elimina, EL SISTEMA DEBE conservar únicamente el evento mínimo de CA-19.7: tipo de cambio y fecha, metadatos mínimos del vínculo/tipo necesarios para auditoría y actor seudonimizado identificable solo por auditores autorizados. La vista normal del historial NO DEBE revelar la identidad del actor. EL SISTEMA NO DEBE conservar respuestas, puntuaciones ni el resultado fuente eliminado ni presentarlo como evidencia vigente. La retención de ese evento solo puede implementarse después de que la política general de conservación/eliminación esté identificada y documentada; no se fija un plazo y la implementación no puede decidirlo por sí misma (CA-19.7/19.8).

La relación es modificable únicamente por los actores autorizados en CA-18.5, conforme a CA-18.6; toda creación, cambio o retiro conserva la trazabilidad exigida por CA-19.8.

El vínculo se establece siempre con un resultado individual completado, no solo con su instrumento: `AssessmentResult` + `Assessment` para catálogo, o `PersonalityAssessment` + `test_version` para personalidad/OCEAN. La cardinalidad aprobada es muchos-a-muchos. Si el resultado deja de estar completado, su asociación histórica se conserva y su estado visible impide tratarla como evidencia vigente; si se elimina, se conserva únicamente el evento mínimo en CA-19.7 (fecha, tipo de cambio, metadatos mínimos del vínculo/tipo y actor seudonimizado identificable solo por auditores autorizados). El historial normal no revela la identidad; no se conservan respuestas, puntuaciones ni el resultado fuente eliminado. Retirar una relación vigente tampoco borra el evento histórico de esa relación. No se crea una referencia `Assessment` ficticia para resultados OCEAN. En el baseline revisado no existe actualmente una relación registrada evaluación-competencia; esta relación es una capacidad aprobada pendiente de implementación y no se presume por el tipo, el resultado o el módulo de la evaluación.

### RF-19 — Historial cronológico unificado

EL SISTEMA DEBE ofrecer un historial cronológico unificado de eventos registrados de objetivos, actividades/rutas y competencias, con fechas y evidencias disponibles, incluidos los cambios explícitos de los vínculos reto-módulo y evaluación-competencia y los cambios de estado o eliminación de resultados vinculados, para el propio participante y para los usuarios autorizados según RF-9 y RF-12. La cronología es un registro de eventos, no una serie temporal ni un indicador agregado nuevo.

**Por qué:** conocer el estado actual no explica cómo se alcanzó; conservar los eventos permite trazabilidad y lectura de evidencias conforme a P5 y P6.

**Criterios de aceptación:**

- **CA-19.1 (Ubicuo):** EL SISTEMA DEBE incluir para objetivos los eventos verificables de creación, primer inicio (entrada en `en progreso`), cambios de estado (incluidos logrado y abandonado) y evidencias asociadas, tanto anteriores como posteriores a esta propuesta cuando las fuentes existentes acrediten sus datos.
- **CA-19.2 (Ubicuo):** EL SISTEMA DEBE incluir para actividades/rutas los eventos verificables de inicio, finalización, cambios de estado, creación/cambio/retiro explícito del vínculo reto-módulo y evidencias disponibles, incluidos los eventos previos que las fuentes existentes puedan acreditar.
- **CA-19.3 (Ubicuo):** EL SISTEMA DEBE incluir para competencias los cambios de nivel efectivamente registrados, las evaluaciones relacionadas de forma explícita y los eventos de creación, cambio y retiro del vínculo entre una competencia y un resultado de evaluación (`AssessmentResult` o `PersonalityAssessment`), así como los cambios de estado o eliminación de resultados vinculados, con sus fechas disponibles, sin atribuir causalidad ni cambios automáticos de nivel. Debe reflejarse la multiplicidad de vínculos; un retiro o la pérdida del estado completado no borra su relación histórica. Si se elimina el resultado, su representación queda limitada al evento mínimo de CA-19.7, cuya identidad de actor no se revela en la vista normal.
- **CA-19.4 (Ubicuo):** EL SISTEMA DEBE presentar los eventos registrados con fecha disponible en orden cronológico y conservar, para cada cambio relevante, quién lo realizó, cuándo ocurrió y qué cambió cuando esos datos estén registrados, salvo los cambios de estado de resultados vinculados mientras el vínculo está activo, que se rigen por el registro obligatorio de CA-19.8. Para el evento de eliminación del resultado, la vista normal NO DEBE revelar la identidad del actor y solo auditores autorizados pueden identificarlo mediante su referencia seudonimizada. Los registros que acreditan un evento pero carecen de fecha DEBEN aparecer en un grupo separado «fecha no disponible» y marcarse incompletos; no se les asigna una posición temporal inventada.
- **CA-19.5 (No deseado):** SI no existe un registro que acredite un evento histórico, ENTONCES EL SISTEMA NO DEBE crear una entrada, afirmar que el evento ocurrió ni afirmar que se produjo un faltante. SI existe el registro pero faltan datos, ENTONCES EL SISTEMA DEBE identificar esos campos como incompletos y NO DEBE reconstruir ni inventar fechas, estados, autores o evidencias. La cronología no altera métricas históricas ni crea estimaciones de tendencia.
- **CA-19.6 (Ubicuo):** EL SISTEMA DEBE permitir consultar el historial individual solo al titular, al mentor autorizado conforme a RF-9 o al admin con permiso explícito de soporte y auditoría; ninguna vista agregada de colectivo debe revelar historiales individuales.
- **CA-19.7 (Ubicuo):** EL SISTEMA DEBE conservar la traza histórica frente a operaciones ordinarias de negocio y no almacenar datos personales innecesarios (P4, P5). CUANDO se elimina un resultado y la solicitud afecta a esta traza, EL SISTEMA DEBE conservar únicamente un evento que contenga la fecha y el tipo de cambio, los metadatos mínimos del vínculo/tipo necesarios para auditoría y una referencia seudonimizada del actor identificable únicamente por auditores autorizados. La vista normal del historial NO DEBE revelar la identidad del actor. El evento NO DEBE conservar respuestas, puntuaciones ni el resultado fuente eliminado; sus datos de vínculo/tipo se limitan a lo estrictamente necesario para auditar qué cambió. Esta respuesta es obligatoria, cumple P5 mediante identificación audit-only y no depende de una elección del usuario caso a caso; no constituye una excepción a P5. Antes de implementar la retención de dicho evento, la política general de conservación/eliminación debe estar identificada y documentada. Esta spec no fija un plazo y la implementación NO DEBE decidirlo por sí misma ni asumir que esa política ya existe.
- **CA-19.8 (Evento):** CUANDO se crea, cambia o retira un vínculo entre un resultado de evaluación (`AssessmentResult` o `PersonalityAssessment`) y una competencia, EL SISTEMA DEBE auditar y registrar el evento en el historial con actor, fecha, relación anterior y relación resultante. Para una creación, la relación anterior se registra como ausente; para un retiro, la relación resultante se registra como ausente, sin borrar el evento ni la asociación histórica. CUANDO la fuente registra un cambio de estado real de un resultado mientras existe un vínculo activo con una o más competencias, EL SISTEMA DEBE registrar obligatoriamente un evento inmutable en el historial que identifique al actor, la fecha, el estado anterior y el estado resultante, y conservar la relación histórica con cada competencia afectada; si el resultado queda incompleto o eliminado, no DEBE presentarlo como evidencia vigente. CUANDO se elimina el resultado, su representación histórica queda limitada al evento mínimo definido en CA-19.7. En ese evento, la vista normal no revela la identidad y solo auditor autorizado puede identificar al actor mediante la referencia seudonimizada; se conservan únicamente la fecha, el tipo de cambio y los metadatos mínimos del vínculo/tipo necesarios para auditoría, sin respuestas, puntuaciones ni el resultado fuente eliminado. La retención de este evento solo puede implementarse después de identificar y documentar la política general de conservación/eliminación. Si una fuente externa no permite una transición de estado, EL SISTEMA NO DEBE afirmar que esa transición ocurrió ni crear un evento que la represente. El registro DEBE conservar la trazabilidad necesaria conforme a CA-19.7 y no depender de la disponibilidad de eventos en fuentes históricas previas.

### RF-20 — Gestión explícita del vínculo entre reto y módulo

CUANDO un participante o su mentor asignado gestiona un vínculo entre un `Challenge` y un `PathModule`, EL SISTEMA DEBE permitir asociarlo, cambiarlo o retirarlo explícitamente solo dentro del ámbito autorizado: el reto debe pertenecer al participante o a un mentee asignado al mentor, y el módulo destino debe pertenecer a una ruta que el actor pueda consultar y que esté asignada o iniciada para el participante. Al retirar el vínculo, EL SISTEMA DEBE comprobar en el servidor, antes de modificarlo, que el actor está autorizado respecto del reto y del módulo actualmente vinculado y que puede consultar la ruta actual de ese módulo bajo las mismas reglas de ámbito. EL SISTEMA DEBE autorizar en el servidor el reto y el módulo destino al asociar o cambiar el vínculo, persistir el resultado y registrar una traza auditable de la operación.

**Por qué:** las tasas de actividad/módulo/ruta requieren relaciones verificables; permitir su gestión explícita cubre la ausencia actual de vínculo sin inferir asociaciones ni convertir datos indirectos en evidencia (P3, P5, P6).

- **CA-20.1 (Evento):** CUANDO el participante titular asocia, cambia o retira el vínculo de un reto propio, EL SISTEMA DEBE autorizar la operación en el servidor y persistir el vínculo resultante, incluida su ausencia si lo retira.
- **CA-20.2 (Evento):** CUANDO el mentor asignado asocia, cambia o retira el vínculo de un reto perteneciente a uno de sus mentees asignados, EL SISTEMA DEBE autorizar la operación en el servidor y persistir el vínculo resultante, incluida su ausencia si lo retira.
- **CA-20.3 (No deseado):** SI un participante intenta gestionar un reto ajeno, un mentor intenta gestionar un reto de un mentee no asignado, o cualquier actor carece de autorización vigente, ENTONCES EL SISTEMA DEBE denegar la operación en el servidor sin revelar ni alterar el vínculo.
- **CA-20.4 (Evento):** CUANDO se crea, cambia o retira el vínculo, EL SISTEMA DEBE conservar una traza auditable que identifique al actor, el momento y el vínculo anterior y resultante (o su ausencia).
- **CA-20.5 (Ubicuo):** EL SISTEMA DEBE mantener el vínculo reto-módulo explícito, persistido e independiente de inferencias por título, actividad, asistencia, porcentaje de ruta u otros datos indirectos. La ausencia del vínculo significa que el reto no participa en las tasas de módulo/ruta.
- **CA-20.6 (Ubicuo):** EL SISTEMA DEBE aplicar únicamente los permisos de RF-20 a la asociación de retos y módulos; este requisito no concede al participante permisos de administración de objetivos ni modifica los permisos de objetivos de RF-1.
- **CA-20.7 (Evento):** CUANDO se asocia o cambia un vínculo, EL SISTEMA DEBE comprobar en el servidor que el módulo destino pertenece a una ruta que el participante titular puede consultar por estar asignada o iniciada; si actúa un mentor, EL SISTEMA DEBE comprobar que esa ruta está dentro de la vista autorizada del sujeto conforme a RF-9 y CA-16.7. CUANDO se retira un vínculo, EL SISTEMA DEBE comprobar en el servidor, antes de modificarlo, que el actor está autorizado respecto del reto y del módulo actualmente vinculado y que puede consultar la ruta actual de ese módulo bajo las mismas reglas de ámbito.
- **CA-20.8 (No deseado):** SI el actor no está autorizado para consultar la ruta del módulo destino al asociar o cambiar el vínculo, o no está autorizado respecto del reto o módulo implicado, ENTONCES EL SISTEMA DEBE denegar la operación sin revelar ni alterar el vínculo. SI se retira un vínculo y el actor carece de autorización respecto del reto, del módulo actualmente vinculado o de la ruta actual de ese módulo, ENTONCES EL SISTEMA DEBE denegar la operación antes de cambiar la relación, sin revelar ni alterar el vínculo.

### RF-21 — Fuentes de resultados vinculables a competencias

CUANDO un resultado individual completado se vincula explícitamente con una competencia, EL SISTEMA DEBE distinguir el resultado individual de catálogo del resultado individual de personalidad/OCEAN, conservar los datos de origen disponibles para cada tipo y permitir vínculos muchos-a-muchos. Si ese resultado deja de estar completado, EL SISTEMA DEBE mantener el vínculo en el historial y mostrarlo como incompleto, nunca como evidencia vigente. Si se elimina, EL SISTEMA DEBE conservar únicamente el evento mínimo definido en CA-19.7: fecha, tipo de cambio, metadatos mínimos del vínculo/tipo necesarios para auditoría y actor seudonimizado identificable únicamente por auditores autorizados. La vista normal del historial no revela la identidad del actor. EL SISTEMA NO DEBE conservar respuestas, puntuaciones ni el resultado fuente eliminado ni presentarlo como evidencia vigente; la retención del evento solo puede implementarse después de identificar y documentar la política general de conservación/eliminación.

**Por qué:** el resultado de personalidad/OCEAN se persiste en una fuente distinta de los resultados del catálogo; tratarlos como la misma entidad inventaría referencias de instrumento y podría confundir la evidencia (P6, P9, P11).

- **CA-21.1 (Ubicuo):** EL SISTEMA DEBE representar una evaluación de catálogo mediante su `AssessmentResult` individual completado y su `Assessment` asociado.
- **CA-21.2 (Ubicuo):** EL SISTEMA DEBE representar una evaluación de personalidad/OCEAN mediante su `PersonalityAssessment` individual completado y conservar el `test_version` registrado.
- **CA-21.3 (No deseado):** SI un resultado OCEAN no tiene un `Assessment` asociado, ENTONCES EL SISTEMA NO DEBE inventar ni exigir una referencia a `Assessment` para mostrarlo como contexto vinculado.
- **CA-21.4 (Ubicuo):** EL SISTEMA DEBE permitir que un resultado individual completado se vincule con varias competencias y que cada competencia se vincule con varios resultados individuales completados, sin imponer exclusividad entre vínculos.
- **CA-21.5 (Estado):** MIENTRAS un resultado previamente vinculado no está completado, EL SISTEMA DEBE conservar la relación histórica, identificarla como incompleta y NO DEBE presentarla como evidencia vigente. CUANDO el resultado se elimina, EL SISTEMA DEBE conservar únicamente el evento mínimo de CA-19.7 —fecha, tipo de cambio y metadatos mínimos del vínculo/tipo necesarios para auditoría— con el actor seudonimizado e identificable solo por auditores autorizados; la vista normal del historial NO DEBE revelar la identidad. EL SISTEMA NO DEBE conservar respuestas, puntuaciones ni el resultado fuente eliminado ni presentarlo como evidencia vigente. La retención de ese evento solo puede implementarse después de que la política general de conservación/eliminación esté identificada y documentada; no se fija un plazo y la implementación no puede decidirlo por sí misma.

---

## 5. Requisitos no funcionales (RNF)

- **RNF-1 (Autorización — P3):** toda operación del panel y de agregados se autoriza en el servidor. Ocultar elementos en el frontend no constituye autorización.
- **RNF-2 (Privacidad — P4):** los agregados de colectivo no deben permitir inferir el valor individual de una persona; se aplica un **umbral uniforme de 3 participantes elegibles** a toda vista (miembro, responsable o mentor) y, por debajo del umbral, el agregado se suprime con motivo explícito. Además, cada nuevo promedio colectivo de actividad/ruta se suprime si aporta menos de **3 porcentajes individuales calculables**, aunque la base general elegible alcance el umbral. Los participantes elegibles sin esa métrica permanecen en la base general, pero no aportan cero ni participan en el promedio. Esta condición adicional no modifica la agregación vigente de objetivos.
- **RNF-3 (Trazabilidad — P5):** los cambios relevantes de objetivos, actividades/rutas, competencias y sus estados quedan auditables y no se alteran desde las operaciones ordinarias de negocio; el historial no inventa eventos ausentes.
- **RNF-4 (Consistencia de métricas — P11):** cada indicador tiene definición, fuente y regla únicas y compartidas (ver Anexo A); los totales deben poder conciliarse con los registros de origen. Las tasas de actividad/ruta usan estados explícitos y persistidos; los datos sin esos estados se marcan incompletos y nunca como cero. Las tasas colectivas nuevas promedian solo porcentajes individuales calculables de la misma métrica y aplican el umbral adicional de 3 contribuciones definido en RNF-2.
- **RNF-5 (Rendimiento — consulta bajo demanda):** al excluirse el tiempo real, las consultas interactivas de lectura del panel y del mapa DEBEN completar la respuesta de la API en ≤ 2 segundos (p95) cuando 50 usuarios realizan consultas interactivas simultáneamente (definición de «carga normal»). Para cada consulta, la duración se mide desde la recepción de la solicitud en el servidor hasta completar la respuesta de la API; el criterio se cumple si el percentil 95 de esas duraciones durante dicha carga es ≤ 2 segundos. No se presupone hardware ni dataset específicos.
- **RNF-6 (Accesibilidad — P16):** navegación por teclado, foco visible, contraste suficiente y distinción visual de estados y ausencia de datos (las tendencias quedan fuera de alcance; ver §7).
- **RNF-7 (Responsive):** en un viewport mínimo de 320 px, el panel y el mapa DEBEN mostrar todos los controles sin recorte y sin scroll horizontal; todos los controles DEBEN ser operables con teclado y toque.
- **RNF-8 (Observabilidad — P20):** los errores de cálculo o de acceso generan registros estructurados y útiles, sin secretos ni datos personales innecesarios.
- **RNF-9 (Calidad demostrable — P19):** las reglas de cálculo y los permisos cuentan con pruebas automatizadas, incluyendo casos límite y datos incompletos.
- **RNF-10 (Contratos de API — P17):** las entradas/salidas del panel están validadas y los errores no exponen información interna.

---

## 6. Casos límite

1. **Sin objetivos definidos:** el % se muestra como «sin datos» (sin objetivos individuales), nunca como `0 %` ni error (RF-13).
2. **Denominador cero en el agregado:** un colectivo sin objetivos no produce media; se indica «sin datos».
3. **Sesión programada vs. realizada:** una sesión `scheduled` no incrementa «sesiones realizadas» (P6); tampoco una `completed` sin asistencia confirmada (decisión §3.1.3).
4. **Sesión cancelada o completada sin asistencia confirmada:** no cuenta como realizada; se conserva en la trazabilidad.
5. **Objetivo vencido y no logrado:** se muestra como pendiente/vencido (condición derivada, CA-2.4), no como logrado.
6. **Actividad sin fecha límite:** no se marca como vencida.
7. **Colectivo con menos de 3 participantes elegibles:** el agregado puede revelar el valor individual → se suprime con motivo explícito, por igual para miembros y responsables (RNF-2, decisión §3.1.5).
8. **Miembro sin permiso a un colectivo:** acceso denegado sin filtrar datos (RF-12).
9. **Plan sin entitlement OCEAN:** la sección OCEAN se bloquea u oculta con motivo explícito.
10. **Personalidad no completada:** OCEAN se muestra como «sin datos», no en cero.
11. **Fuente archivada o eliminada:** un objetivo o actividad vinculado a un recurso retirado no invalida evidencias previas y no rompe el panel (P10).
12. **Relación de mentoría finalizada:** el mentor pierde por defecto el acceso al proceso cerrado (CA-9.1) y solo conserva el historial que una autorización explícita le habilite.
13. **Zona horaria:** las fechas límite y vencimientos se interpretan y muestran de forma consistente.
14. **Cambio de colectivo:** el agregado refleja la pertenencia vigente.
15. **Datos parcialmente disponibles:** si una fuente falla o falta, los demás bloques siguen mostrándose y el faltante se marca como incompleto (degradación parcial, no pantalla en blanco).
16. **Usuario sin proceso activo:** el panel muestra estado vacío guiado, no un error.
17. **Actividad cancelada, abandonada o plantilla:** no cuenta ni como pendiente ni como completada (CA-6.5).
18. **Sesión `completed` sin asistencia confirmada:** no cuenta como realizada (CA-5.2).
19. **Objetivo colectivo:** se refleja en el ámbito del colectivo; no entra en el porcentaje individual de una persona (RF-4/RF-10).
20. **Usuario sin permiso de administración:** no puede crear ni editar objetivos; el sistema deniega la operación en el servidor (CA-1.6).
21. **Ruta con porcentaje estimado sin estado individual de módulo:** el porcentaje agregado de la ruta no completa automáticamente sus módulos; no se usa en las tasas de RF-17 (CA-17.8).
22. **Challenge cancelado, abandonado o plantilla:** queda fuera de las tasas, del denominador y del disparador de completitud del módulo; si todos los retos vinculados están excluidos, el módulo queda «sin datos». Esto no cambia el denominador de objetivos de RF-4 (CA-17.2/17.6).
23. **Ruta sin módulos elegibles:** se muestra «sin datos», no `0 %`; si algún estado aplicable falta, se identifica incompletitud y no se calcula tasa parcial (CA-17.7/17.9).
24. **Módulo sin actividades vinculadas elegibles:** se muestra «sin datos» y no se registra como completado automáticamente, aunque el registro general de progreso de ruta indique un porcentaje (CA-17.6/17.8).
25. **Challenge vinculado sin estado:** se marca como incompleto, no se convierte en cero y bloquea la tasa aplicable y la completitud automática de su módulo (CA-17.5/17.7).
26. **Challenge u objetivo sin relación explícita con ruta/módulo:** no se ubica en el mapa ni se le asigna una ruta/módulo por inferencia. Para los retos, solo una operación explícita y autorizada de RF-20 puede establecer el vínculo con el módulo (CA-16.3, CA-17.2).
27. **Miembro elegible sin tasa individual de actividad/ruta:** sigue contando en la base general de participantes elegibles, pero no se convierte su dato ausente en cero ni contribuye a ese promedio; cada promedio nuevo requiere al menos 3 contribuciones calculables, además del umbral general de 3 (RF-10/RF-11, RNF-2).
28. **Colectivo con base general suficiente pero menos de 3 tasas individuales calculables para una métrica:** se suprime únicamente el nuevo promedio de esa métrica, sin alterar el agregado de objetivos ni las demás métricas que sí cumplan sus bases (RNF-2).
29. **Evaluación sin relación registrada con competencia:** no se muestra como evidencia de esa competencia y no cambia su nivel (RF-18). La relación no se presume ni por instrumento ni por el resultado.
30. **Evento histórico ausente o incompleto:** si no existe registro del evento, no se crea una entrada, no se afirma que ocurrió ni que falta; si existe un registro con campos faltantes, se identifican como incompletos sin inventar autor, fecha, transición o evidencia (RF-19).
31. **Evento histórico con registro pero sin fecha:** se presenta en el grupo «fecha no disponible», marcado incompleto, separado de la cronología ordenada (CA-19.4).
32. **Vista agregada con historial individual:** no expone eventos ni evidencias individuales; se mantienen el umbral uniforme 3 y la supresión existentes (RF-10/RF-11/RNF-2).
33. **Módulos de ruta:** se muestran en la secuencia definida, sin etapas ni divisiones visuales adicionales; el mapa es una vista independiente accesible desde el panel (CA-16.5/16.7).
34. **Evaluación vinculada a competencia:** conforme a v0.5.1 aprobada, el resultado de catálogo se representa con `AssessmentResult` completado y conserva `Assessment`; el resultado OCEAN se representa con `PersonalityAssessment` completado y conserva `test_version`. Sin vínculo explícito no se muestra como evidencia de la competencia ni se modifica el nivel. Esta precisión de origen no altera la decisión aprobada v0.5.0 de admitir ambos tipos mediante vínculo explícito a un resultado individual completado (RF-18/RF-21).
35. **Ruta disponible no asignada ni iniciada:** no aparece en el mapa del participante; el mentor solo ve las rutas de la vista autorizada del sujeto (CA-16.7).
36. **Agregado de rutas distintas o versiones distintas:** no se promedian entre sí; los porcentajes de ruta se agrupan solo para la misma ruta y versión (RF-10/RF-11).
37. **Transición automática de módulo:** la auditoría identifica al sistema y conserva el usuario/acción que disparó la transición (CA-17.4).
38. **Solicitud de eliminación de datos personales:** se conserva únicamente el evento mínimo de CA-19.7, con tipo y fecha del cambio, metadatos mínimos del vínculo/tipo y actor seudonimizado identificable solo por auditores autorizados. La vista normal del historial no revela la identidad del actor. No se retienen respuestas, puntuaciones ni el resultado fuente. Antes de implementar la retención del evento, la política general de conservación/eliminación debe estar identificada y documentada; no se fija un plazo ni puede decidirlo por sí misma la implementación (CA-19.7).
39. **Rendimiento y viewport:** con 50 usuarios realizando consultas interactivas simultáneamente, el p95 de las duraciones de respuesta de API de lectura del panel/mapa es ≤ 2 s; cada duración se mide desde la recepción de la solicitud en el servidor hasta completar la respuesta de la API. A 320 px de ancho, todos los controles se muestran sin recorte ni scroll horizontal y son operables con teclado y toque (RNF-5/RNF-7).
40. **Gestión del vínculo reto-módulo:** el participante puede asociar, cambiar o retirar el vínculo de sus propios retos y el mentor asignado puede hacerlo para retos de sus mentees asignados; el módulo destino debe pertenecer a una ruta consultable asignada/iniciada para el participante o a la vista autorizada del sujeto para el mentor. Para retirar el vínculo, el servidor también valida, antes del cambio, la autorización sobre el reto y el módulo actualmente vinculado y la ruta actual de este; los intentos fuera de cualquiera de esos ámbitos se deniegan. Cada cambio persiste y deja traza con actor, momento y vínculo anterior/resultante; un reto sin vínculo no participa en tasas y el módulo sin retos elegibles sigue «sin datos» (RF-20/CA-17.6/CA-20.7–20.8).
41. **Revocación de completitud automática:** si actualizar o retirar un vínculo hace que el módulo deje de cumplir la regla de completitud, se registra la transición desde `completed` al estado resultante con el sistema como actor y el usuario/acción desencadenante, autor, fecha y ambos estados (CA-17.14).
42. **Transiciones hacia y desde `abandoned`:** se permite pasar de `assigned` o `in_progress` a `abandoned`, y de `abandoned` únicamente a `assigned` o `in_progress`; cada transición permitida queda auditada con actor, fecha y estados anterior/resultante. Se rechaza cualquier otro destino desde `abandoned` sin cambiar el estado ni sus métricas. Mientras permanece `abandoned`, el reto está excluido de tasas y completitud. Al salir a `assigned` o `in_progress`, vuelve al denominador de actividad/ruta y se recalculan las tasas y la completitud afectadas; como no está `completed`, no puede mantenerse completado un módulo que dependa de él. Desde `completed`, `canceled` o `template` se rechaza la transición hacia `abandoned` sin alterar estado ni tasas (CA-17.2/17.5/17.13).
43. **Vínculo evaluación-competencia no autorizado:** si el actor no es el titular ni su mentor asignado y autorizado en el mismo ámbito que la creación, se deniega el cambio o retiro sin alterar la relación. En todo cambio o retiro autorizado, se conserva el actor, fecha y relación anterior/resultante (CA-18.6, CA-19.8).
44. **Resultado OCEAN sin instrumento de catálogo:** se vincula usando `PersonalityAssessment` y su `test_version`; no se inventa ni exige un `Assessment`/`AssessmentResult` para presentarlo como contexto (CA-21.2–21.3).
45. **Cardinalidad de vínculos evaluación-competencia:** un resultado individual completado puede relacionarse con varias competencias y cada competencia puede relacionarse con varios resultados individuales completados; no hay unicidad ni exclusividad de vínculo por resultado o por competencia (CA-18.5/CA-21.4).
46. **Resultado vinculado incompleto o eliminado:** si deja de estar completado, se conserva la asociación histórica con la competencia y se marca «incompleto»; si se elimina, se conserva únicamente el evento mínimo de CA-19.7 con fecha, tipo de cambio, metadatos mínimos del vínculo/tipo y actor seudonimizado identificable solo por auditores autorizados. La vista normal del historial no revela la identidad del actor. No se retienen respuestas, puntuaciones ni el resultado fuente y no se presenta como evidencia vigente. Antes de implementar la retención del evento, la política general de conservación/eliminación debe estar identificada y documentada; no se fija un plazo ni lo decide la implementación. El retiro explícito de la relación tampoco borra su evento histórico (CA-18.6–18.7, CA-19.7–19.8, CA-21.5).
47. **Cambio de estado de resultado con vínculo activo:** cada cambio de estado real registrado por la fuente mientras existe un vínculo activo con una competencia deja un evento inmutable con actor, fecha, estado anterior y resultante; si la fuente externa no permite esa transición, no se afirma que ocurrió ni se inventa el evento o sus campos (CA-19.4/19.8).

---

## 7. Fuera de alcance (v1)

Confirmado por el cliente; no forma parte de esta versión:

- **Tiempo real:** sin push/WebSockets; el panel se recalcula al consultarse.
- **Exportación de reportes:** sin descargas PDF/CSV de indicadores o agregados.
- **IA predictiva sobre progreso:** sin recomendaciones ni predicciones de avance generadas por IA.
- **Analítica de tendencias históricas:** sin series temporales, comparaciones entre periodos ni gráficos de evolución. Esta exclusión es una **excepción formal** a la mención de «tendencias» de P16 y DEBE documentarse conforme a `docs/constitution.md` §5 (medida compensatoria: RNF-6 distingue estados y ausencia de datos); no excluye el historial de eventos de RF-19.
- **Configuración de reglas:** sin panel para que el administrador ajuste pesos, criterios o el %; las reglas son fijas y documentadas.

La exclusión de tendencias se refiere a analítica temporal, comparaciones entre periodos o gráficos de evolución; no excluye el historial de eventos trazables exigido por RF-19. También fuera de esta versión (por coherencia con lo anterior y lo ya construido): app móvil nativa, gamificación/insignias, notificaciones push nuevas y redefinición de los dashboards existentes (se **complementan**, no se reemplazan).

---

## 8. Criterios de finalización

La funcionalidad se considera terminada cuando:

- [ ] **Cumple los principios aplicables** de `docs/constitution.md`, en especial P3, P4, P5, P6, P11 y P16.
- [ ] **Alinea con las decisiones de producto** registradas en `docs/constitution.md` §3.1 (permisos, ámbito, sesiones realizadas, periodos y privacidad colectiva).
- [ ] **Respeta la arquitectura existente** (Next.js, Laravel, PostgreSQL, entitlements) y reutiliza la autorización ya existente de relaciones y colectivos.
- [ ] **Implementa autorización en el servidor** para el panel individual, el del mentor y todos los agregados.
- [ ] **Aplica privacidad desde el diseño** en los agregados (RNF-2, umbral uniforme de 3 participantes elegibles y mínimo adicional de 3 contribuciones calculables para cada nuevo promedio de actividad/ruta).
- [ ] **Define reglas de negocio y criterios de aceptación** para cada indicador (Anexo A conciliable con el origen).
- [ ] **Distingue «sin datos» de «cero»** en todos los indicadores.
- [ ] **Incluye pruebas automatizadas** de cálculo con datos conocidos, casos límite, datos incompletos y permisos (permitido y denegado).
- [ ] **Conserva integridad y trazabilidad** de objetivos, estados y cambios explícitos de vínculos entre retos y módulos, sin alterar los permisos de objetivos.
- [ ] **Cumple el prerrequisito documental de retención:** antes de implementar la retención del evento mínimo seudonimizado tras una eliminación, la política general de conservación/eliminación está identificada y documentada. Esta spec no fija el plazo ni permite que la implementación lo decida por sí misma.
- [ ] **Verifica los requisitos nuevos aprobados y su actualización**: secuencia sin etapas ni asociaciones inferidas, gestión autorizada y auditable del vínculo reto-módulo, tasas de actividad/ruta basadas en estados persistidos y completitud automática de módulos según actividades vinculadas; evaluaciones solo como contexto mediante relación explícita; cronología trazable con eventos anteriores verificables.
- [ ] **Documenta migraciones, dependencias y cambios de API** (incluida la nueva entidad de objetivo).
- [ ] **Es accesible y responsive** (teclado, contraste, estados, móvil).
- [ ] **Está traducida** en es/en/pt y permite diagnosticar fallos sin exponer información sensible.
- [ ] **`AGENTS.md`, `MEMORY.md` y la documentación relevante** quedan actualizados.

---

## 9. Dudas abiertas y aclaraciones resueltas

Los requisitos v0.4.0, v0.5.0 y v0.5.1 fueron aprobados por el usuario, pero aún están pendientes de implementación; v0.3.0 sigue siendo el baseline funcional implementado. v0.5.0 aprobó permitir ambos tipos de evaluaciones como contexto de una competencia mediante una relación explícita con un resultado individual completado. v0.5.1 precisó, a partir de la inspección de las fuentes existentes, qué registro representa cada tipo; esta precisión está aprobada y no se atribuye retroactivamente a v0.5.0. Se mantienen las decisiones ya cerradas que se enumeran a continuación, incluidos los cambios explícitos de política de relación reto-módulo (D-13/D-29) y la precisión aprobada de fuente en v0.5.1 (D-33).

Resueltas el 2026-10-11 e incorporadas a `docs/constitution.md` §3.1 (ya reflejadas en RF-1, RF-5, RF-8, RF-10, RF-11, RF-14 y RNF-2): permisos de objetivos, ámbito de objetivos, sesiones realizadas, periodos predeterminados y privacidad colectiva.

Resueltas el 2026-10-12 por el plan de implementación (`plan.md` §2, decisiones D1–D11) y ya reflejadas en el código:

- **Contenido del objetivo**: **texto plano** en `title`/`description` (idioma del autor), no multi-idioma (D1). Lo escriben usuarios, no admins; traducirlo no es viable en v1.
- **«Actividades» del baseline:** para el indicador general vigente de RF-6 incluye **retos y módulos de ruta**. Esta decisión no redefine las tasas nuevas específicas del mapa: en RF-17 el denominador de actividad incluye exclusivamente `Challenge` vinculado explícitamente a `PathModule` con estado elegible; los módulos son unidades de ruta, no actividades de ese denominador.
- **% de objetivos**: **todos los objetivos valen igual**, sin ponderación por importancia (D3).
- **Denominador del %**: los objetivos `abandonado` **permanecen** (son no logrados); los *soft-deleted* **salen** (D4).
- **«Participante elegible»** (umbral de 3): miembro **activo con pertenencia vigente y ≥1 objetivo individual** (D5).
- **Cifra principal del agregado**: la **media** del % individual; la **suma** logrados/total es conciliación secundaria (D6).
- **Objetivo colectivo**: se reporta como **línea propia del colectivo** (bloque separado), fuera de la media individual (D7).
- **Umbral de privacidad**: constante de config `progress.collective_privacy_threshold = 3` («umbral inicial»); la configuración por organización queda **fuera de alcance** (D8).
- **Mentor / cartera**: **no** ve un agregado de su cartera en v1; ve panel individual por mentee + lista con banderas (D9).
- **Roles «responsables» de colectivo**: `FamilyGroup.head_user_id`, `Cohort.created_by`, `Company.admin_id` (reutiliza `userCanManageSource`); si no hay responsable, no hay vista de responsable (D10).
- **Admins de plataforma**: solo con **permiso explícito** `progress.view.support`, con registro de auditoría; sin él, `403` (D11).

---

### Aclaraciones de la actualización propuesta (resueltas el 2026-10-12)

- **D-12 — Secuencia visual (resuelta):** el mapa muestra únicamente módulos existentes ordenados según la ruta; no se crean etapas, cortes ni agrupaciones adicionales (CA-16.5).
- **D-13 — Relaciones del mapa (resuelta en v0.4.0; actualizada en v0.5.0):** objetivos y actividades aparecen solo si el vínculo con ruta/módulo está registrado. Se mantiene la prohibición de inferir vínculos, pero se reemplaza la política previa de no crear relaciones: ahora se permite establecer, cambiar o retirar explícitamente el vínculo reto-módulo conforme a RF-20. Esta autorización no alcanza a otras relaciones ni modifica permisos de objetivos (CA-16.2/16.3, RF-20).
- **D-14 — Completitud del módulo y ciclo de vida de `abandoned` (resuelta):** la unidad de actividad para tasas del mapa es `Challenge` vinculado explícitamente a `PathModule`, con estado elegible `assigned`, `in_progress` o `completed`; `template`, `canceled` y `abandoned` se excluyen. El módulo se registra completado solo con al menos un reto elegible y todos los retos vinculados no excluidos explícitamente `completed`. Un estado faltante causa incompletitud y bloquea tasa parcial/completitud; sin retos elegibles, «sin datos» (CA-17.2–17.7). Un `Challenge` solo puede transitar a `abandoned` desde `assigned` o `in_progress`, y desde `abandoned` solo puede volver a `assigned` o `in_progress`; todas estas transiciones son trazables. Al salir de `abandoned`, vuelve a ser elegible para las tasas y la regla de completitud del módulo (CA-17.13). Esta es una capacidad propuesta, no atribuida al baseline.
- **D-15 — Enlace evaluación-competencia (decisión aprobada en v0.5.0; precisión aprobada en v0.5.1):** v0.5.0 aprobó que ambos tipos —catálogo y personalidad/OCEAN— puedan mostrarse como contexto de una competencia únicamente mediante una relación explícita con un resultado individual completado. v0.5.1 fijó los registros fuente y la cardinalidad muchos-a-muchos. El participante titular o su mentor asignado y autorizado puede crear, cambiar o retirar el vínculo con el mismo ámbito y autorización vigentes que para crearlo; no se infiere por tipo, resultado o módulo ni se cambia automáticamente el nivel. Cada operación se audita y registra en el historial con actor, fecha y relación anterior/resultante, incluida la ausencia para creación/retiro. Si el resultado deja de estar completado conserva el vínculo histórico como incompleto, y si se elimina conserva solo el evento mínimo de CA-19.7: tipo y fecha del cambio, metadatos mínimos del vínculo/tipo y actor seudonimizado identificable solo por auditores autorizados. El historial normal no revela la identidad; no se conservan respuestas, puntuaciones ni el resultado fuente. La retención del evento requiere identificar y documentar previamente la política general de conservación/eliminación; no se fija un plazo ni lo determina la implementación (CA-18.1–18.7, CA-19.3/19.7/19.8, RF-21).
- **D-16 — Historial previo y fechas ausentes (resuelta):** se incluyen eventos anteriores solo cuando las fuentes existentes acrediten el evento. Si no existe registro, no se crea una entrada ni se afirma que el evento ocurrió o falta; si existe registro sin fecha, se presenta aparte bajo «fecha no disponible» y se marca incompleto. No se reconstruyen eventos/datos ni se altera o estima tendencia (CA-19.1–19.5).
- **D-17 — Faltantes y base de tasas colectivas nuevas (resuelta):** los participantes que pertenecen a la base general de elegibilidad vigente siguen contando en ella aunque carezcan de una tasa individual de actividad/ruta. No se les asigna cero ni se incluyen en el promedio de esa métrica. Cada nuevo promedio requiere al menos 3 porcentajes individuales calculables; si contribuyen menos de 3, esa tasa se suprime, aunque la base general elegible sea de 3 o más. También rige la supresión general si la base general elegible es menor de 3. Esta regla adicional protege cada promedio nuevo y no modifica la agregación existente de objetivos (RF-10/RF-11, RNF-2).
- **D-18 — Universo y estados de tasas (resuelta):** solo los `Challenge` con relación explícita a `PathModule` forman el universo de actividades del mapa; son elegibles los estados `assigned`, `in_progress` y `completed`. `template`, `canceled` y `abandoned` se excluyen del denominador y de la regla de completitud del módulo. Se permite transitar a `abandoned` desde `assigned` o `in_progress`, y desde `abandoned` a `assigned` o `in_progress`; al reactivarse, el reto vuelve a ser elegible en las tasas (RF-17/CA-17.13).
- **D-19 — Estados faltantes y tasas parciales (resuelta):** si falta el estado de un reto vinculado aplicable, se marca incompleto y no se presenta porcentaje parcial de actividad, módulo o ruta; tampoco se da por completado el módulo (CA-17.5/17.7/17.9).
- **D-20 — Rutas visibles (resuelta):** el participante ve rutas asignadas o con progreso iniciado. El mentor autorizado ve las rutas incluidas en la vista autorizada del sujeto. Para administradores no se amplía el acceso más allá del permiso vigente (CA-16.7).
- **D-21 — Ubicación del mapa (resuelta):** el mapa es una vista independiente accesible desde el panel de progreso (CA-16.7).
- **D-22 — Eventos sin fecha (resuelta):** se muestran en grupo separado «fecha no disponible», marcados incompletos; solo los eventos con fecha disponible se ordenan cronológicamente (CA-19.4).
- **D-23 — Tipos de evaluación vinculables (decisión aprobada en v0.5.0, precisada en v0.5.1):** se admiten evaluaciones de catálogo y de personalidad/OCEAN, siempre que el vínculo explícito con la competencia apunte a un resultado individual completado (CA-18.1/18.5, RF-21). La fuente concreta, cardinalidad muchos-a-muchos y conducta histórica ante cambio/eliminación del resultado quedaron aprobadas en v0.5.1 y no se atribuyen a v0.5.0.
- **D-24 — Promedios de ruta (resuelta):** se promedian porcentajes individuales solo para la misma ruta y versión; contribuciones sin porcentaje calculable no se convierten en cero ni entran en ese promedio, aunque permanecen en la base general de elegibilidad (RF-10/RF-11, RNF-2).
- **D-25 — Autoría de completitud automática (resuelta):** la traza identifica al sistema como actor y conserva el usuario y la acción que dispararon la transición (CA-17.4).
- **D-26 — Solicitud de eliminación (resuelta para el alcance funcional; prerrequisito documental pendiente):** ante una solicitud de eliminación que afecte la traza, se conserva únicamente el evento mínimo de CA-19.7: fecha, tipo de cambio, metadatos mínimos del vínculo/tipo necesarios para auditoría y actor seudonimizado identificable únicamente por auditores autorizados. La vista normal del historial no revela la identidad del actor. No se conservan respuestas, puntuaciones ni el resultado fuente eliminado. La respuesta es obligatoria y no depende de una elección del usuario caso a caso. La política general de conservación/eliminación debe identificarse y documentarse antes de implementar la retención del evento; no se fija un plazo y la implementación no puede decidirlo por sí misma. Esta formulación de actor seudonimizado audit-only es la decisión final del usuario y sustituye explícitamente la respuesta anterior que solicitaba anonimizar también al actor; P5 sigue siendo obligatorio y no se crea excepción (CA-19.7/19.8).
- **D-27 — Rendimiento (resuelta):** «carga normal» significa 50 usuarios realizando consultas interactivas simultáneamente. En ese contexto, las consultas interactivas de lectura del panel/mapa deben responder en ≤ 2 s (p95). Cada duración se mide desde la recepción de la solicitud en el servidor hasta completar la respuesta de API; no se presuponen hardware ni dataset específicos (RNF-5).
- **D-28 — Responsive (resuelta):** el viewport mínimo es 320 px; todos los controles deben permanecer visibles sin recorte ni scroll horizontal y ser operables con teclado y toque (RNF-7).
- **D-29 — Gestión del vínculo reto-módulo (resuelta):** el participante titular y el mentor asignado pueden asociar, cambiar o retirar el vínculo de un reto con un módulo dentro de sus respectivos ámbitos; el destino se limita a un módulo de una ruta que el participante puede consultar por estar asignada o iniciada, o que está dentro de la vista autorizada del sujeto para el mentor. La autorización en servidor cubre tanto reto como módulo; al retirar, también cubre el módulo actualmente vinculado y su ruta actual, comprobados antes del cambio. El vínculo se persiste y cada cambio es auditable. No se infieren relaciones faltantes, no se habilita la gestión de relaciones distintas y no se alteran permisos de objetivos. Los retos sin vínculo no contribuyen a las tasas; módulos sin retos vinculados elegibles siguen «sin datos» (RF-20, CA-17.6, CA-20.7–20.8).
- **D-30 — Transiciones permitidas hacia `abandoned` (resuelta):** un `Challenge` solo puede transitar a `abandoned` desde `assigned` o `in_progress`; no puede hacerlo desde `completed`, `canceled` ni `template`. La transición permitida se registra como cambio de estado trazable (CA-17.13). Las transiciones salientes quedan aclaradas en D-32.
- **D-31 — Mutabilidad y trazabilidad del vínculo evaluación-competencia (decisión aprobada en v0.5.0 y precisada en v0.5.1):** el participante titular o su mentor asignado y autorizado puede cambiar o retirar el vínculo con el mismo ámbito y autorización vigentes que para crearlo, para ambos tipos de evaluación aprobados. Cada operación se audita y registra en el historial con actor, fecha, relación anterior y relación resultante, incluyendo explícitamente la ausencia de relación para creación/retiro (CA-18.5–18.6, CA-19.3/19.8). La correspondencia con las fuentes concretas, la cardinalidad y el estado histórico cuando cambia/se elimina el resultado quedaron aprobados en v0.5.1 (D-33).

- **D-32 — Transiciones salientes de `abandoned` (resuelta el 2026-10-12):** `abandoned` no es terminal. Un `Challenge` en ese estado puede reactivarse a `assigned` o `in_progress`, y a ningún otro estado conforme a esta decisión. Las transiciones son auditables según P5. Mientras permanece abandonado queda excluido de las tasas; al reactivarse vuelve a ser elegible y se recalculan las tasas y completitud afectadas (CA-17.13).
- **D-33 — Precisión aprobada de origen, cardinalidad e historial de resultados (v0.5.1):** según los datos existentes, los resultados de catálogo se representan mediante `AssessmentResult` individual completado, conservando `Assessment`, y los resultados de personalidad/OCEAN mediante `PersonalityAssessment` individual completado, conservando `test_version`; no se crea ni exige un `Assessment` para OCEAN. La relación explícita es muchos-a-muchos: un resultado puede vincularse a varias competencias y cada competencia a varios resultados. Si un resultado vinculado deja de estar completado, el historial conserva el vínculo y lo muestra como incompleto. Si se elimina, se conserva únicamente el evento mínimo de CA-19.7 con fecha, tipo de cambio, metadatos mínimos del vínculo/tipo necesarios para auditoría y actor seudonimizado identificable solo por auditores autorizados; el historial normal no revela la identidad. No se conservan respuestas, puntuaciones ni el resultado fuente eliminado, ni se presenta como evidencia vigente. Esta decisión final sustituye la respuesta anterior que pedía anonimizar también al actor; P5 permanece plenamente aplicable y no se agrega excepción. Antes de implementar la retención del evento debe identificarse y documentarse la política general de conservación/eliminación; no se fija un plazo ni puede decidirlo por sí misma la implementación. Además, cada cambio de estado real del resultado mientras existe un vínculo activo debe dejar un evento inmutable en el historial con actor, fecha y estados anterior/resultante; no se registra como ocurrido un cambio que la fuente externa no permita. Estas precisiones desarrollan —y no sustituyen ni se atribuyen a— la decisión de producto aprobada en v0.5.0: ambos tipos mediante vínculo explícito a un resultado individual completado, sin cambio automático del nivel de competencia (RF-18/RF-19/RF-21, CA-21.1–21.5).

### Dudas abiertas

No quedan dudas abiertas de producto para esta revisión: las respuestas al QA v0.5.1 fijan cardinalidad muchos-a-muchos y la representación histórica cuando un resultado pierde completitud o se elimina. Para la eliminación, solo se conserva el evento mínimo seudonimizado de CA-19.7, sin respuestas, puntuaciones ni resultado fuente; su actor es identificable solo por auditores autorizados y no se identifica en el historial normal. La respuesta anterior que pedía anonimizar también al actor queda expresamente sustituida por esta última decisión. Identificar y documentar la política general de conservación/eliminación sigue siendo prerrequisito documental antes de implementar la retención del evento; no se inventa ni fija plazo. Estas respuestas están incorporadas en v0.5.1, aprobada por el usuario y pendiente de implementación; v0.4.0 y v0.5.0 también siguen pendientes de implementación y el baseline v0.3.0 permanece implementado.

**Historial de versiones relevante:** v0.3.0 es el baseline implementado; v0.4.0, v0.5.0 y v0.5.1 están aprobadas por el usuario y pendientes de implementación. v0.5.0 determinó que resultados de ambos tipos (catálogo y personalidad/OCEAN) se relacionan con competencias mediante vínculo explícito a un resultado individual completado, sin cambio automático del nivel. v0.5.1 precisa el origen por tipo, cardinalidad muchos-a-muchos, tratamiento histórico al cambiar/eliminar el resultado y el prerrequisito documental de conservación/eliminación antes de implementar la retención del evento mínimo. La decisión más reciente establece actor seudonimizado identificable únicamente por auditores autorizados, sin revelar identidad en el historial normal, y sustituye la respuesta anterior que pedía anonimizar al actor.

## Anexo A — Definiciones de indicadores (P6/P11)

| Indicador | Definición | Fuente | Regla de cálculo | «Sin datos» / «suprimido» cuando |
|---|---|---|---|---|
| % de objetivos logrados | Proporción de objetivos **individuales** cumplidos sobre el total | Objetivos individuales del usuario | objetivos `logrado` ÷ objetivos individuales totales | El usuario no tiene objetivos individuales |
| Objetivos por estado | Conteo de objetivos en cada estado | Objetivos individuales del usuario | Conteo por estado (`no iniciado`, `en progreso`, `logrado`, `abandonado`), a fecha actual | Sin objetivos |
| Sesiones realizadas | Sesiones efectivamente completadas y con asistencia confirmada, en el mes calendario actual | Sesiones + asistencia | Conteo de sesiones `completed` **con asistencia confirmada** del mes actual | Sin sesiones en el periodo |
| Sesiones programadas | Sesiones futuras previstas | Sesiones | Conteo de sesiones `scheduled` con fecha futura | Sin sesiones programadas |
| Actividades pendientes | Retos/módulos no completados | Retos y progreso de rutas | Conteo de retos `assigned`/`in_progress` + módulos no completados, excluyendo `canceled`, `abandoned` y `template` | Sin actividades |
| Actividades vencidas | Pendientes con fecha límite pasada | Retos/progreso | Subconjunto de pendientes con `due_at` < ahora | Sin pendientes con fecha |
| Tasa de completitud de actividades vinculadas | Proporción de actividades elegibles completadas en el ámbito vinculado | `Challenge` explícitamente vinculado a `PathModule`, con estado explícito | `Challenge` elegibles `completed` ÷ retos elegibles vinculados × 100. Elegibles: `assigned`, `in_progress`, `completed`; cada actividad se presenta con su estado, no con un porcentaje artificial. Si falta un estado aplicable, no se presenta tasa parcial y se marca incompleto | No hay retos vinculados elegibles; cualquier estado aplicable ausente se marca incompleto y no se muestra tasa |
| Estado/completitud de módulo | Estado individual persistido automáticamente a partir de sus actividades vinculadas | `Challenge` vinculado a `PathModule` con estado explícito | Se registra `completed` cuando hay al menos un reto elegible y todos los vinculados no excluidos están `completed`; `template`, `canceled` y `abandoned` se excluyen; cualquier estado aplicable ausente bloquea completitud | No hay retos vinculados elegibles; cualquier estado necesario ausente se marca incompleto |
| % de ruta completada | Proporción de módulos elegibles con completitud automática persistida | Estado individual del módulo y estado de los `Challenge` vinculados | Módulos completados ÷ módulos elegibles; igual peso por módulo; no usa el porcentaje agregado general de ruta. Un estado faltante aplicable bloquea el porcentaje (sin tasa parcial) | No hay módulos elegibles y no hay estados faltantes; marcar incompletitud y no mostrar tasa si faltan estados aplicables |
| Competencias (skills) | Skills con su nivel | `UserSkill` + `SkillTag` | Listado de skills con `SkillLevel` (estado actual) | Sin skills registradas |
| Competencias (OCEAN) | Dimensiones de personalidad y fecha | Evaluación de personalidad | Factores O,C,E,A,N con `completed_at` (estado actual) | Evaluación no completada o plan sin entitlement |
| Evaluaciones relacionadas con competencias | Evidencia contextual relacionada y su relación histórica | Relación explícita muchos-a-muchos con `AssessmentResult` individual completado + `Assessment` para catálogo, o con `PersonalityAssessment` individual completado + `test_version` para personalidad/OCEAN | Mostrar resultado vigente, referencia de origen y fecha disponible; no cambiar el nivel automáticamente. Si el resultado deja de estar completado, conservar el vínculo histórico y marcarlo incompleto; si se elimina, conservar únicamente el evento mínimo: fecha, tipo de cambio, metadatos mínimos del vínculo/tipo y actor seudonimizado identificable solo por auditores autorizados. La vista normal del historial no revela la identidad. No conservar respuestas, puntuaciones ni resultado fuente, ni presentarlo como evidencia vigente. Identificar y documentar la política general de conservación/eliminación antes de implementar la retención; no fijar plazo | No existe relación registrada; el resultado incompleto o eliminado deja de ser evidencia vigente y se identifica según corresponda |
| Historial de progreso | Eventos cronológicos registrados para objetivos, actividades/rutas y competencias | Registros de cambios, fechas y evidencias de las fuentes | Presentar eventos con fecha disponible en orden cronológico; registros sin fecha en grupo separado «fecha no disponible» e incompletos; no inventar eventos inexistentes | Campos faltantes en un evento registrado; registro sin fecha separado e incompleto |
| Próximos compromisos | Sesiones y vencimientos próximos | Sesiones + objetivos + actividades | Sesiones `scheduled` + fechas límite dentro de **7 días** | Sin elementos en la ventana |
| Agregado del colectivo | Agregados vigentes y promedios nuevos de indicadores individuales | Base general de participantes elegibles del colectivo; para cada nueva tasa, participantes con porcentaje individual calculable | Objetivos: suma de logros/totales y media del % individual con la regla vigente. Actividad/ruta: promedio de porcentajes individuales calculables para la misma métrica; los promedios de ruta se agrupan por la misma ruta y versión. No incluir valores ausentes como cero. Mantener el umbral general de 3 y suprimir adicionalmente cada nuevo promedio con menos de 3 contribuciones calculables | Cuando no hay al menos 3 contribuciones calculables, el promedio nuevo se suprime por privacidad; los porcentajes ausentes no se convierten en cero. Se aplica también la supresión general vigente |

Los participantes sin porcentaje individual para una métrica nueva permanecen en la base general de elegibilidad, pero no forman parte de su promedio. Los porcentajes ausentes nunca se interpretan como cero; cada promedio nuevo debe satisfacer ambos umbrales de privacidad antes de mostrarse.
