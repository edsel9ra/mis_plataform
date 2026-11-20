import { describe, expect, it } from 'vitest';

import { trapFocusTarget } from './focus-trap';

const list = ['a', 'b', 'c'];

describe('trapFocusTarget', () => {
  it('returns null for an empty focusable list', () => {
    expect(trapFocusTarget([], 'a', false)).toBeNull();
    expect(trapFocusTarget([], 'a', true)).toBeNull();
    expect(trapFocusTarget([], null, false)).toBeNull();
  });

  it('wraps a single element to itself in both directions', () => {
    expect(trapFocusTarget(['a'], 'a', false)).toBe('a');
    expect(trapFocusTarget(['a'], 'a', true)).toBe('a');
  });

  it('wraps forward from the last element to the first', () => {
    expect(trapFocusTarget(list, 'c', false)).toBe('a');
  });

  it('wraps backward from the first element to the last', () => {
    expect(trapFocusTarget(list, 'a', true)).toBe('c');
  });

  it('lets the browser move focus from middle and non-wrap edges', () => {
    expect(trapFocusTarget(list, 'b', false)).toBeNull();
    expect(trapFocusTarget(list, 'b', true)).toBeNull();
    expect(trapFocusTarget(list, 'a', false)).toBeNull();
    expect(trapFocusTarget(list, 'c', true)).toBeNull();
  });

  it('enters at first or last when the active element is outside the list', () => {
    expect(trapFocusTarget(list, 'x', false)).toBe('a');
    expect(trapFocusTarget(list, 'x', true)).toBe('c');
  });

  it('enters at first or last when nothing is focused', () => {
    expect(trapFocusTarget(list, null, false)).toBe('a');
    expect(trapFocusTarget(list, null, true)).toBe('c');
  });
});
