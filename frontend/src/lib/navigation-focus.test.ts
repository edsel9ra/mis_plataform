import { beforeEach, describe, expect, it, vi } from 'vitest';
import { consumeNavigationFocus, requestFocusAfterNavigation } from './navigation-focus';

function stubSessionStorage() {
  const store = new Map<string, string>();
  const sessionStorage: Storage = {
    get length() {
      return store.size;
    },
    clear: () => store.clear(),
    getItem: (key: string) => store.get(key) ?? null,
    key: (index: number) => [...store.keys()][index] ?? null,
    removeItem: (key: string) => store.delete(key),
    setItem: (key: string, value: string) => store.set(key, String(value)),
  };
  vi.stubGlobal('window', { sessionStorage });
  return store;
}

describe('navigation-focus', () => {
  beforeEach(() => {
    stubSessionStorage();
  });

  it('returns false when no focus was requested', () => {
    expect(consumeNavigationFocus()).toBe(false);
  });

  it('consumes a pending focus request only once', () => {
    requestFocusAfterNavigation();

    expect(consumeNavigationFocus()).toBe(true);
    expect(consumeNavigationFocus()).toBe(false);
  });

  it('keeps the marker until it is consumed', () => {
    requestFocusAfterNavigation();
    requestFocusAfterNavigation();

    expect(consumeNavigationFocus()).toBe(true);
    expect(consumeNavigationFocus()).toBe(false);
  });

  it('fails safe when sessionStorage is unavailable', () => {
    vi.stubGlobal('window', {
      sessionStorage: {
        getItem: () => { throw new Error('denied'); },
        setItem: () => { throw new Error('denied'); },
        removeItem: () => {},
      },
    });

    expect(() => requestFocusAfterNavigation()).not.toThrow();
    expect(consumeNavigationFocus()).toBe(false);
  });
});
