import { describe, expect, it } from 'vitest';

import {
  BOOKING_WEEK_SEARCH_LIMIT,
  bookingDayOptions,
  bookingServerErrors,
  bookingSuccessKey,
  bookingSummaryParts,
  firstWeekWithSlots,
  bookingWeekRange,
  canGoToPreviousWeek,
  groupSlotsByDay,
  shiftBookingWeek,
  slotLabelParts,
  slotStillAvailable,
  toBookingPayload,
  type BookableRelationship,
} from './booking';

const TZ = 'America/Mexico_City';

describe('bookingWeekRange / shiftBookingWeek (T017-03)', () => {
  it('returns the viewer-local Monday→Monday semi-open range', () => {
    // 2026-11-18 is a Wednesday in Mexico City.
    const range = bookingWeekRange('2026-11-18T20:00:00Z', TZ);

    expect(range.from).toBe('2026-11-16T00:00:00-06:00');
    expect(range.to).toBe('2026-11-23T00:00:00-06:00');
  });

  it('anchors on the viewer-local date, not the UTC date', () => {
    // Sunday 23 UTC is still Saturday 23 local… previous week vs next check:
    // 2026-11-23T03:00Z = Sunday 22, 21:00 Mexico City → week starts Mon 16.
    const range = bookingWeekRange('2026-11-23T03:00:00Z', TZ);

    expect(range.from).toBe('2026-11-16T00:00:00-06:00');
  });

  it('shifts the anchor by whole weeks', () => {
    const next = shiftBookingWeek('2026-11-18T20:00:00Z', 1);

    expect(next).toBe('2026-11-25T20:00:00.000Z');
    expect(bookingWeekRange(next, TZ).from).toBe('2026-11-23T00:00:00-06:00');
    expect(shiftBookingWeek('2026-11-18T20:00:00Z', -1)).toBe('2026-11-11T20:00:00.000Z');
  });
});

describe('groupSlotsByDay (T017-03)', () => {
  it('groups slots by the viewer-local day with localized labels', () => {
    const slots = [
      { start: '2026-11-16T16:00:00+00:00', end: '2026-11-16T17:00:00+00:00', timezone: TZ },
      { start: '2026-11-16T18:00:00+00:00', end: '2026-11-16T19:00:00+00:00', timezone: TZ },
      // 2026-11-18T02:00Z = Tuesday 17, 20:00 Mexico City.
      { start: '2026-11-18T02:00:00+00:00', end: '2026-11-18T03:00:00+00:00', timezone: TZ },
    ];

    const days = groupSlotsByDay(slots, TZ, 'es');

    expect(days.map((day) => day.date)).toEqual(['2026-11-16', '2026-11-17']);
    expect(days[0]?.slots).toHaveLength(2);
    expect(days[0]?.slots[0]?.label).toBe('10:00 – 11:00');
    expect(days[1]?.slots[0]?.label).toBe('20:00 – 21:00');
    expect(days[0]?.label.toLowerCase()).toContain('lunes');
  });
});

describe('bookingServerErrors (T017-03)', () => {
  it('routes slot rejections to the summary with refreshSlots', () => {
    const result = bookingServerErrors({
      scheduled_at: ['The slot is not available.'],
      title: ['Required.'],
      relationship_id: ['Unknown.'],
    });

    expect(result.fields).toEqual({ title: 'Required.' });
    expect(result.summary).toEqual(['The slot is not available.', 'Unknown.']);
    expect(result.refreshSlots).toBe(true);
  });

  it('marks refreshSlots for `session` errors and keeps fields only for known keys', () => {
    const result = bookingServerErrors({
      session: ['The session changed.'],
      duration_minutes: ['Bad duration.'],
      description: ['Too long.'],
    });

    expect(result.fields).toEqual({ duration_minutes: 'Bad duration.', description: 'Too long.' });
    expect(result.summary).toEqual(['The session changed.']);
    expect(result.refreshSlots).toBe(true);
    expect(bookingServerErrors({ title: ['x'] }).refreshSlots).toBe(false);
  });
});

describe('toBookingPayload (T017-03)', () => {
  const relationship: BookableRelationship = {
    id: 'rel-1',
    type: 'personal',
    session_type: 'individual',
    is_collective: false,
    mentor: { id: 'm1', full_name: 'Mentora Uno' },
    source: { type: 'user', name: 'Cliente' },
  };

  it('builds the POST /sessions payload from relationship, slot and values', () => {
    const payload = toBookingPayload(
      relationship,
      { start: '2026-11-16T16:00:00+00:00' },
      { title: '  Mi sesión  ', description: '  notas ', duration_minutes: 90 },
      'America/New_York',
    );

    expect(payload).toEqual({
      relationship_id: 'rel-1',
      session_type: 'individual',
      title: 'Mi sesión',
      description: 'notas',
      scheduled_at: '2026-11-16T16:00:00+00:00',
      duration_minutes: 90,
      timezone: 'America/New_York',
    });
  });

  it('omits an empty description', () => {
    const payload = toBookingPayload(relationship, { start: 's' }, {
      title: 'T', description: '   ', duration_minutes: 60,
    }, TZ);

    expect(payload).not.toHaveProperty('description');
  });
});

describe('bookingDayOptions (T017-07)', () => {
  const week = { from: '2026-12-21T06:00:00+00:00', to: '2026-12-28T06:00:00+00:00' }; // Mon 21–Sun 27 in MX

  it('returns the 7 civil days Mon→Sun with localized labels and per-day counts', () => {
    const grouped = [
      { date: '2026-12-22', label: 'martes 22 de diciembre', slots: [
        { start: 'a', end: 'b', label: 'x' },
        { start: 'c', end: 'd', label: 'y' },
      ]},
      { date: '2026-12-24', label: 'jueves 24 de diciembre', slots: [
        { start: 'e', end: 'f', label: 'z' },
      ]},
    ];

    const options = bookingDayOptions(grouped, week, TZ, 'es');

    expect(options).toHaveLength(7);
    expect(options.map((o) => o.date)).toEqual([
      '2026-12-21', '2026-12-22', '2026-12-23', '2026-12-24',
      '2026-12-25', '2026-12-26', '2026-12-27',
    ]);
    expect(options[0]?.label).toBe('lun 21');
    expect(options[1]?.count).toBe(2);
    expect(options[3]?.count).toBe(1);
    expect(options[4]?.count).toBe(0);
    const enLabel = bookingDayOptions([], week, 'America/New_York', 'en')[0]?.label;
    expect(enLabel).toContain('Mon');
    expect(enLabel).toContain('21');
  });
});

describe('canGoToPreviousWeek (T017-07)', () => {
  it('is false for the week containing today and for past weeks, true for future weeks', () => {
    const now = '2026-12-23T12:00:00Z'; // Wednesday of the shown week
    const current = { from: '2026-12-21T06:00:00+00:00', to: '2026-12-28T06:00:00+00:00' };
    const past = { from: '2026-12-14T06:00:00+00:00', to: '2026-12-21T06:00:00+00:00' };
    const future = { from: '2026-12-28T06:00:00+00:00', to: '2027-01-04T06:00:00+00:00' };

    expect(canGoToPreviousWeek(current, now, TZ)).toBe(false);
    expect(canGoToPreviousWeek(past, now, TZ)).toBe(false);
    expect(canGoToPreviousWeek(future, now, TZ)).toBe(true);
  });
});

describe('slotLabelParts (T017-07)', () => {
  it('returns range, date and tz for the accessible label and announcement', () => {
    const day = { date: '2026-12-22', label: 'martes 22 de diciembre', slots: [] };
    const slot = { start: 'a', end: 'b', label: '09:00 – 09:30' };

    expect(slotLabelParts(slot, day, 'America/Bogota')).toEqual({
      range: '09:00 – 09:30',
      date: 'martes 22 de diciembre',
      tz: 'America/Bogota',
    });
  });
});

describe('firstWeekWithSlots (T017-07b)', () => {
  const slot = { start: 's', end: 'e', timezone: TZ };
  const weeks = (counts: number[]) => {
    const calls: { from: string; to: string }[] = [];
    const fetchWeek = async (range: { from: string; to: string }) => {
      calls.push(range);
      const index = calls.length - 1;
      return Array.from({ length: counts[index] ?? 0 }, () => slot);
    };
    return { calls, fetchWeek };
  };

  it('returns the current week when it already has slots (1 fetch)', async () => {
    const { calls, fetchWeek } = weeks([2]);
    const result = await firstWeekWithSlots({
      startAnchor: '2026-11-16T00:00:00Z',
      viewerTimezone: TZ,
      fetchWeek,
    });
    expect(result?.slots).toHaveLength(2);
    expect(calls).toHaveLength(1);
  });

  it('skips empty weeks sequentially and stops at the first non-empty', async () => {
    const { calls, fetchWeek } = weeks([0, 0, 3, 5]);
    const result = await firstWeekWithSlots({
      startAnchor: '2026-11-16T00:00:00Z',
      viewerTimezone: TZ,
      fetchWeek,
    });
    expect(result?.slots).toHaveLength(3);
    expect(calls).toHaveLength(3); // did NOT fetch the 4th week
    // the found range is the 3rd week
    const expected = bookingWeekRange(shiftBookingWeek(shiftBookingWeek('2026-11-16T00:00:00Z', 1), 1), TZ);
    expect(result?.range).toEqual(expected);
  });

  it('respects the limit and returns null', async () => {
    const { calls, fetchWeek } = weeks([]);
    const result = await firstWeekWithSlots({
      startAnchor: '2026-11-16T00:00:00Z',
      viewerTimezone: TZ,
      fetchWeek,
    });
    expect(result).toBeNull();
    expect(calls).toHaveLength(BOOKING_WEEK_SEARCH_LIMIT);
  });

  it('stops mid-search when the signal aborts', async () => {
    const controller = new AbortController();
    const calls: unknown[] = [];
    const fetchWeek = async (range: { from: string; to: string }) => {
      calls.push(range);
      if (calls.length === 2) controller.abort();
      return [];
    };
    const result = await firstWeekWithSlots({
      startAnchor: '2026-11-16T00:00:00Z',
      viewerTimezone: TZ,
      fetchWeek,
      signal: controller.signal,
    });
    expect(result).toBeNull();
    expect(calls).toHaveLength(2);
  });

  it('returns null when the fetch rejects because of the abort', async () => {
    const controller = new AbortController();
    const fetchWeek = async () => {
      controller.abort();
      throw new DOMException('Aborted', 'AbortError');
    };
    await expect(firstWeekWithSlots({
      startAnchor: '2026-11-16T00:00:00Z',
      viewerTimezone: TZ,
      fetchWeek,
      signal: controller.signal,
    })).resolves.toBeNull();
  });
});

describe('bookingSummaryParts / bookingSuccessKey / slotStillAvailable (T017-08)', () => {
  const relationship: BookableRelationship = {
    id: 'r1', type: 'personal', session_type: 'individual', is_collective: false,
    mentor: { id: 'm1', full_name: 'Valeria Mendez' },
    source: { type: 'user', name: 'Ana Lopez' },
  };
  const day = { date: '2026-11-16', label: 'lunes, 16 de noviembre', slots: [] };
  const slot = { start: '2026-11-16T14:00:00Z', end: '2026-11-16T15:00:00Z', label: '08:00 – 09:00' };

  it('builds the summary parts in viewer terms', () => {
    expect(bookingSummaryParts(relationship, slot, day, 60, 'America/Bogota')).toEqual({
      relationship: 'Valeria Mendez — Ana Lopez',
      when: 'lunes, 16 de noviembre, 08:00 – 09:00 (America/Bogota)',
      minutes: 60,
    });
  });

  it('picks the success message key from the created flag', () => {
    expect(bookingSuccessKey(true)).toBe('booking.success_created');
    expect(bookingSuccessKey(false)).toBe('booking.success_existing');
  });

  it('detects whether the selected slot survives a refresh', () => {
    expect(slotStillAvailable(slot, [
      { start: '2026-11-16T13:00:00Z', end: '2026-11-16T14:00:00Z', timezone: TZ },
      { start: '2026-11-16T14:00:00Z', end: '2026-11-16T15:00:00Z', timezone: TZ },
    ])).toBe(true);
    expect(slotStillAvailable(slot, [])).toBe(false);
  });
});
