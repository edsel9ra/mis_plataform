import { describe, expect, it } from 'vitest';

import {
  CalendarNavigator,
  CalendarRange,
  CalendarSessionMapper,
  calendarLocalDate,
  dayWallDates,
  groupCalendarItemsByDate,
  monthGrid,
  type CalendarItem,
  type CalendarPeriod,
  type CalendarSessionInput,
  calendarViewState,
} from './scheduling';

const HOUR_MS = 60 * 60 * 1000;

// Backend contract (backend/app/Http/Requests/Session/IndexSessionsRequest.php):
// ISO timestamp with seconds plus `Z` or ±HH:MM offset, semi-open range.
const BACKEND_ISO_PATTERN =
  /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/;

function wallClockInZone(instant: string, timeZone: string) {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone,
    hour12: false,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    weekday: 'long',
  })
    .formatToParts(new Date(instant))
    .reduce<Record<string, string>>((acc, part) => {
      if (part.type !== 'literal') acc[part.type] = part.value;

      return acc;
    }, {});

  return {
    year: Number(parts.year),
    month: Number(parts.month),
    day: Number(parts.day),
    weekday: parts.weekday,
    date: `${parts.year}-${parts.month}-${parts.day}`,
  };
}

describe('CalendarRange.forPeriod', () => {
  it('formats `from`/`to` to the ISO offsets accepted by GET /sessions', () => {
    const range = CalendarRange.forPeriod('week', {
      timezone: 'Asia/Kolkata',
      now: '2026-03-04T10:00:00Z',
    });

    expect(range.from).toMatch(BACKEND_ISO_PATTERN);
    expect(range.to).toMatch(BACKEND_ISO_PATTERN);
    expect(range.from).toContain('+05:30');
    expect(range.to).toContain('+05:30');
  });

  it('rejects unknown periods', () => {
    expect(() =>
      CalendarRange.forPeriod('year' as CalendarPeriod, {
        timezone: 'UTC',
        now: '2026-05-06T10:00:00Z',
      }),
    ).toThrow();
  });

  it('rejects unparseable dates and unknown zones', () => {
    expect(() =>
      CalendarRange.forPeriod('day', {
        timezone: 'UTC',
        now: 'not-a-date',
      }),
    ).toThrow();
    expect(() =>
      CalendarRange.forPeriod('day', {
        timezone: 'Not/AZone',
        now: '2026-05-06T10:00:00Z',
      }),
    ).toThrow();
  });

  describe('day', () => {
    it('covers the local day minute of `now`, found in the viewer timezone', () => {
      const range = CalendarRange.forPeriod('day', {
        timezone: 'UTC',
        now: '2026-05-06T23:59:00Z',
      });

      expect(range.from).toBe('2026-05-06T00:00:00+00:00');
      expect(range.to).toBe('2026-05-07T00:00:00+00:00');
    });

    it('keeps the local day across a DST transition (23-hour day)', () => {
      // 2026-03-08T05:30:00Z is still Sat/Sun 00:30 local time (-05) in New York
      // and the clocks spring forward at 02:00 local (07:00Z).
      const range = CalendarRange.forPeriod('day', {
        timezone: 'America/New_York',
        now: '2026-03-08T05:30:00Z',
      });

      expect(range.from).toBe('2026-03-08T00:00:00-05:00');
      expect(range.to).toBe('2026-03-09T00:00:00-04:00');
      expect(Date.parse(range.to) - Date.parse(range.from)).toBe(23 * HOUR_MS);
    });
  });

  describe('week (Monday → next Monday local 00:00 in the viewer zone)', () => {
    it('starts on Monday in both UTC and Asia/Kolkata', () => {
      const utc = CalendarRange.forPeriod('week', {
        timezone: 'UTC',
        now: '2026-05-06T15:30:00Z',
      });

      expect(wallClockInZone(utc.from, 'UTC')).toMatchObject({
        date: '2026-05-04',
        weekday: 'Monday',
      });
      expect(wallClockInZone(utc.to, 'UTC')).toMatchObject({
        date: '2026-05-11',
        weekday: 'Monday',
      });
      expect(Date.parse(utc.to) - Date.parse(utc.from)).toBe(168 * HOUR_MS);

      const kolkata = CalendarRange.forPeriod('week', {
        timezone: 'Asia/Kolkata',
        now: '2026-03-04T10:00:00Z',
      });

      expect(wallClockInZone(kolkata.from, 'Asia/Kolkata')).toMatchObject({
        date: '2026-03-02',
        weekday: 'Monday',
      });
      expect(kolkata.from).toBe('2026-03-02T00:00:00+05:30');
      expect(kolkata.to).toBe('2026-03-09T00:00:00+05:30');
      // Absolute instants, not 7×24 h drifted offsets.
      expect(Date.parse(kolkata.from)).toBe(Date.parse('2026-03-01T18:30:00Z'));
      expect(Date.parse(kolkata.to)).toBe(Date.parse('2026-03-08T18:30:00Z'));
      expect(Date.parse(kolkata.to) - Date.parse(kolkata.from)).toBe(168 * HOUR_MS);
    });

    it('gives identical limits for locale-independent callers (es/en/pt share one rule)', () => {
      // The pure rule takes no locale: es, en and pt call it with the same
      // observer zone and must observe the exact same instant and source.
      const es = CalendarRange.forPeriod('week', {
        timezone: 'America/New_York',
        now: '2026-01-07T12:00:00Z',
      });
      const en = CalendarRange.forPeriod('week', {
        timezone: 'America/New_York',
        now: '2026-01-07T12:00:00Z',
      });
      const pt = CalendarRange.forPeriod('week', {
        timezone: 'America/New_York',
        now: '2026-01-07T12:00:00Z',
      });

      expect(es).toEqual(en);
      expect(en).toEqual(pt);
      // The local Monday edge is kept: this is a no-DST week, 168 hours long.
      expect(es.from).toBe('2026-01-05T00:00:00-05:00');
      expect(es.to).toBe('2026-01-12T00:00:00-05:00');
      expect(Date.parse(es.from)).toBe(Date.parse('2026-01-05T05:00:00Z'));
      expect(Date.parse(es.to)).toBe(Date.parse('2026-01-12T05:00:00Z'));
    });

    it('never adds 7×24 h around the spring DST transition (167-hour week, forward change)', () => {
      // America/New_York springs forward on 2026-03-08 (second Sunday).
      const range = CalendarRange.forPeriod('week', {
        timezone: 'America/New_York',
        now: '2026-03-05T10:00:00Z',
      });

      expect(range.from).toBe('2026-03-02T00:00:00-05:00');
      expect(range.to).toBe('2026-03-09T00:00:00-04:00');
      expect(Date.parse(range.from)).toBe(Date.parse('2026-03-02T05:00:00Z'));
      expect(Date.parse(range.to)).toBe(Date.parse('2026-03-09T04:00:00Z'));
      expect(Date.parse(range.to) - Date.parse(range.from)).toBe(167 * HOUR_MS);
      expect(Date.parse(range.to) - Date.parse(range.from)).not.toBe(168 * HOUR_MS);
      // Both local edges remain Monday 00:00 despite the shift.
      expect(wallClockInZone(range.to, 'America/New_York')).toMatchObject({
        date: '2026-03-09',
        weekday: 'Monday',
      });
    });

    it('returns a 169-hour week around the fall DST transition (backward change)', () => {
      // America/New_York falls back on 2026-11-01 (first Sunday).
      const range = CalendarRange.forPeriod('week', {
        timezone: 'America/New_York',
        now: '2026-10-29T10:00:00Z',
      });

      expect(range.from).toBe('2026-10-26T00:00:00-04:00');
      expect(range.to).toBe('2026-11-02T00:00:00-05:00');
      expect(Date.parse(range.from)).toBe(Date.parse('2026-10-26T04:00:00Z'));
      expect(Date.parse(range.to)).toBe(Date.parse('2026-11-02T05:00:00Z'));
      expect(Date.parse(range.to) - Date.parse(range.from)).toBe(169 * HOUR_MS);
      expect(Date.parse(range.to) - Date.parse(range.from)).not.toBe(168 * HOUR_MS);
      expect(wallClockInZone(range.to, 'America/New_York')).toMatchObject({
        date: '2026-11-02',
        weekday: 'Monday',
      });
    });

    it('keeps Monday edges around the America/Santiago transitions (southern hemisphere)', () => {
      // Chile springs forward on 2026-09-06 and falls back on 2026-04-05.
      const springForward = CalendarRange.forPeriod('week', {
        timezone: 'America/Santiago',
        now: '2026-09-02T12:00:00Z',
      });

      expect(springForward.from).toBe('2026-08-31T00:00:00-04:00');
      expect(springForward.to).toBe('2026-09-07T00:00:00-03:00');
      expect(Date.parse(springForward.to) - Date.parse(springForward.from)).toBe(167 * HOUR_MS);

      const fallBack = CalendarRange.forPeriod('week', {
        timezone: 'America/Santiago',
        now: '2026-04-02T12:00:00Z',
      });

      expect(fallBack.from).toBe('2026-03-30T00:00:00-03:00');
      expect(fallBack.to).toBe('2026-04-06T00:00:00-04:00');
      expect(Date.parse(fallBack.to) - Date.parse(fallBack.from)).toBe(169 * HOUR_MS);
    });

    it('derives the anchor from `now` in the viewer zone by default', () => {
      const defaultAnchor = CalendarRange.forPeriod('week', {
        timezone: 'America/New_York',
        now: '2026-03-05T10:00:00Z',
      });
      const explicitAnchor = CalendarRange.forPeriod('week', {
        timezone: 'America/New_York',
        now: '2020-01-01T00:00:00Z',
        anchor: '2026-03-05T10:00:00Z',
      });

      // Anchor wins over now; now only seeds the default anchor.
      expect(defaultAnchor).toEqual(explicitAnchor);

      // A `now` late in the zone's local week still lands on the same Monday.
      const lateWeek = CalendarRange.forPeriod('week', {
        timezone: 'Pacific/Kiritimati',
        now: '2026-03-02T12:00:00Z', // Mon local 2026-03-02 23:46 there
      });

      expect(wallClockInZone(lateWeek.from, 'Pacific/Kiritimati')).toMatchObject({
        date: '2026-03-02',
        weekday: 'Monday',
      });
    });
  });

  describe('month (local first of month → local next month, viewer zone)', () => {
    it('uses the viewer zone boundaries', () => {
      const range = CalendarRange.forPeriod('month', {
        timezone: 'Asia/Kolkata',
        now: '2026-12-31T19:00:00Z', // local 2027-01-01 00:30 in Kolkata
      });

      // The anchor month is January 2027 (end-of-year crossing).
      expect(range.from).toBe('2027-01-01T00:00:00+05:30');
      expect(range.to).toBe('2027-02-01T00:00:00+05:30');
      expect(Date.parse(range.from)).toBe(Date.parse('2026-12-31T18:30:00Z'));
      expect(Date.parse(range.to)).toBe(Date.parse('2027-01-31T18:30:00Z'));
      expect(Date.parse(range.to) - Date.parse(range.from)).toBe(744 * HOUR_MS);
    });

    it('crosses the end of the year into the next zone month', () => {
      const range = CalendarRange.forPeriod('month', {
        timezone: 'America/New_York',
        now: '2026-12-15T18:00:00Z',
      });

      expect(range.from).toBe('2026-12-01T00:00:00-05:00');
      expect(range.to).toBe('2027-01-01T00:00:00-05:00');
      expect(Date.parse(range.from)).toBe(Date.parse('2026-12-01T05:00:00Z'));
      expect(Date.parse(range.to)).toBe(Date.parse('2027-01-01T05:00:00Z'));
      expect(Date.parse(range.to) - Date.parse(range.from)).toBe(744 * HOUR_MS);
    });

    it('accounts for DST inside the month without fixed durations', () => {
      // March 2026 in New York has 31 days but the clocks spring forward
      // on 2026-03-08, so the month spans 31×24 − 1 = 743 hours.
      const range = CalendarRange.forPeriod('month', {
        timezone: 'America/New_York',
        now: '2026-03-15T18:00:00Z',
      });

      expect(range.from).toBe('2026-03-01T00:00:00-05:00');
      expect(range.to).toBe('2026-04-01T00:00:00-04:00');
      expect(Date.parse(range.from)).toBe(Date.parse('2026-03-01T05:00:00Z'));
      expect(Date.parse(range.to)).toBe(Date.parse('2026-04-01T04:00:00Z'));
      expect(Date.parse(range.to) - Date.parse(range.from)).toBe(743 * HOUR_MS);
      expect(Date.parse(range.to) - Date.parse(range.from)).not.toBe(31 * 24 * HOUR_MS);
    });

    it('handles the shortest month with a viewer-zone offset', () => {
      const range = CalendarRange.forPeriod('month', {
        timezone: 'Asia/Kolkata',
        now: '2026-02-10T10:00:00Z',
      });

      expect(range.from).toBe('2026-02-01T00:00:00+05:30');
      expect(range.to).toBe('2026-03-01T00:00:00+05:30');
      expect(Date.parse(range.to) - Date.parse(range.from)).toBe(28 * 24 * HOUR_MS);
    });
  });
});

describe('CalendarSessionMapper.toCalendarItem (spec 009, RF-009.1/2)', () => {
  // Fixed `now` injected on every call — the mapper never reads a global clock.
  const FREEZE_NOW = '2026-11-10T12:00:00Z';

  const baseSession: CalendarSessionInput = {
    id: 's-1',
    title: 'Mentoría 1:1',
    status: 'scheduled',
    scheduled_at: '2026-11-12T09:00:00.000000Z',
    duration_minutes: 60,
    meet_link: null,
  };

  it('projects the start onto the viewer day and zone, even when it crosses local midnight (caso padre 25–27)', () => {
    // 2026-01-15T02:30Z is 2026-01-14 21:30 in America/New_York (EST).
    const item = CalendarSessionMapper.toCalendarItem(
      { ...baseSession, scheduled_at: '2026-01-15T02:30:00.000000Z' },
      'America/New_York',
      FREEZE_NOW,
    );

    expect(item.localDate).toBe('2026-01-14');
    expect(item.startTimeLabel).toContain('21:30');
    expect(item.startTimeLabel).toContain('GMT-5'); // explicit zone label next to the time
    expect(item.timeZone).toBe('America/New_York');
    expect(item.startInstant).toBe(Date.parse('2026-01-15T02:30:00Z'));
  });

  it('derives the absolute end from the persisted duration (fixed minutes as an instant)', () => {
    const item = CalendarSessionMapper.toCalendarItem(baseSession, 'America/New_York', FREEZE_NOW);

    expect(item.endInstant).toBe(item.startInstant + 60 * 60 * 1000);
    expect(item.durationMinutes).toBe(60);
  });

  it.each(['scheduled', 'in_progress', 'completed', 'canceled', 'not_completed'] as const)(
    'keeps the persisted textual status %s without inferring it from time (006)',
    (status) => {
      const item = CalendarSessionMapper.toCalendarItem(
        { ...baseSession, status },
        'UTC',
        FREEZE_NOW,
      );

      expect(item.status).toBe(status);
    },
  );

  it('marks an existing Meet link as available', () => {
    const item = CalendarSessionMapper.toCalendarItem(
      { ...baseSession, meet_link: 'https://meet.google.com/abc-defg-hij' },
      'UTC',
      FREEZE_NOW,
    );

    expect(item.meet).toEqual({ state: 'available', link: 'https://meet.google.com/abc-defg-hij' });
  });

  it('marks a canceled session link as hidden even if a stale value arrived (CA-7.4)', () => {
    const withoutLink = CalendarSessionMapper.toCalendarItem(
      { ...baseSession, status: 'canceled' },
      'UTC',
      FREEZE_NOW,
    );
    const withStaleLink = CalendarSessionMapper.toCalendarItem(
      { ...baseSession, status: 'canceled', meet_link: 'https://meet.google.com/stale' },
      'UTC',
      FREEZE_NOW,
    );

    expect(withoutLink.meet.state).toBe('hidden');
    expect(withoutLink.meet.link).toBeNull();
    expect(withStaleLink.meet.state).toBe('hidden');
    expect(withStaleLink.meet.link).toBeNull();
  });

  it('marks a confirmed future session without a link as pending (008)', () => {
    const item = CalendarSessionMapper.toCalendarItem(baseSession, 'UTC', FREEZE_NOW);

    expect(item.meet.state).toBe('pending');
    expect(item.meet.link).toBeNull();
  });

  it('does not fake a pending link for sessions past their generation window', () => {
    const completed = CalendarSessionMapper.toCalendarItem(
      { ...baseSession, scheduled_at: '2026-11-01T09:00:00.000000Z', status: 'completed' },
      'UTC',
      FREEZE_NOW,
    );
    const expiredScheduled = CalendarSessionMapper.toCalendarItem(
      { ...baseSession, scheduled_at: '2026-11-01T09:00:00.000000Z' },
      'UTC',
      FREEZE_NOW,
    );

    expect(completed.meet.state).toBe('unavailable');
    expect(expiredScheduled.meet.state).toBe('unavailable');
  });

  it('flags collective sessions and carries their source name, private ones not', () => {
    const cohort = CalendarSessionMapper.toCalendarItem(
      {
        ...baseSession,
        relationship: {
          source_type: 'cohort',
          source: { id: 'c-1', type: 'cohort', name: 'Cohorte 2026' },
        },
      },
      'UTC',
      FREEZE_NOW,
    );
    const personal = CalendarSessionMapper.toCalendarItem(
      {
        ...baseSession,
        relationship: {
          source_type: 'user',
          source: { id: 'u-1', type: 'user', name: 'Ana' },
        },
      },
      'UTC',
      FREEZE_NOW,
    );

    expect(cohort.isCollective).toBe(true);
    expect(cohort.collectiveName).toBe('Cohorte 2026');
    expect(personal.isCollective).toBe(false);
    expect(personal.collectiveName).toBeNull();
  });

  it('expands attendees authorized by SessionResource into name strings only', () => {
    const item = CalendarSessionMapper.toCalendarItem(
      {
        ...baseSession,
        attendees: [
          { id: 'a1', user: { id: 'u1', full_name: 'María López' } },
          { id: 'a2', user: null },
          { id: 'a3' },
          { id: 'a4', user: { id: 'u3', full_name: null } },
          { id: 'a5', user: { id: 'u4', name: 'Carlos Ruiz' } },
        ],
      },
      'UTC',
      FREEZE_NOW,
    );

    expect(item.attendeeNames).toEqual(['María López', 'Carlos Ruiz']);
  });

  it('rejects unknown viewer zones instead of silently misplacing the day', () => {
    expect(() =>
      CalendarSessionMapper.toCalendarItem(baseSession, 'Mars/Olympus', FREEZE_NOW),
    ).toThrow();
  });
});

describe('CalendarNavigator (spec 009, RF-009.2 prev/next/today)', () => {
  const now = '2026-11-10T12:00:00Z';

  it('jumps one full wall day for day periods', () => {
    const zone = 'America/Bogota';
    const range = CalendarRange.forPeriod('day', { timezone: zone, now });

    const next = CalendarNavigator.next('day', range.to, zone);
    const nextRange = CalendarRange.forPeriod('day', { anchor: next, timezone: zone, now });
    expect(nextRange.from).toBe(range.to);

    const prev = CalendarNavigator.previous('day', range.from, zone);
    const prevRange = CalendarRange.forPeriod('day', { anchor: prev, timezone: zone, now });
    expect(prevRange.to).toBe(range.from);
  });

  it('crosses DST boundaries week-to-week with wall-day math (167/169-hour weeks)', () => {
    const zone = 'America/New_York';
    const range = CalendarRange.forPeriod('week', {
      anchor: '2026-03-02T00:00:00-05:00',
      timezone: zone,
      now,
    });

    // The week containing the 2026-03-08 spring-forward (Mar 02 → Mar 09).
    expect(Date.parse(range.to) - Date.parse(range.from)).toBe(167 * HOUR_MS);

    const previousMonday = CalendarNavigator.previous('week', range.from, zone);
    const previousRange = CalendarRange.forPeriod('week', { anchor: previousMonday, timezone: zone, now });
    expect(previousRange.from).toBe('2026-02-23T00:00:00-05:00');
    expect(previousRange.to).toBe(range.from);

    const nextMonday = CalendarNavigator.next('week', range.to, zone);
    const nextRange = CalendarRange.forPeriod('week', { anchor: nextMonday, timezone: zone, now });
    expect(nextRange.from).toBe(range.to);
    expect(nextRange.to).toBe('2026-03-16T00:00:00-04:00');
  });

  it('steps months across the end of the year', () => {
    const zone = 'Asia/Kolkata';
    const january = CalendarRange.forPeriod('month', {
      anchor: '2027-01-01T00:00:00+05:30',
      timezone: zone,
      now,
    });

    const previous = CalendarNavigator.previous('month', january.from, zone);
    const december = CalendarRange.forPeriod('month', { anchor: previous, timezone: zone, now });

    expect(december.from).toBe('2026-12-01T00:00:00+05:30');
    expect(december.to).toBe(january.from);

    const next = CalendarNavigator.next('month', january.to, zone);
    const february = CalendarRange.forPeriod('month', { anchor: next, timezone: zone, now });
    expect(february.from).toBe(january.to);
    expect(february.to).toBe('2027-03-01T00:00:00+05:30');
  });

  it('rejects unusable boundaries or zones', () => {
    expect(() => CalendarNavigator.next('day', 'not-a-date', 'UTC')).toThrow();
    expect(() => CalendarNavigator.previous('week', '2026-01-05T00:00:00Z', 'Nowhere/Now')).toThrow();
  });
});

describe('calendar pure helpers for the views (spec 009)', () => {
  it('calendarLocalDate returns the viewer-local wall date of an instant', () => {
    expect(calendarLocalDate('2026-11-12T09:00:00Z', 'UTC')).toBe('2026-11-12');
    expect(calendarLocalDate('2026-11-12T23:30:00Z', 'Pacific/Kiritimati')).toBe('2026-11-13');

    expect(() => calendarLocalDate('broken', 'UTC')).toThrow();
  });

  it('monthGrid returns 6 Monday-start wall weeks covering the viewer-local month', () => {
    // March 2026 starts on a Sunday: the grid begins at the previous Monday.
    const grid = monthGrid('2026-03-15T12:00:00Z', 'America/New_York');

    expect(grid).toHaveLength(42);
    // 2026-02-23 is the Monday of the week containing 2026-03-01.
    expect(grid[0]).toBe('2026-02-23');

    expect(grid.every((date) => /^\d{4}-\d{2}-\d{2}$/.test(date))).toBe(true);
    expect(new Set(grid).size).toBe(42);
  });

  it('dayWallDates walks consecutive wall dates from a viewer-local start day', () => {
    expect(dayWallDates('2026-03-01', 7)).toEqual([
      '2026-03-01',
      '2026-03-02',
      '2026-03-03',
      '2026-03-04',
      '2026-03-05',
      '2026-03-06',
      '2026-03-07',
    ]);
    expect(dayWallDates('2027-01-01', 7)[6]).toBe('2027-01-07');
    expect(() => dayWallDates('20260301', 7)).toThrow();
  });

  it('groupCalendarItemsByDate sorts wall dates and each day by start instant', () => {
    const toItem = (
      id: string,
      scheduledAt: string,
      status: CalendarSessionInput['status'],
    ): CalendarItem =>
      CalendarSessionMapper.toCalendarItem(
        { id, title: id, status, scheduled_at: scheduledAt, duration_minutes: 30 },
        'UTC',
        '2026-11-10T12:00:00Z',
      );

    const a = toItem('a', '2026-11-12T23:00:00Z', 'scheduled');
    const b = toItem('b', '2026-11-10T08:00:00Z', 'scheduled');
    const c = toItem('c', '2026-11-11T05:00:00Z', 'in_progress');

    const dates = groupCalendarItemsByDate([c, a, b]);

    expect(dates.map((day) => day.date)).toEqual(['2026-11-10', '2026-11-11', '2026-11-12']);
    expect(dates[0]?.items.map((item) => item.id)).toEqual(['b']);
    expect(dates[1]?.items.map((item) => item.id)).toEqual(['c']);
    expect(dates[2]?.items.map((item) => item.id)).toEqual(['a']);
  });
});

describe('calendarViewState (spec 009, RF-009.3)', () => {
  it('loading when isLoading', () => {
    expect(calendarViewState({ isLoading: true, isError: false, itemCount: 0 })).toBe('loading');
  });
  it('error when isError', () => {
    expect(calendarViewState({ isLoading: false, isError: true, itemCount: 5 })).toBe('error');
  });
  it('empty when no items', () => {
    expect(calendarViewState({ isLoading: false, isError: false, itemCount: 0 })).toBe('empty');
  });
  it('ready when items', () => {
    expect(calendarViewState({ isLoading: false, isError: false, itemCount: 3 })).toBe('ready');
  });
});
