# Spec 001 — Panel de progreso y seguimiento

**Estado:** implementada
**Versión:** 0.3.0
**Fecha:** revisado 2026-10-12
**Ámbito:** nueva funcionalidad de producto (el QUÉ y el POR QUÉ; sin decisiones de implementación)
**Referencias:** `docs/constitution.md` (principios P1–P20; los aplicables se citan en el texto) y sus **decisiones de producto §3.1** (doc v1.1.0)
**Naturaleza:** esta spec describe **qué** debe hacer la funcionalidad y **por qué**. No define endpoints, esquemas de base de datos ni componentes concretos; esos son decisiones de diseño posteriores.

---

## 1. Contexto y objetivo

### 1.1 Contexto (de dónde partimos)

MIS ya tiene datos dispersos que, hoy, se muestran de forma aislada:

- **Objetivos:** no existen como entidad. Solo hay texto libre (`MentorshipRelationship.objectives`) y el campo `objective`/`success_criteria` de los retos.
- **Sesiones:** existen con estado (`scheduled`, `in_progress`, `completed`, `canceled`) y fecha (`scheduled_at`).
- **Actividades:** existen como retos (`Challenge`, con estado, `progress`, `due_at`, `completed_at`) y como progreso en rutas de aprendizaje (`UserLearningProgress`).
- **Competencias:** existen como skills con nivel (`UserSkill` + `SkillTag`, enum `SkillLevel`) y como dimensiones de personalidad OCEAN.
- **Compromisos:** existen como sesiones futuras programadas y fechas límite de retos.
- **Colectivos:** existen `FamilyGroup`, `Cohort` y `Company`, cada uno con miembros y una relación de mentoría polimórfica (`source` → User | FamilyGroup | Cohort | Company).

Los dashboards actuales (`personal`, `mentor`) muestran piezas sueltas y algunos indicadores aproximados en el cliente. No existe una vista unificada que responda a la pregunta central de un usuario: **«¿cómo va mi proceso (o el de mi colectivo) y qué me toca ahora?»**.

### 1.2 Objetivo (qué queremos y por qué)

Construir un **panel de progreso y seguimiento** que, apoyándose en los dashboards existentes y en los datos ya registrados, presente de forma clara y verificable:

1. **Objetivos** y su grado de logro.
2. **Sesiones realizadas** (no programadas).
3. **Actividades pendientes** y vencidas.
4. **Competencias desarrolladas** (skills y perfil OCEAN).
5. **Próximos compromisos**.

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

Incluye: entidad de objetivo de primera clase; panel individual del mentee; panel del mentor sobre sus mentees; agregados de colectivo para responsables y miembros; cinco bloques de indicadores individuales más la vista agregada de colectivo; autorización y privacidad; i18n y accesibilidad.

Excluye (ver §7): tiempo real, exportación de reportes, IA predictiva sobre progreso, tendencias históricas/series temporales y configuración de reglas por el administrador.

### 1.5 Decisiones cerradas con el cliente (2026-10-11)

Decisiones **vinculantes**, registradas en `docs/constitution.md` §3.1 (doc v1.1.0); esta spec se alinea con ellas:

1. **Permisos de objetivos:** el mentor administra los objetivos individuales; el mentee propone objetivos y aporta evidencias; el responsable autorizado administra los objetivos colectivos.
2. **Ámbito de objetivos:** se admiten objetivos individuales y colectivos, con titular o colectivo responsable obligatorio según corresponda.
3. **Sesiones realizadas:** cuentan solo las de estado `completed` **con asistencia confirmada**.
4. **Periodos predeterminados:** próximos 7 días para compromisos; estado actual para objetivos; mes calendario actual para sesiones realizadas.
5. **Privacidad colectiva:** umbral inicial de 3 participantes elegibles para mostrar agregados, aplicado de forma uniforme a toda vista (miembro, responsable o mentor), con reglas explícitas de pertenencia y tratamiento explícito de datos faltantes.

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
- **HU-8 (Responsable de colectivo):** como responsable, quiero ver el avance agregado de mi familia/grupo/empresa, para tomar decisiones de acompañamiento sin fiscalizar a cada persona.
- **HU-9 (Miembro):** como miembro, quiero ver el avance agregado de mi colectivo, para situarme en el proceso común.
- **HU-10 (Todos):** como usuario autorizado, quiero que el panel distinga «sin datos» de «cero», para no interpretar mal lo que veo.
- **HU-11 (Todos):** como usuario, quiero ver el panel en mi idioma (es/en/pt) y con estados de carga/error claros, para usarlo con confianza.
- **HU-12 (Mentee):** como mentee, quiero proponer objetivos y aportar evidencias, para influir en lo acordado sin administrar el objetivo.
- **HU-13 (Mentor):** como mentor, quiero administrar (crear, ajustar y cerrar) los objetivos individuales de mis mentees, para conducir el proceso.
- **HU-14 (Responsable):** como responsable de colectivo, quiero administrar los objetivos colectivos, para orientar a la organización sin depender de cada persona.

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
- **CA-6.5 (Ubicuo):** El sistema DEBE excluir de «pendientes» y de «completadas» las actividades canceladas (`canceled`) y las plantillas (`template`).

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

**Por qué:** el valor para la organización es la lectura agregada; el cliente fijó agregados por **suma** (totales del colectivo) y **media** (promedio del % de objetivos logrados de sus miembros).

**Criterios de aceptación:**

- **CA-10.1 (Ubicuo):** El sistema DEBE calcular el agregado como suma de logros/totales del colectivo y media del % de objetivos logrados de sus miembros.
- **CA-10.2 (Ubicuo):** El sistema DEBE permitir al responsable ver el agregado solo de los colectivos que le corresponden y sin exponer indicadores individuales.
- **CA-10.3 (No deseado):** SI el colectivo no tiene miembros con objetivos, ENTONCES el sistema DEBE indicar «sin datos» y no calcular una media engañosa.
- **CA-10.4 (Ubicuo):** El sistema DEBE distinguir el tamaño de la base del agregado (cuántos miembros contribuyen).
- **CA-10.5 (No deseado):** SI la base de participantes elegibles es inferior al **umbral de 3** (decisión §3.1.5), ENTONCES el sistema DEBE suprimir el agregado también para el responsable y mostrar el motivo, igual que para los miembros (RNF-2).

### RF-11 — Agregado visible para el miembro del colectivo

El sistema DEBE mostrar a un miembro el agregado del colectivo al que pertenece, **sin** exponer indicadores individuales de otras personas.

**Por qué:** transparencia del grupo con privacidad desde el diseño (P4).

**Criterios de aceptación:**

- **CA-11.1 (Ubicuo):** El sistema DEBE mostrar a un miembro solo métricas agregadas de su colectivo.
- **CA-11.2 (No deseado):** SI un usuario no pertenece al colectivo, ENTONCES el sistema DEBE denegar el acceso a su agregado.
- **CA-11.3 (No deseado):** SI la base de participantes elegibles del colectivo es inferior a **3** (decisión §3.1.5), ENTONCES el sistema DEBE suprimir el agregado, mostrar el motivo y tratar la falta de base como dato protegido (RNF-2).

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

---

## 5. Requisitos no funcionales (RNF)

- **RNF-1 (Autorización — P3):** toda operación del panel y de agregados se autoriza en el servidor. Ocultar elementos en el frontend no constituye autorización.
- **RNF-2 (Privacidad — P4):** los agregados de colectivo no deben permitir inferir el valor individual de una persona; se aplica un **umbral uniforme de 3 participantes elegibles** a toda vista (miembro, responsable o mentor) y, por debajo del umbral, el agregado se suprime con motivo explícito.
- **RNF-3 (Trazabilidad — P5):** los cambios relevantes de objetivos y estados quedan auditables y no se alteran desde las operaciones ordinarias de negocio.
- **RNF-4 (Consistencia de métricas — P11):** cada indicador tiene definición, fuente y regla únicas y compartidas (ver Anexo A); los totales deben poder conciliarse con los registros de origen.
- **RNF-5 (Rendimiento — consulta bajo demanda):** al excluirse el tiempo real, el panel se calcula al consultarse; DEBE responder en un tiempo percibido como inmediato para el uso interactivo y evitar recomputar datos idénticos de forma redundante.
- **RNF-6 (Accesibilidad — P16):** navegación por teclado, foco visible, contraste suficiente y distinción visual de estados y ausencia de datos (las tendencias quedan fuera de alcance; ver §7).
- **RNF-7 (Responsive):** el panel debe verse y usarse bien en móvil.
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
17. **Actividad cancelada o plantilla:** no cuenta ni como pendiente ni como completada (CA-6.5).
18. **Sesión `completed` sin asistencia confirmada:** no cuenta como realizada (CA-5.2).
19. **Objetivo colectivo:** se refleja en el ámbito del colectivo; no entra en el porcentaje individual de una persona (RF-4/RF-10).
20. **Usuario sin permiso de administración:** no puede crear ni editar objetivos; el sistema deniega la operación en el servidor (CA-1.6).

---

## 7. Fuera de alcance (v1)

Confirmado por el cliente; no forma parte de esta versión:

- **Tiempo real:** sin push/WebSockets; el panel se recalcula al consultarse.
- **Exportación de reportes:** sin descargas PDF/CSV de indicadores o agregados.
- **IA predictiva sobre progreso:** sin recomendaciones ni predicciones de avance generadas por IA.
- **Tendencias históricas:** sin series temporales ni gráficos de evolución; solo estado actual y acumulado. Esta exclusión es una **excepción formal** a la mención de «tendencias» de P16 y DEBE documentarse conforme a `docs/constitution.md` §5 (medida compensatoria: RNF-6 distingue estados y ausencia de datos).
- **Configuración de reglas:** sin panel para que el administrador ajuste pesos, criterios o el %; las reglas son fijas y documentadas.

También fuera de esta versión (por coherencia con lo anterior y lo ya construido): app móvil nativa, gamificación/insignias, notificaciones push nuevas y redefinición de los dashboards existentes (se **complementan**, no se reemplazan).

---

## 8. Criterios de finalización

La funcionalidad se considera terminada cuando:

- [ ] **Cumple los principios aplicables** de `docs/constitution.md`, en especial P3, P4, P5, P6, P11 y P16.
- [ ] **Alinea con las decisiones de producto** registradas en `docs/constitution.md` §3.1 (permisos, ámbito, sesiones realizadas, periodos y privacidad colectiva).
- [ ] **Respeta la arquitectura existente** (Next.js, Laravel, PostgreSQL, entitlements) y reutiliza la autorización ya existente de relaciones y colectivos.
- [ ] **Implementa autorización en el servidor** para el panel individual, el del mentor y todos los agregados.
- [ ] **Aplica privacidad desde el diseño** en los agregados (RNF-2, umbral uniforme de 3 participantes elegibles).
- [ ] **Define reglas de negocio y criterios de aceptación** para cada indicador (Anexo A conciliable con el origen).
- [ ] **Distingue «sin datos» de «cero»** en todos los indicadores.
- [ ] **Incluye pruebas automatizadas** de cálculo con datos conocidos, casos límite, datos incompletos y permisos (permitido y denegado).
- [ ] **Conserva integridad y trazabilidad** de objetivos y estados.
- [ ] **Documenta migraciones, dependencias y cambios de API** (incluida la nueva entidad de objetivo).
- [ ] **Es accesible y responsive** (teclado, contraste, estados, móvil).
- [ ] **Está traducida** en es/en/pt y permite diagnosticar fallos sin exponer información sensible.
- [ ] **`AGENTS.md`, `MEMORY.md` y la documentación relevante** quedan actualizados.

---

## 9. Dudas abiertas

Resueltas el 2026-10-11 e incorporadas a `docs/constitution.md` §3.1 (ya reflejadas en RF-1, RF-5, RF-8, RF-10, RF-11, RF-14 y RNF-2): permisos de objetivos, ámbito de objetivos, sesiones realizadas, periodos predeterminados y privacidad colectiva.

Resueltas el 2026-10-12 por el plan de implementación (`plan.md` §2, decisiones D1–D11) y ya reflejadas en el código:

- **Contenido del objetivo**: **texto plano** en `title`/`description` (idioma del autor), no multi-idioma (D1). Lo escriben usuarios, no admins; traducirlo no es viable en v1.
- **«Actividades»**: incluye **retos y módulos de ruta** (D2) — retos `assigned`/`in_progress` + rutas con progreso no completado, excluyendo `canceled`/`template`.
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

## Anexo A — Definiciones de indicadores (P6/P11)

| Indicador | Definición | Fuente | Regla de cálculo | «Sin datos» cuando |
|---|---|---|---|---|
| % de objetivos logrados | Proporción de objetivos **individuales** cumplidos sobre el total | Objetivos individuales del usuario | objetivos `logrado` ÷ objetivos individuales totales | El usuario no tiene objetivos individuales |
| Objetivos por estado | Conteo de objetivos en cada estado | Objetivos individuales del usuario | Conteo por estado (`no iniciado`, `en progreso`, `logrado`, `abandonado`), a fecha actual | Sin objetivos |
| Sesiones realizadas | Sesiones efectivamente completadas y con asistencia confirmada, en el mes calendario actual | Sesiones + asistencia | Conteo de sesiones `completed` **con asistencia confirmada** del mes actual | Sin sesiones en el periodo |
| Sesiones programadas | Sesiones futuras previstas | Sesiones | Conteo de sesiones `scheduled` con fecha futura | Sin sesiones programadas |
| Actividades pendientes | Retos/módulos no completados | Retos y progreso de rutas | Conteo de retos `assigned`/`in_progress` + módulos no completados, excluyendo `canceled` y `template` | Sin actividades |
| Actividades vencidas | Pendientes con fecha límite pasada | Retos/progreso | Subconjunto de pendientes con `due_at` < ahora | Sin pendientes con fecha |
| Competencias (skills) | Skills con su nivel | `UserSkill` + `SkillTag` | Listado de skills con `SkillLevel` (estado actual) | Sin skills registradas |
| Competencias (OCEAN) | Dimensiones de personalidad y fecha | Evaluación de personalidad | Factores O,C,E,A,N con `completed_at` (estado actual) | Evaluación no completada o plan sin entitlement |
| Próximos compromisos | Sesiones y vencimientos próximos | Sesiones + objetivos + actividades | Sesiones `scheduled` + fechas límite dentro de **7 días** | Sin elementos en la ventana |
| Agregado del colectivo | Suma y media de indicadores individuales | Miembros elegibles del colectivo | Suma de logros/totales; media del % individual; **suprimido si la base elegible < 3** | Colectivo sin miembros elegibles con datos |
