# AGENTS.md — MIS (Mentorías Integrales Sistémicas)

## Stack
- **Backend**: PHP 8.4 + Laravel 13 (`backend/`)
- **Frontend**: Next.js 15 + Tailwind CSS 4 (`frontend/`)
- **Matching**: Python 3.13 + FastAPI (`matching-service/`)
- **DB**: PostgreSQL 16, Cache/Queue: Redis 7, Search: Meilisearch
- **WebSockets**: Laravel Reverb
- **Blockchain**: Polygon + IPFS (Pinata)
- **Infra**: Docker Compose

## Key Architecture

- **4 client types** (personal, familiar, grupal, empresa) — enum `App\Enums\ClientType`, drives polymorphic relationships
- **User roles** via `spatie/laravel-permission`: super_admin, admin, company_admin, employee, mentor, mentee
- **Auth**: Sanctum tokens + OAuth (Google/LinkedIn/GitHub) via Laravel Socialite
- **i18n**: 3 locales (es, en, pt). Backend: PHP lang files. Frontend: `next-intl` with subdirectory routing
- **Personality**: IPIP-NEO-120 Big Five test; answers submitted to Laravel → forwarded to FastAPI for scoring via `five-factor-e`
- **Mentorship relationships** are polymorphic: `source` morphs to User|FamilyGroup|Cohort|Company
- **Certificates**: issued via IPFS (Pinata) + Polygon smart contract (`blockchain/contracts/CertificateRegistry.sol`)
- **Company employees**: sub-users with `role=employee`, limited dashboard, managed by `company_admin`

## Essential Commands

```bash
make build && make up        # Build & start all services
make down                    # Stop
make logs                    # Tail all logs
make restart                 # down + up

# Backend (inside container via `docker compose exec`)
make migrate                 # php artisan migrate
make seed                    # php artisan db:seed
make fresh                   # migrate:fresh --seed
make key                     # Generate APP_KEY
make storage                 # storage:link
make queue                   # queue:work
make reverb                  # reverb:start
make shell                   # tinker
make test-backend            # php artisan test (Pest)

# Frontend — NOTE: Dockerfile uses pnpm, but `make npm-install` calls npm.
# If that fails, exec into container and run `pnpm install` instead.
make frontend                # exec into container
make npm-install             # npm install

# Matching service
make matching                # exec into container
# FastAPI auto-reload on :8000

# Search indexing
make meili                   # scout:import "App\Models\User"

# First-time setup order
make build && make up
make composer-install && make npm-install
make migrate && make seed
make key && make storage
```

## Project Layout

```
backend/
├── app/Actions/              # 6 Action classes (CreateUser, CreateRelationship, etc.)
├── app/Enums/                # ClientType, UserRole, SessionStatus, etc.
├── app/Events/               # MessageSent, SessionReminder, NotificationPushed
├── app/Http/Controllers/Api/ # 20 REST controllers, all under /api/v1/
├── app/Http/Requests/        # 6 Form Request classes (Auth, Profile, Relationship, Session, Personality)
├── app/Http/Resources/       # 16 API Resource transformers
├── app/Models/               # 24 Eloquent models (all UUID, HasUuids, scopes added)
├── app/Services/             # GoogleMeetService, IpfsService, BlockchainService
├── app/Console/Commands/     # Scheduled: reminders, subscription cleanup, match sync
├── config/                   # All config files
├── database/factories/       # 5 factories (User, PersonalityAssessment, MentorshipRelationship, Session, Certificate)
├── database/migrations/      # 19 files (UUID PKs, JSONB for i18n content)
├── database/seeders/         # Roles+permissions, plans (6), skill tags (24)
└── tests/                    # Pest tests (Feature: Auth, Profile, Relationship, Session, Personality, Admin)
                                # (Unit: Actions + ModelScopes)

frontend/
└── src/
    ├── app/                  # Pages: login, register/[type], dashboard/{personal,familiar,grupal,empresa},
    │                           # onboarding/test-personalidad, loading.tsx, error.tsx, not-found.tsx,
    │                           # sitemap.ts, robots.ts
    ├── components/
    │   ├── auth/             # AuthProvider (React context)
    │   └── ui/               # 7 reusable components (Button, Input, Card, Badge, Alert, Avatar, Spinner, Select)
    ├── hooks/                # 4 hook files (use-auth-mutations, use-relationships, use-sessions)
    ├── i18n/messages/{es,en,pt}/  # JSON translation files
    ├── lib/
    │   ├── api.ts            # ApiClient (fetch wrapper)
    │   ├── query-provider.tsx # React Query provider
    │   ├── schemas/          # Zod schemas (auth.ts, profile.ts)
    │   └── utils.ts          # cn() utility
    └── middleware.ts          # next-intl locale routing

matching-service/
└── app/
    ├── routes/               # health, personality (Big Five scoring), matching (4 algorithms)
    └── services/             # big_five.py (five-factor-e), matcher.py (weighted random scores)

blockchain/contracts/         # Solidity smart contract (CertificateRegistry.sol)
```

## Key Conventions

- **All models use UUID primary keys** (`HasUuids` trait)
- **Polymorphic relations**: `MentorshipRelationship.source` morphs to 4 types
- **Multi-language content**: JSONB columns `{"es": "...", "en": "...", "pt": "..."}` in DB; cast to `array` in models
- **Laravel Reverb** for WebSockets (channels: `relationship.{id}`, `user.{id}`)
- **FastAPI communicates with Laravel via HTTP** (Laravel is single source of truth)
- **Sanctum SPA auth** — stateful on `SANCTUM_STATEFUL_DOMAINS`, token-based for API
- **Scheduled commands**: session reminders (every min), subscription cleanup (daily), match score sync (hourly)
- **Employee flow**: company_admin invites → creates User with `role=employee` + temp password → Employee status=invited
- **API prefix**: all endpoints under `/api/v1/`
- **Form validation**: always use Form Request classes (never inline `$request->validate()`)
- **Response transformation**: always use API Resource classes for consistent JSON shape
- **Business logic**: extract into Action classes (thin controllers)
- **Frontend forms**: always use react-hook-form + Zod (never manual useState)
- **Data fetching**: always use @tanstack/react-query (useQuery/useMutation)
- **UI components**: use the reusable `src/components/ui/` library; never inline raw Tailwind patterns

## Loaded Skills (from skills-lock.json)

### Backend (`backend/skills-lock.json`)
- `laravel-patterns` — Actions/Services/Controllers, Route Model Binding, Eloquent patterns, Query Objects, Form Requests, API Resources, events/jobs/queues, caching
- `laravel-specialist` — core workflow (analyze → design → implement → test), >85% coverage, code templates for models/migrations/resources/jobs/tests

### Frontend (`frontend/skills-lock.json`)
- `accessibility` — WCAG 2.2 (POUR: color contrast 4.5:1, focus-visible, target size 24×24px, ARIA, skip links, live regions)
- `composition-patterns` — compound components, state decoupling via providers, variant over boolean prop, children over render props, React 19 changes (no forwardRef, use() over useContext)
- `frontend-design` — hero as thesis, typography as personality carrier, structure encoding information, deliberate motion
- `next-best-practices` (discontinued) — see Next.js bundled docs
- `next-cache-components` — `'use cache'` directives, Suspense boundaries, cacheComponents adoption
- `react-best-practices` — 70 rules: eliminate waterfalls (Promise.all/Suspense), bundle size (barrel/dynamic imports), SSR performance (React.cache(), parallel fetch, serialization), re-render optimization (memo, derived state, transitions, refs)
- `react-hook-form` — 45 rules: useWatch over watch, controlled component isolation, validation resolvers, field arrays with keyed IDs, formState destructuring
- `seo` — robots.txt, meta robots, canonicals, sitemaps, title tags (50-60 chars), meta descriptions (150-160 chars), JSON-LD structured data, hreflang, mobile/technical SEO
- `tailwind-css-patterns` — responsive breakpoints, layout utilities, dark mode, form inputs, cards, mobile-first, extract patterns into components
- `typescript-advanced-types` — generics, conditional types, mapped types, template literals, utility types, unknown over any, strict mode
- `zod` — 43 rules: safeParse for user input, z.infer for type inference, branded types, error.format()/flatten(), discriminated unions, schema composition, refine vs superRefine, performance (cache schemas, Zod Mini)

## Quirks & Gotchas

- **Dockerfile uses `pnpm`** but Makefile target is `make npm-install` (calls `npm install`). If it fails, exec into container and run `pnpm install`.
- **No eslint/prettier config** in repo — `next lint` relies on Next.js built-in ESLint. No formatter configured.
- **Frontend standalone output** — `next.config.ts` has `output: 'standalone'` for Docker; proxy rewrites `/api/*` to backend.
- **No lockfiles committed** — `pnpm-lock.yaml*` in Dockerfile COPY (globbing, won't fail if missing).
- **Matching service scores are random** — `matcher.py` uses `random.uniform()` for all score components until Laravel profile data is wired in via HTTP.
- **Token stored in localStorage** (XSS surface) — future improvement: switch to httpOnly Sanctum SPA cookie auth.
- **All pages are 'use client'** — no Server Components yet. Refactor page shells to RSC when time allows.

## Setup Prerequisites (not in code)

These env vars must be set before first use:
- `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` — OAuth + Google Meet
- `storage/app/google-service-account.json` — Google Calendar API SA key
- `PINATA_API_KEY`/`PINATA_SECRET_API_KEY` — IPFS uploads
- `POLYGON_PRIVATE_KEY`/`POLYGON_RPC_URL`/`POLYGON_CONTRACT_ADDRESS` — certificate blockchain
- `STRIPE_KEY`/`STRIPE_SECRET` or `MERCADOPAGO_ACCESS_TOKEN` — payments

## Testing Quirks

- Backend tests use **Pest** (`pestphp/pest-plugin-laravel`). Run: `make test-backend`
  - 6 feature test files (Auth, Profile, Relationship, Session, Personality, Admin)
  - 4 unit test files (CreateUserAction, CreateRelationshipAction, CreateSessionAction, ModelScopes)
  - Uses `RefreshDatabase` trait — tests are safe for CI
- Frontend E2E uses **Playwright**. Run: `make test-frontend`
- Mailpit on `:8025` for email testing in dev
- Matching service has no real DB dependency — uses random scoring until Laravel profile data is linked
