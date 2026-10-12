import { describe, expect, it } from 'vitest';

import { apiIsoToDatetimeLocal, datetimeLocalToApiIso } from './datetime';

describe('datetime helpers', () => {
  it('converts an API timestamp to a datetime-local value', () => {
    expect(apiIsoToDatetimeLocal('2026-08-03T14:07:59.000Z')).toBe('2026-08-03T14:07');
  });

  it('converts a datetime-local value to an API timestamp', () => {
    expect(datetimeLocalToApiIso('2026-08-03T14:07')).toBe('2026-08-03T14:07:00.000Z');
  });

  it('handles empty and invalid input without inventing a date', () => {
    expect(apiIsoToDatetimeLocal(undefined)).toBe('');
    expect(apiIsoToDatetimeLocal('not-a-date')).toBe('');
    expect(datetimeLocalToApiIso('')).toBe('');
    expect(datetimeLocalToApiIso('not-a-date')).toBe('not-a-date');
  });
});
