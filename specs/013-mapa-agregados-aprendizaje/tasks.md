# Tareas — Spec 013: Mapa y agregados de aprendizaje

**Estado:** backlog propuesto (no implementado) · **Spec:** aprobada
**Dependencia:** completar Spec 012 antes de integrar métricas fuente. Cada tarea está acotada a 20–30 min. Tests planificados, no ejecutados.

- [ ] **T013.1 · Contrato tipado del mapa y motivos de supresión** (25 min). RF-013.1–.9. Definir DTOs de ruta/módulo/elemento/métrica y códigos separados de supresión sin conteos. Skills: `sdd`, `laravel-patterns`.
  - Hecho cuando: tipos/contrato documentan estados, fuente y campos omitidos bajo cada supresión; tests de serialización prueban que no hay conteos.
- [ ] **T013.2 · Funciones puras de orden y estado del mapa** (25 min). RF-013.1, .3–.4. Resolver orden con tie-break estable y ubicación solo por relaciones explícitas. Skills: `laravel-tdd`.
  - Hecho cuando: unit tests cubren orden, ruta vacía y elementos sin vínculo sin inferencias.
- [ ] **T013.3 · Clave comparativa de ruta/versión vigente** (25 min). RF-013.6, .8. Generar clave estable de identidad/estructura actual sin crear historial/versionado de catálogo. Skills: `sql-optimization`, `laravel-tdd`.
  - Hecho cuando: casos de misma ruta/definición agrupan y estructura/orden distinto no se mezcla; el contrato no afirma snapshot histórico.
- [ ] **T013.4 · Calculadora pura de promedios y supresión** (25 min). RF-013.6–.8. Reutilizar threshold 3, separar base general de contribución y no convertir faltantes en cero. Skills: `laravel-tdd`.
  - Hecho cuando: tests distinguen base<3, contribuciones<3, promedio con contribuciones suficientes y métrica independiente no suprimida.
- [ ] **T013.5 · Lectura de mapa individual y acceso del mentor** (25 min). RF-013.1–.4, .9. Orquestar rutas asignadas/iniciadas y servicios de acceso ya existentes; integrar calculadoras de Spec 012. Skills: `laravel-specialist`, `laravel-security`.
  - Hecho cuando: feature tests excluyen ruta no asignada, limitan mentor al sujeto/rutas autorizadas y respetan fuente de cada item.
- [ ] **T013.6 · Extender agregación colectiva sin cambiar objetivos** (25 min). RF-013.5–.8. Agregar tasas de actividad/ruta comparables y exponer únicamente motivo de supresión. Skills: `laravel-patterns`, `sql-optimization`, `laravel-tdd`.
  - Hecho cuando: regresión confirma payload/regla baseline de objetivos intactos y ningún payload suprimido revela conteos o datos individuales.
- [ ] **T013.7 · Endpoints/resources y matriz de autorización** (25 min). RF-013.2, .5, .9. Añadir GET mapa propio/mentor y contrato agregado aditivo bajo `/api/v1`. Skills: `laravel-security`, `spatie-laravel-php`.
  - Hecho cuando: feature tests verifican autenticación, membership, soporte vigente, IDOR y respuesta sin detalles protegidos.
- [ ] **T013.8 · Vista independiente y navegación desde progreso** (25 min). RF-013.1–.4. Crear vista secuencial de módulos y enlace complementario, sin etapas ni asociaciones implícitas. Skills: `frontend-design`, `react-best-practices`.
  - Hecho cuando: inspección confirma orden API, solo muestra elementos vinculados y deja intacto el panel base.
- [ ] **T013.9 · Contrato frontend, hooks y supresión traducida** (25 min). RF-013.5–.8, RNF-013.2, .7. Query keys y mensajes diferenciados es/en/pt; sin calcular promedios en UI. Skills: `tanstack-query-best-practices`, `i18n`, `vitest`.
  - Hecho cuando: Vitest prueba ambas razones, ausencia de conteos y fallback español sin claves crudas.
- [ ] **T013.10 · Accesibilidad y perfil p95 del mapa/agregado** (25 min). RNF-013.1–.7. Cerrar revisión teclado/foco, viewport y preparar medición conjunta a 50 consultas. Skills: `accessibility`, `sql-optimization`, `web-design-guidelines`.
  - Hecho cuando: checklist verifica 320 px/teclado y queda benchmark reproducible con límite p95 ≤2 s, sin declarar pruebas no corridas.
