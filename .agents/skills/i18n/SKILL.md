---
name: i18n
description: Use for user-facing strings, translations and locale routing in this project. Covers next-intl (locales es/en/pt, JSON message catalogs), adding keys, interpolation/ICU, server vs client components and localePath(). Use when adding or editing UI text, translations or localized links.
---

# MIS Internationalization (next-intl)

This project uses **next-intl** (Next.js App Router), **not** react-i18next. The
three locales are `es` (default), `en` and `pt`.

## Source of truth

| Concern | File |
|---------|------|
| Locales / default / prefix policy | `frontend/src/i18n/routing.ts` (`defineRouting`) |
| Loads messages per request | `frontend/src/i18n/request.ts` (`getRequestConfig`) |
| Message catalogs | `frontend/src/i18n/messages/{es,en,pt}/common.json` |
| Locale-aware link helper | `frontend/src/i18n/paths.ts` (`localePath`) |
| Middleware (intl + auth guard) | `frontend/src/middleware.ts` |
| Provider + server helpers | `frontend/src/app/layout.tsx` (`NextIntlClientProvider`, `getMessages`, `getLocale`, `getTranslations`) |

Key facts:

- `locales: ['es', 'en', 'pt']`, `defaultLocale: 'es'`, `localePrefix: 'as-needed'` → **Spanish routes have no prefix**, English/Portuguese do (`/en/...`, `/pt/...`).
- There is **one namespace** (`common`), stored as a **single nested JSON object** per locale. Keys are nested paths like `"nav": { "home": "Inicio" }`, not flat dot keys.
- `request.ts` loads `./messages/${locale}/common.json`; `layout.tsx` passes it to `NextIntlClientProvider` and applies `getLocale()` to `<html lang>`.

## Adding or changing a string

1. Add the key to **all three** catalogs (`es`, `en`, `pt`) at the same nested path — keep them in parity.
2. Use it in the component:
   - Client component (`'use client'`): `const t = useTranslations();` then `t('nav.home')`.
   - Server component: `const t = await getTranslations();` then `t('nav.home')`.
3. Interpolate with ICU placeholders:
   ```json
   { "admin": { "active_mentees_count": "{count} mentees activos" } }
   ```
   ```tsx
   t('admin.active_mentees_count', { count: mentor.active_mentees_count })
   ```
4. For links/hrefs, never build localized URLs by hand — use `localePath()`:
   ```tsx
   import { localePath } from '@/i18n/paths';
   href={localePath('/dashboard', locale)} // '/dashboard' (es) | '/en/dashboard'
   ```
5. Verify: `docker compose exec frontend pnpm typecheck` and `docker compose exec frontend pnpm test` (Vitest covers `src/i18n/paths.test.ts`).

## Conventions

- **Never hardcode user-facing text** in JSX, `title`, `aria-label`, etc. — always route through `t()`.
- Keep the three `common.json` files structurally identical (same keys, same nesting).
- Naming: lowercase `snake_case` segments inside groups, e.g. `mentor_workspace.plan_not_entitled`, `personality.improvement_score`.
- Use ICU syntax for plurals/select when needed; keep messages simple and testable.
- **Backend i18n is separate**: Laravel stores multi-language content as JSONB `{"es","en","pt"}` and has PHP language files (see `AGENTS.md`). Do not put frontend strings there, nor backend strings in `common.json`.

## Anti-patterns (signs you copied the wrong skill/framework)

- `useTranslation` / `t('common:save')` namespaces — that is **react-i18next**, not this project.
- `bun run i18n`, `lobe-i18n`, `packages/locales/src/default/*` — not this project.
- Adding a key to only one locale, or using flat dot keys (`"nav.home"`) instead of nested objects.
- Hardcoding `/en/...` prefixes instead of `localePath()` (breaks the `as-needed` policy).
