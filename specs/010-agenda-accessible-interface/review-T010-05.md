# Revisión T010-05 — Contraste, reflow y tamaño de objetivo (WCAG 2.2 AA)

**Fecha:** 2026-11-13 · **Alcance:** superficies de agenda existentes (`/sessions` día/semana/mes, `/sessions/{id}`, `/admin/sessions` + modal + diálogo de borrado). **Herramientas:** axe-core 4.14.0 (publicado 2026-10-05) dentro de contenedor Playwright desechable `mcr.microsoft.com/playwright:v1.64.1-noble`, etiquetas `wcag2a, wcag2aa, wcag21aa, wcag22aa`; Playwright para reflow, targets y teclado. Evidencia: `/tmp/audit/a11y/` (`axe-results.json`, `targets.json`, `reflow.json`, `admin-kbd.log`, capturas `*-320.png`/`*-1280.png`); las ejecuciones previas a las correcciones quedan archivadas en `/tmp/audit/a11y/before/`.

## Matriz ruta × locale × viewport × método × resultado

| Ruta / estado | Locale | Viewport | Método | Resultado |
|---|---|---|---|---|
| `/sessions` semana | es | 1280×900 | axe | 0 violaciones |
| `/sessions` día | es | 1280×900 | axe | 0 violaciones |
| `/sessions` mes | es | 1280×900 | axe | 0 violaciones |
| `/sessions` semana/día/mes | es | 320×800 | axe + reflow | 0 violaciones; `scrollWidth=320` |
| `/sessions` semana | en | 1280 + 320 | axe + reflow | 0 violaciones; 320 OK |
| `/sessions` semana | pt | 1280 + 320 | axe + reflow | 0 violaciones; 320 OK |
| `/sessions/{id}` (con Meet) | es | 1280 + 320 | axe + reflow | 0 violaciones; 320 OK |
| `/admin/sessions` | es | 1280 + 320 | axe + reflow | **antes:** contrast 2, select-name 1, reflow 822px → **después: 0 violaciones, scrollWidth=320** |
| `/admin/sessions` modal edición | es | 1280 | axe | **antes:** 18 nodos contrast (falso positivo por animación `slide-up` en curso) → **después (con espera): 0** |
| `/admin/sessions` diálogo borrado | es | 1280 | axe | 0 violaciones |

## Criterios WCAG 2.2 AA verificados

- **1.4.3 Contraste de texto:** correcciones — `text-gray-500` → `text-gray-600` en textos sobre fondo `slate-50` de `/admin/sessions` (descripción y total); `text-gray-400` → `text-gray-500` en "sin sesiones" de la vista semana; celdas fuera de mes: eliminado `opacity-60` (reducía el contraste efectivo de todo el contenido) y número de día a `text-gray-500`, manteniendo la distinción visual por fondo.
- **1.4.10 Reflow:** todas las páginas en alcance a 320 CSS px sin desplazamiento horizontal a nivel de página. La tabla de datos de `/admin/sessions` desplaza dentro de su propio contenedor `overflow-x-auto` (excepción 2D para datos tabulares, justificada abajo). La retícula mensual del calendario se comprime sin desbordar la página (320 OK).
- **1.4.11 Contraste no textual:** anillos de foco `ring-primary-500` sobre blanco/gris cumplen ≥3:1; controles y estados no dependen solo del color (badges con texto, iconos).
- **2.5.8 Tamaño de objetivo:** `targets.json` — todos los controles ≥24×24 CSS px salvo dos casos: enlace Meet del detalle (145×20 → corregido a 24px con `min-h-6`) y el skip link "Saltar al contenido" (1×1 — patrón sr-only: no es un objetivo mientras está oculto; al recibir foco se muestra con tamaño normal; excepción documentada).
- **1.3.1 / 4.1.2:** `select-name` crítico en el filtro de estado de `/admin/sessions` (sin nombre accesible) → corregido con `aria-label`. Encabezados h1/h2, `role="group"`, `aria-expanded`, `aria-current` ya introducidos en T010-03.
- **2.4.7 Foco visible:** todos los controles focables muestran anillo u outline (verificado por `admin-kbd.log` y `kbd.log`/`detail.log` de T010-03).

## Correcciones aplicadas (archivos)

- `admin/sessions/page.tsx`: `aria-label` en el `<Select>` de filtro; `text-gray-600` en descripción/total; `aria-label` con título de sesión en botones de fila (Editar/Completar/Eliminar — label-in-name); `grid-cols-2` → `grid-cols-1 sm:grid-cols-2` en el modal; eliminado `confirm()` nativo por `Dialog` accesible (foco inicial en Cancelar vía `data-autofocus`, Escape, retorno de foco al botón que abrió); región `role="status"` que anuncia "Sesión marcada como completada" y foco a la celda de estado tras éxito.
- `sessions/[id]/page.tsx`: enlace Meet `min-h-6` (24px).
- `CalendarMonthView.tsx`: contraste de celdas fuera de mes (sin `opacity-60`).
- `CalendarWeekView.tsx`: `text-gray-500` para "sin sesiones".
- `components/ui/dialog.tsx`: verificado — ya implementa focus trap, foco inicial (`data-autofocus` > inputs > primer botón), Escape, retorno de foco y `aria-modal` + `aria-labelledby`; no requirió cambios.

## Evidencia de teclado (admin, `/tmp/audit/a11y/admin-kbd.log`)

- Editar → foco inicial en primer campo del modal; 12×Tab permanecen dentro del diálogo; Escape devuelve foco al botón "Editar {título}".
- Eliminar → diálogo con foco inicial en "Cancelar"; Enter cancela y devuelve foco a "Eliminar {título}"; flujo real probado borrando una sesión creada para la prueba (`01a1d531-…`, ya eliminada).

## Excepciones justificadas

- **Tabla de datos de `/admin/sessions`:** desplazamiento horizontal dentro de su contenedor `overflow-x-auto` — excepción de contenido 2D/datos tabulares de 1.4.10.
- **Retícula del calendario mensual:** comprimible a 320 (medido 320, no requiere scroll propio); se reserva la excepción 2D del enunciado por si crecen los contenidos.
- **Skip link 1×1:** no es objetivo mientras está oculto (sr-only); al enfocarse se muestra con tamaño completo.

## Hallazgos residuales

- **El botón "Completar" de `/admin/sessions` siempre falla (422):** `PUT /admin/sessions/{id}/status` solo acepta `status=canceled` con `reason` obligatorio (`UpdateAdminSessionStatusRequest`), por lo que marcar como completada es imposible por API. Bug preexistente fuera del alcance de T010-04/05 (requiere decisión de producto: quitar el botón o exponer cancelación con motivo). La región de anuncio queda implementada para la rama de éxito; el fallo sí es anunciado por `Alert role="alert"` (verificado en `admin-kbd.log`: `alert:"Revisa los datos introducidos."`).
- Los 18 nodos de `color-contrast` reportados al abrir el modal eran un artefacto transitorio de la animación `slide-up` (texto semitransparente durante 0.6 s); con la animación terminada: 0 violaciones.
