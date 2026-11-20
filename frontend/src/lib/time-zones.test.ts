import { describe, expect, it } from 'vitest';
import { canonicalTimeZone, LEGACY_TIME_ZONE_ALIASES, profileTimeZoneOptions } from './time-zones';

describe('canonicalTimeZone (T020.4)', () => {
  it.each([
    ['Asia/Calcutta', 'Asia/Kolkata'],
    ['America/Buenos_Aires', 'America/Argentina/Buenos_Aires'],
    ['Europe/Kiev', 'Europe/Kyiv'],
  ])('maps legacy %s to %s', (legacy, current) => {
    expect(canonicalTimeZone(legacy)).toBe(current);
  });

  it('passes through canonical and unknown names', () => {
    expect(canonicalTimeZone('Europe/Madrid')).toBe('Europe/Madrid');
    expect(canonicalTimeZone('Not/AZone')).toBe('Not/AZone');
  });
});

describe('profileTimeZoneOptions (T020.4)', () => {
  it('canonicalizes, dedupes and sorts a supplied list', () => {
    const options = profileTimeZoneOptions('UTC', [
      'America/Buenos_Aires',
      'Europe/Madrid',
      'America/Argentina/Buenos_Aires',
      'UTC',
    ]);

    const values = options.map((o) => o.value);
    expect(values).toEqual([
      'America/Argentina/Buenos_Aires',
      'Europe/Madrid',
      'UTC',
    ]);
  });

  it('always includes UTC even when absent from the list', () => {
    const options = profileTimeZoneOptions('Europe/Madrid', ['Europe/Madrid']);
    expect(options.map((o) => o.value)).toContain('UTC');
  });

  it('includes the current zone when missing from the supported list, canonicalized', () => {
    const options = profileTimeZoneOptions('Europe/Kiev', ['Europe/Madrid', 'UTC']);
    const values = options.map((o) => o.value);
    expect(values).toContain('Europe/Kyiv');
    expect(values).not.toContain('Europe/Kiev');
  });

  it('keeps an unknown current zone as an option', () => {
    const options = profileTimeZoneOptions('Custom/Zone', ['UTC']);
    expect(options.map((o) => o.value)).toContain('Custom/Zone');
  });

  it('labels replace underscores with spaces', () => {
    const options = profileTimeZoneOptions('UTC', ['America/Argentina/Buenos_Aires']);
    const entry = options.find((o) => o.value === 'America/Argentina/Buenos_Aires');
    expect(entry?.label).toBe('America/Argentina/Buenos Aires');
  });

  it('falls back to UTC plus the current zone when Intl.supportedValuesOf is missing', () => {
    const original = Intl.supportedValuesOf;
    // @ts-expect-error simulate old runtime
    Intl.supportedValuesOf = undefined;
    try {
      const options = profileTimeZoneOptions('Europe/Madrid');
      expect(options.map((o) => o.value)).toEqual(['Europe/Madrid', 'UTC']);
    } finally {
      Intl.supportedValuesOf = original;
    }
  });

  it('uses Intl.supportedValuesOf by default and contains no legacy names', () => {
    const values = profileTimeZoneOptions('UTC').map((o) => o.value);
    expect(values).toContain('UTC');
    for (const legacy of Object.keys(LEGACY_TIME_ZONE_ALIASES)) {
      expect(values).not.toContain(legacy);
    }
    expect(values).toContain('Asia/Kolkata');
    expect(values).toContain('America/Argentina/Buenos_Aires');
    expect(values).toContain('Europe/Kyiv');
  });
});
