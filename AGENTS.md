# AGENTS.md — MIS (Mentorías Integrales Sistémicas)

## Fuentes de Verdad
- Preferir `Makefile`, `docker-compose.yml`, manifiestos y archivos de config por encima de la prosa del README cuando no coincidan.
- Puntos desactualizados del README/setup: Laravel no está en `localhost:8000` (ese puerto es del matching FastAPI); la contraseña del admin sembrado por defecto es `Password123!`, no `password`.
- Los contenedores leen `.env.backend`, `.env.frontend` y `.env.matching` en la raíz; editar solo `backend/.env` no cambia el entorno de Compose en runtime.

## Stack
- **Backend**: PHP 8.4 + Laravel 13 (`backend/`)
- **Frontend**: Next.js 15 + React 19 + Tailwind CSS 4 (`frontend/`)
- **Matching**: Python 3.13 + FastAPI (`matching-service/`)
- **DB**: PostgreSQL 16, Cache/Queue: Redis 7, Búsqueda: Meilisearch
- **WebSockets**: Laravel Reverb
- **Blockchain**: Polygon + IPFS (Pinata)
- **Infra**: Docker Compose

## Límites del Proyecto
- `backend/` es la API Laravel 13; las rutas están en `backend/routes/api.php` y se montan bajo `/api/v1`.
- El wiring de Laravel 13 está en `backend/bootstrap/app.php`; las tareas programadas están en `backend/routes/console.php`, **no** en `app/Console/Kernel.php`.
- `frontend/` es Next 15 App Router; las páginas viven bajo `frontend/src/app/[locale]`, con providers en `frontend/src/app/layout.tsx`.
- `matching-service/` es FastAPI montado bajo `/api/v1`; el scoring de personalidad funciona, pero `calculate`/`suggestions` devuelven **501** desde `app/services/matcher.py`.
- `blockchain/contracts/CertificateRegistry.sol` lo usan los servicios de certificados del backend con envs de IPFS/Pinata y Polygon.
- Siempre: actualizar `MEMORY.md` al terminar cada tarea.

## Arquitectura Clave
- **4 tipos de cliente** (personal, familiar, grupal, empresa) — enum `App\Enums\ClientType`, impulsa las relaciones polimórficas
- **Roles de usuario** vía `spatie/laravel-permission`: super_admin, admin, company_admin, employee, mentor, mentee
- **Auth**: tokens Sanctum en cookie HttpOnly + OAuth (Google/LinkedIn/GitHub) vía Laravel Socialite
- **i18n**: 3 locales (es, en, pt). Backend: archivos de idioma PHP. Frontend: `next-intl` con enrutamiento por subdirectorio
- **Personalidad**: test Big Five IPIP-NEO-120; las respuestas se envían a Laravel → se reenvían a FastAPI para el scoring vía `five-factor-e`
- **Relaciones de mentoría** polimórficas: `source` hace morph a User|FamilyGroup|Cohort|Company
- **Certificados**: emitidos vía IPFS (Pinata) + contrato inteligente en Polygon (`blockchain/contracts/CertificateRegistry.sol`)
- **Empleados de empresa**: sub-usuarios con `role=employee`, dashboard limitado, gestionados por el `company_admin`
- **Mentor Workspace**: observaciones neurocognitivas, check-ins emocionales, retos + submissions + feedback con archivos, mapas sistémicos y alertas de estancamiento/desenganche, con entitlements por plan

## Comandos
- Setup completo: `bash setup.sh` verifica Docker, ejecuta `make build`, `make up`, composer install, migraciones, seeders y `storage:link`. El `APP_KEY` viene del archivo de entorno de Compose (no existe un target `make key`).
- Servicios: `make build`, `make up`, `make down`, `make restart`, `make logs`, `make wait`.
- La mayoría de targets `make` usan `docker compose exec`: ejecuta `make up` antes de `make composer-install`, `make migrate`, `make test-backend`, `make npm-install`, etc.
- Backend: `make composer-install`, `make migrate`, `make seed`, `make fresh`, `make storage`, `make shell`.
- Tests backend: `make test-backend`; focalizados: `docker compose exec backend php artisan test tests/Feature/AuthTest.php` o `docker compose exec backend php artisan test --filter PersonalityTest`.
- El frontend usa `pnpm@9.15.0`; a pesar del nombre, `make npm-install` ejecuta `pnpm install --frozen-lockfile`.
- Checks de frontend: `make test-frontend` ejecuta `pnpm test` (Vitest). Focalizados: `docker compose exec frontend pnpm typecheck`; usa `docker compose exec frontend pnpm build` antes de fiarte del output de producción.
- Shells de servicios: `make backend`, `make frontend`, `make matching`; import de Scout: `make meili`.

```bash
# Setup inicial completo (genera envs, build, up, composer, migrate, seed, storage)
bash setup.sh

make build && make up        # Construir e iniciar todos los servicios
make down                    # Detener
make logs                    # Ver logs de todos los servicios
make restart                 # down + up

# Backend (dentro del contenedor vía `docker compose exec`)
make migrate                 # php artisan migrate
make seed                    # php artisan db:seed
make fresh                   # migrate:fresh --seed
make storage                 # storage:link
make queue                   # queue:work
make reverb                  # reverb:start
make shell                   # php artisan tinker
make test-backend            # php artisan test (Pest, DB mis_project_testing)

# Frontend (pnpm@9.15.0)
make frontend                # entrar al contenedor
make npm-install             # pnpm install --frozen-lockfile (¡no npm!)
make test-frontend           # pnpm test (Vitest)

# Servicio de matching
make matching                # entrar al contenedor (FastAPI con auto-reload en :8000)

# Indexación de búsqueda
make meili                   # scout:import "App\Models\User"
```

## Runtime
- Nginx expone Laravel en el puerto `80` con vhosts `api.mis-project.local` y `mis-project.local`; `app.mis-project.local` hace proxy a Next y `ws.mis-project.local` a Reverb.
- Puertos directos publicados: Next dev `localhost:3000`, FastAPI matching `127.0.0.1:8000`, Meilisearch `127.0.0.1:7700`, Mailpit UI `127.0.0.1:8025`.
- Los health endpoints difieren: Laravel en `/up`; FastAPI matching en `/api/v1/health`.
- El frontend llama a `/api/v1` relativo; `next.config.ts` reescribe `/api/*` hacia `API_INTERNAL_URL` tras quitar un `/api` final (por defecto `http://nginx`).
- Los endpoints de matching distintos de health requieren el header `X-Internal-API-Key` igual a `MATCHING_SERVICE_API_KEY` en ambos envs (backend y matching), salvo el modo debug local con claves placeholder.

## Estructura del Proyecto

```
.agents/skills/               # 27 skills de OpenCode (SKILL.md + referencias); ubicación versionada
.opencode/                    # commands/feature.md y opencode.json (declara skills: ["./.agents/skills"])
skills-lock.json              # Origen, ruta y hash de las 27 skills instaladas

backend/
├── app/Actions/              # 20 clases Action (CreateUser, CreateRelationship, etc.)
├── app/Enums/                # ClientType, UserRole, SessionStatus, etc.
├── app/Events/               # MessageSent, SessionReminder, NotificationPushed
├── app/Http/Controllers/Api/ # 38 controladores REST, todos bajo /api/v1/
├── app/Http/Requests/        # 19 clases Form Request
├── app/Http/Resources/       # 40 transformadores API Resource
├── app/Models/               # 39 modelos Eloquent (todos UUID, HasUuids, scopes añadidos)
├── app/Policies/             # 10 policies (relaciones, sesiones, media, mapas sistémicos, alertas, etc.)
├── app/Services/             # 10 servicios (Mentor*, GoogleMeet, Ipfs, Blockchain, PlanEntitlement, etc.)
├── app/Console/Commands/     # Programados: recordatorios, limpieza, sync de matches, alertas
├── config/                   # Todos los archivos de configuración (incl. mentor.php)
├── database/factories/       # 17 factories
├── database/migrations/      # 34 archivos (PKs UUID, JSONB para contenido i18n)
├── database/seeders/         # Roles+permisos, planes (6), skill tags (24), demo
└── tests/                    # Pest (Feature: 21 archivos, incl. MentorWorkspace, Security*)
                              # (Unit: Actions, Models, IpfsService, SetRequestLocale)

frontend/
└── src/
    ├── app/[locale]/         # 31 páginas: login, register/[type], dashboard/{personal,familiar,grupal,empresa,mentor}
    │                           # onboarding/test-personalidad, loading/error/not-found, sitemap, robots
    ├── components/
    │   ├── auth/             # AuthProvider (React context)
    │   ├── mentor-workspace/ # Componentes del espacio del mentor (mapas, retos, feedback, planes)
    │   └── ui/               # Componentes reutilizables (Button, Input, Card, Badge, Alert, Avatar, Spinner, Select, Tabs)
    ├── hooks/                # 7 archivos de hooks (auth, relationships, sessions, mentor-workspace, etc.)
    ├── i18n/messages/{es,en,pt}/  # Archivos de traducción JSON
    ├── lib/
    │   ├── api.ts            # ApiClient (fetch wrapper, credentials: 'include')
    │   ├── query-provider.tsx # Provider de React Query
    │   ├── query-keys.ts     # Claves de query compartidas
    │   ├── schemas/          # Esquemas Zod (auth, profile, mentor-workspace)
    │   └── utils.ts          # utilidad cn()
    └── middleware.ts          # enrutamiento de locales + guard de auth

matching-service/
└── app/
    ├── routes/               # health, personality (scoring Big Five), matching (calculate/suggestions → 501)
    └── services/             # big_five.py (five-factor-e), matcher.py (NotImplementedError pendiente de datos de Laravel)

blockchain/contracts/         # Contrato inteligente Solidity (CertificateRegistry.sol)
```

## Convenciones Clave

### Backend
- **Todos los modelos usan claves primarias UUID** (trait `HasUuids`); mantener migraciones, factories y columnas FK compatibles con UUID.
- **Roles**: se guardan en la columna `role` y en los roles de Spatie; llama a `User::syncApplicationRole()` tras cambiar roles fuera de las actions/controladores existentes.
- **Tipos de cliente**: `personal`, `familiar`, `grupal`, `empresa`; los contextos de `source` de relación son `user`, `family_group`, `cohort`, `company` respectivamente.
- **Autorización de relaciones**: reutiliza `MentorshipRelationship::userCanAccessSource()` e `involvesUser()` para sesiones, mensajes y certificados; no dupliques checks de propiedad.
- **Contenido multi-idioma**: columnas JSONB `{"es": "...", "en": "...", "pt": "..."}` en la DB; cast a `array` en los modelos.
- **Prefijo de API**: todos los endpoints bajo `/api/v1/`.
- **Validación de formularios**: siempre usar clases Form Request (nunca `$request->validate()` en línea).
- **Transformación de respuestas**: siempre usar clases API Resource para un JSON consistente.
- **Lógica de negocio**: extraer a clases Action (controladores delgados).
- **Laravel Reverb** para WebSockets (canales: `relationship.{id}`, `user.{id}`).
- **FastAPI se comunica con Laravel vía HTTP** (Laravel es la única fuente de verdad).
- **Comandos programados** en `routes/console.php`: recordatorios de sesión cada minuto, limpieza de suscripciones diaria, Scout flush/import diario, sync de puntajes de match cada hora, detección de alertas de mentor cada hora.
- **Flujo de empleado**: el company_admin invita → crea User con `role=employee` + contraseña temporal → Employee status=invited.

### Frontend
- **Enrutamiento por locale** con `next-intl`: locales es/en/pt, default `es`, `localePrefix: 'as-needed'`; usa `localePath()` para que las rutas en español queden sin prefijo.
- **Guard de auth** en `frontend/src/middleware.ts` (y `layout.tsx`), chequeando `AUTH_COOKIE_NAME`/`mis_auth_token` para los prefijos protegidos.
- **Cliente de API** en `frontend/src/lib/api.ts`: `credentials: 'include'`, timeout por defecto de 5s vía `NEXT_PUBLIC_API_TIMEOUT_MS`, y emite `auth:unauthorized` ante un 401.
- **Obtención de datos**: siempre vía hooks de `@tanstack/react-query` y el `queryClient` compartido; invalida las query keys relacionadas tras las mutaciones.
- **Formularios**: usar `react-hook-form` + esquemas Zod en `frontend/src/lib/schemas` cuando sea posible; algunos formularios admin legacy siguen usando estado local.
- **Componentes de UI**: reutiliza `frontend/src/components/ui/*`; nunca patrones Tailwind crudos en línea. Tailwind 4 se configura vía `postcss.config.mjs` con `@tailwindcss/postcss` y sin archivo de config de Tailwind aparte.
- **Diseño Responsive**: diseño limpio y responsive; cualquier pantalla nueva debe verse bien en el móvil.

## Skills

Ubicación versionada: **`.agents/skills/`** (27 skills, registradas en el `skills-lock.json` de la raíz). OpenCode las descubre automáticamente desde `.agents/skills` como fuente de compatibilidad de proyecto; además `.opencode/opencode.json` la declara explícitamente en `skills`.

### Backend y API
- `laravel-patterns` — Actions/Services/Controllers, Route Model Binding, patrones Eloquent, Query Objects, Form Requests, API Resources, eventos/jobs/colas, caché
- `laravel-specialist` — flujo principal (analizar → diseñar → implementar → testear), >85% de cobertura, plantillas de modelos/migraciones/resources/jobs/tests, Sanctum, Horizon, Livewire, Pest/PHPUnit
- `laravel-security` — autenticación, autorización, Eloquent seguro, CSRF, XSS, seguridad de API y configuración de despliegue
- `fastapi` — APIs FastAPI, modelos Pydantic, dependencias, respuestas en streaming (SSE) y servido de apps frontend

### Frontend
- `accessibility` — WCAG 2.2 (POUR: contraste 4.5:1, focus-visible, objetivo 24×24px, ARIA, skip links, live regions)
- `composition-patterns` — componentes compuestos, desacoplamiento de estado vía providers, variantes sobre props booleanas, children sobre render props, cambios de React 19
- `frontend-design` — hero como tesis, tipografía con personalidad, estructura que codifica información, motion deliberado
- `next-best-practices` (descontinuado) — convenciones de archivos, límites RSC, datos, APIs async, metadata, errores, route handlers, imágenes/fuentes
- `next-cache-components` — directivas `'use cache'`, límites de Suspense, adopción de cacheComponents (PPR, cacheLife, cacheTag, updateTag). **Requiere Next.js 16**; este repo está en Next 15 (ver nota en la skill)
- `next-upgrade` — actualizar Next.js con las guías de migración y codemods oficiales
- `react-best-practices` — 70 reglas: waterfalls, tamaño de bundle, rendimiento SSR, optimización de re-renders
- `react-hook-form` — 45 reglas: useWatch sobre watch, aislamiento de componentes controlados, resolvers, field arrays con IDs, destructuring de formState
- `seo` — robots.txt, canonicals, sitemaps, title tags (50-60 chars), meta descriptions (150-160 chars), JSON-LD, hreflang, SEO técnico/móvil
- `tailwind-css-patterns` — breakpoints responsivos, utilidades de layout, modo oscuro, inputs, cards, mobile-first
- `tanstack-query-best-practices` — fetching, caché, mutaciones y estado de servidor con TanStack Query
- `typescript-advanced-types` — genéricos, tipos condicionales, mapped types, template literals, utilidades, `unknown` sobre `any`, modo estricto
- `vitest` — testing unitario con Vitest: mocks, cobertura, filtrado y fixtures
- `zod` — 43 reglas: safeParse, z.infer, tipos branded, error.format()/flatten(), uniones discriminadas, refine vs superRefine, rendimiento
- `i18n` — **adaptada a este repo**: next-intl (es/en/pt), catálogos JSON en `src/i18n/messages/*`, `useTranslations`/`getTranslations`, interpolación ICU y `localePath()`
- `web-design-guidelines` — revisión de UI según Web Interface Guidelines (`file:line`); descarga las reglas actualizadas desde el repo de Vercel
- `websocket-engineer` — WebSockets/Socket.IO, mensajería bidireccional, escalado horizontal con Redis, presencia y salas (en este repo: Laravel Reverb)

### Infraestructura y Tooling
- `docker-patterns` — Docker/Compose, desarrollo local, seguridad de contenedores, redes, volúmenes y orquestación multi-servicio
- `github-actions-templates` — workflows de CI/CD listos para producción
- `sql-optimization` — tuning de queries, indexación, planes de ejecución, paginación y batch (MySQL/PostgreSQL/SQL Server/Oracle)
- `solidity-security` — seguridad de contratos inteligentes y patrones Solidity seguros
- `find-skills` — descubrir e instalar skills de agente cuando se necesita una capacidad nueva
- `systematic-debugging` — proceso de depuración en 4 fases (causa raíz → patrón → hipótesis → fix); úsalo ante cualquier bug, test roto o comportamiento inesperado

## Rarezas y Gotchas

- **El Dockerfile usa `pnpm`** y `make npm-install` ejecuta `pnpm install --frozen-lockfile` (a pesar del nombre del target). Si el install falla, entra al contenedor y ejecútalo manualmente con `pnpm`.
- **ESLint sí existe**: `frontend/eslint.config.mjs` (comando `pnpm lint`). No hay formateador (prettier) configurado.
- **Salida standalone del frontend** — `next.config.ts` tiene `output: 'standalone'` para Docker; el proxy reescribe `/api/*` hacia el backend.
- **Sin lockfile de pnpm commiteado** — `pnpm-lock.yaml*` en el COPY del Dockerfile (globbing, no falla si no existe).
- **Los endpoints de matching (`calculate`/`suggestions`) devuelven 501** — `matcher.py` lanza `NotImplementedError` hasta que los datos de perfil/skills/disponibilidad de Laravel se conecten vía HTTP. El scoring de personalidad sí funciona.
- **Token en cookie HttpOnly `mis_auth_token`** (ya no localStorage) — el cliente usa `credentials: 'include'`; `api.ts` solo limpia el antiguo `auth_token` de localStorage por compatibilidad.
- **La mayoría de páginas son 'use client'** (37 archivos) — aún hay pocos Server Components. Refactorizar shells de página a RSC cuando haya tiempo.
- **Skills en `.agents/skills/`** (no `.opencode/skills/`): OpenCode las autodescubre por compatibilidad de proyecto y `.opencode/opencode.json` las declara en `skills`. `npx skills add` instala directamente ahí, así que no hay que mover nada; si se añade una skill, actualizar el `skills-lock.json` de la raíz.
- **`AGENTS.md` anidados dentro de las skills**: `composition-patterns`, `react-best-practices`, `react-hook-form` y `zod` incluyen un `AGENTS.md` (guía compilada). OpenCode carga cualquier `AGENTS.md` que esté entre el archivo que lee y el workspace, así que **al explorar esas carpetas se inyecta esa guía entera en el contexto**. Es ruido esperado, no un error; evita leer esos directorios sin necesidad.
- **Adaptaciones locales vs. upstream**: `i18n` está reescrita para next-intl y algunas skills llevan notas del proyecto (laravel-specialist, next-cache-components, websocket-engineer) y enlaces corregidos. `npx skills update` restaura el contenido upstream y **sobrescribe estas adaptaciones**.
- **`skills list` muestra `Source: local`** para `composition-patterns` y `react-best-practices`: su `name` de frontmatter (`vercel-*`) no coincide con la clave del lock/directorio. El ID de OpenCode sí es el del directorio, así que funciona; es solo cosmético en el CLI.
- **Recalcular `computedHash`**: es `sha256` de `(relativePath + contenido)` de todos los archivos de la skill ordenados por ruta (ignora `.git`/`node_modules`). Tras editar una skill, actualiza su entrada en `skills-lock.json` con `node` para mantener el lock consistente.

## Prerrequisitos de Configuración (fuera del código)

Estas variables de entorno deben definirse antes del primer uso:
- `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` — OAuth + Google Meet
- `storage/app/google-service-account.json` — clave SA de la API de Google Calendar
- `PINATA_API_KEY`/`PINATA_SECRET_API_KEY` — subidas a IPFS
- `POLYGON_PRIVATE_KEY`/`POLYGON_RPC_URL`/`POLYGON_CONTRACT_ADDRESS` — blockchain de certificados
- `STRIPE_KEY`/`STRIPE_SECRET` o `MERCADOPAGO_ACCESS_TOKEN` — pagos

## Testing

- Los tests del backend usan **Pest** (`pestphp/pest-plugin-laravel`) vía `php artisan test`, con `RefreshDatabase` y Postgres `mis_project_testing`; ejecútalos dentro del contenedor backend para que `DB_HOST=postgres` resuelva. Ejecutar: `make test-backend`
  - 21 archivos de tests feature (Auth, Profile, Relationship, Session, Personality, Admin, MentorWorkspace, Security*, IntegrityConstraint, etc.)
  - Tests unit: Actions, Models, IpfsService, SetRequestLocale
  - `docker/postgres/init/01-create-testing-db.sql` crea `mis_project_testing` solo al inicializar el volumen de Postgres; recrea el volumen si esa DB falta.
  - Las llamadas HTTP externas en tests deben fakearse con `Http::fake()`; los tests de personalidad ya verifican que no haya llamadas a FastAPI con payloads inválidos.
  - Admin sembrado: `admin@mis.com` con `SUPER_ADMIN_PASSWORD` o `Password123!` por defecto.
- El frontend usa **Vitest** (`pnpm test`, target `make test-frontend`), no Playwright.
- El matching-service usa **pytest**: `make test-matching`.
- Mailpit en `:8025` para probar emails en desarrollo.
- El servicio de matching no tiene dependencia real de DB — usa puntajes aleatorios hasta que los datos de perfil de Laravel se conecten.

## Memoria
- Al empezar, lee `MEMORY.md` para conocer el estado del proyecto y las decisiones tomadas.
- Al terminar una tarea, actualízalo: estado actual, decisiones importantes (con su porqué) y errores a evitar.
- Mantenlo breve (máximo ~50 líneas): resume o elimina lo que ya no aporte.
- Si algo se convierte en una regla permanente, propón moverlo a `AGENTS.md` en lugar de dejarlo en la memoria.
- No guardes nunca datos sensibles (claves, tokens, datos personales).
