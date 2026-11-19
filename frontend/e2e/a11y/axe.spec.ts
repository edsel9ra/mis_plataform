import { expect, test } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import fs from 'node:fs';
import path from 'node:path';
import { ROUTES } from './routes';
import { storageStatePath } from './accounts';
import { formatViolations } from './format';

const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];
const WIDTHS = [1280, 320];

interface Fixtures {
  [key: string]: string;
}

const fixtures = JSON.parse(
  fs.readFileSync(path.join(__dirname, '..', '.auth', 'fixtures.json'), 'utf8'),
) as Fixtures;

function resolvePath(raw: string): string {
  return raw.replace(/\{(\w+)\}/g, (_, key: string) => {
    const value = fixtures[key];
    if (!value) throw new Error(`Missing fixture "${key}" in e2e/.auth/fixtures.json`);
    return value;
  });
}

for (const route of ROUTES) {
  for (const width of WIDTHS) {
    test(`${route.name} @ ${width}px`, async ({ browser }) => {
      const context = await browser.newContext({
        viewport: { width, height: 800 },
        // The audit app is Spanish-first; en-US would make next-intl redirect / -> /en.
        locale: 'es',
        ...(route.role ? { storageState: storageStatePath(route.role) } : {}),
      });
      try {
        const page = await context.newPage();
        const resolved = resolvePath(route.path);
        await page.goto(resolved);

        const expectedPathname = resolved.split(/[?#]/)[0];
        await page.waitForURL((url) => url.pathname === expectedPathname, { timeout: 15_000 }).catch(() => {});
        expect(
          new URL(page.url()).pathname,
          `${route.name}: expected to stay on ${expectedPathname} but landed on ${new URL(page.url()).pathname}`,
        ).toBe(expectedPathname);

        await expect(page.locator('h1').first(), `${route.name}: no visible h1`).toBeVisible();
        if (route.wait) {
          await expect(page.locator(route.wait).first()).toBeVisible();
        }
        // Loading regions (PageSpinner and the in-panel loaders) all render a
        // decorative ThinkingOrb inside a role="status" div; the sr-only live
        // regions in AppShell do not contain orbs.
        await expect(page.locator('div[role="status"]:has(.thinking-orb)')).toHaveCount(0);

        // Finite entrance animations (fade-in, slide-up, …) blend colors while
        // running; audit the settled state. Infinite loops (orbs, drift) are
        // allowed to keep running.
        await page.waitForFunction(() => Array.from(document.getAnimations())
          .every((animation) => animation.playState === 'finished'
            || animation.playState === 'idle'
            || (animation instanceof CSSAnimation
              && animation.effect?.getComputedTiming().iterations === Infinity)));

        const { violations } = await new AxeBuilder({ page }).withTags(AXE_TAGS).analyze();
        expect(violations, formatViolations(route.name, width, violations)).toEqual([]);
      } finally {
        await context.close();
      }
    });
  }
}
