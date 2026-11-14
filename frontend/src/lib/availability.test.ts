import { describe, expect, it } from 'vitest';

import {
  conflictSessionsPresentation,
  exceptionItemPresentation,
  exceptionPeriodParts,
  exceptionServerFieldErrors,
  groupWindowsByDay,
  sortExceptions,
  windowServerFieldErrors,
  type AvailabilityWindow,
} from './availability';

const windowEntry = (day: number, start: string, id = `${day}-${start}`): AvailabilityWindow => ({
  id,
  day_of_week: day,
  start_time: start,
  end_time: '11:00',
  timezone: 'America/Mexico_City',
});

describe('groupWindowsByDay (T017-03)', () => {
  it('orders Monday→Sunday mapping backend 0=Sunday, with empty days and localized labels', () => {
    const days = groupWindowsByDay(
      [windowEntry(0, '09:00'), windowEntry(2, '18:00'), windowEntry(2, '08:00'), windowEntry(6, '10:00')],
      'es',
    );

    expect(days.map((day) => day.dayOfWeek)).toEqual([1, 2, 3, 4, 5, 6, 0]);
    expect(days[0]?.label).toBe('lunes');
    expect(days[6]?.label).toBe('domingo');
    // Sunday sits last but keeps dayOfWeek 0; windows sorted by start within the day.
    expect(days[6]?.windows.map((window) => window.day_of_week)).toEqual([0]);
    expect(days[1]?.windows.map((window) => window.start_time)).toEqual(['08:00', '18:00']);
    expect(days[2]?.windows).toEqual([]);
  });

  it('localizes weekday names', () => {
    const days = groupWindowsByDay([], 'en');

    expect(days[0]?.label).toBe('Monday');
    expect(days[6]?.label).toBe('Sunday');
  });
});

describe('sortExceptions (T017-03)', () => {
  const exception = (overrides: Record<string, unknown>) => ({
    id: Math.random().toString(),
    mode: 'block',
    start_date: '2026-12-01',
    end_date: null,
    start_time: null,
    end_time: null,
    timezone: 'UTC',
    reason: null,
    ...overrides,
  });

  it('hides past exceptions and orders by start_date then start_time', () => {
    const current = exception({ start_date: '2026-12-10', id: 'b' });
    const timed = exception({ start_date: '2026-12-10', start_time: '10:00', end_time: '12:00', id: 'c' });
    const past = exception({ start_date: '2026-11-01', id: 'past' });
    const pastRange = exception({ start_date: '2026-11-01', end_date: '2026-11-30', id: 'past-range' });
    const stillOpen = exception({ start_date: '2026-11-01', end_date: '2026-12-20', id: 'a' });

    const sorted = sortExceptions([timed, past, current, pastRange, stillOpen], '2026-12-05');

    expect(sorted.map((entry) => entry.id)).toEqual(['a', 'b', 'c']);
  });

  it('keeps exceptions ending today', () => {
    const endsToday = exception({ start_date: '2026-12-01', end_date: '2026-12-05', id: 'x' });

    expect(sortExceptions([endsToday], '2026-12-05')).toHaveLength(1);
  });
});

describe('exceptionPeriodParts (T017-03)', () => {
  const base = {
    id: 'x', mode: 'block', start_date: '2026-12-24', end_date: null,
    start_time: null, end_time: null, timezone: 'UTC', reason: null,
  };

  it('reports single all-day, ranged, and banded periods', () => {
    expect(exceptionPeriodParts(base)).toEqual({
      kind: 'single', startDate: '2026-12-24', endDate: null, band: null,
    });
    expect(exceptionPeriodParts({ ...base, end_date: '2026-12-26' })).toEqual({
      kind: 'range', startDate: '2026-12-24', endDate: '2026-12-26', band: null,
    });
    expect(exceptionPeriodParts({ ...base, start_time: '10:00', end_time: '12:00' })).toEqual({
      kind: 'single', startDate: '2026-12-24', endDate: null,
      band: { startTime: '10:00', endTime: '12:00' },
    });
  });
});

describe('exceptionItemPresentation (T017-05)', () => {
  const base = {
    id: 'x', mode: 'block' as const, start_date: '2026-12-24', end_date: null,
    start_time: null, end_time: null, timezone: 'UTC', reason: null,
  };

  it('localizes single day, ranges and bands and maps the mode badge key', () => {
    expect(exceptionItemPresentation(base, 'es')).toEqual({
      modeKey: 'mode_block', periodText: '24 dic 2026', bandText: null,
    });
    expect(exceptionItemPresentation({ ...base, mode: 'open', end_date: '2026-12-26' }, 'es')).toEqual({
      modeKey: 'mode_open', periodText: '24 dic 2026 – 26 dic 2026', bandText: null,
    });
    expect(exceptionItemPresentation({ ...base, start_time: '10:00', end_time: '12:00' }, 'en').bandText)
      .toBe('10:00–12:00');
    // end_date equal to start_date collapses to a single day.
    expect(exceptionItemPresentation({ ...base, end_date: '2026-12-24' }, 'es').periodText)
      .toBe('24 dic 2026');
  });
});

describe('conflictSessionsPresentation (T017-05)', () => {
  it('labels conflicts in the viewer timezone with a short zone name', () => {
    const [view] = conflictSessionsPresentation(
      [{ id: 's1', title: 'Sesión', scheduled_at: '2026-12-24T17:00:00.000000Z', duration_minutes: 60 }],
      'America/Mexico_City',
      'es',
    );

    expect(view?.id).toBe('s1');
    expect(view?.label).toMatch(/^Sesión — 24 dic.*11:00.*(CST|GMT-6)/u);
  });
});

describe('server field error maps (T017-03)', () => {
  it('maps window server keys to form fields and unknown keys to the form-level error', () => {
    const result = windowServerFieldErrors({
      end_time: ['The end time must be after start time.'],
      day_of_week: ['Overlaps another window.'],
      unexpected: ['Boom'],
    });

    expect(result.fields).toEqual({
      end_time: 'The end time must be after start time.',
      day_of_week: 'Overlaps another window.',
    });
    expect(result.formError).toBe('Boom');
  });

  it('maps exception keys and returns null form error when everything is known', () => {
    const result = exceptionServerFieldErrors({
      start_time: ['Invalid.'],
      start_date: ['Bad date.'],
    });

    expect(result.fields).toEqual({ start_time: 'Invalid.', start_date: 'Bad date.' });
    expect(result.formError).toBeNull();
    expect(exceptionServerFieldErrors({}).formError).toBeNull();
  });
});
