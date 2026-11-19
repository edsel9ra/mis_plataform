import { describe, expect, it } from 'vitest';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';

import { CardTitle } from './card';

describe('CardTitle heading level', () => {
  it('defaults to an h3', () => {
    const html = renderToStaticMarkup(createElement(CardTitle, null, 'Título'));
    expect(html).toMatch(/^<h3[\s>]/);
  });

  it.each(['h1', 'h2'] as const)('renders an <%s> when as is set', (as) => {
    const html = renderToStaticMarkup(createElement(CardTitle, { as }, 'Título'));
    expect(html).toMatch(new RegExp(`^<${as}[\\s>]`));
  });

  it('preserves the title classes with a custom level', () => {
    const html = renderToStaticMarkup(createElement(CardTitle, { as: 'h2' }, 'Título'));
    expect(html).toContain('text-lg font-semibold text-gray-900');
  });
});
