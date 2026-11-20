# Plan — Spec 019, verificación accesible

Estado: aprobado

Base: `spec.md` (aprobada el 2026-11-17), `specs/011-frontend-accessibility/audit.md` §T-011.8 y las unidades T-011.2a a T-011.7a de `specs/011-frontend-accessibility/tasks.md`.

## Archivos y responsabilidades

| Archivo | Responsabilidad | RF |
|---|---|---|
| `backend/database/seeders/AccessibilityAuditSeeder.php` | Genera los datos de auditoría. Ejecuta primero los seeders base (roles, planes, skill tags y catálogo de evaluaciones) y después los fixtures. | RF-019.1 |
| `backend/app/Support/AuditTargetGuard.php` | Función pura `refusal(string $environment, string $database, int $userCount): ?string`, que devuelve el motivo para negarse o `null`. | RF-019.2 |
| `backend/tests/Unit/AuditTargetGuardTest.php` | Casos de la guarda. | RF-019.2 |
| `specs/019-verificacion-accesible/fixtures.md` | Cuentas y recursos que crea el seeder (emails, rol, para qué estado sirven), sin contraseñas reales de ningún entorno compartido. | RF-019.1 y 019.3 |
| `specs/019-verificacion-accesible/guion-lector-pantalla.md` | Guion versionado para la persona que hace la prueba (D-3): preparación, pasos por flujo, anuncio esperado y tabla de resultados. | RF-019.4 |
| `frontend/src/app/[locale]/mentors/[id]/page.tsx` y `frontend/src/lib/errors.ts` | Estado «no encontrado» cuando el API responde 404, con el helper puro `isNotFoundError(error)`. | RF-019.8 |
| `frontend/src/i18n/messages/{es,en,pt}/common.json` | Claves `mentors.not_found_title`, `mentors.not_found_desc` y `mentors.back_to_list`. | RF-019.8 |
| `frontend/package.json` | Dependencias de desarrollo `@playwright/test` **1.65.0** y `@axe-core/playwright` **4.14.0**, con la versión exacta (las dos publicadas hace al menos 7 días el 2026-11-19), y el script `test:a11y`. | RF-019.6 |
| `frontend/playwright.config.ts` | Proyecto Chromium, `baseURL` desde `A11Y_BASE_URL`, `globalSetup`, reporter `list` y HTML en `playwright-report/` (en el `.gitignore`). | RF-019.6 |
| `frontend/e2e/a11y/accounts.ts` | Cuentas del seeder por rol. La contraseña llega por la variable `A11Y_PASSWORD`, nunca escrita en el código. | RF-019.6 |
| `frontend/e2e/a11y/global-setup.ts` | Inicia sesión con cada cuenta por `POST /api/v1/auth/login` y guarda un `storageState` por rol. | RF-019.6 |
| `frontend/e2e/a11y/routes.ts` | Lista declarativa `{ path, role, viewports, wait }` con las rutas corregidas en la 011. | RF-019.6 |
| `frontend/e2e/a11y/axe.spec.ts` | Una prueba por ruta y ancho, con axe y el formateo del fallo. | RF-019.6 y 019.7 |
| `frontend/e2e/a11y/focus.spec.ts` | Comprobaciones de teclado y foco de T-011.2a a T-011.7a, y de RF-019.8. | RF-019.6 y 019.7 |
| `frontend/e2e/a11y/format.ts` | Función pura `formatViolations(route, viewport, violations)` → texto con ruta, regla, impacto y selectores. Tiene un test de Vitest. | RF-019.7 |
| `frontend/vitest.config.mts`, `eslint.config.*`, `tsconfig.json` | Excluir `e2e/` de Vitest y, si hace falta, dar a ESLint y TS los tipos de Playwright. | — |
| `.github/workflows/ci.yml` | Job nuevo, `a11y`. | RF-019.6 y 019.7 |
| `specs/011-frontend-accessibility/audit.md` | Sección nueva con la re-auditoría y los resultados del lector de pantalla. | RF-019.3 a 019.5 |

## Funciones puras

- `AuditTargetGuard::refusal($environment, $database, $userCount)`:
  - motivo «production» si `$environment === 'production'`;
  - motivo «not_audit_db» si `$database` no termina en `_audit`, lo que cubre `mis_project` (desarrollo) y `mis_project_testing` (tests);
  - motivo «not_empty» si `$userCount > 0`;
  - si no, `null`.
- `isNotFoundError(error)`: `error instanceof ApiError && error.status === 404`.
- `formatViolations(route, viewport, violations)`: una línea por violación, con `regla (impacto): selector1, selector2…`, y una cabecera con la ruta y el ancho.

No dependen de «hoy». Las fechas de los fixtures (sesiones pasadas y ventana de asistencia) se calculan en el seeder con relación a `now()`, que se pasa a sus métodos privados para poder razonar sobre ellas.

## Algoritmo

```text
seeder:
  motivo := AuditTargetGuard::refusal(app()->environment(), db_name, users.count)
  si motivo: escribir el motivo en STDERR y terminar con código ≠ 0, sin escribir nada
  ejecutar los seeders base
  crear los fixtures de fixtures.md con factories y Actions reales (como el seed del benchmark de la 015),
    con contraseña = env('A11Y_SEED_PASSWORD'), obligatoria
  imprimir el resumen de recuentos

suite (pnpm test:a11y):
  globalSetup: para cada rol de accounts.ts → login → storageState/<rol>.json
  axe.spec: para cada ruta de routes.ts y cada ancho (1280 y 320):
    abrir con el storageState del rol; esperar la condición `wait` (selector del h1 o datos)
    resultado := AxeBuilder.withTags([wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa, best-practice])
    esperar violaciones = [], o fallar con formatViolations(...)
  focus.spec: una prueba por unidad corregida (lista en «Estrategia de tests»)
CI (job a11y):
  servicios postgres:16 y redis:7 → php 8.4 + composer install
  DB_DATABASE=mis_project_audit → migrate → db:seed --class=AccessibilityAuditSeeder
  php artisan serve :8000 en segundo plano; esperar /up
  pnpm install → build con API_INTERNAL_URL=http://127.0.0.1:8000 → next start :3000
  playwright install --with-deps chromium → pnpm test:a11y
  si falla: subir playwright-report como artifact
```

## Interfaz

- Variables:
  - `A11Y_SEED_PASSWORD` (seeder) y `A11Y_PASSWORD` (suite): en el CI, un valor fijo de prueba, porque la base de datos es efímera; en local, la que elija quien lo ejecuta;
  - `A11Y_BASE_URL`: por defecto `http://localhost:3000`.
- Comando local: `pnpm test:a11y`, contra una base de datos `mis_project_audit` y un build de producción, siguiendo las reglas de memoria de RNF-019.2. El procedimiento va en `fixtures.md`.
- RF-019.8, «no encontrado»: un h1 con `mentors.not_found_title`, un párrafo con `mentors.not_found_desc` y un `Link` a `/mentors` con `mentors.back_to_list`, dentro del `frame()` de la página (`main` sin sesión, `AppShell` con sesión). Los demás errores conservan el `Alert` genérico, con h1 y enlace.

## Decisiones

1. **Seeder de Laravel, en lugar de un script suelto como el del benchmark de la 015.** Queda en el autoload, se ejecuta con `artisan db:seed` igual en local y en el CI, y reutiliza las factories. Alternativa descartada: un script PHP en `specs/`, porque el CI tendría que montarlo a mano.
2. **La guarda exige el sufijo `_audit` y una base de datos vacía**, en lugar de comparar con una lista negra de nombres. Una lista negra deja pasar cualquier base de datos nueva o renombrada. Alternativa descartada: comparar solo con `mis_project` y `mis_project_testing`.
3. **axe con `best-practice` además de los tags WCAG.** Muchas correcciones de la 011 fueron reglas de buenas prácticas (`heading-order`, `region`, `landmark-one-main`, `page-has-heading-one`), y la spec pide que la suite falle si se reintroducen. Alternativa descartada: solo los tags WCAG, que no detectarían esas regresiones.
4. **axe 4.14 (la versión de `@axe-core/playwright`) en lugar de la 4.10.3 de la auditoría.** Es la versión vigente que cumple la regla de los 7 días. Las violaciones nuevas que aparezcan con ella se tratan como hallazgos (RF-019.5) en T019.9, no se silencian. Alternativa descartada: fijar axe 4.10.3, que es antigua y no es la que trae el paquete.
5. **Una sola configuración regional en la suite (es), y en/pt solo en las comprobaciones de textos traducidos.** Limita el tiempo de CI. En la 011, los fallos por idioma eran de textos y no de estructura, y esos se cubren con las pruebas de `focus.spec` y con Vitest (paridad de claves). Alternativa descartada: los 3 idiomas en todas las rutas, que triplica el tiempo.
6. **Build de producción, no servidor de desarrollo.** Así se audita lo que se despliega y se evitan los tiempos de compilación de dev vistos en la 011. Alternativa descartada: `next dev`.
7. **Login por API en `globalSetup`.** Es un login por cuenta y ejecución, que no satura el throttle `auth-login`. Alternativa descartada: login por la interfaz en cada test, más lento y expuesto al rate limit.

**Hipótesis que verifica T019.4:**
- con `API_INTERNAL_URL=http://127.0.0.1:8000`, la reescritura de `next.config.ts` sirve `/api/v1` y `/sanctum`;
- la cookie `mis_auth_token` se guarda para `localhost` con `SESSION_DOMAIN` sin definir y `SANCTUM_STATEFUL_DOMAINS=localhost:3000`.

Si alguna falla, T019.4 se detiene y se informa antes de cambiar la configuración del backend.

## Estrategia de tests

- **Backend (Pest):** `AuditTargetGuardTest` cubre production, sin sufijo, `mis_project`, `mis_project_testing`, `_audit` con usuarios y `_audit` vacía. La ejecución del seeder se comprueba en T019.1 contra `mis_project_audit`, con recuentos por fixture.
- **Frontend (Vitest):** `isNotFoundError` y `formatViolations`.
- **Suite de Playwright** (`focus.spec`), un caso por unidad corregida:
  - **T-011.2a:** a 375 px, menú cerrado sin paradas de foco ocultas; al abrirlo, el foco va a «Cerrar»; Tab no sale del menú; Escape devuelve el foco. Skip link del admin.
  - **T-011.3a:** en `/mentors`, el foco sigue en la búsqueda tras escribir, y la región `status` anuncia el resultado.
  - **T-011.4a:** el login con contraseña errónea deja el foco en el botón (un solo intento). En el test de personalidad, las flechas no avanzan y la opción 3 se puede elegir con el teclado (sin enviar). Anillo de foco en los planes del registro.
  - **T-011.5a:** en la evaluación, Enter marca la opción sin avanzar, y «Siguiente» y «Anterior» mueven el foco.
  - **T-011.6a:** los errores de «Proponer objetivo» salen en es y en en.
  - **T-011.7a:** Inicio y Fin en las pestañas; a 320 px, la tabla de usuarios se alcanza con Tab y se desplaza.
  - **RF-019.8:** un id inexistente y uno mal formado muestran el h1 «no encontrado» y el enlace.
- **Demostración de RF-019.7:** en T019.6 y T019.7 se revierte en local una corrección (por ejemplo, el `aria-label` de un filtro del admin o el `disabled` de `Button`), se comprueba que la suite falla con la ruta, la regla y el selector, y se restaura. El experimento no se sube.

## Riesgos

- **Tiempo del job de CI:** unas 30 rutas por 2 anchos. Si pasa de unos 10 minutos, se reparte con `--shard` o se limita el ancho de 320 px a las rutas con correcciones de reflow.
- **Pruebas inestables** por los tiempos del API. Cada ruta espera su propia condición de carga, y se usa `retries: 1` en el CI y 0 en local. Un fallo que solo pase con reintento se registra.
- **La prueba con lector de pantalla depende de otra persona (D-3):** T019.10 queda bloqueada hasta que llegue su resultado, y la spec no puede cerrarse antes.
