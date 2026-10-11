# AGENTS.md - MIS

<<<<<<< Updated upstream
## Sources Of Truth
- Prefer `Makefile`, `docker-compose.yml`, manifests, and config files over README prose when they disagree.
- Current README/setup stale points: Laravel is not on `localhost:8000` (that port is FastAPI matching), and the seeded admin password defaults to `Password123!`, not `password`.
- Containers read root `.env.backend`, `.env.frontend`, and `.env.matching`; editing `backend/.env` alone will not change Compose runtime env.

## Project Boundaries
- `backend/` is Laravel 13 API; routes are in `backend/routes/api.php` and mount under `/api/v1`.
- Laravel 13 wiring is in `backend/bootstrap/app.php`; scheduled tasks are in `backend/routes/console.php`, not `app/Console/Kernel.php`.
- `frontend/` is Next 15 App Router; app pages live under `frontend/src/app/[locale]`, with providers in `frontend/src/app/layout.tsx`.
- `matching-service/` is FastAPI mounted under `/api/v1`; personality scoring works, but matching calculate/suggestions currently return 501 from `app/services/matcher.py`.
- `blockchain/contracts/CertificateRegistry.sol` is used by backend certificate services with IPFS/Pinata and Polygon envs.

## Commands
- Full setup: `bash setup.sh` runs Docker check, `make build`, `make up`, composer install, migrations, seeders, and storage link; `APP_KEY` comes from the Compose environment file.
- Services: `make build`, `make up`, `make down`, `make restart`, `make logs`.
- Most `make` targets use `docker compose exec`; run `make up` before `make composer-install`, `make migrate`, `make test-backend`, `make npm-install`, etc.
- Backend: `make composer-install`, `make migrate`, `make seed`, `make fresh`, `make storage`, `make shell`.
- Backend tests: `make test-backend`; focused: `docker compose exec backend php artisan test tests/Feature/AuthTest.php` or `docker compose exec backend php artisan test --filter PersonalityTest`.
- Frontend uses `pnpm@9.15.0`; despite the name, `make npm-install` runs `pnpm install`.
- Frontend checks: `make test-frontend` runs the repository's `pnpm test` script (Vitest).
- Focused frontend checks: `docker compose exec frontend pnpm typecheck`; use `docker compose exec frontend pnpm build` before relying on production output.
- Service shells: `make backend`, `make frontend`, `make matching`; Scout import: `make meili`.

## Runtime
- Nginx exposes Laravel on port `80` with vhosts `api.mis-project.local` and `mis-project.local`; `app.mis-project.local` proxies Next and `ws.mis-project.local` proxies Reverb.
- Published direct ports: Next dev `localhost:3000`, FastAPI matching `127.0.0.1:8000`, Meilisearch `127.0.0.1:7700`, Mailpit UI `127.0.0.1:8025`.
- Health endpoints differ: Laravel health is `/up`; FastAPI matching health is `/api/v1/health`.
- Frontend API calls use relative `/api/v1`; `next.config.ts` rewrites `/api/*` to `API_INTERNAL_URL` after stripping a trailing `/api` (default `http://nginx`).
- Matching non-health endpoints require `X-Internal-API-Key` equal to `MATCHING_SERVICE_API_KEY` in both backend and matching envs, except local debug mode with placeholder keys.

## Backend Conventions
- All Eloquent models use UUID primary keys; keep migrations, factories, and FK columns UUID-compatible.
- Client types are `personal`, `familiar`, `grupal`, `empresa`; relationship source contexts are `user`, `family_group`, `cohort`, `company` respectively.
- Reuse `MentorshipRelationship::userCanAccessSource()` and `involvesUser()` for relationship/session/message/certificate authorization instead of duplicating ownership checks.
- Users store role in a `role` column and in Spatie roles; call `User::syncApplicationRole()` after changing roles outside existing actions/controllers.
- Keep API endpoints thin: Form Request classes for validation, API Resources when available, Actions for business operations.
- Auth uses Sanctum bearer tokens stored in HttpOnly cookie `mis_auth_token`; frontend must use `credentials: 'include'`, not localStorage tokens.
- Auth responses set cookies and return `user`/`message`; older docs/tests may still mention a JSON `token`.
- Scheduler entries in `routes/console.php`: reminders every minute, subscription cleanup daily, Scout flush/import daily, pending match score sync hourly.

## Frontend Conventions
- Locale routing uses `next-intl` locales `es`, `en`, `pt`, default `es`, and `localePrefix: 'as-needed'`; use `localePath()` for links so Spanish routes stay unprefixed.
- Auth guarding is in `frontend/src/middleware.ts` and checks `AUTH_COOKIE_NAME`/`mis_auth_token` for protected prefixes.
- API client in `frontend/src/lib/api.ts` sets `credentials: 'include'`, has a 5s default timeout via `NEXT_PUBLIC_API_TIMEOUT_MS`, and emits `auth:unauthorized` on 401.
- Data fetching should go through `@tanstack/react-query` hooks and shared `queryClient`; invalidate related query keys after mutations.
- New validated forms should use `react-hook-form` plus Zod schemas in `frontend/src/lib/schemas` when possible; some legacy admin forms still use local state.
- Reuse `frontend/src/components/ui/*`; Tailwind 4 is wired through `postcss.config.mjs` with `@tailwindcss/postcss` and no separate Tailwind config.

## Testing Notes
- Backend tests use Pest through `php artisan test`, `RefreshDatabase`, and Postgres `mis_project_testing`; run them inside the backend container so `DB_HOST=postgres` resolves.
- `docker/postgres/init/01-create-testing-db.sql` creates `mis_project_testing` only when the Postgres volume is initialized; recreate the volume if that DB is missing.
- External HTTP calls in backend tests should be faked with `Http::fake()`; personality tests already assert no FastAPI calls on invalid payloads.
- Seeded super admin is `admin@mis.com` with `SUPER_ADMIN_PASSWORD` or default `Password123!`.
=======
## Stack
- **Backend**: PHP 8.4 + Laravel 13 (`backend/`)
- **Frontend**: Next.js 15 + Tailwind CSS 4 (`frontend/`)
- **Matching**: Python 3.13 + FastAPI (`matching-service/`)
- **DB**: PostgreSQL 16, Cache/Queue: Redis 7, Búsqueda: Meilisearch
- **WebSockets**: Laravel Reverb
- **Blockchain**: Polygon + IPFS (Pinata)
- **Infra**: Docker Compose

## Arquitectura Clave

- **4 tipos de cliente** (personal, familiar, grupal, empresa) — enum `App\Enums\ClientType`, impulsa las relaciones polimórficas
- **Roles de usuario** vía `spatie/laravel-permission`: super_admin, admin, company_admin, employee, mentor, mentee
- **Auth**: tokens Sanctum + OAuth (Google/LinkedIn/GitHub) vía Laravel Socialite
- **i18n**: 3 locales (es, en, pt). Backend: archivos de idioma PHP. Frontend: `next-intl` con enrutamiento por subdirectorio
- **Personalidad**: test Big Five IPIP-NEO-120; las respuestas se envían a Laravel → se reenvían a FastAPI para el scoring vía `five-factor-e`
- **Relaciones de mentoría** polimórficas: `source` hace morph a User|FamilyGroup|Cohort|Company
- **Certificados**: emitidos vía IPFS (Pinata) + contrato inteligente en Polygon (`blockchain/contracts/CertificateRegistry.sol`)
- **Empleados de empresa**: sub-usuarios con `role=employee`, dashboard limitado, gestionados por el `company_admin`

## Comandos Esenciales

```bash
make build && make up        # Construir e iniciar todos los servicios
make down                    # Detener
make logs                    # Ver logs de todos los servicios
make restart                 # down + up

# Backend (dentro del contenedor vía `docker compose exec`)
make migrate                 # php artisan migrate
make seed                    # php artisan db:seed
make fresh                   # migrate:fresh --seed
make key                     # Generar APP_KEY
make storage                 # storage:link
make queue                   # queue:work
make reverb                  # reverb:start
make shell                   # tinker
make test-backend            # php artisan test (Pest)

# Frontend — NOTA: el Dockerfile usa pnpm, pero `make npm-install` llama a npm.
# Si falla, entra al contenedor y ejecuta `pnpm install` en su lugar.
make frontend                # entrar al contenedor
make npm-install             # npm install

# Servicio de matching
make matching                # entrar al contenedor
# FastAPI con auto-reload en :8000

# Indexación de búsqueda
make meili                   # scout:import "App\Models\User"

# Orden de configuración inicial
make build && make up
make composer-install && make npm-install
make migrate && make seed
make key && make storage
```

## Estructura del Proyecto

```
backend/
├── app/Actions/              # 6 clases Action (CreateUser, CreateRelationship, etc.)
├── app/Enums/                # ClientType, UserRole, SessionStatus, etc.
├── app/Events/               # MessageSent, SessionReminder, NotificationPushed
├── app/Http/Controllers/Api/ # 20 controladores REST, todos bajo /api/v1/
├── app/Http/Requests/        # 6 clases Form Request (Auth, Profile, Relationship, Session, Personality)
├── app/Http/Resources/       # 16 transformadores API Resource
├── app/Models/               # 24 modelos Eloquent (todos UUID, HasUuids, scopes añadidos)
├── app/Services/             # GoogleMeetService, IpfsService, BlockchainService
├── app/Console/Commands/     # Programados: recordatorios, limpieza de suscripciones, sync de matches
├── config/                   # Todos los archivos de configuración
├── database/factories/       # 5 factories (User, PersonalityAssessment, MentorshipRelationship, Session, Certificate)
├── database/migrations/      # 19 archivos (PKs UUID, JSONB para contenido i18n)
├── database/seeders/         # Roles+permisos, planes (6), skill tags (24)
└── tests/                    # Tests Pest (Feature: Auth, Profile, Relationship, Session, Personality, Admin)
                                # (Unit: Actions + ModelScopes)

frontend/
└── src/
    ├── app/                  # Páginas: login, register/[type], dashboard/{personal,familiar,grupal,empresa},
    │                           # onboarding/test-personalidad, loading.tsx, error.tsx, not-found.tsx,
    │                           # sitemap.ts, robots.ts
    ├── components/
    │   ├── auth/             # AuthProvider (React context)
    │   └── ui/               # 7 componentes reutilizables (Button, Input, Card, Badge, Alert, Avatar, Spinner, Select)
    ├── hooks/                # 4 archivos de hooks (use-auth-mutations, use-relationships, use-sessions)
    ├── i18n/messages/{es,en,pt}/  # Archivos de traducción JSON
    ├── lib/
    │   ├── api.ts            # ApiClient (wrapper de fetch)
    │   ├── query-provider.tsx # Provider de React Query
    │   ├── schemas/          # Esquemas Zod (auth.ts, profile.ts)
    │   └── utils.ts          # utilidad cn()
    └── middleware.ts          # enrutamiento de locales de next-intl

matching-service/
└── app/
    ├── routes/               # health, personality (scoring Big Five), matching (4 algoritmos)
    └── services/             # big_five.py (five-factor-e), matcher.py (puntajes aleatorios ponderados)

blockchain/contracts/         # Contrato inteligente Solidity (CertificateRegistry.sol)
```

## Convenciones Clave

- **Todos los modelos usan claves primarias UUID** (trait `HasUuids`)
- **Relaciones polimórficas**: `MentorshipRelationship.source` hace morph a 4 tipos
- **Contenido multi-idioma**: columnas JSONB `{"es": "...", "en": "...", "pt": "..."}` en la DB; cast a `array` en los modelos
- **Laravel Reverb** para WebSockets (canales: `relationship.{id}`, `user.{id}`)
- **FastAPI se comunica con Laravel vía HTTP** (Laravel es la única fuente de verdad)
- **Auth SPA con Sanctum** — stateful según `SANCTUM_STATEFUL_DOMAINS`, basada en tokens para la API
- **Comandos programados**: recordatorios de sesión (cada minuto), limpieza de suscripciones (diario), sync de puntajes de match (cada hora)
- **Flujo de empleado**: el company_admin invita → crea User con `role=employee` + contraseña temporal → Employee status=invited
- **Prefijo de API**: todos los endpoints bajo `/api/v1/`
- **Validación de formularios**: siempre usar clases Form Request (nunca `$request->validate()` en línea)
- **Transformación de respuestas**: siempre usar clases API Resource para un JSON consistente
- **Lógica de negocio**: extraer a clases Action (controladores delgados)
- **Formularios del frontend**: siempre usar react-hook-form + Zod (nunca useState manual)
- **Obtención de datos**: siempre usar @tanstack/react-query (useQuery/useMutation)
- **Componentes de UI**: usar la librería reutilizable `src/components/ui/`; nunca patrones Tailwind crudos en línea
- **Diseño Responsive**: Diseño limpio y responsive; cualquier pantalla nueva debe verse bien en el móvil.

## Skills Cargadas (desde skills-lock.json)

### Backend (`backend/skills-lock.json`)
- `laravel-patterns` — Actions/Services/Controllers, Route Model Binding, patrones Eloquent, Query Objects, Form Requests, API Resources, eventos/jobs/colas, caché
- `laravel-specialist` — flujo de trabajo principal (analizar → diseñar → implementar → testear), >85% de cobertura, plantillas de código para modelos/migraciones/resources/jobs/tests

### Frontend (`frontend/skills-lock.json`)
- `accessibility` — WCAG 2.2 (POUR: contraste de color 4.5:1, focus-visible, tamaño de objetivo 24×24px, ARIA, skip links, live regions)
- `composition-patterns` — componentes compuestos, desacoplamiento de estado vía providers, variantes sobre props booleanas, children sobre render props, cambios de React 19 (sin forwardRef, use() en lugar de useContext)
- `frontend-design` — hero como tesis, tipografía como portadora de personalidad, estructura que codifica información, motion deliberado
- `next-best-practices` (descontinuado) — ver los docs incluidos de Next.js
- `next-cache-components` — directivas `'use cache'`, límites de Suspense, adopción de cacheComponents
- `react-best-practices` — 70 reglas: eliminar waterfalls (Promise.all/Suspense), tamaño del bundle (imports barrel/dinámicos), rendimiento SSR (React.cache(), fetch en paralelo, serialización), optimización de re-renders (memo, estado derivado, transiciones, refs)
- `react-hook-form` — 45 reglas: useWatch sobre watch, aislamiento de componentes controlados, resolvers de validación, field arrays con IDs con key, destructuring de formState
- `seo` — robots.txt, meta robots, canonicals, sitemaps, title tags (50-60 chars), meta descriptions (150-160 chars), datos estructurados JSON-LD, hreflang, SEO móvil/técnico
- `tailwind-css-patterns` — breakpoints responsivos, utilidades de layout, modo oscuro, inputs de formulario, cards, mobile-first, extraer patrones a componentes
- `typescript-advanced-types` — genéricos, tipos condicionales, mapped types, template literals, tipos utilitarios, unknown sobre any, modo estricto
- `zod` — 43 reglas: safeParse para input de usuario, z.infer para inferencia de tipos, tipos branded, error.format()/flatten(), uniones discriminadas, composición de esquemas, refine vs superRefine, rendimiento (cachear esquemas, Zod Mini)

## Rarezas y Gotchas

- **El Dockerfile usa `pnpm`** pero el target del Makefile es `make npm-install` (llama a `npm install`). Si falla, entra al contenedor y ejecuta `pnpm install`.
- **Sin configuración de eslint/prettier** en el repo — `next lint` se apoya en el ESLint integrado de Next.js. Sin formateador configurado.
- **Salida standalone del frontend** — `next.config.ts` tiene `output: 'standalone'` para Docker; el proxy reescribe `/api/*` hacia el backend.
- **Sin lockfiles commiteados** — `pnpm-lock.yaml*` en el COPY del Dockerfile (globbing, no falla si no existe).
- **Los puntajes del servicio de matching son aleatorios** — `matcher.py` usa `random.uniform()` para todos los componentes del puntaje hasta que los datos de perfil de Laravel se conecten vía HTTP.
- **Token almacenado en localStorage** (superficie XSS) — mejora futura: cambiar a auth por cookie httpOnly de Sanctum SPA.
- **Todas las páginas son 'use client'** — aún no hay Server Components. Refactorizar los shells de página a RSC cuando haya tiempo.

## Prerrequisitos de Configuración (fuera del código)

Estas variables de entorno deben definirse antes del primer uso:
- `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` — OAuth + Google Meet
- `storage/app/google-service-account.json` — clave SA de la API de Google Calendar
- `PINATA_API_KEY`/`PINATA_SECRET_API_KEY` — subidas a IPFS
- `POLYGON_PRIVATE_KEY`/`POLYGON_RPC_URL`/`POLYGON_CONTRACT_ADDRESS` — blockchain de certificados
- `STRIPE_KEY`/`STRIPE_SECRET` o `MERCADOPAGO_ACCESS_TOKEN` — pagos

## Rarezas de Testing

- Los tests del backend usan **Pest** (`pestphp/pest-plugin-laravel`). Ejecutar: `make test-backend`
  - 6 archivos de tests feature (Auth, Profile, Relationship, Session, Personality, Admin)
  - 4 archivos de tests unit (CreateUserAction, CreateRelationshipAction, CreateSessionAction, ModelScopes)
  - Usa el trait `RefreshDatabase` — los tests son seguros para CI
- El E2E del frontend usa **Playwright**. Ejecutar: `make test-frontend`
- Mailpit en `:8025` para probar emails en desarrollo
- El servicio de matching no tiene dependencia real de DB — usa puntajes aleatorios hasta que los datos de perfil de Laravel se conecten

## Memoria 
- Al empezar, lee `MEMORY.md` para conocer el estado del proyecto y las decisiones tomadas. - Al terminar una tarea, actualízalo: estado actual, decisiones importantes (con su porqué) y errores a evitar.
- Mantenlo breve (máximo ~50 líneas): resume o elimina lo que ya no aporte.
- Si algo se convierte en una regla permanente, propón moverlo a `AGENTS.md` en lugar de dejarlo en la memoria.
- No guardes nunca datos sensibles (claves, tokens, datos personales).

## Límites
- Siempre: actualizar `MEMORY.md` al terminar cada tarea. 
>>>>>>> Stashed changes
