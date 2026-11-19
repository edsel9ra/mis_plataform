import { describe, expect, it, vi } from 'vitest';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { Button } from './button';

describe('Button loading semantics', () => {
  it('does not render disabled while loading', () => {
    const html = renderToStaticMarkup(createElement(Button, { loading: true }, 'Enviar'));
    expect(html).not.toMatch(/\sdisabled(?:[=> ]|$)/);
    expect(html).toContain('aria-disabled="true"');
    expect(html).toContain('aria-busy="true"');
  });

  it('keeps an explicit disabled', () => {
    const html = renderToStaticMarkup(createElement(Button, { disabled: true }, 'Enviar'));
    expect(html).toMatch(/\sdisabled(?:[=> ]|$)/);
  });

  it('disabled wins over aria-disabled when both set', () => {
    const html = renderToStaticMarkup(createElement(Button, { disabled: true, loading: true }, 'Enviar'));
    expect(html).toMatch(/\sdisabled(?:[=> ]|$)/);
    expect(html).toContain('aria-disabled="true"');
  });
});

describe('Button loading click behavior', () => {
  it('cancels the click and does not call the consumer handler while loading', () => {
    const spy = vi.fn();
    const preventDefault = vi.fn();
    const element = Button({ loading: true, onClick: spy, children: 'x' });
    element.props.onClick({ preventDefault });
    expect(preventDefault).toHaveBeenCalledOnce();
    expect(spy).not.toHaveBeenCalled();
  });

  it('calls the consumer handler when not loading', () => {
    const spy = vi.fn();
    const element = Button({ onClick: spy, children: 'x' });
    element.props.onClick({ preventDefault: vi.fn() });
    expect(spy).toHaveBeenCalledOnce();
  });
});
