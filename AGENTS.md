# AGENTS.md - MIS

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
- Full setup: `bash setup.sh` runs Docker check, `make build`, `make up`, composer install, key generation, migrations, seeders, and storage link.
- Services: `make build`, `make up`, `make down`, `make restart`, `make logs`.
- Most `make` targets use `docker compose exec`; run `make up` before `make composer-install`, `make migrate`, `make test-backend`, `make npm-install`, etc.
- Backend: `make composer-install`, `make key`, `make migrate`, `make seed`, `make fresh`, `make storage`, `make shell`.
- Backend tests: `make test-backend`; focused: `docker compose exec backend php artisan test tests/Feature/AuthTest.php` or `docker compose exec backend php artisan test --filter PersonalityTest`.
- Frontend uses `pnpm@9.15.0`; despite the name, `make npm-install` runs `pnpm install`.
- Frontend checks: `make test-frontend` runs Playwright only if `playwright.config.*` exists; otherwise it runs `pnpm typecheck`.
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
