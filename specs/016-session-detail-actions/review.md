# Revisión — Spec 016: accesibilidad de acciones en el detalle de sesión

Fecha: 2026-11-13 · Entorno: dev stack local (`app.mis-project.local`) · Herramienta: Playwright desechable (no es dependencia del proyecto) + axe-core 4.14 inyectado.
Evidencia: `/tmp/pw016/a11y/016-T08/`, `/tmp/pw016/a11y/016-T09/`, `/tmp/pw016/a11y/016-T10/` (`results.json` + capturas). Nota: `/tmp/audit` no es escribible (root), por eso la evidencia vive en `/tmp/pw016`.

## Reglas de presentación del panel (RF-016.6)

- `sessionActionsPresentation(available_actions)` decide qué se muestra en `SessionActionsPanel`:
  - `allowed` → botón-disparador real que abre el diálogo.
  - `forbidden` → **oculto por completo** (ni botón ni texto).
  - cualquier otra razón → texto plano «Acción: razón traducida» (sin botón deshabilitado), p. ej. «Reprogramar: Se necesitan al menos 2 horas de antelación.»
  - orden canónico: reprogramar → cancelar → completar; todas las acciones bloqueadas se listan aunque compartan razón.
  - si no hay nada permitido ni explicable, la sección «Acciones» no se renderiza; «Asistencia» es una sección independiente.
- Verificado en vivo: para el responsable familiar, «Acciones» aparece porque cancelar está legítimamente permitido, «Reprogramar» figura como texto bloqueado («La sesión ya ha comenzado.») y «Completar» (forbidden) no se renderiza en absoluto.

## Correcciones de cabecera (AppShell) — hallazgos arrastrados de T016-08

- **Enlace «Dashboard» (logo MIS)** — `label-content-name-mismatch`: se eliminó el `aria-label="…nav.dashboard"` del enlace cuyo texto visible es «MIS + subtítulo»; el nombre accesible ahora deriva del texto visible.
- **Campana de notificaciones** — `label-content-name-mismatch`: `notifications.aria_button` ahora interpola la insignia visible al inicio («9+ · Notificaciones, {count} sin leer»), de modo que el nombre accesible contiene el texto visible exacto.
- **Insignia de no leídos** — `color-contrast`: fondo `bg-red-500` → `bg-red-700` (blanco sobre #b91c1c ≈ 5,9:1 ≥ 4,5:1).
- axe confirma 0 violaciones de estos tipos en detalle de sesión (es/en/pt), `/sessions` y `/dashboard` (es/en/pt) a 1280 y 320 px.

## Matriz de verificación T016-10 (48/48 checks)

| Check | Locale | Viewport | Resultado | Evidencia |
|---|---|---|---|---|
| Encabezado «Acciones» + disparadores visibles | es | 1280 | PASS | results.json |
| axe página completa (cabecera + panel) | es | 1280 | PASS, 0 violaciones | 01-detail-es-1280.png |
| Botones de acción ≥ 24×24 | es | 1280 | PASS | results.json |
| Sin claves `session_actions.` crudas | es/en/pt | 1280 | PASS ×3 | results.json |
| axe página completa | es | 320 | PASS, 0 violaciones | 02-detail-es-320.png |
| Sin scroll horizontal | es/en/pt | 320 | PASS ×3 | results.json |
| axe con diálogo de cancelación abierto | es | 1280/320 | PASS, 0 violaciones | 03/04-cancel-open-*.png |
| axe con diálogo de reprogramación abierto | es | 1280 | PASS, 0 violaciones | 05-reschedule-open-1280.png |
| axe con diálogo de completar abierto | es | 1280/320 | PASS, 0 violaciones | 08/09-complete-open-*.png |
| Cancelar por teclado → anuncio + foco `#session-status` + disparadores desaparecen + motivos bloqueados listados | es | 1280 | PASS | 06-cancel-done-es.png |
| Reprogramar por teclado → anuncio en zona del visor | es | 1280 | PASS ×2 | results.json |
| Refresco de calendario sin recarga (semana siguiente muestra nueva hora, respuestas API verificadas) | es | 1280 | PASS | 07-calendar-after-reschedule.png |
| Completar por teclado → anuncio + foco | es | 1280 | PASS | results.json |
| Rechazo obsoleto 2 pestañas: cancelar en B → reprogramar en A muestra rechazo del servidor; tras refetch, motivos terminales y sin disparadores | es | 1280 | PASS | 10-stale-blocked-es.png |
| axe página completa | en/pt | 1280 | PASS, 0 violaciones | 11-detail-en-1280.png, 12-detail-pt-1280.png |
| Cancelar por teclado (anuncio «Session canceled.», foco) | en | 1280 | PASS | results.json |
| Completar por teclado (anuncio «Sessão concluída.», foco) | pt | 1280 | PASS | results.json |
| Asistencia (responsable familiar): auto-marcar «Asistió» y menor «No asistió» por teclado, `aria-pressed` actualiza, anuncio «Asistencia registrada» | es | 1280 | PASS ×3 | 14-attendance-es.png |
| axe panel de asistencia | es | 1280/320 | PASS, 0 violaciones | 13/15-attendance-es-*.png |
| Botones de asistencia ≥ 24×24 | es | 1280 | PASS | results.json |
| axe `/sessions` lista | es | 1280 | PASS, 0 violaciones | 16-list-es-1280.png |
| axe `/dashboard` (cabecera) | es/en/pt | 1280 | PASS — sin `label-content-name-mismatch` ni contraste de insignia | dashboard-*-1280.png |

## Hallazgos preexistentes fuera de alcance (registrados, no de esta spec)

- `/dashboard/mentor` (contenido de página, no la cabecera): pestaña «Todos» con `text-slate-500` por debajo de 4,5:1; durante la carga se observan `page-has-heading-one` y `landmark-one-main` transitorios. Pendiente para una futura tarea de la spec 010.

## Límites conocidos

- No se usó un lector de pantalla real (NVDA/VoiceOver); la verificación de nombres/navegación se hizo con axe + árbol de accesibilidad de Playwright.
- Los diálogos y live regions se verificaron con el DOM/árbol AAPI; la pronunciación exacta de lectores puede variar.
- axe no evalúa todo WCAG (p. ej. sentido común de orden de foco se verificó manualmente por teclado).
