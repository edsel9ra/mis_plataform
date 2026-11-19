# Tareas — Spec 019, verificación accesible

Estado: aprobado

Plan: `plan.md`. En orden de dependencia. Ninguna tarea toca la base de datos de desarrollo (RNF-019.1).

- [x] **T019.1. Seeder de datos de auditoría con su guarda.** RF-019.1, RF-019.2
  - Resultado (2026-11-19):
    - Código: `AuditTargetGuard` con 6 tests, en rojo primero; `AccessibilityAuditSeeder` (5 seeders base, sin los demo); `fixtures.md` con las cuentas y el procedimiento.
    - Datos en `mis_project_audit`: 36 usuarios (25 de relleno), 2 empleados, 3 relaciones, 4 sesiones (una con la ventana de asistencia abierta y otra pasada sin completar, a −26 h, dentro del plazo para completarla), 6 mensajes, un reto con entrega y feedback, una observación, un check-in, un mapa sistémico, 2 objetivos con evidencia y un vínculo de competencia.
    - No se creó la bio del mentor: `users` no tiene columna `bio` y `MentorResource.bio` siempre es `null`.
    - Negativas, con código de salida 1 y sin escribir nada: base sin sufijo, `mis_project_testing`, `_audit` con usuarios y falta de `A11Y_SEED_PASSWORD`.
    - BD de desarrollo idéntica antes y después.
    - `php artisan test`: 1174 en verde y 1 fallo inestable ajeno a la tarea, `ProgressHistoryIndexesTest` (EXPLAIN de `challenges_mentee_id_index`), que pasa si se ejecuta solo.
  - `AuditTargetGuard` (función pura) con `AuditTargetGuardTest` en rojo primero. `AccessibilityAuditSeeder` con los fixtures del RF-019.1, documentados en `fixtures.md` junto con el procedimiento local.
  - Hecho cuando:
    - el test de la guarda pasa;
    - con `DB_DATABASE=mis_project_audit` y la base vacía, el seeder crea cada fixture (con recuentos por tipo en el reporte);
    - si se ejecuta contra una base vacía sin el sufijo `_audit` (una base de prueba desechable), contra `mis_project_testing` o contra una `_audit` con usuarios, termina con un error sin escribir nada. El caso `mis_project`, la base de desarrollo, lo cubre solo el test unitario: no se ejecuta el seeder contra ella;
    - la BD de desarrollo no cambia (recuentos antes y después).

- [x] **T019.2. Guion de lector de pantalla.** RF-019.4, RNF-019.3
  - Resultado (2026-11-19): `guion-lector-pantalla.md` v1.0, con los 7 flujos, las 2 combinaciones, la tabla de teclas, el orden de las acciones que solo se pueden hacer una vez (el test de `kai` solo se envía en la combinación B) y la tabla de resultados. Los textos esperados se comprobaron contra el catálogo es y el código.
  - Falta que el usuario lo entregue a la persona del equipo, junto con la URL del entorno de auditoría y la contraseña de prueba.
  - `guion-lector-pantalla.md`:
    - cómo levantar el entorno de auditoría;
    - las cuentas de `fixtures.md`;
    - para cada flujo principal, los pasos con teclas de NVDA y de VoiceOver y lo que debería anunciarse en es;
    - una tabla de resultados por flujo y combinación (resultado, problema y WCAG).
  - Hecho cuando: el guion cubre los 7 flujos en las 2 combinaciones y se entrega a la persona del equipo.

- [x] **T019.3. Estado «no encontrado» del mentor.** RF-019.8, RNF-019.3
  - Resultado (2026-11-19):
    - Código: `isNotFoundError` en `lib/errors.ts`, con 4 tests en rojo primero. En `mentors/[id]`, un 404 o la ausencia de mentor muestran el h1 «No encontramos a este mentor», su descripción y «Volver a mentores» (28 px); cualquier otro error muestra el h1 «Mentores», el `Alert` y el mismo enlace, todo dentro de `frame()`.
    - 3 claves nuevas en es/en/pt.
    - Vitest 390/390; typecheck y lint limpios.
    - La comprobación en el navegador pasa a T019.6.
  - `isNotFoundError` con test en rojo primero, la rama 404 en `mentors/[id]` y las 3 claves en es/en/pt.
  - Hecho cuando: Vitest, typecheck y lint pasan, y la paridad i18n sigue en verde. La comprobación en el navegador queda cubierta por T019.6.

- [x] **T019.4. Infraestructura de Playwright y axe.** RF-019.6
  - Resultado (2026-11-19):
    - Dependencias de desarrollo `@playwright/test` 1.65.0 y `@axe-core/playwright` 4.14.0, exactas. El lockfile solo cambia por ellas y por las claves de peers de `next` y `next-intl`.
    - Código: `playwright.config.ts`; en `e2e/a11y/`, `accounts.ts`, `global-setup.ts` (CSRF de Sanctum y luego login), `format.ts` (con 4 tests de Vitest, escritos junto con la implementación, no en rojo antes) y `smoke.spec.ts` (3 pruebas).
    - Vitest excluye `e2e/**/*.spec.ts`. El `.gitignore` cubre el reporte, los resultados, `.auth` y `backend/storage/app/private/`, donde el seeder escribe el fichero del `MediaAsset`.
    - Hipótesis verificadas, sin cambiar el backend: la reescritura sirve `/sanctum` y `/api/v1`, y `mis_auth_token` es una cookie del host del frontend, sin `Domain`.
    - El limitador de login es de 5 por minuto por IP y email, así que no afecta.
    - `pnpm test:a11y` 3/3; Vitest 394/394; typecheck, lint y `pnpm audit --prod` limpios. BD de desarrollo sin cambios.
    - `fixtures.md` tiene ahora (a) la ejecución local y (b) el montaje en otro equipo; esto último solo se pudo reproducir aquí con variables de entorno. El guion remite a (b).
    - `php artisan serve` no pasa las variables de entorno de `docker exec -e` a su proceso hijo, así que se usa `php -S` con el router de Laravel.
    - axe 4.14 encontró un hallazgo nuevo, A11Y-PG-29 (logo de la portada, `label-content-name-mismatch`, 2.5.3), que se corrigió aquí. Se registra en T019.9.
  - Pendiente para T019.5: documentar el runner con la imagen `mcr.microsoft.com/playwright:v1.65.0-noble`, igual a la versión de `@playwright/test`. En local se usó la v1.64.1, que ya estaba descargada.
  - Dependencias con versión exacta, `playwright.config.ts`, `accounts.ts`, `global-setup.ts`, `format.ts` (con test de Vitest) y el script `test:a11y`. Excluir `e2e/` de Vitest.
  - Verificar las hipótesis del plan (reescritura de la API y cookie en `localhost`).
  - Decisión del usuario (2026-11-19): la persona del lector de pantalla monta el entorno de auditoría **en su propio equipo**. `fixtures.md` debe incluir el procedimiento completo para levantar el stack contra `mis_project_audit` en otro equipo (Docker Desktop, `.env.backend` con `DB_DATABASE=mis_project_audit`, build de producción del frontend), y el guion debe enlazarlo.
  - Hecho cuando:
    - `pnpm test:a11y` ejecuta una prueba de humo (`/` y `/login`) contra el entorno de auditoría local y pasa;
    - Vitest, lint, typecheck y `pnpm audit --prod` siguen como antes;
    - el lockfile solo cambia por las dos dependencias y sus transitivas.

- [x] **T019.5. Suite de axe sobre las rutas corregidas.** RF-019.6, RF-019.7
  - Resultado (2026-11-19):
    - `routes.ts` con 58 rutas de 10 roles; `axe.spec.ts` las prueba a 1280 y 320 px, es decir, 116 pruebas.
    - `global-setup.ts` resuelve los IDs por API (logins, `/relationships`, `/sessions` y `/evaluations`) y los guarda en `e2e/.auth/fixtures.json`.
    - Si una ruta redirige, la prueba falla. El contexto usa `locale: 'es'`, porque el navegador del runner manda `Accept-Language: en-US`.
    - `smoke.spec.ts` se borró: la matriz ya lo cubre.
    - Runner documentado con `mcr.microsoft.com/playwright:v1.65.0-noble`.
    - **109 de 116 pasan, en 18,2 min.** Los 7 fallos son 2 hallazgos nuevos, que pasan a T019.9 sin exclusiones: A11Y-PG-30 (5 rutas del admin a 1280 px) y PG-31 (`/dashboard/empresa/employees` en los 2 anchos).
    - Ninguna redirección inesperada. Las dos BD, sin cambios, incluidos los tokens de `mis_project_audit`.
    - Riesgo para T019.7: con 18 min supera el umbral de unos 10 min que fija el plan, así que el job usará `--shard`.
  - `routes.ts` con todas las rutas que la 011 corrigió o comprobó, cada una con su rol, y `axe.spec.ts` a 1280 y 320 px.
  - Hecho cuando: la suite pasa en local con los datos de auditoría. Si axe 4.14 trae violaciones nuevas, se registran en T019.9 y no se excluyen sin aprobación.

- [x] **T019.6. Comprobaciones de foco y teclado.** RF-019.6, RF-019.7, RF-019.8
  - Resultado (2026-11-19):
    - `focus.spec.ts` tiene 19 pruebas: menús a 375 px con ana y admin, el skip link del admin, la búsqueda de mentores, el login fallido, el test de personalidad, el anillo de foco de los planes, la evaluación, los errores del objetivo en es y en en, Inicio y Fin en las pestañas, el desplazamiento de la tabla a 320 px y RF-019.8 (2 rutas en 2 contextos). No envía nada.
    - **Pasan 18 de 19, en unos 48 s.** El fallo es un defecto real, A11Y-PG-32, que pasa a T019.9.
    - Demostración de RF-019.7, con el build de producción:
      - al quitar el `aria-label` de un filtro, la prueba de axe de `/admin/users @ 1280px` falla con `select-name (critical): #_r_1_`;
      - al volver a poner `disabled={disabled || loading}`, la prueba del login falla con el foco en `body`;
      - al restaurar (hashes `sha256` correctos y `git diff` igual que antes), las dos vuelven a pasar.
      - Evidencia en `/tmp/a11y-019/T019.6/`.
    - Las dos BD, sin cambios.
  - `focus.spec.ts` con los casos de «Estrategia de tests» del plan.
  - Hecho cuando:
    - la suite pasa en local;
    - revertir en local una corrección (`aria-label` de un filtro del admin y, por separado, el `disabled` de `Button`) hace fallar la suite con la ruta, la regla o la comprobación y el selector. La reversión no se sube.

- [x] **T019.7. Job de CI `a11y`.** RF-019.6, RF-019.7
  - Resultado (2026-11-19):
    - Job `Accessibility regression (N/3)` en `ci.yml`, validado en local con actionlint 1.7.12.
    - El seed usa una contraseña aleatoria en cada ejecución (`openssl rand`), sin ningún literal en el workflow.
    - Los navegadores se cachean por versión de Playwright, y si el job falla se suben el reporte y los logs (`upload-artifact` v4.6.2, fijado por SHA).
    - `playwright.config.ts` con `fullyParallel: true`, para que `--shard` reparta por prueba (45 por shard) y no por fichero.
    - **En el PR #8, cada shard pasa sus 45 pruebas en unos 40 s, y el job entero tarda de 2,5 a 3,5 min.**
    - En la primera ejecución falló la prueba de búsqueda de mentores, porque usaba un apellido fijo y el seeder los genera con faker. Ahora `global-setup` toma el apellido real del mentor sembrado.
    - **Prueba de que el job falla:** en la rama `tmp/a11y-ci-negative`, quitar el `aria-label` del filtro de rol de `/admin/users` hizo fallar el shard 3/3 con «admin-users @ 1280px: 1 violation(s) - select-name (critical): #_r_1_» (run 44532669113).
    - Falta borrar esa rama, pendiente de la confirmación del usuario.
  - Job en `.github/workflows/ci.yml` según el plan, con el reporte como artifact si falla.
  - Hecho cuando:
    - el job pasa en el PR;
    - se anota su duración;
    - en una rama temporal, un cambio que reintroduce una violación hace fallar el job. Después se borra la rama, previa confirmación del usuario.

- [x] **T019.8. Re-auditoría de los estados bloqueados por datos.** RF-019.3
  - Resultado (2026-11-19): registrado en `audit.md`, sección de la spec 019.
    - Cumplen o cumplen en parte 17 estados; queda sin probar la asistencia, porque los fixtures no la permiten.
    - Hallazgos nuevos para T019.9: PG-34 (alta: el campo de mensajes pierde el foco al enviar) y, de prioridad media, PG-35 a PG-39 (paginación silenciosa, éxitos de progreso y de redirección sin foco ni anuncio, contraste en mensajes y 429 sin explicación).
    - Hay 4 funciones que no existen en la interfaz y no son defectos de accesibilidad: invitar empleados, el check-in del mentee, adjuntar ficheros a una evidencia y editar los datos personales.
    - `mis_project` quedó idéntica. `mis_project_audit` se volvió a sembrar limpia.
  - Con el entorno de auditoría local y Playwright, recorrer cada estado marcado como límite o «sin probar» en `audit.md` §T-011.4 a §T-011.7, incluidas las respuestas de éxito.
  - Hecho cuando: cada estado tiene un resultado registrado en `audit.md` (sección de la 019) o un motivo concreto por el que no se pudo probar, y los hallazgos nuevos tienen ID, prioridad y corrección propuesta.

- [ ] **T019.9. Corregir los hallazgos nuevos.** RF-019.5
  - Avance (2026-11-19, a petición del usuario, antes de T019.7):
    - Corregidos PG-30, PG-31, PG-32 y PG-33, este último encontrado al ejecutar la suite completa.
    - Para evitar falsos positivos de contraste a mitad de `animate-fade-in`, `axe.spec.ts` espera ahora a que terminen las animaciones no infinitas. Es un cambio solo de la prueba.
    - Vitest 394/394. La suite completa pasa 135/135 (2 ejecuciones, 7,4 min). BD sin cambios.
    - Corregidos también PG-34 a PG-39 y PG-40, este último encontrado durante la tarea.
    - El seeder permite ahora marcar la asistencia (las altas se fechan antes de `end_at`) y crea 4 evaluaciones (1 respondida y 3 sin responder).
    - Helper `lib/navigation-focus.ts` (con tests) y `NavigationFocus` en `app/layout.tsx`.
    - `focus.spec` sube a 27 pruebas: la suite tiene 143 y pasa entera dos veces seguidas sin volver a sembrar (6,4 y 6,3 min). Vitest 400/400.
    - `playwright.config.ts` acepta `A11Y_EXPECT_TIMEOUT_MS` y `A11Y_TEST_TIMEOUT_MS`, para cuando el equipo local va muy cargado; sin ellas, el comportamiento es el mismo.
    - PG-41, corregido después: `keepPreviousData` en los 8 consumidores de `PaginationControls`. Vitest 400/400.
    - Queda abierta solo por T019.10.
    - Siguen pendientes los hallazgos que salgan de T019.10.
  - Los de T019.5, T019.8 y, cuando llegue, T019.10, según RF-011.8, y la suite se amplía para cubrirlos.
  - Si hay más de lo que cabe en una tarea, o alguno pide decisiones de producto, se para y se propone una unidad aparte.
  - Hecho cuando: no queda ninguna barrera alta abierta y la suite y el CI pasan.

- [ ] **T019.10. Resultados del lector de pantalla y cierre.** RF-019.4, RF-019.5
  - Registrar en `audit.md` los resultados que entregue la persona del equipo y clasificar los problemas (los que requieran código vuelven a T019.9).
  - Hecho cuando:
    - los 7 flujos tienen resultado en las 2 combinaciones;
    - no queda ninguna barrera alta;
    - las specs 011 y 019 pasan a «implementada» y `MEMORY.md` está actualizado.
  - **Bloqueada** hasta recibir los resultados de D-3.
