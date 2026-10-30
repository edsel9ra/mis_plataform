# Tareas — Spec 010: Interfaz accesible de agenda

**Estado:** aprobado; implementación no iniciada; T010-07 pendiente y puerta de aceptación final de RF-008.2.  
**Spec:** `specs/010-agenda-accessible-interface/spec.md` · **Plan:** `plan.md`.

- [ ] **T010-01. Añadir catálogos es/en/pt para todas las superficies agenda.** RF-010.1; CA hija RF-010.1; CA padre CA-12.1/12.2.
  - Hecho cuando: labels, estados, errores y avisos de 003–009 están traducidos; resolver catálogos usa fallback es sin clave visible y Vitest lo valida.
- [ ] **T010-02. Asociar validaciones a campos y regiones anunciables.** RF-010.3; CA hija RF-010.3; CA padre CA-12.4.
  - Hecho cuando: formularios enlazan error de campo y live region con nombres accesibles; pruebas unitarias y revisión manual verifican anuncio y corrección.
- [ ] **T010-03. Hacer navegables con teclado calendario y detalle.** RF-010.2; CA hija RF-010.2; CA padre CA-12.3, RNF-6.
  - Hecho cuando: navegación y apertura de detalle funcionan sin ratón, orden de foco es coherente, foco visible y estados no dependen solo del color.
- [ ] **T010-04. Hacer accesibles formularios y acciones de agenda.** RF-010.2–010.3; CA hija RF-010.2/3; CA padre CA-12.3/12.4.
  - Hecho cuando: reserva, disponibilidad, reprogramación, cancelación y asistencia son operables por teclado; targets cumplen 24×24 CSS o excepción documentada.
- [ ] **T010-05. Verificar contraste y reflow en anchos aprobados.** RF-010.2, RNF-010.1–010.2; CA hija RNF-010.1/2; padre RNF-6/7.
  - Hecho cuando: revisión documenta WCAG 2.2 AA aplicable, contraste, reflow a 320 CSS px y presentación a 1280 CSS px; excepción 2D se limita al calendario.
- [ ] **T010-06. Probar autorización server-side y estados/error en todas las superficies.** RF-010.1–010.3, RNF-010.3; CA hija RF-010.1–3; CA padre CA-10.5, CA-12.1–12.4.
  - Hecho cuando: las pruebas muestran que UI no sustituye autorización, y checklist por ruta/locale confirma carga, éxito/error, texto fallback, teclado y anuncio.
- [ ] **T010-07. Consumir `SessionReminder` y actualizar el aviso visible por ID estable.** RF-010.1, RF-010.4; RF-008.2; CA hija RF-010.1/4 y RF-008.2/caso 43; CA padre CA-5.2/5.6.
  - Hecho cuando: el consumidor frontend recibe el evento privado existente `SessionReminder`; dos entregas con el mismo `notification_id` dejan un solo aviso visible actualizado y dos IDs distintos permanecen como avisos distintos; payload/contenido e idioma activo se preservan y la presentación es accesible/anunciable. No se añade canal, API, bandeja persistente ni se prescribe componente; no se mueve de 008 la persistencia, confirmación del proveedor o reintento. La prueba de integración que pasa es requisito explícito para la aceptación final de RF-008.2 por 008.
