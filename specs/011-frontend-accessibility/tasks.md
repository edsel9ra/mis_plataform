# Tareas — Spec 011: Accesibilidad global del frontend

Estado: propuesta pendiente de aprobación. Estas tareas ejecutan primero el inventario acordado; las correcciones se desglosarán en specs hijas después de priorizar hallazgos, para mantener cada unidad dentro del límite SDD de 10 tareas.

- [x] **T-011.1. Construir la matriz de alcance y estados.** RNF-011.1, RNF-011.4
  - Enumerar las 34 páginas de `frontend/src/app/[locale]/**/page.tsx` y agruparlas por superficie: shell, pública, cuenta/onboarding, participante/progreso, mentor/administración y módulos funcionales.
  - Registrar locales, necesidad de autenticación, roles/estados de prueba disponibles y flujos principales por ruta; anotar qué rutas dinámicas no pueden abrirse sin datos.
  - **Hecho cuando:** existe una entrada para cada page route, las áreas y estados están clasificados y las brechas de acceso/datos quedan explícitas.

- [ ] **T-011.2. Auditar fundamentos y componentes compartidos.** RF-011.1–RF-011.7, RNF-011.2
  - Revisar `globals.css`, `layout.tsx`, `AppShell.tsx` y `components/ui/*`, incluyendo foco, skip link, landmarks, nombres/estados, formularios, mensajes, diálogo y tabs.
  - Comprobar al menos un flujo de navegación móvil y desktop, teclado y árbol accesible; conservar las mejoras actuales y los cambios locales del usuario.
  - **Hecho cuando:** los componentes compartidos tienen hallazgos reproducibles vinculados a WCAG o quedan documentados como revisados, junto con evidencia y estados no verificables.

- [ ] **T-011.3. Auditar páginas públicas y descubrimiento.** RF-011.1–RF-011.8, RNF-011.1–RNF-011.4
  - Revisar home, catálogo/detalle de mentores, rutas de aprendizaje, certificados y recomendaciones en estados accesibles sin sesión cuando corresponda.
  - Inspeccionar alternativa textual, jerarquía, contraste visible, operación por teclado y reflow en las tareas principales.
  - **Hecho cuando:** todas las páginas de este grupo tienen resultado y evidencia registrada o una limitación de acceso concreta.

- [ ] **T-011.4. Auditar acceso, perfil y onboarding.** RF-011.1–RF-011.8, RNF-011.1–RNF-011.4
  - Revisar login, registro y variante por tipo, callback OAuth, perfil y test de personalidad en estados de carga, validación, error y éxito que puedan reproducirse.
  - Revisar etiquetado, errores anunciables, controles de preguntas, progreso y conservación del foco.
  - **Hecho cuando:** los flujos del grupo están documentados, se recorrieron con teclado y cada estado no disponible quedó identificado sin simular resultados.

- [ ] **T-011.5. Auditar agenda, evaluaciones y comunicación.** RF-011.1–RF-011.8, RNF-011.1–RNF-011.4
  - Revisar sesiones, evaluaciones/listado/detalle y mensajes; considerar la spec 010 para requisitos específicos de agenda sin redefinir su comportamiento.
  - Probar estados dinámicos y formularios principales, nombre/estado de controles y operación por teclado.
  - **Hecho cuando:** cada ruta y flujo del grupo tiene registro de revisión y los hallazgos están separados entre requisitos globales y específicos de la spec 010.

- [ ] **T-011.6. Auditar dashboards de participantes y progreso.** RF-011.1–RF-011.8, RNF-011.1–RNF-011.4
  - Revisar dashboards personal, familiar, grupal, empresa y progreso con sus variantes de estado/rol disponibles.
  - Inspeccionar si métricas, ausencia de datos, restricciones y estados se comprenden sin depender del color; comprobar zoom/reflow y uso por teclado.
  - **Hecho cuando:** rutas/roles del grupo están documentados y los límites por datos o permisos se anotaron como tales, no como defectos visuales.

- [ ] **T-011.7. Auditar espacio de mentor y administración.** RF-011.1–RF-011.8, RNF-011.1–RNF-011.4
  - Revisar páginas de mentor, perfil de mentee, progreso individual, retos, feedback y paneles administrativos de usuarios, mentores, evaluaciones y sesiones.
  - Inspeccionar formularios, diálogo/subida de archivos, acciones, mensajes asíncronos y navegación entre secciones.
  - **Hecho cuando:** cada ruta/rol de este grupo tiene resultado reproducible o una limitación de acceso/datos documentada.

- [ ] **T-011.8. Consolidar hallazgos y definir las unidades de corrección.** RF-011.8, RNF-011.1–RNF-011.3
  - Completar `specs/011-frontend-accessibility/audit.md` con criterio WCAG, ruta/estado, evidencia, severidad/prioridad, componente compartido afectado y método de regresión.
  - Deduplicar causas comunes, identificar barreras de prioridad alta y proponer specs hijas de corrección con alcance independiente y no más de 10 tareas cada una.
  - **Hecho cuando:** no quedan hallazgos sin clasificar; las barreras altas están priorizadas y las propuestas de specs hijas tienen requisitos/casos verificables para revisión del usuario.
