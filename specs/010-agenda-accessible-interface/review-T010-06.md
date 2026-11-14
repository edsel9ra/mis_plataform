# Revisión T010-06 — Autorización server-side y estados/error en todas las superficies

**Fecha:** 2026-11-13 · **Alcance:** superficies de agenda existentes (`/sessions` día/semana/mes, `/sessions/{id}`, `/admin/sessions`). El frontend **no tiene** pantallas de reserva/disponibilidad/reprogramación/cancelación por usuario ni asistencia; esos flujos se cubren solo a nivel servidor. Evidencia: `/tmp/audit/a11y/states.log` + `states.json`, `admin-cancel.log`, `admin-cancel-axe.json`, `admin-cancel-after.png`, `admin-cancel-422.png`.

## Inventario de autorización (endpoint → prueba existente / añadida)

| Endpoint | 401 sin auth | No autorizado (403/404) | Datos ajenos | Archivo::test |
|---|---|---|---|---|
| `GET /sessions` | **nuevo** | `SessionCalendarTest::'does not grant list access…'` (support sin permiso 403); admin sin permiso 403 (`SessionAuthorizationTest::'does not grant admins…'`) | `SessionCalendarTest::'filters participant sessions by a semi-open range…'` + **nuevo** | `AgendaServerAuthorizationTest` (nuevo) |
| `GET /sessions/{id}` | **nuevo** | outsider → 404 (`SessionAuthorizationTest::'returns not found…'`, `SecurityAuthorizationTest::'prevents accessing another user session by id'`) | idem | — |
| `PUT /sessions/{id}/reschedule` | **nuevo** | `SessionRescheduleTest::'rejects unauthorized access according to the scheduling policy…'` | — | — |
| `POST /sessions/{id}/cancel` | **nuevo** | `SessionCancelTest::'enforces the session policy for an outsider'` | — | — |
| `PUT /sessions/{id}/attendance` | **nuevo** | `SessionAttendanceTest::'rejects confirmations outside the window… and by unauthorized actors'` | — | — |
| `GET/POST/PUT/DELETE /availability/*` | **nuevo** (windows + slots) | `AvailabilityTest::'hides another mentors availability…'`, `::'allows only a legitimate mentor or scheduler…'` | idem | — |
| `GET /admin/sessions` | **nuevo** | admin sin permiso → 403 (`SessionAuthorizationTest::'does not grant admins…'`); mentee → 403 (**nuevo**); support read ok / manage separado (`::'separates support read and manage permissions'`) | — | `AgendaServerAuthorizationTest` |
| `GET /admin/sessions/{id}` | **nuevo** | admin sin permiso → 403; mentee → 403 (**nuevo**) | — | idem |
| `POST /admin/sessions` | **nuevo** | mentee → 403 (**nuevo**) | — | idem |
| `PUT /admin/sessions/{id}` | **nuevo** | mentee → 403 (**nuevo**); viewer-sin-manage → 403 (`'separates support read and manage permissions'`) | — | idem |
| `PUT /admin/sessions/{id}/status` | **nuevo** | mentee → 403 (**nuevo**); solo `status=canceled` + `reason` (`UpdateAdminSessionStatusRequest`, `SessionMutationApiTest`) | — | idem |
| `DELETE /admin/sessions/{id}` | **nuevo** | mentee → 403 (**nuevo**); borrado físico restringido (`SessionAuthorizationTest::'restricts physical deletion…'`) | — | idem |

**Resultado:** `tests/Feature/Scheduling/AgendaServerAuthorizationTest.php` — 20 tests, todos verdes. La UI nunca sustituye autorización: toda acción pasa por el servidor.

## Checklist estados por ruta × locale (Playwright con `page.route`, `/tmp/audit/a11y/states.log`)

| Ruta × locale | carga (spinner `role=status` traducido) | éxito | vacío | error 500 (texto traducido + Retry por teclado → refetch) | 403 (traducido, sin datos) | 404 |
|---|---|---|---|---|---|---|
| `/sessions` es | ✓ "Cargando..." | ✓ | ✓ | ✓ anuncio + Enter → refetch (3→4) | ✓ "No tienes permisos…" | — |
| `/sessions` en | ✓ "Loading..." | ✓ | ✓ | ✓ anuncio + Enter → refetch | ✓ | — |
| `/sessions` pt | ✓ "Carregando..." | ✓ | ✓ | ✓ anuncio + Enter → refetch | ✓ | — |
| `/sessions/{id}` es/en/pt | ✓ | ✓ | — | ✓ anuncio + retry | ✓ | — |
| `/sessions/{uuid inexistente}` es/en/pt | — | — | — | — | — | ✓ "Ha ocurrido un error"/"An error occurred"/"Ocorreu um erro" + retry |
| `/admin/sessions` es/en/pt | ✓ | ✓ | ✓ | ✓ anuncio + retry (añadido en esta tarea) | ✓ | — |
| `/admin/sessions` como mentee | — | — | — | — | ✓ redirect a `/dashboard/personal`, sin datos, API 403 (test Pest) | — |

**Sin claves i18n crudas** en ningún estado/locale (regex de forma `seccion.clave` sobre texto visible). Anuncios presentes (`role=status`/`role=alert`) en carga, éxito de anuncio de acciones y errores.

## Defectos corregidos

- `/admin/sessions` carecía de control de reintento en estado de error → añadido botón `common.retry` (verificado: Enter dispara refetch 3→4 llamadas API).
- Envío vacío del diálogo de cancelación quedaba bloqueado por validación nativa `required` sin error accesible → `noValidate` en el formulario (y en el modal de alta/edición) para que la vía accesible (error de campo + `FormErrorSummary`) siempre gobierne; verificado: `aria-invalid`, `aria-describedby`, resumen anunciado, 0 peticiones enviadas.

## Flujo de cancelación con motivo (nuevo, verificado en vivo)

`admin-cancel.log`: foco inicial en `#admin-cancel-reason`; submit vacío → error de campo + resumen, 0 requests; con motivo → 1 PUT, diálogo cierra, celda de estado muestra "Cancelada", región `role=status` = "Sesión cancelada.", foco en `session-status-{id}`; 422 de dominio (sesión ya cancelada por API) → error traducido dentro del diálogo; axe del diálogo abierto: 0 violaciones.

## Residuales

- Las pantallas de reserva/disponibilidad/reprogramación/cancelación por usuario/asistencia no existen en el frontend: su cobertura de autorización queda probada solo a nivel servidor (tabla anterior). T010-04 sigue `[ ]` parcial por la misma razón.
- Mensajes de error del backend llegan ya traducidos vía `Accept-Language`; el frontend muestra `apiErrorMessage` o `common.load_error` como fallback.
