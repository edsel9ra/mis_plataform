# Spec 013 — Mapa y agregados de aprendizaje

Estado: aprobada

**Spec padre aprobada:** `specs/001-progress-tracking-dashboard/spec.md`, v0.5.1 (2026-10-13). Esta spec desglosa RF-16 y la ampliación agregada ya aprobada de RF-10/RF-11; no altera la agregación baseline ni amplía permisos.

## Contexto y objetivo

El panel de progreso existente no ofrece la secuencia visual de rutas y módulos ni agregados colectivos de las nuevas tasas de actividad/ruta. Esta ampliación presenta la secuencia registrada y, cuando las bases de privacidad lo permiten, promedios verificables comparables, sin revelar mapas o datos individuales. El cálculo de retos y módulos se rige por RF-17/RF-20 de la Spec 001 y la Spec 012.

## Usuarios

- **Participante:** consulta rutas asignadas o con progreso iniciado.
- **Mentor asignado:** consulta las rutas incluidas en la vista autorizada del sujeto.
- **Responsable de colectivo y miembro:** consultan únicamente los agregados permitidos para su colectivo.
- **Admin de plataforma:** mantiene solo los permisos de soporte vigentes; no adquiere visibilidad nueva.

## Historias de usuario

- HU-1. Como participante, quiero recorrer la ruta en su secuencia registrada y reconocer los elementos vinculados para comprender dónde estoy.
- HU-2. Como miembro o responsable de colectivo, quiero ver promedios de actividad/ruta solo cuando sean comparables y respeten la privacidad, para conocer el avance común sin acceder a datos individuales.
- HU-3. Como mentor autorizado, quiero ver el mapa de la ruta autorizada del sujeto para orientar su proceso.

## Definiciones

- **Base general elegible:** miembros activos con pertenencia vigente y al menos un objetivo individual, según la definición ya aprobada en la Spec 001.
- **Contribución calculable:** participante de la base general con el porcentaje individual definido para esa métrica.
- Cada porcentaje de ruta solo es comparable con porcentajes de la misma ruta y versión.

## Requisitos funcionales

- **RF-013.1 (trazabilidad RF-16):** CUANDO un usuario autorizado consulte el mapa, EL SISTEMA DEBE presentarlo como vista independiente accesible desde el panel de progreso, con los módulos existentes ordenados según el orden vigente de cada ruta y sin etapas, divisiones ni agrupaciones añadidas.
- **RF-013.2 (trazabilidad RF-16):** MIENTRAS se presenta una ruta, EL SISTEMA DEBE mostrar solo rutas asignadas al participante o en las que inició progreso; al mentor autorizado, solo rutas de la vista autorizada del sujeto. Para administradores, EL SISTEMA DEBE mantener exclusivamente los permisos de soporte vigentes.
- **RF-013.3 (trazabilidad RF-16):** MIENTRAS el mapa presenta objetivos, actividades, módulos o logros, EL SISTEMA DEBE mostrar el estado de la fuente y ubicar cada elemento bajo ruta/módulo solo si la relación correspondiente está explícitamente registrada. La vista no crea vínculos ni modifica estados; para módulos se aplica la completitud verificable de RF-17.
- **RF-013.4 (trazabilidad RF-16):** SI no existe una relación registrada entre una entidad y ruta/módulo, ENTONCES EL SISTEMA NO DEBE inferirla por título, asistencia, porcentaje estimado, mentoría u otra señal indirecta. La gestión del vínculo reto-módulo se limita a RF-20; no se habilita la creación de otras relaciones ni se cambian permisos de objetivos.
- **RF-013.5 (trazabilidad RF-16/RF-10/RF-11):** CUANDO un responsable autorizado o miembro consulte un agregado colectivo, EL SISTEMA DEBE mostrar únicamente métricas agregadas permitidas, incluidas las nuevas tasas de actividad/ruta, y nunca mapas, historiales o indicadores individuales de miembros. El agregado existente de objetivos no cambia.
- **RF-013.6 (trazabilidad RF-10/RF-11):** CUANDO se calcule una tasa colectiva nueva de actividad o ruta, EL SISTEMA DEBE calcular el promedio de los porcentajes individuales calculables para esa misma métrica; para rutas, solo agrupará la misma ruta y versión. Quien carezca de la métrica no suma cero ni participa en ese promedio, pero permanece en la base general elegible.
- **RF-013.7 (trazabilidad RF-10/RF-11):** SI la base general elegible es inferior a 3, ENTONCES EL SISTEMA DEBE suprimir el agregado conforme a la regla uniforme vigente y mostrar explícitamente que se suprime por insuficiencia de la base general. SI una tasa nueva tiene menos de 3 contribuciones calculables, ENTONCES EL SISTEMA DEBE suprimir esa tasa aunque la base general alcance 3 y mostrar explícitamente que se suprime por insuficiencia de contribuciones calculables para esa métrica. En ambos casos, la explicación no debe revelar el número de participantes elegibles, el tamaño de la base, el número de contribuciones ni numeradores, denominadores u otros datos que permitan deducirlos; la supresión de una tasa no debe cambiar las demás métricas que sí cumplan sus umbrales.
- **RF-013.8 (trazabilidad RF-10/RF-11):** EL SISTEMA DEBE aplicar el mismo umbral general de 3 participantes elegibles a todas las vistas colectivas (miembro, responsable y mentor cuando consulte un agregado autorizado); el umbral adicional de 3 contribuciones se aplica a cada nuevo promedio de actividad/ruta. Los agregados baseline de objetivos conservan sus reglas aprobadas.
- **RF-013.9 (trazabilidad RF-16/RF-10/RF-11):** SI un usuario no pertenece al colectivo ni tiene autorización vigente como responsable o mentor en el ámbito consultado, ENTONCES EL SISTEMA DEBE denegar el acceso sin revelar datos individuales ni colectivos protegidos.

## Requisitos no funcionales

- **RNF-013.1 (P3):** visibilidad del mapa y agregados se verifica en servidor en cada operación; una presentación u ocultamiento en pantalla no concede acceso.
- **RNF-013.2 (P4/P11):** agregados no permiten inferir datos individuales; se concilian con fuentes verificables y distinguen métricas ausentes, incompletas y suprimidas sin convertirlas en cero. Cada supresión por incumplir el umbral general o el mínimo de contribuciones calculables DEBE comunicar explícitamente el motivo correspondiente, sin exponer el conteo ni tamaño de la base general, el conteo de contribuciones, numeradores o denominadores de la métrica suprimida.
- **RNF-013.3 (P5/P6):** el mapa muestra relaciones y estados registrados; no inventa enlaces ni acredita completitud desde asistencia o estimaciones.
- **RNF-013.4 (P16):** secuencia, estados, datos ausentes y supresión se comunican de forma accesible, con navegación por teclado, foco visible y estados distinguibles más allá del color.
- **RNF-013.5 (rendimiento/responsive):** las lecturas interactivas del panel/mapa cumplen RNF-5 padre: respuesta API p95 ≤2 s bajo 50 consultas simultáneas, medida desde recepción en servidor hasta completar respuesta. A 320 px, controles visibles sin recorte ni scroll horizontal y operables con teclado y toque, según RNF-7 padre.
- **RNF-013.6 (P19):** cálculos de agregados, supresión, autorización y ausencia de datos se verifican mediante pruebas automatizadas.
- **RNF-013.7 (idioma):** el texto nuevo de la interfaz DEBE respetar RF-15 de la Spec 001: idioma activo es/en/pt y fallback al español sin mostrar claves crudas.

## Casos límite

- Ruta disponible pero no asignada ni iniciada: no se muestra al participante.
- Mentor consulta fuera de la vista autorizada del sujeto, o admin sin permiso de soporte: acceso denegado.
- Elemento sin vínculo verificable: no se ubica en la ruta/módulo por inferencia.
- Ruta y versión distintas: sus porcentajes no se promedian juntos.
- Miembro elegible sin porcentaje individual: permanece en la base general, pero no contribuye ni suma cero.
- Base general menor que 3: se suprime el agregado para todas las vistas colectivas y se muestra el motivo de insuficiencia de la base general, sin indicar su tamaño ni el número de participantes.
- Base general de 3 o más con menos de 3 porcentajes calculables: se suprime solamente el promedio nuevo afectado y se muestra el motivo de insuficiencia de contribuciones para esa métrica, sin indicar el número de contribuciones ni su numerador o denominador.
- Ausencia de dato frente a valor cero: se distingue; ningún faltante se trata como cero.
- Agregado colectivo: no expone mapa, historial ni indicador individual aun si el usuario tiene acceso al colectivo.
- El orden refleja la secuencia vigente de módulos, sin incorporar etapas o agrupaciones nuevas.

## Fuera de alcance

- Cambiar el panel o reemplazar dashboards existentes; agregar permisos administrativos no aprobados.
- Inferir relaciones, etapas o estructura de aprendizaje; modificar objetivos, retos o estados; calcular las tasas fuente definidas por RF-17 (Spec 012).
- Alterar la agregación baseline de objetivos, sus reglas de elegibilidad o el umbral general de §3.1 de la Constitución.
- Analítica de tendencias, exportación, tiempo real o configuración del umbral por organización.

## Criterios de finalización

- [ ] RF-013.1–RF-013.9 se verifican para participante, mentor, responsable, miembro y acceso denegado.
- [ ] La secuencia, visibilidad y relaciones coinciden con datos registrados y no incluyen inferencias.
- [ ] Las tasas nuevas cumplen el promedio por misma métrica/ruta/versión y ambas condiciones de privacidad; cada supresión indica su motivo sin revelar conteos ni bases suprimidas; los agregados baseline permanecen sin cambios.
- [ ] Se preservan P3/P4/P5/P6/P11/P16 y las decisiones aplicables de §3.1 sin ampliar permisos.
- [ ] Se verifica el criterio p95 del panel/mapa y la interfaz de 320 px del padre.

## Trazabilidad RF padre → RF hija

| Spec 001 v0.5.1 | Requisitos de esta spec |
|---|---|
| RF-16, CA-16.1–16.7 | RF-013.1–RF-013.5, RF-013.9 |
| RF-10, CA-10.6; RF-11, CA-11.1 y CA-11.3; RNF-2 | RF-013.5–RF-013.9 |

## Dudas abiertas

- No hay dudas de producto abiertas para el alcance heredado de RF-16 y RF-10/RF-11.
