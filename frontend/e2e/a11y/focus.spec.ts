import { expect, test, type Browser, type BrowserContext, type Page } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import fs from 'node:fs';
import path from 'node:path';
import { storageStatePath, type AccountRole } from './accounts';
import { AXE_TAGS, waitForFiniteAnimations } from './animations';

const WIDTHS = { mobile: 375, desktop: 1280, narrow: 320 };

const fixtures = JSON.parse(
  fs.readFileSync(path.join(__dirname, '..', '.auth', 'fixtures.json'), 'utf8'),
) as Record<string, string>;

const openContexts: BrowserContext[] = [];

test.afterEach(async () => {
  await Promise.all(openContexts.splice(0).map((context) => context.close()));
});

async function newPage(browser: Browser, role: AccountRole | null, width: number): Promise<Page> {
  const context = await browser.newContext({
    viewport: { width, height: 800 },
    locale: 'es',
    ...(role ? { storageState: storageStatePath(role) } : {}),
  });
  openContexts.push(context);
  return context.newPage();
}

function describeFocused(page: Page): Promise<string> {
  return page.evaluate(() => {
    const el = document.activeElement;
    if (!el) return 'nothing';
    const html = el as HTMLElement;
    return `${el.tagName.toLowerCase()}${html.id ? `#${html.id}` : ''} "${(html.getAttribute('aria-label') ?? html.textContent ?? '').trim().slice(0, 60)}"`;
  });
}

async function focusedInside(page: Page, selector: string): Promise<boolean> {
  return page.evaluate(
    (sel) => Boolean(document.querySelector(sel)?.contains(document.activeElement)),
    selector,
  );
}

async function focusedEntersViewport(page: Page): Promise<boolean> {
  return page
    .waitForFunction(
      () => {
        const el = document.activeElement as HTMLElement | null;
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0
          && rect.right > 0 && rect.bottom > 0
          && rect.left < window.innerWidth && rect.top < window.innerHeight;
      },
      undefined,
      { timeout: 3_000 },
    )
    .then(() => true)
    .catch(() => false);
}

async function waitForPage(page: Page): Promise<void> {
  await expect(page.locator('h1').first()).toBeVisible({ timeout: 20_000 });
  await expect(page.locator('div[role="status"]:has(.thinking-orb)')).toHaveCount(0);
}

// ———————————————————————————— T-011.2a · menús laterales @375

for (const [unit, role, route] of [
  ['ana', 'ana', '/dashboard/personal'],
  ['admin', 'admin', '/admin'],
] as const) {
  test(`T-011.2a · ${unit}: menú cerrado no tiene paradas de foco ocultas @375`, async ({ browser }) => {
    const page = await newPage(browser, role, WIDTHS.mobile);
    await page.goto(route);
    await waitForPage(page);

    for (let i = 0; i < 30; i += 1) {
      await page.keyboard.press('Tab');
      expect(await focusedInside(page, 'aside'), `Tab ${i + 1}: focus inside aside (${await describeFocused(page)})`).toBe(false);
      expect(await focusedEntersViewport(page), `Tab ${i + 1}: focused element never entered the viewport (${await describeFocused(page)})`).toBe(true);
    }
  });

  test(`T-011.2a · ${unit}: abrir enfoca Cerrar, Tab queda dentro, Escape devuelve foco @375`, async ({ browser }) => {
    const page = await newPage(browser, role, WIDTHS.mobile);
    await page.goto(route);
    await waitForPage(page);

    await page.getByRole('button', { name: 'Abrir menú' }).focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('button', { name: 'Cerrar' }).first(), 'focus should land on Cerrar').toBeFocused();

    for (let i = 0; i < 20; i += 1) {
      await page.keyboard.press('Tab');
      expect(await focusedInside(page, 'aside'), `Tab ${i + 1}: focus escaped the drawer (${await describeFocused(page)})`).toBe(true);
    }
    for (let i = 0; i < 20; i += 1) {
      await page.keyboard.press('Shift+Tab');
      expect(await focusedInside(page, 'aside'), `Shift+Tab ${i + 1}: focus escaped the drawer (${await describeFocused(page)})`).toBe(true);
    }

    await page.keyboard.press('Escape');
    await expect(page.getByRole('button', { name: 'Abrir menú' }), 'Escape should return focus to Abrir menú').toBeFocused();
    expect(await focusedInside(page, 'aside'), 'focus should not stay inside the closed drawer').toBe(false);
  });
}

test('T-011.2a · admin: skip link mueve el foco al contenido @1280', async ({ browser }) => {
  const page = await newPage(browser, 'admin', WIDTHS.desktop);
  await page.goto('/admin');
  await waitForPage(page);

  await page.keyboard.press('Tab');
  const skip = page.getByRole('link', { name: 'Saltar al contenido' });
  await expect(skip, `first Tab should focus the skip link (got ${await describeFocused(page)})`).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('main#app-content'), 'skip link should move focus to main#app-content').toBeFocused();
});

// ———————————————————————————— T-011.3a · búsqueda de mentores

test('T-011.3a · /mentors: la búsqueda conserva el foco y la región anuncia', async ({ browser }) => {
  const page = await newPage(browser, null, WIDTHS.desktop);
  await page.goto('/mentors');
  await waitForPage(page);

  const search = page.getByRole('searchbox').or(page.getByLabel('Buscar'));
  const status = page.locator('p[role="status"]');

  await search.focus();
  await page.keyboard.type('zzqqxx');
  await expect(status).toContainText('Sin resultados', { timeout: 15_000 });
  await expect(search, `focus left the search field (${await describeFocused(page)})`).toBeFocused();

  await search.fill(fixtures.mentorLastName);
  await expect(status).toContainText('1 mentor encontrado', { timeout: 15_000 });
  await expect(search, `focus left the search field (${await describeFocused(page)})`).toBeFocused();
});

// ———————————————————————————— T-011.4a

test('T-011.4a · login: el foco se queda en el botón tras la contraseña errónea', async ({ browser }) => {
  const page = await newPage(browser, null, WIDTHS.desktop);
  await page.goto('/login');
  await waitForPage(page);

  await page.getByLabel('Correo electrónico').or(page.getByLabel('Email')).fill('relleno.01.audit@audit.mis.test');
  await page.getByLabel('Contraseña').fill('wrong-password-000');
  await page.getByRole('button', { name: 'Iniciar Sesión' }).focus();
  await page.keyboard.press('Enter');

  await expect(page.locator('[role="alert"]')).toBeVisible({ timeout: 15_000 });
  await expect(
    page.getByRole('button', { name: 'Iniciar Sesión' }),
    `focus should stay on the submit button after the error (got ${await describeFocused(page)})`,
  ).toBeFocused();
});

test('T-011.4a · test de personalidad: flechas, navegación y foco (sin enviar)', async ({ browser }) => {
  const page = await newPage(browser, 'kai', WIDTHS.desktop);
  await page.goto('/onboarding/test-personalidad');
  await waitForPage(page);

  const radios = page.locator('input[type="radio"]');
  const progress = page.getByText(/Pregunta 1 de /);
  const next = page.getByRole('button', { name: 'Siguiente' });
  const previous = page.getByRole('button', { name: 'Anterior' });

  await expect(next, 'Siguiente should be disabled before answering').toBeDisabled();

  await radios.first().focus();
  await page.keyboard.press('ArrowDown');
  await expect(progress).toBeVisible();
  expect(await radios.evaluateAll((els) => els.filter((el) => (el as HTMLInputElement).checked).length)).toBe(1);

  await radios.first().focus();
  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('ArrowDown');
  await expect(radios.nth(2), 'ArrowDown ×2 from the first radio should select the third').toBeChecked();

  await expect(next, 'Siguiente should be enabled after answering').toBeEnabled();
  await next.focus();
  await page.keyboard.press('Enter');
  await expect(page.getByText(/Pregunta 2 de /)).toBeVisible();
  expect(await focusedInside(page, 'fieldset'), `focus should land on a radio of question 2 (got ${await describeFocused(page)})`).toBe(true);

  await previous.focus();
  await page.keyboard.press('Enter');
  await expect(page.getByText(/Pregunta 1 de /)).toBeVisible();
  const active = page.locator('input[type="radio"]:focus');
  await expect(active, 'Anterior should focus the answered radio of question 1').toBeChecked();
});

test('T-011.4a · registro: el radio de plan muestra anillo de foco', async ({ browser }) => {
  const page = await newPage(browser, null, WIDTHS.desktop);
  await page.goto('/register/personal');
  await waitForPage(page);
  await expect(page.locator('div[role="status"]:has(.thinking-orb)')).toHaveCount(0);

  const radio = page.locator('input[type="radio"]').first();
  await radio.focus();
  const boxShadow = await radio.evaluate((el) => getComputedStyle(el.closest('label')!).boxShadow);
  expect(boxShadow, 'plan label should show a focus ring (box-shadow)').not.toBe('none');
});

// ———————————————————————————— T-011.5a · evaluación

test('T-011.5a · evaluación: Enter marca, el grupo se nombra y la barra se etiqueta', async ({ browser }) => {
  const page = await newPage(browser, 'ana', WIDTHS.desktop);
  await page.goto(`/evaluations/${fixtures.evaluationOpenId}`);
  await waitForPage(page);

  const options = page.locator('button[aria-pressed]');
  await expect(options.first()).toBeVisible();
  const questionText = await page.locator('h2').first().textContent();

  await options.first().focus();
  await page.keyboard.press('Enter');
  await expect(options.first()).toHaveAttribute('aria-pressed', 'true');
  await expect(options.first(), `Enter should keep focus on the option (got ${await describeFocused(page)})`).toBeFocused();
  await expect(page.getByText(/Pregunta 1 de /), 'question should not advance').toBeVisible();

  await expect(page.locator('[role="group"]')).toHaveAccessibleName(questionText!.trim());
  await expect(page.locator('[role="progressbar"]').first()).toHaveAccessibleName(/Pregunta 1 de /);

  await page.getByRole('button', { name: 'Siguiente' }).focus();
  await page.keyboard.press('Enter');
  await expect(page.getByText(/Pregunta 2 de /)).toBeVisible();
  await expect(options.first(), `Siguiente should move focus to an option (got ${await describeFocused(page)})`).toBeFocused();

  await page.getByRole('button', { name: 'Anterior' }).focus();
  await page.keyboard.press('Enter');
  await expect(page.getByText(/Pregunta 1 de /)).toBeVisible();
  await expect(options.first(), `Anterior should move focus to the answered option (got ${await describeFocused(page)})`).toBeFocused();
  await expect(options.first()).toHaveAttribute('aria-pressed', 'true');
});

// ———————————————————————————— T-011.6a · proponer objetivo

for (const [locale, pathPrefix, errorText] of [
  ['es', '', 'El título debe tener al menos 3 caracteres.'],
  ['en', '/en', 'The title must be at least 3 characters.'],
] as const) {
  test(`T-011.6a · proponer objetivo: errores de validación en ${locale}`, async ({ browser }) => {
    const page = await newPage(browser, 'ana', WIDTHS.desktop);
    await page.goto(`${pathPrefix}/dashboard/progreso`);
    await waitForPage(page);

    await page.getByRole('button', { name: locale === 'es' ? 'Proponer objetivo' : 'Propose objective' }).click();
    await page.getByRole('button', { name: locale === 'es' ? 'Enviar propuesta' : 'Send proposal' }).click();

    await expect(page.getByText(errorText)).toBeVisible();
    const titleLabel = locale === 'es' ? /título/i : /title/i;
    await expect(
      page.getByLabel(titleLabel).first(),
      `focus should move to the title field (got ${await describeFocused(page)})`,
    ).toBeFocused();
    await page.reload();
  });
}

// ———————————————————————————— T-011.7a · tabs y región de tabla

test('T-011.7a · pestañas del mentor: Inicio y Fin seleccionan y enfocan', async ({ browser }) => {
  const page = await newPage(browser, 'mentor', WIDTHS.desktop);
  await page.goto(`/dashboard/mentor/users/${fixtures.anaId}?relationship_id=${fixtures.relationshipId}`);
  await waitForPage(page);

  const tabs = page.getByRole('tab');
  await tabs.nth(1).focus();
  await page.keyboard.press('End');
  const last = tabs.last();
  await expect(last).toBeFocused();
  await expect(last).toHaveAttribute('aria-selected', 'true');
  await page.keyboard.press('Home');
  const first = tabs.first();
  await expect(first).toBeFocused();
  await expect(first).toHaveAttribute('aria-selected', 'true');
});

test('T-011.7a · pestañas de admin: Inicio y Fin seleccionan y enfocan', async ({ browser }) => {
  const page = await newPage(browser, 'admin', WIDTHS.desktop);
  await page.goto('/admin/evaluations');
  await waitForPage(page);

  const tabs = page.getByRole('tab');
  await tabs.first().focus();
  await page.keyboard.press('End');
  const last = tabs.last();
  await expect(last).toBeFocused();
  await expect(last).toHaveAttribute('aria-selected', 'true');
  await page.keyboard.press('Home');
  const first = tabs.first();
  await expect(first).toBeFocused();
  await expect(first).toHaveAttribute('aria-selected', 'true');
});

test('T-011.7a · admin/users: la región de la tabla se alcanza y se desplaza @320', async ({ browser }) => {
  const page = await newPage(browser, 'admin', WIDTHS.narrow);
  await page.goto('/admin/users');
  await waitForPage(page);

  const region = page.getByRole('region', { name: 'Usuarios' });
  for (let i = 0; i < 40; i += 1) {
    if (await focusedInside(page, 'div[role="region"]')) break;
    await page.keyboard.press('Tab');
  }
  await expect(region, `Tab should reach the table region (got ${await describeFocused(page)})`).toBeFocused();

  const before = await region.evaluate((el) => el.scrollLeft);
  await page.keyboard.press('ArrowRight');
  await page.waitForFunction(
    (initial) => (document.activeElement as HTMLElement | null)?.scrollLeft ?? 0 > initial,
    before,
  );
  const after = await region.evaluate((el) => el.scrollLeft);
  expect(after, `ArrowRight should scroll the region (${before} -> ${after})`).toBeGreaterThan(before);
});

// ———————————————————————————— RF-019.8 · mentor no encontrado

for (const [idLabel, idPath] of [
  ['not-a-uuid', '/mentors/not-a-uuid'],
  ['uuid inexistente', '/mentors/11111111-1111-1111-1111-555555555555'],
] as const) {
  for (const [roleLabel, role] of [
    ['sin sesión', null],
    ['ana', 'ana'],
  ] as const) {
    test(`RF-019.8 · /mentors/${idLabel} (${roleLabel}): h1 y enlace de vuelta`, async ({ browser }) => {
      const page = await newPage(browser, role, WIDTHS.desktop);
      await page.goto(idPath);
      await waitForPage(page);

      await expect(page.getByRole('heading', { name: 'No encontramos a este mentor' })).toBeVisible();
      const back = page.getByRole('link', { name: 'Volver a mentores' });
      await expect(back).toBeVisible();
      await expect(back).toHaveAttribute('href', /\/mentors$/);
    });
  }
}

// ———————————————————————————— T019.9 · correcciones PG-34 a PG-39

test('T019.9 · PG-34 · mensajes: enviar conserva el foco en el redactor', async ({ browser }) => {
  const page = await newPage(browser, 'ana', WIDTHS.desktop);
  await page.goto(`/messages?relationship=${fixtures.relationshipId}`);
  await waitForPage(page);

  const composer = page.locator('#message-composer');
  await composer.fill(`Mensaje de foco T019.9 ${Date.now()}`);
  await composer.press('Enter');

  await expect(composer, `composer should stay focusable while sending (got ${await describeFocused(page)})`)
    .toBeFocused();
  await expect(composer, 'composer should be empty after the successful send').toHaveValue('');
  await expect(composer, `focus must stay in the composer (got ${await describeFocused(page)})`)
    .toBeFocused();
});

test('T019.9 · PG-35 · admin/users: paginar anuncia la página y mueve el foco', async ({ browser }) => {
  const page = await newPage(browser, 'admin', WIDTHS.desktop);
  await page.goto('/admin/users');
  await waitForPage(page);

  // Filtering to the 25 "relleno" accounts keeps the count at exactly two
  // pages even while the registration test adds users in parallel. The filter
  // is debounced and resets the page to 1 when it lands, so wait for its
  // response before clicking "Siguiente" or the late reset eats the click.
  const filtered = page.waitForResponse(
    (response) => response.url().includes('/api/v1/admin/users')
      && response.url().includes('search=relleno'),
  );
  await page.getByPlaceholder('Buscar').fill('relleno');
  await filtered;

  const status = page.getByRole('status').filter({ hasText: /Página \d+ de \d+/ });
  const next = page.getByRole('button', { name: 'Siguiente' });
  const previous = page.getByRole('button', { name: 'Anterior' });

  await expect(status).toHaveText('Página 1 de 2');
  // Wait for the debounced search refetch to settle: while a query is in
  // flight the buttons are aria-disabled and their click is blocked.
  await expect(next).not.toHaveAttribute('aria-disabled', 'true');
  await next.click();

  // Reaching the last page disables "Siguiente": focus moves to "Anterior"
  // and the status region announces the loaded page, not a stale one.
  await expect(status, 'status region should announce the loaded page').toHaveText('Página 2 de 2');
  await expect(next).toBeDisabled();
  await expect(previous, `focus should move to the other button (got ${await describeFocused(page)})`)
    .toBeFocused();
});

test('T019.9 · PG-36 · progreso: crear objetivo anuncia el éxito y devuelve el foco', async ({ browser }) => {
  const page = await newPage(browser, 'mentor', WIDTHS.desktop);
  await page.goto(`/dashboard/mentor/progress/${fixtures.anaId}?relationship_id=${fixtures.relationshipId}`);
  await waitForPage(page);

  const addButton = page.getByRole('button', { name: 'Nuevo objetivo' });
  await addButton.click();
  await page.getByLabel('Título').fill(`Objetivo de foco T019.9 ${Date.now()}`);
  await page.getByLabel('Criterio de logro').fill('Completar la auditoría de accesibilidad');
  await page.getByLabel('Fecha límite').fill('2027-12-31T10:00');
  await page.getByRole('button', { name: 'Crear objetivo' }).click();

  const status = page.locator('div[role="status"]');
  await expect(status.filter({ hasText: 'Objetivo creado' })).toHaveCount(1);
  await expect(addButton, `focus should return to "Nuevo objetivo" (got ${await describeFocused(page)})`)
    .toBeFocused();
});

test('T019.9 · PG-42 · progreso: proponer objetivo anuncia el éxito y devuelve el foco', async ({ browser }) => {
  const page = await newPage(browser, 'ana', WIDTHS.desktop);
  await page.goto('/dashboard/progreso');
  await waitForPage(page);

  const proposeButton = page.getByRole('button', { name: 'Proponer objetivo' });
  await proposeButton.click();
  await page.getByLabel('Título').fill(`Propuesta de foco T019.9 ${Date.now()}`);
  await page.getByLabel('Criterio de logro').fill('El guion del lector queda verificado');
  await page.getByLabel('Fecha límite').fill('2027-12-31T10:00');
  await page.getByRole('button', { name: 'Enviar propuesta' }).click();

  const status = page.locator('div[role="status"]');
  await expect(status.filter({ hasText: 'Propuesta enviada' }), 'the block status region should announce the proposal').toHaveCount(1);
  await expect(proposeButton, `focus should return to "Proponer objetivo" (got ${await describeFocused(page)})`)
    .toBeFocused();
});

test('T019.9 · PG-43 · mensajes: cada mensaje anuncia su autor', async ({ browser }) => {
  const page = await newPage(browser, 'ana', WIDTHS.desktop);
  await page.goto(`/messages?relationship=${fixtures.relationshipId}`);
  await waitForPage(page);

  const log = page.locator('div[role="log"]');
  const counterpart = (await page.locator('h2').first().textContent())?.trim() ?? '';
  expect(counterpart, 'the conversation header should name the counterpart').not.toBe('');

  await expect(log, 'a message from the counterpart should announce its author')
    .toContainText(`${counterpart}:`);
  await expect(log, 'an own message should announce "Tú"').toContainText('Tú:');

  const composer = page.locator('#message-composer');
  const content = `Autor T019.9 ${Date.now()}`;
  await composer.fill(content);
  await composer.press('Enter');

  await expect(log.locator('div').filter({ hasText: `Tú: ${content}` }).last(),
    'a message sent in the test should announce its author',
  ).toBeVisible();
});

test('T019.9 · reserva: los días con huecos son botones pulsables', async ({ browser }) => {
  const page = await newPage(browser, 'ana', WIDTHS.desktop);
  await page.goto('/sessions/new');
  await waitForPage(page);

  const dayButtons = page.getByRole('group', { name: '2. Elige un hueco' }).locator('button[aria-pressed]');
  await expect(dayButtons.first(), 'seeded availability should offer at least one day with slots')
    .toBeVisible({ timeout: 20_000 });
  await dayButtons.first().click();
  await expect(dayButtons.first(), 'the chosen day should announce pressed').toHaveAttribute('aria-pressed', 'true');
});

test('T019.9 · PG-37 · evaluación: el foco va al h1 del resultado', async ({ browser }) => {
  test.slow(); // answering every question of a catalog evaluation takes a while
  const page = await newPage(browser, 'ana', WIDTHS.desktop);
  await page.goto(`/evaluations/${fixtures.evaluationSubmitId}`);
  await waitForPage(page);

  const submit = page.getByRole('button', { name: 'Validar resultado' });
  const next = page.getByRole('button', { name: 'Siguiente' });
  // "Siguiente" is not rendered on the last question, so the loop must check
  // the always-present submit button instead of probing for "Siguiente".
  for (let i = 0; i < 60 && !(await submit.isEnabled()); i += 1) {
    await page.locator('button[aria-pressed]').first().click();
    if (!(await submit.isEnabled())) await next.click();
  }
  await expect(submit, 'every question should be answered before submitting').toBeEnabled();
  await submit.click();

  const heading = page.locator('#evaluation-result-heading');
  await expect(heading).toBeVisible({ timeout: 15_000 });
  await expect(heading, `result heading should be focused (got ${await describeFocused(page)})`)
    .toBeFocused();
});

test('T019.9 · PG-37 · registro: el foco llega al h1 del onboarding', async ({ browser }) => {
  const page = await newPage(browser, null, WIDTHS.desktop);
  await page.goto('/register/personal');
  await waitForPage(page);

  await page.getByLabel('Nombre').fill('Auditor');
  await page.getByLabel('Apellidos').fill('Foco');
  await page.getByLabel('Correo electrónico').fill(`registro.t0199.${Date.now()}@audit.mis.test`);
  await page.getByLabel('Contraseña', { exact: true }).fill('AuditFoco123');
  await page.getByLabel('Sexo').selectOption('N');
  await page.getByLabel('Fecha de nacimiento').fill('1990-01-15');
  await expect(page.locator('input[type="radio"]').first(), 'plans should load').toBeAttached();
  await page.locator('input[type="radio"]').first().check({ force: true });
  await page.getByRole('button', { name: 'Crear Cuenta' }).click();

  const heading = page.locator('main h1');
  await expect(heading).toBeVisible({ timeout: 20_000 });
  await expect(heading, `onboarding h1 should be focused (got ${await describeFocused(page)})`)
    .toBeFocused();
});

test('T019.9 · PG-37 · perfil: cambiar el idioma mueve el foco al h1', async ({ browser }) => {
  const page = await newPage(browser, 'relleno04', WIDTHS.desktop);
  await page.goto('/profile');
  await waitForPage(page);

  // The page URL is the Spanish /profile, so the saved locale must differ
  // from both the select's current value (so onChange fires) and 'es' (so the
  // handler actually saves and navigates instead of returning early).
  const select = page.getByLabel('Idioma');
  const current = await select.inputValue();
  const nextLocale = current === 'en' ? 'pt' : 'en';
  await select.selectOption(nextLocale);

  const heading = page.locator('main h1');
  await expect(heading).toBeVisible({ timeout: 15_000 });
  await expect(heading, `localized profile h1 should be focused (got ${await describeFocused(page)})`)
    .toBeFocused();
});

test('T019.9 · PG-37 · mentoría: solicitar enfoca el aviso de pendiente', async ({ browser }) => {
  const page = await newPage(browser, 'relleno05', WIDTHS.desktop);
  await page.goto(`/mentors/${fixtures.mentorId}`);
  await waitForPage(page);

  // A previous suite run may have left a pending relationship; delete it so
  // the request form is available again (idempotent across runs). The DELETE
  // needs the Sanctum XSRF header, so it runs inside the page.
  await page.evaluate(async (mentorId) => {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    const xsrf = document.cookie.split('; ')
      .find((cookie) => cookie.startsWith('XSRF-TOKEN='))?.split('=')[1];
    const list = await fetch('/api/v1/relationships', {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    }).then((res) => res.json());
    for (const rel of list.data ?? []) {
      if (rel.mentor?.id === mentorId && rel.status === 'pending') {
        await fetch(`/api/v1/relationships/${rel.id}`, {
          method: 'DELETE',
          headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(xsrf ?? '') },
          credentials: 'include',
        });
      }
    }
  }, fixtures.mentorId);
  await page.reload();
  await waitForPage(page);

  await page.getByRole('button', { name: 'Enviar solicitud de mentoría' }).click();

  const pending = page.locator('#mentorship-pending');
  await expect(pending).toBeVisible({ timeout: 15_000 });
  await expect(pending, `pending alert should be focused (got ${await describeFocused(page)})`)
    .toBeFocused();
});

test('T019.9 · asistencia: marcar un estado anuncia, conserva el foco y no usa solo color', async ({ browser }) => {
  const page = await newPage(browser, 'ana', WIDTHS.desktop);
  await page.goto(`/sessions/${fixtures.pastSessionId}`);
  await waitForPage(page);

  // ana marks her own row: "Asistió" or "No asistió", whichever is not
  // already pressed, so the action is idempotent across suite runs.
  const selfButtons = page.locator(`button[data-attendance-button="${fixtures.anaId}"]`);
  const attended = selfButtons.filter({ hasText: /^Asistió$/ });
  const absent = selfButtons.filter({ hasText: /^No asistió$/ });
  const target = (await attended.getAttribute('aria-pressed')) === 'true' ? absent : attended;
  const pressedText = await target.textContent();

  await target.click();

  await expect(page.locator('div[role="status"]').filter({ hasText: 'Asistencia registrada' }))
    .toHaveCount(1, { timeout: 15_000 });
  await expect(target).toHaveAttribute('aria-pressed', 'true');

  // Focus stays inside the attendance panel (on the button if it persists,
  // or on the row's name element after a refetch).
  const focusHeld = await page.waitForFunction(
    (uid) => {
      const el = document.activeElement as HTMLElement | null;
      if (!el) return false;
      return el.dataset?.attendanceButton === uid || el.dataset?.attendanceName === uid;
    },
    fixtures.anaId,
    { timeout: 15_000 },
  ).then(() => true).catch(() => false);
  expect(focusHeld, `attendance row should hold focus (got ${await describeFocused(page)})`).toBe(true);

  // The state is also conveyed by the "Estado actual:" text, not color alone.
  await expect(page.getByText(/Estado actual:/)).toContainText(pressedText!.trim());
});

// ———————————————————————————— T020.8 · pantallas nuevas de la spec 020
// Todas las pruebas dejan el estado consistente para poder repetir la suite
// sin volver a sembrar (el check-in y la evidencia se acumulan; el empleado
// invitado nuevo queda en la lista sin romper las demás pruebas).

const tinyPng = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
  'base64',
);

// — T020.8 · perfil —

test('T020.8 · perfil: guardar datos anuncia el éxito y conserva el foco', async ({ browser }) => {
  const page = await newPage(browser, 'ana', WIDTHS.desktop);
  await page.goto('/profile');
  await waitForPage(page);

  const save = page.getByRole('button', { name: 'Guardar datos' });
  await save.click();
  await expect(page.locator('[role="status"]').filter({ hasText: 'Datos guardados.' })).toHaveCount(1, { timeout: 15_000 });
  expect(await save.evaluate((el) => el === document.activeElement), `focus should stay on Guardar datos (${await describeFocused(page)})`).toBe(true);
});

test('T020.8 · perfil: un 422 del servidor enfoca la zona horaria', async ({ browser }) => {
  const page = await newPage(browser, 'ana', WIDTHS.desktop);
  await page.route('**/api/v1/profile', (route) => {
    if (route.request().method() === 'PUT') {
      void route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ errors: { timezone: ['La zona horaria no es válida.'] } }) });
    } else {
      void route.continue();
    }
  });
  await page.goto('/profile');
  await waitForPage(page);

  await page.getByRole('button', { name: 'Guardar datos' }).click();
  await expect(page.getByText('La zona horaria no es válida.')).toBeVisible({ timeout: 10_000 });
  const timezone = page.getByLabel('Zona horaria');
  expect(await timezone.evaluate((el) => el === document.activeElement), `focus should be on Zona horaria (${await describeFocused(page)})`).toBe(true);
});

test('T020.8 · perfil: el avatar sube una imagen y rechaza un .txt sin petición', async ({ browser }) => {
  const page = await newPage(browser, 'ana', WIDTHS.desktop);
  let avatarPosts = 0;
  page.on('request', (r) => { if (r.url().includes('/profile/avatar')) avatarPosts += 1; });
  await page.goto('/profile');
  await waitForPage(page);

  const fileInput = page.getByLabel('Nueva foto');
  const submit = page.getByRole('button', { name: 'Cambiar foto' });

  await fileInput.setInputFiles({ name: 'avatar-t0208.png', mimeType: 'image/png', buffer: tinyPng });
  await submit.click();
  await expect(page.locator('[role="status"]').filter({ hasText: 'Foto actualizada.' })).toHaveCount(1, { timeout: 20_000 });
  expect(await submit.evaluate((el) => el === document.activeElement), `focus should stay on Cambiar foto (${await describeFocused(page)})`).toBe(true);
  expect(avatarPosts).toBe(1);

  await fileInput.setInputFiles({ name: 'nota-t0208.txt', mimeType: 'text/plain', buffer: Buffer.from('hola') });
  avatarPosts = 0;
  await submit.click();
  await expect(page.getByText('Selecciona una imagen JPG, PNG, GIF o WebP.')).toBeVisible({ timeout: 5_000 });
  expect(avatarPosts).toBe(0);
});

// — T020.8 · check-in emocional —

const checkInCard = (page: Page) => page.locator('section').filter({ has: page.getByRole('heading', { name: '¿Cómo te sientes hoy?' }) }).first();

test('T020.8 · check-in: envío por teclado anuncia, conserva el foco y encabeza la lista', async ({ browser }) => {
  const page = await newPage(browser, 'ana', WIDTHS.desktop);
  await page.goto('/dashboard/personal');
  await waitForPage(page);

  const card = checkInCard(page);
  await expect(card).toBeVisible();

  // T020.9: la nota usa el useId de la tarjeta (sin duplicados entre tarjetas).
  const titleId = await card.getByRole('heading', { name: '¿Cómo te sientes hoy?' }).getAttribute('id');
  expect(await card.getByLabel('Nota (opcional)').getAttribute('id')).toBe(`${titleId}-note`);

  // Teclado: flechas dentro del grupo de radios eligen ánimo y estrés.
  await card.getByRole('radio', { name: 'Muy mal' }).focus();
  await page.keyboard.press('ArrowRight');
  await page.keyboard.press('ArrowRight');
  await page.keyboard.press('ArrowRight');
  await expect(card.getByRole('radio', { name: 'Bien', exact: true })).toBeChecked();
  await card.getByRole('radio', { name: 'Sin indicar' }).focus();
  await page.keyboard.press('ArrowRight');
  await page.keyboard.press('ArrowRight');
  await page.keyboard.press('ArrowRight');
  await page.keyboard.press('ArrowRight');
  await expect(card.getByRole('radio', { name: 'Alto', exact: true })).toBeChecked();

  const submit = card.getByRole('button', { name: 'Guardar check-in' });
  await submit.focus();
  await page.keyboard.press('Enter');
  await expect(card.locator('[role="status"]').filter({ hasText: 'Check-in guardado.' })).toHaveCount(1, { timeout: 15_000 });
  expect(await submit.evaluate((el) => el === document.activeElement), `focus should stay on Guardar check-in (${await describeFocused(page)})`).toBe(true);

  const newest = card.getByRole('list').first().locator('li').first();
  await expect(newest).toContainText('Ánimo: Bien');
  await expect(newest).toContainText('Estrés: Alto');
});

test('T020.8 · check-in: envío vacío muestra el error de ánimo sin petición', async ({ browser }) => {
  const page = await newPage(browser, 'ana', WIDTHS.desktop);
  let posts = 0;
  page.on('request', (r) => { if (r.url().includes('/emotional-check-ins') && r.method() === 'POST') posts += 1; });
  await page.goto('/dashboard/personal');
  await waitForPage(page);

  const card = checkInCard(page);
  await card.getByRole('button', { name: 'Guardar check-in' }).click();
  await expect(card.getByText('Elige cómo te sientes.')).toBeVisible({ timeout: 5_000 });
  expect(posts).toBe(0);
});

test('T020.8 · check-in: kai sin entitlement no ve la tarjeta ni pide sus datos', async ({ browser }) => {
  const page = await newPage(browser, 'kai', WIDTHS.desktop);
  let checkInRequests = 0;
  page.on('request', (r) => { if (r.url().includes('/emotional-check-ins')) checkInRequests += 1; });
  await page.goto('/dashboard/personal');
  await waitForPage(page);

  await expect(page.getByRole('heading', { name: '¿Cómo te sientes hoy?' })).toHaveCount(0);
  expect(checkInRequests).toBe(0);
});

// — T020.8 · evidencia de objetivo —

test('T020.8 · evidencia: adjunta un .txt, anuncia y devuelve el foco; .html es error de campo', async ({ browser }) => {
  const page = await newPage(browser, 'ana', WIDTHS.desktop);
  let evidencePosts = 0;
  page.on('request', (r) => { if (r.url().includes('/evidences') || r.url().includes('/media')) evidencePosts += 1; });
  await page.goto('/dashboard/progreso');
  await waitForPage(page);

  const objective = page.locator('li').filter({ has: page.getByRole('button', { name: 'Aportar evidencia' }) }).first();
  const addButton = objective.getByRole('button', { name: 'Aportar evidencia' });
  await addButton.click();

  const fileName = `evidencia-t0208-${Date.now() % 100000}.txt`;
  await objective.getByLabel('Nota').fill('Evidencia de la suite T020.8');
  await objective.getByLabel('Archivo (opcional)').setInputFiles({ name: fileName, mimeType: 'text/plain', buffer: Buffer.from('contenido') });
  await objective.getByRole('button', { name: 'Adjuntar evidencia' }).click();

  await expect(objective.locator('[role="status"]').filter({ hasText: 'Evidencia añadida' })).toHaveCount(1, { timeout: 20_000 });
  expect(await addButton.evaluate((el) => el === document.activeElement), `focus should return to Aportar evidencia (${await describeFocused(page)})`).toBe(true);
  await expect(objective.getByRole('link', { name: `Descargar ${fileName}` })).toBeVisible();
  expect(evidencePosts).toBeGreaterThan(0);

  await addButton.click();
  await objective.getByLabel('Nota').fill('Otra evidencia');
  await objective.getByLabel('Archivo (opcional)').setInputFiles({ name: 'mala-t0208.html', mimeType: 'text/html', buffer: Buffer.from('<p>x</p>') });
  evidencePosts = 0;
  await objective.getByRole('button', { name: 'Adjuntar evidencia' }).click();
  await expect(objective.getByText('Selecciona un archivo de un tipo admitido.')).toBeVisible({ timeout: 5_000 });
  expect(evidencePosts).toBe(0);
});

test('T020.9 · evidencia solo con nota: se acepta, anuncia y devuelve el foco', async ({ browser }) => {
  const page = await newPage(browser, 'ana', WIDTHS.desktop);
  await page.goto('/dashboard/progreso');
  await waitForPage(page);

  const objective = page.locator('li').filter({ has: page.getByRole('button', { name: 'Aportar evidencia' }) }).first();
  const addButton = objective.getByRole('button', { name: 'Aportar evidencia' });
  await addButton.click();

  // Sin fichero: RHF entrega una FileList vacía, que el esquema normaliza.
  await objective.getByLabel('Nota').fill('Evidencia solo con nota T020.9');
  await objective.getByRole('button', { name: 'Adjuntar evidencia' }).click();

  await expect(objective.locator('[role="status"]').filter({ hasText: 'Evidencia añadida' })).toHaveCount(1, { timeout: 20_000 });
  expect(await addButton.evaluate((el) => el === document.activeElement), `focus should return to Aportar evidencia (${await describeFocused(page)})`).toBe(true);
});

// — T020.8 · invitar empleado —

test('T020.8 · invitar: teclado, duplicado, éxito y axe del diálogo', async ({ browser }) => {
  const page = await newPage(browser, 'empresaAdmin', WIDTHS.desktop);
  await page.goto('/dashboard/empresa/employees');
  await waitForPage(page);

  const inviteButton = page.getByRole('button', { name: 'Invitar empleado' });
  await inviteButton.focus();
  await page.keyboard.press('Enter');
  const dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible({ timeout: 10_000 });
  await waitForFiniteAnimations(page);
  const emailInput = page.getByLabel('Correo electrónico');
  expect(await emailInput.evaluate((el) => el === document.activeElement), `focus should be in Email (${await describeFocused(page)})`).toBe(true);

  // Axe con el diálogo abierto a ambos anchos.
  for (const width of [1280, 320]) {
    await page.setViewportSize({ width, height: 800 });
    await waitForFiniteAnimations(page);
    const { violations } = await new AxeBuilder({ page }).withTags(AXE_TAGS).analyze();
    expect(violations, `axe invite dialog @${width}px: ${JSON.stringify(violations.map((v) => v.id))}`).toEqual([]);
  }
  await page.setViewportSize({ width: 1280, height: 800 });

  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden({ timeout: 5_000 });
  expect(await inviteButton.evaluate((el) => el === document.activeElement), `focus should return to Invitar empleado (${await describeFocused(page)})`).toBe(true);

  // Email duplicado: error bajo Email, foco allí y valores conservados.
  await inviteButton.click();
  await expect(dialog).toBeVisible();
  await emailInput.fill('empleado.audit@audit.mis.test');
  await page.getByLabel('Nombre').fill('Duplicado');
  await page.getByLabel('Apellidos (opcional)').fill('Audit');
  await page.getByLabel('Puesto (opcional)').fill('Guardado');
  await page.getByRole('button', { name: 'Enviar invitación' }).click();
  await expect(page.getByText('El email ya está registrado.')).toBeVisible({ timeout: 10_000 });
  expect(await emailInput.evaluate((el) => el === document.activeElement), `focus should be on Email (${await describeFocused(page)})`).toBe(true);
  expect(await page.getByLabel('Puesto (opcional)').inputValue()).toBe('Guardado');

  // Éxito con email único.
  const unique = `${Date.now() % 100000}`;
  await emailInput.fill(`invite.t0208.${unique}@audit.mis.test`);
  await page.getByRole('button', { name: 'Enviar invitación' }).click();
  await expect(dialog).toBeHidden({ timeout: 20_000 });
  await expect(page.locator('[role="status"]').filter({ hasText: `Invitación enviada a invite.t0208.${unique}@audit.mis.test.` })).toHaveCount(1, { timeout: 10_000 });
  expect(await inviteButton.evaluate((el) => el === document.activeElement), `focus should return to Invitar empleado (${await describeFocused(page)})`).toBe(true);
  const firstCard = page.locator('div.rounded-xl', { has: page.getByRole('heading') }).first();
  await expect(firstCard.locator('span.rounded-full').first()).toHaveText('Invitado');
});

// — T020.8 · editar / desactivar / activar —

const employeeCard = (page: Page, name: string) => page.locator('div.rounded-xl', { has: page.getByRole('heading', { name, exact: true }) }).first();

test('T020.8 · empleados: estados sembrados y ciclo editar/desactivar/activar con foco', async ({ browser }) => {
  const page = await newPage(browser, 'empresaAdmin', WIDTHS.desktop);
  await page.goto('/dashboard/empresa/employees');
  await waitForPage(page);

  // Estados sembrados: el invitado muestra Activar + Desactivar; el inactivo, Activar.
  const invitedCard = page.locator('div.rounded-xl').filter({ has: page.locator('span.rounded-full', { hasText: 'Invitado' }) }).first();
  const invitedName = (await invitedCard.getByRole('heading').first().innerText()).trim();
  await expect(invitedCard.getByRole('button', { name: `Activar ${invitedName}`, exact: true })).toBeVisible();
  await expect(invitedCard.getByRole('button', { name: `Desactivar ${invitedName}`, exact: true })).toBeVisible();
  const inactiveCard = page.locator('div.rounded-xl').filter({ has: page.locator('span.rounded-full', { hasText: 'Inactivo' }) }).first();
  const inactiveName = (await inactiveCard.getByRole('heading').first().innerText()).trim();
  await expect(inactiveCard.getByRole('button', { name: `Activar ${inactiveName}`, exact: true })).toBeVisible();
  await expect(inactiveCard.getByRole('button', { name: `Desactivar ${inactiveName}`, exact: true })).toHaveCount(0);

  const ROTATIVO = 'Rotativo Audit';
  const card = employeeCard(page, ROTATIVO);
  const heading = page.getByRole('heading', { name: ROTATIVO, exact: true });
  const editButton = card.getByRole('button', { name: `Editar ${ROTATIVO}`, exact: true });

  // Editar: cambia el puesto, el foco vuelve a «Editar …» y se anuncia.
  await editButton.click();
  let dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible({ timeout: 10_000 });
  await waitForFiniteAnimations(page);
  const puesto = page.getByLabel('Puesto');
  expect(await puesto.evaluate((el) => el === document.activeElement), `focus should be in Puesto (${await describeFocused(page)})`).toBe(true);
  await puesto.fill(`Rot-${Date.now() % 100000}`);
  await dialog.getByRole('button', { name: 'Guardar cambios' }).click();
  await expect(dialog).toBeHidden({ timeout: 15_000 });
  await expect(page.locator('[role="status"]').filter({ hasText: `Datos de ${ROTATIVO} actualizados.` })).toHaveCount(1, { timeout: 10_000 });
  expect(await editButton.evaluate((el) => el === document.activeElement), `focus should return to Editar (${await describeFocused(page)})`).toBe(true);

  // Desactivar: foco en «Cancelar», axe del diálogo, Escape sin petición.
  const deactivateButton = card.getByRole('button', { name: `Desactivar ${ROTATIVO}`, exact: true });
  let employeeCalls = 0;
  const countCalls = (r: { url: () => string }) => { if (r.url().includes('/api/v1/employees/')) employeeCalls += 1; };
  page.on('request', countCalls);
  await deactivateButton.click();
  dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible({ timeout: 10_000 });
  await waitForFiniteAnimations(page);
  const cancelButton = dialog.getByRole('button', { name: 'Cancelar' });
  expect(await cancelButton.evaluate((el) => el === document.activeElement), `focus should be on Cancelar (${await describeFocused(page)})`).toBe(true);
  const { violations } = await new AxeBuilder({ page }).withTags(AXE_TAGS).analyze();
  expect(violations, `axe deactivate dialog: ${JSON.stringify(violations.map((v) => v.id))}`).toEqual([]);

  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden({ timeout: 5_000 });
  expect(employeeCalls).toBe(0);
  expect(await deactivateButton.evaluate((el) => el === document.activeElement), `focus should return to Desactivar (${await describeFocused(page)})`).toBe(true);

  // Confirmar: foco en el heading del nombre y anuncio.
  await deactivateButton.click();
  await expect(dialog).toBeVisible();
  await dialog.getByRole('button', { name: 'Desactivar', exact: true }).click();
  await expect(dialog).toBeHidden({ timeout: 15_000 });
  await expect(page.locator('[role="status"]').filter({ hasText: `${ROTATIVO} desactivado.` })).toHaveCount(1, { timeout: 15_000 });
  expect(await heading.evaluate((el) => el === document.activeElement), `focus should be on the name heading (${await describeFocused(page)})`).toBe(true);

  // Activar de vuelta (termina activo → la prueba es repetible).
  const activateButton = card.getByRole('button', { name: `Activar ${ROTATIVO}`, exact: true });
  await activateButton.click();
  await expect(page.locator('[role="status"]').filter({ hasText: `${ROTATIVO} activado.` })).toHaveCount(1, { timeout: 15_000 });
  expect(await heading.evaluate((el) => el === document.activeElement), `focus should be on the name heading (${await describeFocused(page)})`).toBe(true);
  await expect(card.locator('span.rounded-full').first()).toHaveText('Activo');
});
