# Spec 011 — Accesibilidad global del frontend

Estado: aprobada

## Contexto y objetivo

La interfaz de MIS incluye páginas públicas, autenticación, paneles por rol y componentes compartidos. Esta spec establece una revisión global según WCAG 2.2 nivel AA y correcciones para las barreras verificables que afecten a la percepción, operación, comprensión o compatibilidad con tecnologías de asistencia. El objetivo es que las personas puedan completar las tareas existentes, no rediseñar la experiencia ni añadir funciones de producto.

## Usuarios

Todas las personas usuarias del frontend, incluidas personas que navegan con teclado, usan tecnologías de asistencia, amplían el contenido o prefieren movimiento reducido.

## Historias de usuario

- HU-1. Como persona que usa teclado o tecnología de asistencia, quiero identificar y operar los controles y entender los cambios de estado para completar las mismas tareas que el resto.
- HU-2. Como persona con baja visión o sensibilidad al movimiento, quiero percibir el contenido y usar la interfaz con zoom y preferencias de movimiento del sistema.

## Definiciones

- **Superficie del frontend:** páginas, layouts y componentes compartidos disponibles en `frontend`, incluidos los flujos públicos, autenticados y administrativos.
- **Barreras de prioridad alta:** incumplimientos que impiden o dificultan gravemente navegación, autenticación, envío de formularios o acceso a información esencial.
- La spec 010 conserva los requisitos específicos de accesibilidad e internacionalización de agenda; esta spec los complementa sin redefinir flujos ni autorización.

## Requisitos funcionales

- **RF-011.1:** EL SISTEMA DEBE permitir completar con teclado, sin trampas de foco, las tareas principales de navegación, autenticación, formularios y dashboards en las superficies del frontend.
- **RF-011.2:** EL SISTEMA DEBE mostrar foco perceptible en cada control operable y mantener visible el elemento enfocado al navegar, incluso cuando hay regiones fijas o desplazables.
- **RF-011.3:** EL SISTEMA DEBE exponer nombre, función y estado accesibles para controles interactivos; las entradas de formularios DEBEN tener etiquetas asociadas y los errores DEBEN identificarse junto al campo y anunciarse a tecnologías de asistencia.
- **RF-011.4:** CUANDO cambie dinámicamente el contenido relevante para una tarea (carga, resultado, error, confirmación o actualización), EL SISTEMA DEBE comunicar el cambio mediante texto y semántica accesibles sin depender solo del color ni mover el foco de forma inesperada.
- **RF-011.5:** EL SISTEMA DEBE proporcionar alternativa textual apropiada para imágenes informativas y tratar como decorativas las imágenes que no aportan información.
- **RF-011.6:** EL SISTEMA DEBE conservar contenido y funcionalidad al ampliar texto hasta 200 % y al presentar la interfaz en un viewport de 320 CSS px, sin desplazamiento bidimensional salvo contenido que lo requiera por naturaleza.
- **RF-011.7:** EL SISTEMA DEBE respetar la preferencia de movimiento reducido para animaciones y transiciones no esenciales.
- **RF-011.8:** CUANDO la auditoría detecte una barrera WCAG 2.2 AA reproducible dentro del alcance, EL SISTEMA DEBE corregirla y añadir una verificación de regresión proporcional al riesgo; las exclusiones deben justificarse y aprobarse explícitamente.

## Requisitos no funcionales

- **RNF-011.1:** La revisión debe cubrir las rutas de página existentes del frontend y los componentes compartidos que influyan en dichas rutas; se deben registrar alcance, hallazgos, criterio WCAG relacionado y método de verificación.
- **RNF-011.2:** La validación debe combinar comprobaciones automatizadas disponibles con pruebas manuales de teclado, zoom/reflow y tecnologías de asistencia en los flujos principales. Las herramientas automáticas no se consideran prueba suficiente de conformidad.
- **RNF-011.3:** No se deben cambiar reglas de negocio, permisos, textos de producto ni contratos de API salvo que un hallazgo de accesibilidad los requiera y se apruebe como cambio de alcance.
- **RNF-011.4:** La interfaz debe mantener los locales es/en/pt y respetar el idioma activo para nombres accesibles, instrucciones y mensajes de error.

## Casos límite

- Una operación que solo podía realizarse con ratón debe tener una interacción equivalente por teclado.
- Un icono informativo sin texto visible debe tener una alternativa accesible; un icono decorativo no debe duplicar el anuncio del texto adyacente.
- Los estados diferenciados por color deben comunicar también su significado mediante texto, forma o semántica.
- Los mensajes asíncronos no deben anunciarse repetidamente ni ocultar el control necesario para corregir o reintentar.
- A 200 % de zoom y 320 CSS px, los controles principales deben seguir disponibles y las etiquetas y errores no deben quedar recortados.
- Una comprobación automática limpia no exime de investigar problemas encontrados mediante teclado o tecnologías de asistencia.

## Fuera de alcance

- Cambios de accesibilidad del backend o de servicios externos.
- Rediseño visual, nuevas funciones o cambios de contenido que no sean necesarios para corregir una barrera verificada.
- Certificación legal de conformidad WCAG o garantía de compatibilidad con toda combinación de navegador y tecnología de asistencia.
- Cambios de producto específicos de agenda ya cubiertos por la spec 010.

## Criterios de finalización

- Todas las rutas y componentes compartidos en alcance están inventariados y revisados; los hallazgos tienen severidad, criterio WCAG y resultado de verificación.
- No quedan barreras de prioridad alta conocidas sin corregir ni exclusiones sin aprobación explícita.
- Las tareas principales pueden completarse mediante teclado y muestran foco visible; formularios y actualizaciones dinámicas comunican errores y estados a tecnologías de asistencia.
- Las superficies revisadas mantienen uso y contenido a 200 % de zoom y 320 CSS px, y respetan movimiento reducido.
- Las pruebas automatizadas aplicables y las regresiones añadidas pasan; se documentan las comprobaciones manuales y sus límites.

## Dudas abiertas

- No hay dudas de alcance: el usuario confirmó que se incluya toda la interfaz. Quedan sujetos a la aprobación de esta spec los requisitos y criterios aquí propuestos.
