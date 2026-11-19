import { describe, expect, it } from 'vitest';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';

import { Alert } from './alert';

describe('Alert roles', () => {
  it('renders role="alert" for the error variant', () => {
    const html = renderToStaticMarkup(createElement(Alert, { variant: 'error' }, 'fallo'));
    expect(html).toContain('role="alert"');
  });

  it.each(['info', 'success', 'warning'] as const)('renders role="status" for %s', (variant) => {
    const html = renderToStaticMarkup(createElement(Alert, { variant }, 'aviso'));
    expect(html).toContain('role="status"');
    expect(html).not.toContain('role="alert"');
  });

  it('keeps an explicit role over the variant default', () => {
    const html = renderToStaticMarkup(createElement(Alert, { variant: 'info', role: 'alert' }, 'aviso'));
    expect(html).toContain('role="alert"');
    expect(html).not.toContain('role="status"');
  });
});
