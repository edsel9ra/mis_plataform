# Plan — Spec 011: Accesibilidad global del frontend

Estado: aprobado (2026-10-12, confirmado por el usuario el 2026-11-14)  
Base: `spec.md` aprobada por el usuario el 2026-10-12.

## Enfoque

Primero levantar una línea base de las 34 rutas de página bajo `frontend/src/app/[locale]/**/page.tsx` y sus estados relevantes. Después corregir hallazgos en componentes compartidos y en grupos de flujos, verificando cada corrección antes de avanzar. El alcance incluye las tres locales es/en/pt y las variantes de acceso autenticadas que se puedan recorrer con cuentas/estados de prueba existentes.

La inspección inicial confirma que ya existen bases útiles: foco global `:focus-visible`, soporte `prefers-reduced-motion` y skip link en `AppShell`. Se conservan y se prueban; no se reemplazan por una solución nueva sin hallazgo que lo justifique.

## Archivos y responsabilidades

- `frontend/src/app/globals.css`: contraste de foco, movimiento reducido, reflow y estilos globales. Ajustar solo tras identificar una barrera concreta.
- `frontend/src/app/layout.tsx`: idioma del documento y elementos globales compartidos.
- `frontend/src/components/layout/AppShell.tsx`: skip link, navegación móvil/desktop, foco y landmark principal.
- `frontend/src/components/ui/*.tsx`: revisar semántica y comportamiento de controles compartidos (botones, campos, diálogos, tabs, alertas, carga y paginación); una corrección aquí debe verificarse en consumidores existentes.
- `frontend/src/app/[locale]/**/page.tsx` y `frontend/src/components/{progress,mentor-workspace,mentors,charts}/**`: corregir defectos propios de rutas o componentes funcionales encontrados en la auditoría, sin reescrituras generales.
- `specs/011-frontend-accessibility/audit.md` (propuesto): matriz de rutas/estados, hallazgos WCAG, severidad, evidencia y resultado; no contendrá datos personales.
- Tests Vitest junto al módulo corregido cuando exista lógica comprobable en el entorno actual. `frontend/vitest.config.mts` usa entorno Node y hoy no configura render de componentes.

Antes de editar un archivo con cambios locales preexistentes —en particular `frontend/src/app/globals.css`, `frontend/src/components/ui/dialog.tsx`, `frontend/src/components/ui/avatar.tsx` y `frontend/src/app/[locale]/onboarding/test-personalidad/page.tsx`— revisar su diff y preservar las modificaciones del usuario.

## Procedimiento de auditoría

```text
rutas := enumerar páginas bajo frontend/src/app/[locale]
para cada ruta:
  registrar acceso público/autenticado, rol y estado relevante
  abrir la ruta en navegador en español, inglés o portugués según cobertura
  inspeccionar árbol de accesibilidad y ejecutar Lighthouse Accessibility
  recorrer con Tab / Shift+Tab / Enter / Space / Escape las tareas disponibles
  comprobar foco, nombre/rol/estado, etiquetas/errores y anuncios dinámicos
  revisar versión móvil y viewport 320 CSS px; comprobar zoom al 200 %
  comprobar movimiento reducido y que estados no dependan solo del color
  registrar hallazgos reproducibles con criterio WCAG, evidencia y prioridad
ordenar por bloqueo de tareas principales, luego severidad y alcance compartido
corregir primero la causa común; repetir las mismas verificaciones
cerrar solo hallazgos verificados y registrar exclusiones para aprobación explícita
```

No se añade una dependencia de auditoría en esta propuesta: Chrome DevTools ofrece árbol de accesibilidad, Lighthouse y navegación por teclado; se combinarán con pruebas del proyecto. Si durante la ejecución estas herramientas no cubren un requisito o estado, se detendrá esa parte y se propondrá el cambio de herramientas antes de ampliar dependencias.

## Decisiones y alternativas

1. **Auditar primero y cambiar solo hallazgos reproducibles.** Evita un rediseño general y cambios cosméticos sin beneficio accesible. Alternativa descartada: aplicar reglas genéricas a todas las páginas sin evidencia.
2. **Corregir componentes compartidos antes de consumidores.** Una causa única corregida en `components/ui`/`AppShell` reduce inconsistencias; después se prueban varias páginas que usan esos controles. Alternativa descartada: duplicar ARIA/estilos por ruta.
3. **Usar comprobación de navegador y Lighthouse sin instalar nuevas dependencias inicialmente.** Respeta la arquitectura y las herramientas disponibles. Alternativa descartada: introducir Playwright/axe u otra dependencia antes de conocer los estados que necesitan automatización.
4. **Mantener locales es/en/pt y autorización actual.** Solo se modifican nombres, ayudas y errores accesibles donde lo requiera un hallazgo; no se cambian reglas de acceso ni contenido de negocio.
5. **Dividir la ejecución si el backlog excede diez tareas.** Las 34 rutas y variaciones por rol probablemente superan el límite SDD de 10 tareas; se propone completar inventario/priorización y luego crear specs hijas por grupos de superficies antes de redactar tareas. No se amplía ni se modifica la spec aprobada sin aprobación del usuario.

## Trazabilidad RF

| Requisito | Trabajo y verificación |
|---|---|
| RF-011.1–2 | Teclado y foco en shell, controles compartidos y flujos principales; repetir recorrido manual tras cada arreglo. |
| RF-011.3 | Revisión de nombres/roles/estados y formularios; inspección del árbol accesible y pruebas de regresión para lógica de validación que cambie. |
| RF-011.4 | Revisar carga, error, éxito y actualización en páginas/componentes; inspección del árbol accesible y comportamiento sin mover foco inesperadamente. |
| RF-011.5 | Inventario de imágenes en rutas/componentes afectados; comprobar alternativas informativas y decoración ignorada por AT. |
| RF-011.6 | Revisión en viewport 320 CSS px y zoom 200 % en rutas representativas y flujos críticos; comprobar ausencia de pérdida de contenido/función. |
| RF-011.7 | Emular `prefers-reduced-motion`; verificar que animación no esencial se detiene/reduce sin perder significado o estado. |
| RF-011.8 | Registrar cada hallazgo con criterio WCAG y prueba; reejecutar la comprobación de regresión que corresponda tras la corrección. |
| RNF-011.1–4 | Matriz de rutas/roles/locales, mezcla de auditoría automática y manual, preservación de autorización e idiomas; `pnpm test`, `pnpm lint`, `pnpm typecheck` y `pnpm build`. |

## Estrategia de pruebas

- **Antes de corregir:** guardar evidencia reproducible y criterio WCAG; no convertir sugerencias estéticas en defectos.
- **Automatizadas:** ejecutar `pnpm test`, `pnpm lint`, `pnpm typecheck` y `pnpm build`; añadir pruebas Vitest cuando el comportamiento corregido sea comprobable en entorno Node. No declarar cubierto por Vitest un flujo de DOM que la configuración actual no monta.
- **Navegador:** Chrome DevTools accessibility snapshot y Lighthouse Accessibility en rutas públicas y estados autenticados representativos; usar móvil y escritorio. Registrar rutas no accesibles por falta de cuenta/datos y no inferir su resultado.
- **Manual:** teclado (incluido escape/cierre y retorno de foco en overlays), zoom 200 %, ancho 320 CSS px, mensajes de formularios y movimiento reducido. La inspección manual se documenta y no se sustituye por puntuación Lighthouse.
- **Cierre:** no quedan bloqueos de prioridad alta conocidos; cambios cubiertos por pruebas y sin regresiones funcionales ni de localización.

## División propuesta antes de crear tareas

Por el número de superficies, antes de redactar `tasks.md` se debe acordar si implementar en specs hijas separadas: (1) auditoría y shell/controles globales, (2) rutas públicas y autenticación/onboarding, (3) dashboards de participantes/progreso, y (4) espacio de mentor, administración y demás módulos. Las hijas conservarían trazabilidad a esta spec 011. La división y el número final de tareas se decidirán tras aprobar este plan y cerrar el inventario inicial.
