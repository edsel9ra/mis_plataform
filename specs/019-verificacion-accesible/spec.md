# Spec 019 — Verificación accesible con datos de auditoría y tecnologías de asistencia

Estado: aprobada

**Spec padre:** `specs/011-frontend-accessibility/spec.md`, aprobada. Cubre las lagunas de verificación que recoge `specs/011-frontend-accessibility/audit.md` §T-011.8. El usuario aprobó la propuesta el 2026-11-17.
**Origen:** la auditoría de la 011 no pudo probar varios estados por falta de datos en desarrollo, no incluyó pruebas con un lector de pantalla real (RNF-011.2) y sus comprobaciones de navegador no forman parte del repositorio.

## Contexto y objetivo

La spec 011 auditó las 44 rutas del frontend y corrigió las barreras que encontró. Sin embargo, no puede declararse implementada por tres motivos:

1. Varios estados no existían en los datos de desarrollo y no se crearon para no alterarlos: listas con varias páginas, administración de empresa con empleados, bloqueo por plan, asistencia, mensajes, entregas con feedback y las respuestas de éxito.
2. No se hizo ninguna prueba con un lector de pantalla real.
3. Las comprobaciones de navegador se ejecutaron en un entorno desechable, así que una regresión futura no se detectaría.

El objetivo es cerrar esas lagunas sin alterar los datos de desarrollo y dejar decidida la forma de la regresión.

## Usuarios

- Las mismas que la spec 011: personas que usan teclado, lector de pantalla, zoom o movimiento reducido.
- El equipo, que necesita detectar regresiones de accesibilidad.

## Historias de usuario

- HU-1. Como persona que usa un lector de pantalla, quiero que los flujos principales se hayan probado con lectores reales, para no depender solo de herramientas automáticas.
- HU-2. Como equipo, quiero auditar los estados que dependen de datos sin tocar los datos de desarrollo, para que la verificación sea completa y repetible.
- HU-3. Como equipo, quiero que una regresión de accesibilidad en las rutas ya corregidas se detecte antes de llegar a `main`.

## Definiciones

- **Datos de auditoría:** un conjunto de datos generado por un script versionado, en una base de datos propia y desechable, que nunca es la de desarrollo ni la de tests.
- **Estados bloqueados por datos:** los marcados como «límite por datos o permisos» o «sin probar» en `audit.md` §T-011.4 a §T-011.7.
- **Flujos principales:** login, registro, test de personalidad, reserva de sesión, responder una evaluación, mensajes y panel de progreso.

## Requisitos funcionales

- **RF-019.1:** EL SISTEMA DEBE disponer de un script versionado que genere los datos de auditoría en una base de datos desechable. El conjunto debe incluir como mínimo:
  - un `company_admin` con compañía y al menos un empleado;
  - una lista del admin con más de una página;
  - un mentee cuyo plan no incluya el espacio del mentor;
  - una sesión pasada con la ventana de asistencia abierta y otra pasada sin completar;
  - una conversación con mensajes propios y ajenos;
  - un reto con una entrega y feedback;
  - una observación, un check-in y un mapa sistémico.
- **RF-019.2:** SI el script detecta que la base de datos de destino es la de desarrollo o la de tests, o que ya contiene usuarios, ENTONCES DEBE negarse a ejecutarse.
- **RF-019.3:** CUANDO existan los datos de auditoría, se DEBEN volver a auditar todos los estados bloqueados por datos con los criterios y el formato de la 011, y registrar el resultado en `specs/011-frontend-accessibility/audit.md`. Incluye las respuestas de éxito: registro, reserva, envío de evaluación y de test, y restablecimiento de contraseña.
- **RF-019.4:** Una persona del equipo distinta del agente (D-3) DEBE recorrer los flujos principales con NVDA + Firefox en Windows y con VoiceOver + Safari en macOS o iOS, siguiendo un guion versionado. Por cada flujo se registran el resultado y los problemas que se encuentren, con el criterio WCAG correspondiente.
- **RF-019.5:** SI la verificación de RF-019.3 o RF-019.4 encuentra una barrera reproducible, ENTONCES se DEBE registrar como hallazgo de la 011, con su ID, prioridad y corrección propuesta, y corregirse según RF-011.8.
- **RF-019.6:** EL SISTEMA DEBE tener una suite de regresión de accesibilidad en el repositorio, con Playwright y axe como dependencias de desarrollo del frontend (D-1). Debe ejecutar axe con las reglas WCAG 2.0, 2.1 y 2.2 A/AA y las comprobaciones de foco de las unidades T-011.2a a T-011.7a sobre las rutas corregidas, en un job de CI que construya el frontend y use los datos de auditoría.
- **RF-019.7:** SI la suite encuentra una violación de axe o falla una comprobación de foco, ENTONCES el job de CI DEBE fallar e indicar la ruta, la regla y el selector afectado.
- **RF-019.8:** CUANDO se pida `/mentors/<id>` con un id inexistente o mal formado, EL SISTEMA DEBE mostrar un estado «no encontrado» (D-2) con un h1, un mensaje traducido en es/en/pt y un enlace a `/mentors`, dentro de `main` sin sesión y dentro del shell con sesión. Cualquier otro error de carga sigue mostrando el error genérico, también con h1 y enlace.

## Requisitos no funcionales

- **RNF-019.1:** Ninguna tarea de esta spec puede crear, cambiar ni borrar datos en la base de datos de desarrollo. Cada verificación compara los recuentos antes y después.
- **RNF-019.2:** La verificación debe caber en la memoria del entorno local (5,8 GB): los servicios que no hagan falta se paran durante el build y la auditoría, y se vuelven a arrancar al terminar.
- **RNF-019.3:** El guion del lector de pantalla y los datos de auditoría deben estar en es/en/pt cuando haya textos visibles.

## Casos límite

- Un estado que siga sin poder reproducirse con los datos de auditoría se documenta con su motivo concreto; nunca se simula.
- Si el resultado de un lector de pantalla contradice al árbol accesible de Playwright, manda el del lector de pantalla y se registra la discrepancia.
- Las respuestas de éxito crean datos: solo se hacen sobre la base de datos de auditoría.
- La regresión no debe depender de los datos de desarrollo.
- Un id con un formato válido pero sin mentor y un id mal formado muestran el mismo estado «no encontrado».

## Fuera de alcance

- Nuevas funciones o rediseños.
- Certificación legal WCAG.
- Probar combinaciones de navegador y lector de pantalla distintas de las dos de RF-019.4.
- Cambios de producto distintos de los de D-1 y D-2.

## Criterios de finalización

- Los estados bloqueados por datos tienen un resultado registrado o un motivo concreto por el que siguen sin poder probarse.
- El guion de lector de pantalla se ha ejecutado en las dos combinaciones y sus resultados están registrados.
- Los hallazgos nuevos están clasificados y no queda ninguna barrera alta abierta.
- La suite de RF-019.6 y RF-019.7 corre en el CI y pasa, y falla si se reintroduce una de las violaciones corregidas.
- Con todo esto, la spec 011 puede pasar a «implementada».

## Decisiones (usuario, 2026-11-17)

- **D-1, forma de la regresión: Playwright y axe en el CI.** Se añaden `@playwright/test` y `@axe-core/playwright` como dependencias de desarrollo del frontend, en versiones publicadas hace al menos 7 días, y un job de CI. Sustituye la decisión 3 de `specs/011-frontend-accessibility/plan.md` (no añadir dependencias de auditoría). Alternativa descartada: un arnés manual versionado.
- **D-2, mentor inexistente: página «no encontrado».** Alternativa descartada: mantener el error genérico.
- **D-3, quién usa el lector de pantalla: otra persona del equipo.** El agente prepara un guion versionado con pasos y resultados esperados, y esa persona registra sus resultados. Alternativa descartada: excluir la prueba.

## Dudas abiertas

- Ninguna. Queda pendiente aprobar esta spec.
