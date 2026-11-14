import { describe, expect, it } from 'vitest';

import esMessages from './messages/es/common.json';
import enMessages from './messages/en/common.json';
import ptMessages from './messages/pt/common.json';

import { resolveAgendaMessage } from '../lib/scheduling';

function flattenKeys(value: unknown, prefix = ''): string[] {
  if (value === null || typeof value !== 'object' || Array.isArray(value)) {
    return prefix ? [prefix] : [];
  }
  const entries = Object.entries(value as Record<string, unknown>);
  if (entries.length === 0) return prefix ? [prefix] : [];
  const out: string[] = [];
  for (const [key, child] of entries) {
    const next = prefix ? `${prefix}.${key}` : key;
    out.push(...flattenKeys(child, next));
  }
  return out;
}

describe('agenda catalogs parity (T010-01)', () => {
  it('has the identical key set in es, en and pt', () => {
    const esKeys = new Set(flattenKeys(esMessages));
    const enKeys = new Set(flattenKeys(enMessages));
    const ptKeys = new Set(flattenKeys(ptMessages));

    expect(enKeys.size).toBe(esKeys.size);
    expect(ptKeys.size).toBe(esKeys.size);
    for (const key of esKeys) {
      expect(enKeys.has(key)).toBe(true);
      expect(ptKeys.has(key)).toBe(true);
    }
  });

  it('resolves a missing translation via the spanish fallback, never the raw key', () => {
    const catalogs = {
      es: { calendar: { today: 'Hoy (fallback)' } },
      en: { calendar: {} },
      pt: { calendar: {} },
    };
    expect(resolveAgendaMessage('calendar.today', 'en', catalogs)).toBe('Hoy (fallback)');
    expect(resolveAgendaMessage('calendar.today', 'pt', catalogs)).toBe('Hoy (fallback)');
    expect(resolveAgendaMessage('calendar.today', 'en', catalogs)).not.toBe('calendar.today');
  });

  it('returns an empty string (never the raw key) when the key is absent everywhere', () => {
    const catalogs = { es: {}, en: {}, pt: {} };
    expect(resolveAgendaMessage('calendar.today', 'en', catalogs)).toBe('');
    expect(resolveAgendaMessage('calendar.today', 'en', catalogs)).not.toBe('calendar.today');
  });
});