# Inventario de alcance — Spec 011

## Estado

Inventario de rutas (T-011.1) y resultados de la auditoría (T-011.2 a T-011.7), consolidados en §T-011.8. Con T-011.7a ya no quedan hallazgos abiertos; solo PG-09, pendiente de decisión de producto (D-2 de la spec 019). Las lagunas de verificación pasan a la spec 019.

## Cobertura general

- Conteo reproducible: `find 'frontend/src/app/[locale]' -type f -name 'page.tsx' | sort | wc -l` → **34 rutas de página** (2026-10-12) y **44 rutas** (2026-11-17; filas 35–44).
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
| 35 | `/forgot-password` | Cuenta (T-011.4) | Pública. | Formulario, validación, respuesta neutra y error. |
| 36 | `/reset-password` | Cuenta (T-011.4) | Pública; requiere token y email en la URL. | Token válido o inválido, validación, éxito y error. |
| 37 | `/sessions/[id]` | Agenda (T-011.5) | Cookie requerida; participante o responsable según el API. | Detalle, acciones disponibles o con motivo, diálogos, éxito y rechazo (spec 016). |
| 38 | `/sessions/new` | Agenda (T-011.5) | Cookie requerida; relaciones reservables según el API. | Pasos de relación, hueco y confirmación; sin huecos; carrera (spec 017). |
| 39 | `/availability` | Agenda (T-011.5) | Cookie requerida; solo mentor. | Ventanas y excepciones: listas, diálogos, conflictos (spec 017). |
| 40 | `/challenges` | Participante (T-011.6) | Cookie requerida; retos del mentee. | Lista, vínculo con módulo, vacío y error (spec 012). |
| 41 | `/dashboard/progreso/mapa` | Participante/progreso (T-011.6) | Cookie requerida; el titular. | Mapa de rutas y módulos, vacío y error (spec 013). |
| 42 | `/dashboard/progreso/historial` | Participante/progreso (T-011.6) | Cookie requerida; el titular. | Historial por días, sin fecha, incompletos, «Cargar más» y fallos (spec 015). |
| 43 | `/dashboard/mentor/progress/[id]/mapa` | Mentor Workspace (T-011.7) | Mentor asignado con entitlement del plan. | Mapa, bloqueo por plan, 404 (spec 013). |
| 44 | `/dashboard/mentor/progress/[id]/historial` | Mentor Workspace (T-011.7) | Mentor asignado con entitlement del plan. | Historial, bloqueo por plan, 404 (spec 015). |

## Límites pendientes

- Los estados de la última columna son objetivos de inspección, no evidencia de que haya cuentas o datos para reproducirlos.
- No se recorrieron las 34 rutas en navegador en esta tarea ni se ejecutó Lighthouse; se hará en las tareas asignadas.
- La tabla no altera ni evalúa permisos del backend. En especial, `middleware` comprueba presencia de cookie y no constituye autorización.

## T-011.2 — Fundamentos y componentes compartidos

**Estado: completada el 2026-11-17.**

- **Cómo se verificó:** Playwright con Chromium y axe-core 4.10, con las reglas WCAG 2.0, 2.1 y 2.2 A/AA y best-practice, más revisión del código.
- **Sobre qué:** un build de producción del frontend en el commit `985b7f6`, en un contenedor desechable que apuntaba al API de desarrollo. El servidor dev era inutilizable: cada ruta tardaba en compilar entre 400 y más de 3 000 s.
- **Cuentas:** `ana.demo` (mentee personal), `mentor.bienestar` y `admin`, en es/en/pt.
- **Evidencia:** 87 ficheros (JSON por comprobación, capturas y salida de axe) en `/tmp/a11y-011/T-011.2/`, que es una ubicación efímera.
- **Límites:**
  - No se probó con un lector de pantalla real; el árbol accesible y los roles `alert`/`status` se usan como aproximación.
  - La paginación no se pudo ejecutar porque ninguna lista de desarrollo tiene más de una página (fila I).

### Resultado por comprobación

| Comprobación | Resultado | Observación |
|---|---|---|
| Idioma, título, landmarks y h1 (`AppShell`, es/en/pt) | Cumple | `lang` correcto, título localizado, un único `main`, `nav` «Navegación principal», `header` y h1 correctos. |
| Skip link (`AppShell`) | Cumple | Es la primera parada del Tab y tiene foco visible. Con Enter el foco pasa a `main#app-content` y el siguiente Tab ya cae dentro del contenido. |
| Orden y foco visible en escritorio (1280 px) | Cumple | 25 paradas en orden lógico, todas con un contorno sólido de 2 px. |
| Menú móvil (375 y 320 px) | **No cumple** | A11Y-SH-01 y A11Y-SH-04. |
| Diálogo compartido (`ui/dialog.tsx`), probado en `/admin/users` y `/availability` | Cumple | `role=dialog` con `aria-modal` y nombre accesible a través del consumidor. El foco inicial va al primer campo, la trampa de foco se mantiene (15 Tab sin salir), Escape cierra y el foco vuelve al disparador. Enter y Espacio lo abren una sola vez. |
| Pestañas (`ui/tabs.tsx`) en el perfil de mentee | Cumple en parte | Roles, `aria-selected`, enlaces `controls`/`labelledby` y tabindex móvil correctos; las flechas mueven el foco y seleccionan. A11Y-SH-07. |
| Formularios (`Input` y errores), en `/login` es/en | Cumple en parte | Cada etiqueta está asociada a su campo; los errores van con `aria-invalid`, `aria-describedby` y `role=alert`, y el foco pasa al primer campo inválido. A11Y-SH-06. |
| Mensajes (`ui/alert.tsx`) | Cumple, con observación | Al cargar no hay varios `role=alert` a la vez; solo regiones `status` vacías. Ver A11Y-SH-03. |
| Paginación (`ui/pagination.tsx`) | Bloqueada | No hay datos con más de una página. Por código: `nav` con `aria-label`, botones con nombre, `disabled` y región `status`. Queda pendiente para T-011.7. |
| Reflow a 320 px y zoom 200 % (640 px) | Cumple | Sin scroll horizontal en `/login`, `/dashboard/personal`, `/admin/users` y `/dashboard/mentor`. La tabla de usuarios del admin se desplaza dentro de su propio contenedor (excepción de tabla de datos, a revisar en T-011.7). |
| Movimiento reducido | Cumple | La regla global de `globals.css` reduce todas las animaciones y transiciones medidas a 0,01 ms: menú lateral, fade-in, slide-up y cabecera. |
| Foco oculto por la cabecera fija (2.4.11), a 375 px | Cumple, con observación | El aviso sobre el skip link es un falso positivo: con `z-60` se pinta por encima de la cabecera. El fondo invisible del admin entra en el orden de Tab (A11Y-SH-01). |

### Hallazgos de fundamentos y componentes compartidos

| ID | Ubicación | WCAG | Prioridad | Evidencia | Corrección propuesta |
|---|---|---|---|---|---|
| A11Y-SH-01 | `components/layout/AppShell.tsx` y la cabecera propia de `app/[locale]/admin/layout.tsx` | 2.4.3, 2.4.7, 2.4.11 | **Alta** | Con el menú cerrado por debajo de 1024 px, el `<aside>` está en x=−288 y sus **15 enlaces y botones siguen en el orden de Tab** sin verse (`d-closed-375.json` y `d-closed-320.json`). En el admin, además, el fondo `<button>` «Cerrar» (`fixed inset-0`, sin `tabIndex=-1` ni `aria-hidden`) es una parada invisible que cubre toda la pantalla (`m-admin-users.json`). Afecta también a escritorio con zoom: a 1280 px y 200 % el ancho efectivo es de 640 px, que usa el layout móvil. | Sacar el menú cerrado del orden de foco y del árbol accesible (`inert` o `hidden` cuando está cerrado por debajo de `lg`) y hacer lo mismo con el fondo del admin. |
| A11Y-SH-04 | Los mismos dos shells, con el menú abierto | 2.4.3, 2.1.1 | **Alta** | Al abrirlo con el teclado, el foco se queda en el botón; el Tab sale del menú hacia la página de detrás; Escape no lo cierra. Al cerrarlo con la X, el foco queda en un elemento fuera de pantalla en vez de volver a «Abrir menú» (`d-open-stops-*.json`). | Llevar el foco al menú al abrirlo, contenerlo mientras está abierto (o hacer inerte el resto), cerrar con Escape y devolver el foco al botón. Debe ser la misma unidad de corrección que SH-01. |
| A11Y-SH-05 | `app/[locale]/admin/layout.tsx` | 2.4.1 | Media | El shell del admin no tiene skip link: la primera parada es «MIS Admin» y `main` no es destino (`m-admin-users.json`; `grep` sin `skip_to_content`). | Mismo skip link y `main` con id y `tabIndex=-1` que en `AppShell`. Conviene valorar si ambos shells deben compartir componente. |
| A11Y-SH-06 | `components/ui/button.tsx` (`loading` ⇒ `disabled`), con 53 usos | 2.4.3 | Media | Tras un envío fallido en el servidor (`/login`, contraseña incorrecta), el error se anuncia con `role=alert` pero **el foco cae a `body`**: el botón se deshabilita mientras carga y el navegador pierde el foco (`g-es-server.json`). Es el mismo mecanismo que se corrigió en el «Cargar más» de la spec 015. | Mientras carga, mantener el botón enfocable (`aria-disabled` + `aria-busy` y bloquear el clic) o devolver el foco de forma explícita. Hay que verificarlo en los consumidores con formularios. |
| A11Y-SH-07 | `components/ui/tabs.tsx` | 4.1.2 (aria-controls); APG | Baja | Inicio y Fin no están implementados (`f-keys.json`). `aria-controls` apunta a paneles que no se renderizan cuando la pestaña no está seleccionada, porque `TabsContent` devuelve `null`. En una corrida, el Tab desde la lista dejó el foco en `body`. Esto no es concluyente: se vuelve a verificar en T-011.7 con un panel que tenga contenido enfocable. | Añadir Inicio y Fin, y mantener los paneles en el DOM con `hidden` o retirar `aria-controls` cuando el panel no existe. |
| A11Y-SH-08 | Patrón de clases de color en varias páginas, no un componente | 1.4.3 | Media | axe: `text-slate-500` o `text-gray-500` sobre `slate-50`/`slate-100` dan **4,34–4,41:1**, por debajo de 4,5:1. Aparece en los chips de filtro de `/dashboard/personal`, en «Todos» de `/dashboard/mentor`, en «11 Total» de `/admin/users` y en el texto auxiliar del diálogo del admin (`axe-*.json`). | Barrido de esas combinaciones a `slate-600`/`gray-600`, como ya se hizo en la spec 010. Cada grupo confirma sus casos. |
| A11Y-SH-02 | `components/ui/dialog.tsx`, `DialogTrigger` | — | Sin hallazgo | No se puede reproducir: **ningún consumidor usa `DialogTrigger`** (comprobado con `grep`). El diálogo real abre una sola vez con Enter y con Espacio. | Opcional: eliminar ese código muerto. Queda fuera de las barreras. |
| A11Y-SH-03 | `components/ui/alert.tsx` | 4.1.3 | Observación | Todas las variantes usan `role="alert"` (29 usos info/success/warning). En las páginas probadas no hubo anuncios asertivos al cargar. | Se vuelve a verificar en cada grupo cuando se muestren mensajes info/success. Solo se corrige si aparecen anuncios indebidos. |

### Hallazgos de páginas detectados aquí y asignados a su grupo

| ID | Ruta | WCAG | Prioridad | Evidencia | Grupo |
|---|---|---|---|---|---|
| A11Y-PG-01 | `/admin/users` | 4.1.2, 1.3.1 | **Alta** | axe `select-name` (impacto *critical*): los dos `<select>` de filtro no tienen nombre accesible, a 1280 y a 320 px. | T-011.7 |
| A11Y-PG-02 | `/login` | 1.3.1, 2.4.6 | Media | axe: sin landmark `main`, sin h1 a 320 px, el primer encabezado es un `h3` y hay contenido fuera de landmarks (`region`, entre 7 y 13 nodos). | T-011.4 |
| A11Y-PG-03 | `/dashboard/personal` | 1.3.1 (best-practice) | Baja | Un segundo `aside` («Siguientes pasos») sin nombre accesible, junto al lateral «Dashboard». | T-011.6 |
| A11Y-PG-04 | `/admin/users` (320 px) | 2.1.1 | Media | axe `scrollable-region-focusable` (*serious*): el contenedor `.overflow-x-auto` de la tabla de usuarios se desplaza en horizontal pero no se puede enfocar, así que no se recorre con el teclado. Detectado en la reverificación de T-011.2a; no aparece en la línea base de T-011.2 porque entonces la tabla no llegaba a desbordar. | T-011.7 |

**Corrección aplicada (T-011.2a, 2026-11-17):** SH-01, SH-04 y SH-05 están corregidas y verificadas; ver `tasks.md` T-011.2a.

**Propuesta inicial de corrección:** SH-01, SH-04 y SH-05 forman una unidad («shells accesibles») y son las únicas barreras altas de componentes compartidos; conviene corregirlas antes de auditar más páginas, porque afectan a todas las rutas autenticadas en móvil y con zoom. SH-06 y SH-08 son unidades transversales de prioridad media. La división en specs hijas se decide en T-011.8.

## T-011.3 — Páginas públicas y descubrimiento

**Estado: completada el 2026-11-17.**

- **Cómo se verificó:** Playwright con Chromium y axe-core 4.10.3, con las reglas WCAG 2.0, 2.1 y 2.2 A/AA y best-practice, más revisión del código.
- **Sobre qué:** un build de producción del árbol de trabajo (`985b7f6` más T-011.2a).
- **Cuentas:** visitante anónimo para `/`, `/mentors` y `/mentors/[id]`; `ana.demo` para el resto.
- **Evidencia:** 76 ficheros en `/tmp/a11y-011/T-011.3/` (ubicación efímera).
- **Límites:** no se probó con un lector de pantalla real. El formulario «Solicitar mentoría» no se pudo abrir porque `ana.demo` ya tiene relación con todos los mentores de desarrollo; solo se revisó su código.

### Resultado por ruta

| Ruta | Resultado | Observación |
|---|---|---|
| `/` (anónimo, es/en/pt) | Cumple | axe sin violaciones en los 3 idiomas a 1280 y 320 px. Un `main`, `nav`, `header` y `footer`; h1 y 13 encabezados sin saltos. Los 12 `svg` son decorativos (`aria-hidden`). 11 paradas de Tab, todas con foco visible. Sin scroll horizontal y con el espaciado de texto ampliado (1.4.12) sin desbordes. Con movimiento reducido, las 47 animaciones se anulan. |
| `/mentors` (anónimo) | **No cumple** | A11Y-PG-05 (buscar hace perder el foco y el resultado no se anuncia) y A11Y-PG-06 (sin landmarks). axe: `landmark-one-main`, `region` y `heading-order` en los 3 idiomas. |
| `/mentors/[id]` (anónimo) | **No cumple** | A11Y-SH-08 (contraste de `text-gray-400`, 3 nodos *serious*) y SH-09 (sin h1). No hay ningún elemento enfocable: el visitante no tiene enlace de vuelta ni CTA (ver PG-09). |
| `/mentors/<uuid inexistente o mal formado>` | Observación | Muestra un `Alert` de error genérico, sin encabezado, landmark ni salida (PG-09). |
| `/mentors/[id]` (`ana`) | Cumple en parte | Se ve dentro del shell. Pendientes de confirmar en el navegador: PG-08 (`main` anidado) y el anuncio del aviso de éxito (SH-03). El formulario no se pudo ejecutar; por código, `Select` y `Textarea` tienen etiqueta y el envío usa `Button loading`, así que le afecta SH-06. |
| `/learning-paths` (`ana`) | Cumple, con observación | axe `heading-order` (SH-09). Es una lista de solo lectura: no hay controles dentro de `main`. |
| `/certificates` (`ana`) | Cumple | axe sin violaciones a 1280 y 320 px; lista de solo lectura. |
| `/recommendations` (`ana`) | Cumple, con observación | axe `heading-order` (SH-09). Los 3 enlaces de contenido tienen foco visible. |

Ninguna de las 6 rutas tiene scroll horizontal a 320 px ni a 640×400 px, y todas las paradas de Tab tienen foco visible.

### Hallazgos

| ID | Ubicación | WCAG | Prioridad | Evidencia | Corrección propuesta |
|---|---|---|---|---|---|
| A11Y-PG-05 | `/mentors`, búsqueda | 2.4.3, 3.2.2, 4.1.3 | **Alta** | Cada búsqueda con texto nuevo crea una query nueva sin datos previos. Mientras carga, `isLoading` sustituye **toda la página** por `PageSpinner` (`if (authLoading \|\| isLoading) return …`), así que el campo de búsqueda se desmonta mientras se escribe y el foco cae a `body` (`state-empty-search.json`). Además, el resultado («Sin resultados») no se anuncia: no hay región viva. Es la tarea principal de la página. | Mantener la página montada durante las recargas: spinner solo en la carga inicial y datos anteriores (`placeholderData`) mientras se busca. Añadir una región `status` con el número de resultados o «Sin resultados». |
| A11Y-PG-06 | `/mentors` y `/mentors/[id]` sin sesión | 1.3.1 | Media | Se muestran sin shell. `/mentors` no tiene ningún landmark (axe `landmark-one-main`, `region`); `/mentors/[id]` tiene `main` pero ni h1 ni cabecera. | Envolver el contenido en `main` y dar un h1 a cada página. No hace falta un shell nuevo. |
| A11Y-SH-08 (ampliado) | Clases de color: ahora también `text-gray-400` | 1.4.3 | Media | `/mentors/[id]`: «· Experto» en `text-gray-400` sobre fondo claro (axe *serious*, 3 nodos; unos 2,5:1). Por código, `text-gray-400` también aparece en textos de vacío de `/mentors` y `/recommendations`. | El mismo barrido de SH-08, incluyendo `gray-400`. |
| A11Y-SH-09 | `components/ui/card.tsx`: `CardTitle` siempre es un `h3` | 1.3.1, 2.4.6 (best-practice) | Baja | Es la causa común de `heading-order` (h1 → h3) en `/mentors`, `/learning-paths` y `/recommendations`, y de la falta de h1 en `/mentors/[id]`, donde el nombre del mentor es un `CardTitle`. | Permitir elegir el nivel (`as`/`level`) y ajustar los consumidores afectados. |
| A11Y-PG-08 | `/mentors/[id]` con sesión | 1.3.1 (best-practice) | Baja, por confirmar | Por código, la página pone su propio `<main>` dentro del `<main>` de `AppShell` (`withShell`). axe no se ejecutó en ese estado. | Usar `main` solo sin shell, o `div`/`section` dentro del shell. Confirmar con axe al corregir. |
| A11Y-SH-03 (instancia) | `/mentors/[id]` con sesión | 4.1.3 | Baja | El aviso de éxito «relación activa» lleva `role="alert"` y aparece al resolverse los contextos, así que se anuncia de forma asertiva sin ser urgente (por código; en el navegador se vio que se renderiza). | Lo resuelve la corrección general de SH-03: `role="status"` para info/success. |
| A11Y-PG-09 | `/mentors/[id]` | — | Observación | Un id inexistente o mal formado muestra el `Alert` genérico «Ha ocurrido un error», sin encabezado ni enlace de vuelta. La ficha anónima no tiene ningún elemento navegable. No incumple WCAG por sí mismo; cambiarlo es una decisión de producto (RNF-011.3). | Proponer en T-011.8 un estado «no encontrado» con enlace a `/mentors`. |
| Inicio | `/` | 2.2.2 | Observación | Hay orbes decorativos difuminados con la animación `drift` de 16 s en bucle. No transmiten información y se detienen con movimiento reducido. | Ninguna. |

**Corrección aplicada (T-011.3a, 2026-11-17):** PG-05, PG-06, PG-08 y SH-09 están corregidas. También SH-08 (`gray-400` y las instancias confirmadas en T-011.2) y SH-03, con `role="status"` para info, success y warning. Verificación en `tasks.md` T-011.3a. Sigue abierta PG-09 (decisión de producto, T-011.8). SH-08 continúa como barrido: cada grupo confirma sus combinaciones `-500` sobre `slate-50`/`slate-100`.

## T-011.4 — Acceso, perfil y onboarding

**Estado: completada el 2026-11-17.**

- **Cómo se verificó:** Playwright con Chromium y axe-core 4.10.3, con WCAG 2.0, 2.1 y 2.2 A/AA y best-practice, más revisión del código.
- **Sobre qué:** un build de producción del árbol de trabajo, que ya incluye T-011.2a y T-011.3a.
- **Evidencia:** 56 ficheros en `/tmp/a11y-011/T-011.4/` (ubicación efímera).
- **Cuentas:**
  - `ana.demo`: un login fallido y el perfil;
  - `mentor.bienestar`: el perfil de mentor;
  - `kellie.breitenberg@example.net`: mentee sembrada sin evaluación, para recorrer el test sin enviarlo.
- **Datos de desarrollo sin cambios, comprobado en la BD:**
  - no se creó ningún usuario (el servidor rechazó con 422 el email duplicado);
  - kellie sigue sin evaluación;
  - el idioma de ana no cambió;
  - no se pidió ningún restablecimiento con un email real.
- **No probado, para no causar efectos:** OAuth real (solo el estado de error), registro y login con éxito, rate limit, restablecimiento con token válido, guardar el perfil o el idioma y enviar el test. El caso «ya tiene evaluación», que redirige a `/dashboard`, solo se revisó en el código (`page.tsx:59-65`).
- **Límites:** no se probó con un lector de pantalla real, y `page.accessibility` está obsoleto en esta versión de Playwright. Los ficheros `struct-*.json` recogen landmarks, encabezados y regiones vivas.

### Resultado por ruta

| Ruta | Resultado | Observación |
|---|---|---|
| `/login` (es/en/pt, 1280 y 320 px) | **No cumple** | PG-02 reconfirmado: no hay landmarks. El h1 (`hidden lg:block`) desaparece por debajo de `lg`, así que a 320 px falla `page-has-heading-one`. Además, `heading-order` (h1 → h3 de `CardTitle`) y `region` ×13. SH-06 reconfirmado. Etiquetas, `autocomplete`, errores por campo y pegar en la contraseña: correctos. |
| `/register` | Cumple en parte | h1 y h2 correctos y orden de Tab lógico; sin landmarks (PG-10). |
| `/register/[type]` (4 tipos + inválido) | **No cumple** | PG-12 (foco invisible en los planes), PG-10, SH-09 (`heading-order`), SH-08 (4,41:1) y PG-16. Con un tipo inválido, la página no ofrece salida (PG-11). |
| `/oauth/callback` (sin parámetros o con `#error`) | **No cumple** | PG-10, sin h1. PG-11: el estado de error no tiene ningún elemento enfocable (12 Tab sin parada). |
| `/forgot-password` | Cumple en parte | PG-10. La respuesta neutra se anuncia con `role=status` (efecto de la corrección de SH-03). SH-06 tras el envío. |
| `/reset-password` (sin token, token inválido, sin email) | Cumple en parte | El enlace inválido ofrece cómo pedir otro. El email del enlace viene ya rellenado (3.3.7 cumple). PG-10 y SH-06. |
| `/profile` (`ana` 1280/320, mentor 1280) | Cumple | axe sin violaciones, landmarks y h1. Los selectores de idioma y de niveles de habilidad tienen etiqueta. Sin scroll horizontal. |
| `/onboarding/test-personalidad` (es/en/pt, 320 y 640 px) | **No cumple** | PG-15 (el avance automático impide elegir con el teclado), PG-13 (sin landmarks ni h1) y PG-14 (nombres accesibles que son claves i18n sin traducir). |

En todas las rutas: sin scroll horizontal a 320×800 ni a 640×400 px. En `/login`, el espaciado de texto ampliado no desborda, y con movimiento reducido las 137 animaciones se anulan.

Formularios: cada campo tiene etiqueta, `aria-invalid`, `aria-describedby` y un error con `role=alert`. Tras un error del cliente, el foco va al primer campo inválido. El `autocomplete` es correcto (`email`, `current-password`, `new-password`, `given-name`, `family-name`), salvo en PG-16. Se puede pegar en las contraseñas (3.3.8). No existe el botón de mostrar la contraseña. El registro es de un solo paso. Los botones de OAuth tienen nombre.

### Hallazgos

| ID | Ubicación | WCAG | Prioridad | Evidencia | Corrección propuesta |
|---|---|---|---|---|---|
| A11Y-PG-15 | `onboarding/test-personalidad/page.tsx:93-99` (`handleAnswer` avanza solo) | 2.1.1, 3.2.2 | **Alta** | Al elegir una respuesta se pasa sola a la siguiente pregunta. Con el teclado, las flechas seleccionan al moverse, así que la primera flecha ya responde y avanza: en el navegador, ArrowDown marcó la opción 2 y saltó a «Pregunta 2» (`test-keyboard.json`). Solo se pueden elegir las opciones a las que se llega sin flechas, es decir, la primera y la última. Es la tarea principal del onboarding. | No avanzar al seleccionar: añadir un botón «Siguiente» (y «Enviar» en la última pregunta), o avanzar solo con un clic o con Enter o Espacio, nunca con las flechas. Al cambiar de pregunta, el foco va al grupo nuevo. |
| A11Y-PG-12 | `register/[type]/page.tsx:273-279` (radio de plan `sr-only` dentro de un `label` sin estilo de foco) | 2.4.7 | **Alta** | El radio mide 1×1 px y la tarjeta no muestra ningún anillo al recibir el foco (`plan-radio-focus.json`, `plan-radio-focused.png`). Elegir el plan es obligatorio para registrarse, y quien usa el teclado no ve en cuál está. | Anillo en la tarjeta con `has-[:focus-visible]:ring-2` (o equivalente) y la selección indicada también con un icono o texto. |
| A11Y-PG-13 | `onboarding/test-personalidad/page.tsx:169-227` | 1.3.1, 2.4.6 | Media | Fuera del shell, sin `main` ni h1 en los 3 idiomas. La pregunta es un h2 y la `legend` es genérica (`test-struct.json`, `struct-test-*.json`). | Envolver en `main` con un h1 de página. La `legend` debe ser el texto de la pregunta. |
| A11Y-PG-14 | `i18n/messages/{es,en,pt}/common.json`; usado en `page.tsx:181,191` | 4.1.2, 1.3.1 | Media | Faltan `personality.progress_label` y `personality.select_answer` en los 3 catálogos (comprobado con grep). El `aria-label` de `<progress>` y la `legend` son la clave sin traducir. Hay texto visible «Pregunta X de 120» y el porcentaje. | Añadir las 2 claves en es/en/pt. |
| A11Y-PG-10 | `register/page.tsx:60`, `register/[type]/page.tsx:134`, `forgot-password/page.tsx:45`, `reset-password/page.tsx:64`, `oauth/callback/page.tsx:63` | 1.3.1 (best-practice) | Media | `landmark-one-main` y `region` en las 6 rutas, y `page-has-heading-one` en 4 (`struct-*.json`). Es el mismo patrón que PG-02 y PG-06. | `main` y un h1 por página, como en T-011.3a. Conviene tratarlo junto con PG-02. |
| A11Y-PG-11 | `oauth/callback/page.tsx:61-67`, `register/[type]/page.tsx:113-121` | 2.4.5 / RNF-011.3 | Media | Con un error de OAuth o un tipo de registro inválido solo aparece un `Alert`, sin ningún enlace: es un callejón sin salida (`tab-oauth-error.json`, `struct-register-bogus-1280.json`). | Enlace a `/login` o `/register` en esos estados, con textos existentes o una clave nueva en es/en/pt. |
| A11Y-SH-06 (reconfirmado) | `components/ui/button.tsx` | 2.4.3 | Media | Confirmado en 4 flujos: login, registro con error del servidor, forgot y reset. El foco cae a `body` (`forms-*.json`, `register-servererror.json`). | Sin cambios respecto a T-011.2. |
| A11Y-SH-08 (ampliado) | `register/[type]/page.tsx:284` | 1.4.3 | Media | `text-gray-500` sobre `bg-primary-50` en la tarjeta seleccionada: 4,41:1 en los 4 tipos (`contrast-register-*-1280b.json`). | `text-gray-600`. |
| A11Y-SH-09 (consumidores) | `login/page.tsx:109`, `register/[type]/page.tsx:174` | 1.3.1 (best-practice) | Baja | `heading-order`: h1 → h3 de `CardTitle` (`struct-login-*.json`, `struct-register-*-1280.json`). | Usar `CardTitle as="h2"` o convertirlo en el h1 de la página. |
| A11Y-PG-16 | `register/[type]/page.tsx:239-256` | 1.3.5 | Baja | Faltan `autocomplete="sex"` y `autocomplete="bday"` (`forms-register.json`). | Añadir los atributos. |

**Corrección aplicada (T-011.4a, 2026-11-17):** están corregidas PG-02, PG-10 a PG-16 y SH-06. SH-06 se resolvió en `Button`, que ya no se deshabilita mientras carga, así que la corrección alcanza a todos sus consumidores. También se corrigieron los casos de SH-08 y SH-09 de este grupo. Verificación en `tasks.md` T-011.4a.

## T-011.5 — Agenda, evaluaciones y comunicación

**Estado: completada el 2026-11-17.**

- **Cómo se verificó:** Playwright con Chromium y axe-core 4.10.3, con WCAG 2.0, 2.1 y 2.2 A/AA y best-practice, más revisión del código, sobre un build de producción del árbol de trabajo (incluye T-011.2a, T-011.3a y T-011.4a).
- **Evidencia:** 69 ficheros en `/tmp/a11y-011/T-011.5/` (ubicación efímera).
- **Cuentas:** `ana.demo` (sesiones, reserva, evaluaciones y mensajes) y `mentor.bienestar` (disponibilidad, evaluaciones y detalle de sesión del lado del mentor).
- **Datos de desarrollo sin cambios:** la BD se comparó antes y después (sesiones 8, franjas 0, excepciones 0, mensajes 0, eventos de asistencia 2, cambios de agenda 0, evaluaciones 11, resultados 1). No se confirmó ninguna reserva, diálogo, evaluación ni mensaje.
- **Clasificación pedida por la tarea:** todos los hallazgos son **globales (spec 011)**. Los requisitos de las specs 010, 016 y 017 que se pudieron comprobar se cumplen (ver abajo).

### Resultado por ruta

| Ruta | Resultado | Observación |
|---|---|---|
| `/sessions` (es 1280/320, en, pt) | Cumple | axe sin violaciones. 40 paradas de Tab, todas con nombre y foco visible. Día/Semana/Mes con `aria-pressed`. Objetivos de 36×36 px o más. Cada enlace de sesión incluye la hora y el estado en su nombre. |
| `/sessions/[id]` | Cumple | h1 y h2 («Acciones», «Asistencia») y regiones `status`. Diálogos de reprogramar y cancelar: `aria-modal`, `aria-labelledby` y `aria-describedby`, foco inicial en el primer campo, contención del foco, Escape devuelve el foco al botón que lo abrió, y validación con `aria-invalid` y resumen `role=status`. |
| `/sessions/<id inválido>` | **No cumple** | PG-17: solo hay un `Alert`, sin h1 (`page-has-heading-one`). |
| `/sessions/new` (es 1280/320, en, pt) | Cumple en parte | axe sin violaciones. Días y huecos con `aria-pressed`, nombre completo (fecha, hora y zona) y 121×38 px o más. La selección se anuncia con `status`. Si se envía vacío, el foco va al campo inválido. SH-10: `disabled` redundante con `loading`. |
| `/availability` (mentor) | Cumple | axe sin violaciones (estado vacío). Diálogo «Nueva franja»: campos con etiqueta, el foco va al primer campo inválido, resumen `status`, contención del foco y Escape devuelve el foco. En «Excepción», los radios van agrupados con `fieldset` y `legend`. |
| `/evaluations` | Cumple en parte | Vista de mentor sin violaciones. En la de `ana`, `heading-order` (PG-18). |
| `/evaluations/[id]` | **No cumple** | PG-20 (avance automático y foco perdido), PG-19 (barra de progreso sin nombre), PG-21 (opciones sin agrupar) y PG-17 (sin h1). |
| `/evaluations/<id inválido>` | **No cumple** | PG-17: sin h1. |
| `/messages` (es 1280/320, en, pt) | Cumple | axe sin violaciones. Conversaciones como `button` con `aria-current`. Lista con `role="log"`, `aria-live="polite"` y nombre. El campo de escritura tiene etiqueta. «Enviar mensaje» está deshabilitado si no hay texto. Enter con el campo vacío no envía y Shift+Enter hace un salto de línea. Ningún objetivo de menos de 24 px. |

Sin scroll horizontal a 320 px en ninguna ruta. Los estados no dependen solo del color: el estado de la sesión es texto, el hueco seleccionado lleva `aria-pressed` y la opción de evaluación, `aria-pressed` con icono.

### Hallazgos

| ID | Ubicación | WCAG | Prioridad | Evidencia | Corrección propuesta |
|---|---|---|---|---|---|
| A11Y-PG-20 | `evaluations/[id]/page.tsx:108-116` (`answerQuestion` avanza solo) | 3.2.2, 2.4.3 | **Alta** | En el navegador: Enter sobre una opción cambia a la pregunta 2 y el foco cae a `body` (`eval-messages.json`). Hay que volver a recorrer unas 17 paradas por pregunta. Es la tarea principal de la página. Es el mismo patrón que PG-15. | Igual que en T-011.4a: sin avance automático, botón «Siguiente» y foco a la pregunta nueva. |
| A11Y-PG-19 | `evaluations/[id]/page.tsx:152` y `:214` (`role="progressbar"` sin nombre) | 4.1.2 | **Alta** | axe `aria-progressbar-name`, *serious* (`axe-evaluation-id-ana-*.json`). | `aria-label` con una clave i18n o `aria-labelledby` a un texto visible. |
| A11Y-PG-21 | `evaluations/[id]/page.tsx:225-246` | 1.3.1 | Media | Las 5 opciones son `button aria-pressed` dentro de un `div` sin grupo ni nombre (`eval-messages.json`). | `role="group"` (o `radiogroup` con radios) y `aria-labelledby` al h2 de la pregunta. |
| A11Y-PG-17 | `sessions/[id]/page.tsx:49` (id inválido o error), `evaluations/[id]/page.tsx:94` (error) y la vista de preguntas (`CardTitle` h3, :193) | 1.3.1, 2.4.6 (best-practice) | Media | axe `page-has-heading-one` (`axe-session-badid-ana.json`, `axe-evaluation-*.json`). | Un h1 visible en los estados de error y en la vista de preguntas (`CardTitle as="h1"`). |
| A11Y-PG-18 | `evaluations/page.tsx:221` | 1.3.1 (best-practice) | Baja | `heading-order`: h1 → h3 de `CardTitle` (`axe-evaluations-ana-*.json`). Es otro consumidor de SH-09. | `CardTitle as="h2"`. |
| A11Y-SH-10 | Consumidores de `Button` que pasan `disabled` igual a `loading`; confirmado en `sessions/new/page.tsx:587-589` | 2.4.3 | Baja | Por código: con el `disabled` explícito, el botón se sigue deshabilitando y pierde el foco al enviar, lo que anula la corrección de SH-06. | Quitar el `disabled` redundante. Cada grupo confirma sus consumidores. |

**Observación:** la hora de los mensajes propios (`text-[10px] text-primary-100` sobre `bg-primary-600`, `messages/page.tsx:220`) no se pudo medir porque la BD no tiene mensajes.

### Specs 010, 016 y 017: lo comprobado

- **010:** objetivos de 24 px o más, teclado y validación que se anuncia. `FormErrorSummary` con `role=status` está siempre montado.
- **016:** el detalle muestra solo las acciones que devuelve `available_actions`, y sus diálogos son accesibles.
- **017:** relación preseleccionada, huecos que da el servidor con `aria-pressed` y validación que conserva los datos. El foco va a `booking-success` tras reservar, aunque esto solo se comprobó en el código porque no se confirmó ninguna reserva.

### Sin probar

- **Asistencia:** las sesiones sembradas son futuras o ya están completadas, así que la ventana está cerrada y no aparecen botones.
- **Completar sesión (mentor):** no hay ninguna sesión pasada sin completar.
- **Confirmación de reserva y envío de evaluación:** no se hicieron, para no crear datos.
- **Mensajes:** con 0 mensajes no se pudieron observar las burbujas, «cargar anteriores» ni la llegada en vivo.
- **Recordatorios por Reverb:** no se emitió ningún evento.

**Verificación de T-011.5a (2026-11-17), después de reiniciar Docker:** PG-17 a PG-21 y SH-10 quedan corregidas y comprobadas en el navegador; ver `tasks.md` T-011.5a.

## T-011.6 — Paneles de participante y progreso

**Estado: completada el 2026-11-17.**

- **Cómo se verificó:** Playwright con Chromium y axe-core 4.10.3, con WCAG 2.0, 2.1 y 2.2 A/AA y best-practice, sobre un build de producción del árbol de trabajo (incluye T-011.5a), más revisión del código.
- **Cobertura:** 26 combinaciones de ruta, cuenta y ancho; todas cargaron. Evidencia: 41 ficheros en `/tmp/a11y-011/T-011.6/` (ubicación efímera).
- **Cuentas:** `ana` (personal), `carlos` (familiar), `diana` (grupal), `felipe` (empresa, sin compañía). `mentor.bienestar` y `admin` solo para comprobar a dónde redirige `/dashboard`.
- **Datos de desarrollo sin cambios** (BD comparada antes y después): objetivos 16, evidencias 4, compañías 1, empleados 0, entregas de retos 0 y vínculos de competencias 1.

### Resultado por ruta

| Ruta | Resultado | Observación |
|---|---|---|
| `/dashboard` | Cumple | Redirige según el rol y el tipo de cliente: personal, familiar, grupal, empresa, mentor y `/admin`. |
| `/dashboard/personal` (es/en/pt, 1280 y 320 px) | Cumple en parte | axe sin violaciones. Los filtros llevan `aria-pressed`, y el orden de Tab es lógico y con nombres. PG-03 reconfirmado y un enlace de 20 px de alto (PG-24). |
| `/dashboard/familiar` y `/dashboard/grupal` | Cumple en parte | `heading-order`: del h1 se salta a un h3 (PG-22). |
| `/dashboard/empresa` (`felipe`, sin compañía) | **No cumple** | El formulario de configuración no tiene h1 (PG-23). Validación: el foco va al campo inválido y los mensajes salen traducidos. |
| `/dashboard/empresa/employees` (sin compañía) | **No cumple** | Solo muestra un `Alert` de aviso, sin h1 (PG-23). |
| `/dashboard/progreso` (es/en/pt, 1280 y 320 px) | Cumple en parte | `heading-order` (PG-22), enlaces de 20 px (PG-24) y errores de validación en inglés (SH-11). En los dos formularios probados, el foco va al campo inválido (SH-06 y SH-10 bien). Los selectores tienen etiqueta. |
| `/challenges` | Cumple | axe sin violaciones; las insignias de estado son texto y los selectores tienen etiqueta. |
| `/dashboard/progreso/mapa` | Cumple en parte | `heading-order` (PG-22) y el enlace «Volver al panel» mide 20 px de alto (PG-24). Los estados son texto («Sin datos», «Incompleto…»), sin depender del color. |
| `/dashboard/progreso/historial` | Cumple | axe sin violaciones; h1 y región `status` mientras carga. |

En ninguna ruta hay scroll horizontal a 320×800 ni a 640×400 px, y el espaciado de texto ampliado no desborda. No hay ningún elemento animado. El foco es visible en todas las paradas recorridas. Métricas, insignias y estados se leen como texto, sin depender del color. Ninguna ruta del grupo tiene diálogos.

### Hallazgos

| ID | Ubicación | WCAG | Prioridad | Evidencia | Corrección propuesta |
|---|---|---|---|---|---|
| A11Y-SH-11 | `ObjectiveProposalForm.tsx:53-69`, `ObjectiveForm.tsx:107-146` y `ObjectiveEvidenceForm.tsx:47` muestran `errors.X?.message` tal cual; `lib/schemas/objective.ts:94` | 3.1.2, RNF de i18n | Media | Con la interfaz en español, los errores salen en inglés: «Too small: expected string to have >=3 characters» y «Add a note or attach a file.» (`progreso-objective-form.json`, `progreso-evidence-form.json`). El formulario de empresa ya usa `validationMessage()`. | Usar `validationMessage(errors.X?.message, t)` y que los esquemas devuelvan claves i18n en lugar de texto en inglés. |
| A11Y-PG-03 (reconfirmado) | `dashboard/personal/page.tsx:336` | 1.3.1 (best-practice) | Baja | El segundo `aside` sigue sin nombre (`axe-dashboard_personal-*.json`). | Darle nombre con `aria-labelledby` a su título o con `aria-label`. |
| A11Y-PG-22 | `dashboard/familiar/page.tsx:17`, `dashboard/grupal/page.tsx:17`, `components/progress/ProgressCard.tsx:26` y `LearningMap.tsx:79` | 1.3.1 (best-practice) | Baja | `heading-order` de h1 a h3 en 5 rutas. Son consumidores de SH-09. | Títulos de tarjeta con `as="h2"`. |
| A11Y-PG-23 | `dashboard/empresa/page.tsx:52` (formulario de configuración) y `dashboard/empresa/employees/page.tsx:56` (sin compañía) | 1.3.1, 2.4.6 (best-practice) | Media | `page-has-heading-one` (`axe-dashboard_empresa*-felipe-*.json`). | Un h1 visible en los dos estados. |
| A11Y-PG-24 | «Ver historial» (`ProgressDashboard.tsx:21`), «Ver mapa» (`LearningPathsCard.tsx:43`), «Volver al panel» en el mapa y «Acciones sugeridas…» en el panel personal | 2.5.8 | Baja | Enlaces sueltos de 20 px de alto (`personal-targets.json`, `progreso-elements.json`, `mapa-color-targets.json`). Dentro de un párrafo estarían exentos, pero estos van solos. | Alto mínimo de 24 px (`py-1` o `min-h-6`). |

### Límites por datos o permisos (no son defectos)

- No hay ninguna cuenta `company_admin` ni `employee` (0 empleados), así que no se probaron el panel real de empresa con métricas ni la invitación de empleados.
- El mentee no ve el formulario para crear objetivos ni el control de estado; solo «Proponer objetivo».
- No hay evidencias con archivo adjunto que probar.

**Corrección aplicada (T-011.6a, 2026-11-17):** SH-11, PG-03 y PG-22 a PG-24 están corregidas; la verificación está en `tasks.md` T-011.6a.

## T-011.7 — Espacio del mentor y administración

**Estado: completada el 2026-11-17.**

- **Cómo se verificó:** Playwright con Chromium y axe-core 4.10.3, con WCAG 2.0, 2.1 y 2.2 A/AA y best-practice, más revisión del código, sobre un build de producción del árbol de trabajo (incluye T-011.6a). 30 combinaciones de ruta y ancho; todas cargaron.
- **Evidencia:** 43 ficheros en `/tmp/a11y-011/T-011.7/` (ubicación efímera).
- **Cuentas:** `admin` y `mentor.bienestar`, con `ana` como su mentee activa.
- **Datos de desarrollo sin cambios** (BD comparada antes y después): usuarios 11, skills 13, retos 4, feedback 0, observaciones 0, mapas sistémicos 0, media 0, objetivos 16, relaciones 4, entregas 0, evaluaciones 11 y sesiones 8.
  - Los formularios se abrieron, se validaron y se cerraron sin guardar.
  - `FileUploadField` no sube nada hasta enviar el formulario (comprobado en el código); aun así, no se eligió ningún fichero.

### Resultado por ruta

| Ruta | Resultado | Observación |
|---|---|---|
| `/admin` | Cumple en parte | `heading-order`: los títulos de las tarjetas son h3, directamente bajo el h1 (PG-26). |
| `/admin/users` (es/en/pt) | **No cumple** | PG-01 y PG-04 reconfirmados. La tabla tiene `th` con `scope`. Diálogo «Crear»: foco en el primer campo, errores en el idioma de la interfaz con `aria-invalid`, y Escape cierra y devuelve el foco al botón. |
| `/admin/mentors` | **No cumple** | PG-01 (un selector), PG-26 y PG-27 (`text-gray-500`). |
| `/admin/sessions` | Cumple | axe sin violaciones; tabla con `th` y `scope`. |
| `/admin/evaluations` | **No cumple** | PG-01 (un selector) y PG-27 (×3: `gray-500` y las pestañas inactivas). SH-07. |
| `/dashboard/mentor` | Cumple | axe sin violaciones; filtros con `aria-pressed`. |
| `/dashboard/mentor/challenges` | Cumple en parte | PG-26 y SH-12. Los campos tienen etiqueta, el selector de archivo se llama «Archivo del reto» y tiene `accept`, y si se envía vacío el foco va al campo inválido. |
| `/dashboard/mentor/feedback` y `/dashboard/mentor/progress` | Cumple | axe sin violaciones (feedback, en estado vacío). PG-25. |
| `/dashboard/mentor/progress/[id]` | **No cumple** | PG-27: 48 nodos con contraste insuficiente (*serious*) en los estados «Logrado» y «En progreso». PG-28. |
| `/dashboard/mentor/users/[id]` (5 pestañas) | Cumple en parte | axe sin violaciones; SH-07. Sin `relationship_id`, la página muestra un aviso con un enlace, que es una salida válida. |
| `/dashboard/mentor/progress/[id]/mapa` y `/historial` | Cumple | axe sin violaciones; también aquí se aplican las correcciones de T-011.6a. |

En ninguna ruta hay scroll horizontal de la página a 320 px. Los estados (insignias, «Sin acceso» y estados de reto) llevan texto y hay regiones `status`.

### Hallazgos

| ID | Ubicación | WCAG | Prioridad | Evidencia | Corrección propuesta |
|---|---|---|---|---|---|
| A11Y-PG-01 (reconfirmado y ampliado) | Selectores de filtro de `/admin/users` (2), `/admin/mentors` (1) y `/admin/evaluations` (1) | 4.1.2, 1.3.1 | **Alta** | axe `select-name`, impacto *critical*, en 3 rutas (`axe-admin_*.json`). | Etiqueta visible o `aria-label` con una clave i18n en cada selector de filtro. |
| A11Y-PG-04 (reconfirmado) | `/admin/users`, `div.overflow-x-auto` de la tabla | 2.1.1 | **Alta** (antes media) | A 320 px y con zoom al 200 %, la tabla solo se desplaza con el ratón, y quien usa el teclado no llega a las columnas ocultas (`users-320-pagin.json`). Sube a alta porque impide acceder a la información principal de la página. | `tabIndex={0}`, `role="region"` y `aria-label` en el contenedor desplazable, con foco visible. Aplicar lo mismo a las demás tablas del admin que desborden. |
| A11Y-SH-12 | `lib/schemas/mentor-workspace.ts` (líneas 7, 10, 37, 48 y 52) y los formularios que lo usan (retos, feedback, observaciones, check-in) | 3.1.2, RNF-011.4 | Media | Con la interfaz en español salen errores en inglés: «Too small: expected string to have >=3 characters» y «Select a file.» (`mentor-challenges-form.json`). Es el mismo patrón que SH-11. | Lo mismo que en T-011.6a: claves i18n en el esquema y `validationMessage`. |
| A11Y-PG-27 | `/dashboard/mentor/progress/[id]` (estados «Logrado» y «En progreso», 48 nodos), `/admin/mentors` (`.mt-1.text-gray-500`) y `/admin/evaluations` (`gray-500` y pestañas inactivas) | 1.4.3 | Media | axe `color-contrast` *serious* (`axe-dashboard_mentor_progress_ID-*.json`, `progress-contrast.json`). Son restos del barrido de SH-08. Hay que medir la relación exacta al corregir. | Oscurecer el texto de esos estados y de las pestañas inactivas, y usar `text-gray-600`/`slate-600` en los metadatos. |
| A11Y-SH-07 (reconfirmado) | `components/ui/tabs.tsx:111-131` | 2.1.1 (patrón APG) | Baja | En dos listas de pestañas, Inicio y Fin no hacen nada. Las flechas mueven el foco y seleccionan, el tabindex móvil y `aria-selected` son correctos, y axe no marca `aria-controls`. El «detalle de foco» de T-011.2 no se reproduce (`users-id-tabs.json`, `admin-eval-tabs.json`). | Añadir Inicio y Fin. Comprobar en el código si `aria-controls` apunta a paneles que no se renderizan. |
| A11Y-PG-25 | Shell del espacio del mentor: el título «Workspace de mentoría» es un h1 y la página tiene otro h1 | 1.3.1 (best-practice) | Baja | Dos h1 en todas las rutas del mentor (`axe-dashboard_mentor*.json`). | Que el título del shell deje de ser un encabezado de nivel 1. |
| A11Y-PG-26 | `admin/page.tsx`, `admin/mentors` y `dashboard/mentor/challenges` | 1.3.1 (best-practice) | Baja | `heading-order`, h1 → h3. Son los consumidores que quedaban de SH-09. | Títulos de tarjeta con `as="h2"`. |
| A11Y-PG-28 | `/dashboard/mentor/progress/[id]`: «Volver a la cartera» y «Ver historial» | 2.5.8 | Baja | 20 px de alto (`small-progress-id.json`). | `py-1`, como en T-011.6a. |

### Límites por datos o permisos (no son defectos)

- **Paginación:** ninguna lista tiene más de una página (usuarios 11, sesiones 8, evaluaciones unas 11), así que `PaginationControls` sigue sin probarse en el navegador.
- **Bloqueo por plan:** el único mentee de `mentor.bienestar` tiene un plan con espacio del mentor, así que el bloqueo del mapa y del historial no se pudo ver.
- **Feedback, observaciones, check-ins, mapas sistémicos, media y entregas:** sin datos, así que esos paneles solo se vieron vacíos. Los formularios de observación, check-in y feedback se revisaron en el código; en el navegador solo se probó el de retos.

## T-011.8 — Consolidación y unidades de corrección

**Estado: completada el 2026-11-17. Las propuestas de esta sección esperan la aprobación del usuario.**

### Situación de cada hallazgo

Hay 39 IDs (PG-07 no se llegó a usar): **29 corregidos y verificados**, **8 abiertos**, 1 observación de producto y 1 sin hallazgo. Ningún hallazgo queda sin clasificar.

| ID | Prioridad | Componente o causa | Estado | Regresión |
|---|---|---|---|---|
| SH-01, SH-04 | Alta | Shells (`AppShell` y layout del admin) | Corregido (T-011.2a) | `focus-trap.test.ts` (7) y Playwright T-011.2a |
| SH-05 | Media | Layout del admin | Corregido (T-011.2a) | Playwright T-011.2a |
| SH-03 | Baja | `ui/alert.tsx` | Corregido (T-011.3a) | `alert.test.ts` |
| SH-06 | Media | `ui/button.tsx` | Corregido (T-011.4a) | `button.test.ts` y Playwright T-011.4a |
| SH-08 | Media | Clases de color | Corregido en las instancias confirmadas (T-011.3a y T-011.4a); los restos pasan a PG-27 | axe por grupo |
| SH-09 | Baja | `ui/card.tsx` (`CardTitle` siempre `h3`) | Corregido en las rutas de T-011.3 a T-011.6; los restos pasan a PG-26 | `card.test.ts` y axe |
| SH-10 | Baja | Consumidores de `Button` | Corregido (T-011.5a); barrido completo | grep y Playwright T-011.5a |
| SH-11 | Media | `lib/schemas/objective.ts` | Corregido (T-011.6a) | `objective.test.ts` |
| SH-02 | — | `DialogTrigger` sin uso | Sin hallazgo | — |
| PG-05 | Alta | `/mentors` | Corregido (T-011.3a) | Playwright T-011.3a |
| PG-06, PG-08 | Media, Baja | Mentores sin sesión y `main` anidado | Corregido (T-011.3a) | axe |
| PG-12, PG-15 | Alta | Planes del registro y test de personalidad | Corregido (T-011.4a) | Playwright T-011.4a |
| PG-02, PG-10, PG-11, PG-13, PG-14, PG-16 | Media y Baja | Auth y test | Corregido (T-011.4a) | axe y paridad i18n |
| PG-19, PG-20 | Alta | `/evaluations/[id]` | Corregido (T-011.5a) | Playwright T-011.5a |
| PG-17, PG-18, PG-21 | Media y Baja | Evaluaciones y sesiones | Corregido (T-011.5a) | axe |
| PG-03, PG-22, PG-23, PG-24 | Media y Baja | Paneles y progreso | Corregido (T-011.6a) | axe y medición de alto |
| **PG-01** | **Alta** | Selectores de filtro del admin | **Abierto** | axe `select-name` |
| **PG-04** | **Alta** | Tabla desplazable del admin | **Abierto** | axe `scrollable-region-focusable` y Tab a 320 px |
| SH-12 | Media | `lib/schemas/mentor-workspace.ts` | Abierto | Test del esquema, como en SH-11 |
| PG-27 | Media | Contraste en el progreso del mentor y en admin | Abierto | axe `color-contrast` |
| SH-07 | Baja | `ui/tabs.tsx` | Abierto | Test del manejador de teclado y Playwright |
| PG-25, PG-26, PG-28 | Baja | Shell del mentor, títulos de tarjeta y enlaces | Abierto | axe y medición de alto |
| PG-09 | Observación | `/mentors/<id inexistente>` | Pendiente de decisión de producto (RNF-011.3) | — |

**Barreras altas abiertas, en orden:** primero PG-01, porque es *critical* y está en 3 pantallas de admin, y después PG-04. Las dos son de la administración. No queda ninguna barrera alta abierta en las superficies de participante, públicas o de autenticación.

**Lagunas de verificación** que impiden declarar los criterios de finalización de la spec:
1. Estados que no se pudieron probar por falta de datos: paginación, panel de `company_admin` y de empleado, bloqueo por plan, asistencia y completar sesión, mensajes reales, entregas y feedback con datos, y las respuestas de éxito (registro, reserva, envío de evaluación y de test, restablecimiento de contraseña).
2. RNF-011.2 pide pruebas con tecnologías de asistencia, pero no se ha hecho ninguna prueba con un lector de pantalla real.
3. Las comprobaciones de Playwright y axe se ejecutan en un entorno desechable y no forman parte del repositorio ni del CI. La regresión que queda en el repositorio son los tests de Vitest de los componentes y los esquemas.

### Propuesta 1: T-011.7a, dentro de esta spec

Es una unidad de corrección como las anteriores (T-011.Xa). Cabe en una sola tarea, con la misma forma que T-011.6a:
- **PG-01:** nombre accesible en los 4 selectores de filtro, con una etiqueta visible o un `aria-label` i18n. *Verificable:* axe sin `select-name` en las 3 rutas.
- **PG-04:** contenedor desplazable enfocable, con `role="region"` y nombre. *Verificable:* a 320 px, Tab llega al contenedor y las flechas desplazan la tabla; axe sin `scrollable-region-focusable`.
- **SH-12:** claves i18n en `mentor-workspace.ts` y `validationMessage` en sus formularios. *Verificable:* un test como `objective.test.ts`, y en el navegador, errores en es y en en en el formulario de retos.
- **PG-27:** colores de los estados, las pestañas inactivas y los metadatos. *Verificable:* axe sin `color-contrast` en las 3 rutas.
- **SH-07:** Inicio y Fin en `tabs.tsx`, y `aria-controls` correcto. *Verificable:* un test del manejador y Playwright en las 2 listas de pestañas.
- **PG-25, PG-26 y PG-28:** un solo h1, títulos de tarjeta con `h2` y enlaces de 24 px o más. *Verificable:* axe y medición del alto.

### Propuesta 2: spec hija 019, «Verificación accesible con datos de auditoría y tecnologías de asistencia»

Sería una spec nueva; su borrador completo (spec, plan y tareas) se redactaría después de la aprobación. Requisitos propuestos:
- **RF-019.1:** Crear un conjunto de datos de auditoría reproducible, en una base de datos desechable y separada de desarrollo, con:
  - un `company_admin` con empleados;
  - listas con más de una página;
  - un mentee cuyo plan no incluya el espacio del mentor;
  - sesiones pasadas con la ventana de asistencia abierta;
  - conversaciones con mensajes;
  - retos con entregas y feedback.
- **RF-019.2:** Con esos datos, volver a auditar los estados que este documento marca como límite por datos, con los mismos criterios y formato.
- **RF-019.3:** Una pasada manual con NVDA + Firefox y VoiceOver + Safari por los flujos principales: login, registro, test de personalidad, reserva, evaluación, mensajes y panel de progreso. La tiene que hacer una persona, porque el agente no puede manejar un lector de pantalla. Cada flujo se registra con su resultado.
- **RF-019.4 (decisión pendiente):** convertir las comprobaciones de Playwright y axe en una suite de regresión del repositorio que se ejecute en el CI. Hay dos opciones: añadir `@playwright/test` y `@axe-core/playwright` como dependencias de desarrollo, o mantener el arnés desechable y documentado. Elegir la primera cambia la decisión de no añadir dependencias de auditoría que tomó la spec 011.
- **RF-019.5:** Decidir PG-09: un estado «no encontrado» con enlace a `/mentors`, o el error genérico actual.
- Se estima en 6 a 8 tareas, dentro del límite de 10.

**Criterio para cerrar la spec 011:** con T-011.7a hecha, no quedaría ninguna barrera alta conocida. Aun así, «implementada» solo se puede declarar cuando la spec 019 cubra las lagunas de verificación, o cuando el usuario apruebe de forma explícita dejarlas como exclusiones.

**Corrección aplicada (T-011.7a, 2026-11-17):** PG-01, PG-04, SH-12, PG-27, SH-07, PG-25, PG-26 y PG-28 están corregidas y verificadas; ver `tasks.md` T-011.7a. Con esto, de los 39 IDs quedan **37 corregidos**, 1 observación (PG-09, que pasa a la spec 019, D-2) y 1 sin hallazgo. **No queda ninguna barrera de prioridad alta conocida.** La spec 011 sigue «aprobada» hasta que la spec 019 cierre las lagunas de verificación.

## Spec 019 — Verificación con datos de auditoría y lector de pantalla

Hallazgos nuevos que aparecen durante la spec 019 (RF-019.5). Se clasifican con el mismo criterio que la spec 011.

| ID | Ubicación | WCAG | Prioridad | Evidencia | Estado |
|---|---|---|---|---|---|
| A11Y-PG-29 | `app/[locale]/page.tsx`, enlace del logo de la portada | 2.5.3 | Media | axe 4.14 (`label-content-name-mismatch`, *serious*): el `aria-label` («MIS - Mentorías…») no contenía el texto visible «M MIS». axe 4.10.3, la versión usada en la 011, no lo detectaba. | Corregido en T019.4: el monograma lleva `aria-hidden` y el `aria-label` empieza por el texto visible. Lo cubre la prueba de humo de la suite. |
| A11Y-PG-30 | `app/[locale]/admin/layout.tsx:126-134`, enlace del logo del lateral del admin | 2.5.3 | Media | axe 4.14 (`label-content-name-mismatch`, *serious*) en las 5 rutas del admin a 1280 px: el enlace muestra «MIS Admin» y el rol («Super Admin»), pero su `aria-label` solo dice «MIS Admin». A 320 px no aparece, porque el lateral está oculto. Es el mismo patrón que PG-29. | Corregido (T019.9): el `aria-label` incluye el rol y hay un espacio entre los textos visibles |
| A11Y-PG-31 | `app/[locale]/dashboard/empresa/employees/page.tsx:88` | 1.3.1 (best-practice) | Baja | axe `heading-order` a 1280 y 320 px: del h1 «Gestiona empleados» se salta a un h3 con el nombre de cada empleado (`CardTitle` por defecto). Solo se ve con datos, porque en la 011 no había empleados. | Corregido (T019.9): `CardTitle as="h2"` |
| A11Y-PG-32 | `/dashboard/personal` a 375 px, durante la carga | 2.4.7, 2.4.3 | Baja | Si se tabula mientras la página carga, el foco llega al enlace «Recomendaciones» (`page.tsx:325-331`). Mientras el navegador lo desplaza hasta la vista (con `scroll-behavior: smooth` en `globals.css:86`), terminan de cargarse los bloques de planes y sesiones de arriba, que no tienen la altura reservada, y añaden unos 1680 px. El elemento enfocado queda 2655 px por debajo del borde de la pantalla. Se reprodujo con una traza de `rect.top` y `scrollY` (`/tmp/a11y-019/T019.6/`). Solo pasa durante la carga. | Corregido (T019.9): se quita `scroll-behavior: smooth` del `html` (los skip links saltan al instante). La prueba pasó 3 de 3 veces. |
| A11Y-PG-33 | Contraste en `admin/{page,mentors,sessions,users,evaluations}`, `evaluations/[id]`, `SkillTagCatalogManager`, `ui/badge.tsx` (success) y `ui/button.tsx` (danger) | 1.4.3 | Media | La suite completa (axe 4.14 y datos de auditoría) encontró `color-contrast` en textos `gray-500`/`slate-500`, en la insignia success (`green-700`) y en el botón danger (`red-600`). Algunos nodos solo aparecen con datos o cuando termina una carga asíncrona. | Corregido (T019.9): `-500` → `-600`, success `green-800` y danger `red-700` (hover `red-800`). Afecta a todos los consumidores de `Badge` y `Button`. |
