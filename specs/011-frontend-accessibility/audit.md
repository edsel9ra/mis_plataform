# Inventario de alcance — Spec 011

## Estado

Inventario de rutas, completado en T-011.1. **No es un resultado de auditoría WCAG:** la inspección visual, de teclado y de tecnologías de asistencia se hará en T-011.2–T-011.7. No hay hallazgos ni conformidad declarados todavía.

## Cobertura general

- Conteo reproducible: `find 'frontend/src/app/[locale]' -type f -name 'page.tsx' | sort | wc -l` → **34 rutas de página**.
- Locales configurados: `es`, `en`, `pt`; `es` es el predeterminado y usa prefijo opcional (`frontend/src/i18n/routing.ts`). La matriz cubre plantillas de ruta; la revisión de traducciones por locale sigue pendiente.
- Guards inspeccionados: `frontend/src/middleware.ts`, `frontend/src/app/[locale]/dashboard/layout.tsx`, `frontend/src/app/[locale]/dashboard/mentor/layout.tsx` y `frontend/src/app/[locale]/admin/layout.tsx`.
- Middleware exige cookie `mis_auth_token` (o `AUTH_COOKIE_NAME`) para `/admin`, `/dashboard`, `/evaluations`, `/certificates`, `/learning-paths`, `/messages`, `/onboarding`, `/profile`, `/recommendations` y `/sessions`; redirige a login si falta. No prueba rol ni autorización del servidor.
- El dashboard raíz envía admin/super_admin a `/admin`, mentor a `/dashboard/mentor` y los demás usuarios al dashboard por `client_type`. Las rutas directas no deben considerarse autorizadas solo por su path.
- Admin layout limita la interfaz a `admin`/`super_admin`. El layout de Mentor Workspace limita sus subrutas a `mentor`. La autorización final de recursos depende del backend y queda fuera de esta verificación de inventario.
- **Disponibilidad de cuentas, datos de prueba y estados de API: sin verificar en T-011.1.** En tareas posteriores se registrará para cada ruta qué estados se pudieron recorrer y cuáles quedaron bloqueados por acceso/datos.

## Matriz de rutas

| # | Ruta bajo `[locale]` | Superficie | Acceso/rol observado | Estados o contexto que deberán cubrirse |
|---:|---|---|---|---|
| 1 | `/` | Pública | Pública; es la landing localizada. | Visitante anónimo; navegación y enlaces por locale. |
| 2 | `/login` | Cuenta | Pública. | Formulario inicial, validación, error y resultado de login. |
| 3 | `/register` | Cuenta | Pública. | Selección de tipo de cliente y navegación a registro. |
| 4 | `/register/[type]` | Cuenta | Pública; el parámetro debe corresponder a un tipo de cliente válido. | Formulario, carga de planes, validación y tipo inválido. |
| 5 | `/oauth/callback` | Cuenta | Ruta pública de retorno OAuth; depende de parámetros/estado del proveedor. | Estado de carga, retorno exitoso y error/cancelación si reproducible. |
| 6 | `/mentors` | Descubrimiento | Pública; resultados pueden variar por sesión/tipo de cliente. | Búsqueda, carga, error, vacío, resultados y paginación. |
| 7 | `/mentors/[id]` | Descubrimiento | Página pública; solicitud de mentoría aparece solo para usuario conectado que no sea mentor. | ID válido/inválido, datos, errores y vista CTA anónima/autenticada. |
| 8 | `/admin` | Administración | Cookie requerida; admin/super_admin según `admin/layout.tsx`. | Carga/error y dashboard con datos. |
| 9 | `/admin/users` | Administración | Admin/super_admin por layout; opciones dependen de rol. | Tabla/filtros, formulario, validación, error y paginación. |
| 10 | `/admin/mentors` | Administración | Admin/super_admin por layout. | Tabla, filtros, formulario y estados de error/éxito. |
| 11 | `/admin/sessions` | Administración | Admin/super_admin por layout. | Tabla/filtros, acciones y estados de carga/error. |
| 12 | `/admin/evaluations` | Administración | Admin/super_admin por layout. | Catálogo/lista, filtros, formularios y errores/confirmaciones. |
| 13 | `/dashboard` | Dashboard/router | Cookie requerida; redirige según rol y `client_type`. | Carga y destino para admin, mentor y tipos de cliente. |
| 14 | `/dashboard/personal` | Participante | Cookie requerida; dashboard elegido para `client_type=personal`; el layout común no restringe el tipo. | Datos vacíos/completos, carga/error y personalidad pendiente/completada. |
| 15 | `/dashboard/familiar` | Participante | Cookie requerida; destino de cliente familiar desde `/dashboard`; no se observó guard de tipo en la página. | Datos iniciales y estados de carga disponibles. |
| 16 | `/dashboard/grupal` | Participante | Cookie requerida; destino de cliente grupal desde `/dashboard`; no se observó guard de tipo en la página. | Datos iniciales y estados de carga disponibles. |
| 17 | `/dashboard/empresa` | Participante | Cookie requerida; `company_admin` ve gestión; usuario empresa sin compañía ve setup; otros roles ven vista de empleado. | Setup, administrador, empleado, carga/error y formularios. |
| 18 | `/dashboard/empresa/employees` | Participante | Cookie requerida; consulta empleados por `company_id`; permiso de datos lo valida el API. | Sin compañía, lista vacía/poblada, carga/error y paginación. |
| 19 | `/dashboard/progreso` | Participante/progreso | Cookie requerida; interfaz varía según autorización/contexto del usuario. | Carga, error parcial, sin datos y bloques disponibles. |
| 20 | `/dashboard/mentor` | Mentor Workspace | Cookie requerida; `mentor/layout.tsx` exige `role=mentor`. | Lista, búsqueda/filtros, carga, error y vacío. |
| 21 | `/dashboard/mentor/challenges` | Mentor Workspace | Mentor por layout anidado. | Lista, formulario/acciones y estados asíncronos disponibles. |
| 22 | `/dashboard/mentor/feedback` | Mentor Workspace | Mentor por layout anidado. | Selección de mentee, carga/error, formulario y feedback existente. |
| 23 | `/dashboard/mentor/progress` | Mentor Workspace | Mentor por layout anidado. | Alertas/lista, vacío, carga y error. |
| 24 | `/dashboard/mentor/progress/[id]` | Mentor Workspace | Mentor por layout; requiere ID de usuario/datos de progreso. | ID válido/inválido y estados de carga, datos o error. |
| 25 | `/dashboard/mentor/users/[id]` | Mentor Workspace | Mentor por layout; requiere mentee/relación accesible. | ID/relación válida, carga/error y pestañas/secciones con datos. |
| 26 | `/sessions` | Agenda | Cookie requerida; alcance de datos según API/relaciones. | Calendario/listado, vacío, carga/error, navegación y acciones aplicables; requisitos específicos en spec 010. |
| 27 | `/evaluations` | Evaluaciones | Cookie requerida; vista y resultados según usuario/relaciones; la página reconoce rol mentor. | Lista/resultados, filtros, vacío, carga/error. |
| 28 | `/evaluations/[id]` | Evaluaciones | Cookie requerida; detalle por ID y permiso de recurso del API. | ID válido/inválido, contenido, carga/error y acción disponible. |
| 29 | `/certificates` | Aprendizaje | Cookie requerida. | Lista vacía/poblada, carga/error y verificación/enlaces. |
| 30 | `/learning-paths` | Aprendizaje | Cookie requerida; contenido consultado para tipo de cliente. | Carga, error, vacío y rutas/módulos disponibles. |
| 31 | `/messages` | Colaboración | Cookie requerida. | Estado inicial/conversaciones y estados de carga/error si disponibles. |
| 32 | `/onboarding/test-personalidad` | Cuenta/onboarding | Cookie requerida; usuario/assessment condiciona el flujo. | Carga, preguntas/respuestas, progreso, error y finalización. |
| 33 | `/profile` | Cuenta | Cookie requerida. | Perfil, edición/validación, carga/error y cambio de locale si aplica. |
| 34 | `/recommendations` | Recomendaciones | Cookie requerida; contenido depende del perfil/datos. | Test pendiente, carga, error, sin resultados y resultados. |

## Límites pendientes

- Los estados de la última columna son objetivos de inspección, no evidencia de que haya cuentas o datos para reproducirlos.
- No se recorrieron las 34 rutas en navegador en esta tarea ni se ejecutó Lighthouse; se hará en las tareas asignadas.
- La tabla no altera ni evalúa permisos del backend. En especial, `middleware` comprueba presencia de cookie y no constituye autorización.

## T-011.2 — Fundamentos y componentes compartidos

**Estado: revisión estática provisional; verificación de navegador bloqueada.** Se revisaron `globals.css`, `layout.tsx`, `AppShell.tsx` y los componentes UI compartidos (botón, campos, etiqueta, alerta, spinner, tabs, diálogo, paginación, tarjeta, badge, avatar y thinking orb). Los cambios locales preexistentes fueron inspeccionados y preservados. Los siguientes puntos son candidatos a confirmar, no hallazgos reproducidos:

| ID provisional | Ubicación / criterio | Evidencia estática | Verificación pendiente |
|---|---|---|---|
| A11Y-SH-01 | `components/layout/AppShell.tsx` (aside del menú móvil, líneas 130–138); WCAG 2.4.3 y 2.4.11 | Al cerrar el menú se aplica `-translate-x-full`, pero el `<aside>` y los links permanecen montados y no se ve `inert`, `hidden` ni `aria-hidden` condicionado. Posible tabulación hacia navegación fuera de pantalla. | Recorrer Tab/Shift+Tab con menú cerrado y abierto a ancho móvil; confirmar orden, visibilidad del foco y que el menú cerrado no capture foco. |
| A11Y-SH-02 | `components/ui/dialog.tsx` (`DialogTrigger`, líneas 71–80); WCAG 2.1.1 / 4.1.2 | El `<button>` nativo conserva un `onKeyDown` que invoca manualmente la acción para Enter/Espacio; el botón nativo también genera activación por teclado. Posible doble invocación, especialmente al usar Espacio. | Abrir diálogo y contar callbacks por una pulsación Enter y una de Espacio en navegador; comprobar cierre/restauración de foco. |
| A11Y-SH-03 | `components/ui/alert.tsx` (líneas 16–24); WCAG 4.1.3 | Todas las variantes (incluidas info, success y warning) usan por defecto `role="alert"`; posible anuncio asertivo excesivo para mensajes no urgentes. Consumidores pueden sobreescribir el role vía props. | Examinar uso real y árbol accesible de mensajes dinámicos; determinar severidad/frecuencia antes de proponer cambio. |

**Bases observadas, aún no validadas en ejecución:** existe `:focus-visible` global y una regla `prefers-reduced-motion` en `globals.css`; `AppShell` contiene skip link a `#app-content`; los inputs/select/textarea enlazan etiqueta cuando reciben `label` y enlazan error con `aria-describedby`; los consumidores actuales de `DialogContent` revisados especifican `aria-labelledby`; tabs usan roles, estado seleccionado y navegación con flechas.

**Bloqueo de verificación:** la herramienta Chrome DevTools no pudo listar páginas porque el entorno no encuentra un ejecutable Chrome estable; tampoco hay binario `chromium`, `chromium-browser` o `google-chrome` en PATH. Por tanto no fue posible obtener el árbol de accesibilidad, ejecutar Lighthouse ni validar navegación/teclado en móvil y escritorio. Los contenedores están iniciados, pero no sustituyen al navegador. El usuario autorizó habilitar Chrome; instalarlo en `/opt/google/chrome/chrome` requiere `sudo`, que no está disponible sin autenticación interactiva. No se alteró el sistema ni se añadieron dependencias.
