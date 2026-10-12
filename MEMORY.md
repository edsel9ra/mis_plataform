# MEMORY.md — MIS (Mentorías Integrales Sistémicas)
Memoria del proyecto entre sesiones. Máximo ~50 líneas: resume o elimina lo que ya no aporte.

## Estado actual (2026-10-11)
- Rama activa `mod_22072026`. Último commit `5fa17a7` "Resolve merge conflicts and update documentation".
- Feature grande reciente: **Mentor Workspace** (~10k líneas). Observaciones neurocognitivas, check-ins emocionales, retos + submissions + feedback con archivos, mapas sistémicos (nodos/aristas), alertas de estancamiento/desenganche y entitlements por plan.
  - Backend: controladores/servicios `Mentor*`, 4 policies nuevas, 6 migraciones `2026_08_31_*`, comando horario `app:detect-mentor-alerts`, `config/mentor.php`.
  - Frontend: páginas/hook/componentes `mentor-workspace`, schemas Zod, `lib/query-keys.ts`, tests Vitest.
- Entorno local **sin inicializar**: solo existen `.env.*.example`; `docker compose` falla por variables faltantes. Falta correr `bash setup.sh` (genera envs + build + migrate + seed).
- Tests: backend Pest (`tests/Feature` 21 archivos, incl. `MentorWorkspaceTest`, `Security*`, `IntegrityConstraintTest`; `tests/Unit`); frontend Vitest (`pnpm test`); matching pytest.
- `matching-service`: scoring de personalidad OK; `calculate`/`suggestions` devuelven **501** (`NotImplementedError`) hasta conectar datos reales de Laravel.
- `AGENTS.md`: conflicto de merge ya resuelto y unificado en español (2026-10-11).
- Skills (**27**) unificadas y versionadas en **`.agents/skills/`**; el `skills-lock.json` de la raíz lista las 27. Se eliminaron las carpetas por subproyecto `backend/.agents/skills`, `frontend/.agents/skills` y sus locks. OpenCode las descubre por compatibilidad desde `.agents/skills`; `.opencode/opencode.json` también las declara en `skills`.
- Skills nuevas (12): las 10 del batch anterior (`fastapi`, `tanstack-query-best-practices`, `vitest`, `docker-patterns`, `laravel-security`, `sql-optimization`, `github-actions-templates`, `websocket-engineer`, `solidity-security`, `i18n`) + `systematic-debugging` y `web-design-guidelines`.
- **Auditoría de APIs deprecadas con Context7 (2026-10-11)**: los tres servicios ya estaban prácticamente al día. Único cambio de código: **React 19 `forwardRef` → `ref` como prop** en 7 componentes UI (`button`, `input`, `textarea`, `select`, `label`, `card`, `components/mentor-workspace/FileUploadField`), porque la referencia de React marca `forwardRef` como `<Deprecated>` en v19. Validado con `pnpm typecheck`, `pnpm lint`, `pnpm test` (32 pasan) y `pnpm build` (OK). Backend compatible con Laravel 13 (sin `VerifyCsrfToken`, ni `Kernel.php`/`Handler.php` legacy, sin upsert con `uniqueBy` vacío), matching ya en Pydantic v2 + `lifespan`. Instaladas las deps del frontend con `corepack pnpm install --frozen-lockfile` (React 19.2.8, Next 15.5.21, next-intl 3.26.5, TS 5.9.3).
- **Revisión de skills (2026-10-11)**: contadores corregidos (25→27); `i18n` reescrita para **next-intl** (antes era LobeHub/react-i18next, no aplicaba); enlaces rotos corregidos (`accessibility`→web-quality-audit, `seo`→core-web-vitals/web-quality-audit, `react-best-practices/AGENTS.md`→`rules/*`); notas de versión del proyecto en `laravel-specialist` (Laravel 13/PHP 8.4, sin Livewire), `next-cache-components` (Next 16 vs Next 15) y `websocket-engineer` (Reverb); `computedHash` recalculado tras editar. `systematic-debugging` y `web-design-guidelines` añadidas a git y registradas. Además, 9 `computedHash` arrastraban desajuste preexistente (no coincidían ni con el contenido en HEAD: `composition-patterns`, `find-skills`, `frontend-design`, `laravel-patterns`, `next-best-practices`, `next-upgrade`, `tailwind-css-patterns`, `typescript-advanced-types`, `zod`); se recalcularon desde disco y las **27** coinciden ahora.

## Decisiones importantes (con su porqué)
- Auth Sanctum con token en cookie **HttpOnly `mis_auth_token`** (no localStorage) → el cliente API usa `credentials: 'include'`. Cierra la superficie XSS.
- i18n `next-intl`: locales es/en/pt, default es, `localePrefix: 'as-needed'`; usar `localePath()` para que español quede sin prefijo.
- Modelos con PK UUID + `HasUuids`; autorización de relaciones reutiliza `MentorshipRelationship::userCanAccessSource()`/`involvesUser()` (no duplicar checks de propiedad).
- Validación con Form Requests, respuestas con API Resources, lógica de negocio en Actions (controladores delgados).
- Uploads: nginx `client_max_body_size 128m` + `config/mentor.php` limita media (5 archivos, 25 MB, mimetypes). Motivo: fix del error de subida de archivos grandes.
- Frontend: react-query (hooks + `query-keys`), react-hook-form + Zod, componentes `components/ui/*`; pantallas nuevas deben verse bien en móvil (responsive).
- Rediseño editorial científico (2026-10-11): tokens globales en `globals.css` (verde vidriera como `primary`, arcilla como acento, neutros papel/ink, escala OCEAN refinada) + tipografías **Fraunces** (display serif) e **Instrument Sans** vía `next/font` en `layout.tsx`. **Impacta todas las páginas** (botones/cards/fondos cambian de azul a verde), aunque solo se rediseñó en profundidad la landing. Motivo: abandonar el look genérico azul+Inter.

## Errores a evitar
- Correr targets `make` antes de `bash setup.sh` y `make up`: casi todos usan `docker compose exec`.
- Confiar en puertos del README: Laravel no está en `:8000` (ese es FastAPI matching). Admin sembrado: `admin@mis.com` / `Password123!` (o `SUPER_ADMIN_PASSWORD`).
- Editar `backend/.env`: los contenedores leen `.env.backend`/`.env.frontend`/`.env.matching` en la raíz.
- `make npm-install` ejecuta **pnpm** (`pnpm@9.15.0`), no npm.
- Endpoints de matching distintos de health requieren header `X-Internal-API-Key` = `MATCHING_SERVICE_API_KEY`.
- Correr tests backend fuera del contenedor: `DB_HOST=postgres` no resuelve en el host.
- `npx skills add` instala en `.agents/skills` (ignora `-a opencode`), no en `.opencode/skills`. En este repo la ubicación versionada es `.agents/skills`, así que coinciden: no hay que mover nada tras instalar.
- El `opencode.json` **de la raíz** (no `.opencode/opencode.json`) es un archivo local que contiene una API key de Context7; está sin rastrear y **no debe commitearse**. Tampoco commitear `frontend/tsconfig.tsbuildinfo` (cache de `tsc` generado al correr `pnpm typecheck`).
