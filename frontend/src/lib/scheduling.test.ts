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
  instantToWallTime,
  isExistingWallTime,
  wallTimeToInstant,
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

// RF-010.3 (spec 010, T010-02): validation errors must be presented next to
// the affected field AND announced via an assistive-technology live region.
import { fieldErrorPresentation, formErrorAnnouncement } from './scheduling';

describe('fieldErrorPresentation', () => {
  it('normalizes a string error into a single-message presentation', () => {
    expect(fieldErrorPresentation({ 'admin-session-title': 'required' }, 'admin-session-title')).toEqual({
      errorId: 'admin-session-title-error',
      invalid: true,
      describedBy: 'admin-session-title-error',
      messages: ['required'],
    });
  });

  it('keeps every message of an array error', () => {
    expect(
      fieldErrorPresentation({ 'admin-session-duration': ['too small', 'not numeric'] }, 'admin-session-duration'),
    ).toEqual({
      errorId: 'admin-session-duration-error',
      invalid: true,
      describedBy: 'admin-session-duration-error',
      messages: ['too small', 'not numeric'],
    });
  });

  it('ignores empty strings and empty arrays', () => {
    const presentation = fieldErrorPresentation(
      { 'admin-session-title': ['', '  '], other: '' },
      'admin-session-title',
    );

    expect(presentation.invalid).toBe(false);
    expect(presentation.messages).toEqual([]);
    expect(presentation.describedBy).toBeUndefined();
    expect(presentation.errorId).toBe('admin-session-title-error');
  });

  it('marks an unknown field as not invalid but keeps the conventional error id', () => {
    const presentation = fieldErrorPresentation({ title: 'required' }, 'admin-session-duration');

    expect(presentation).toEqual({
      errorId: 'admin-session-duration-error',
      invalid: false,
      describedBy: undefined,
      messages: [],
    });
  });

  it('handles nullish error bags', () => {
    expect(fieldErrorPresentation(null, 'admin-session-title').invalid).toBe(false);
    expect(fieldErrorPresentation(undefined, 'admin-session-title').invalid).toBe(false);
    expect(fieldErrorPresentation(null, 'admin-session-title').describedBy).toBeUndefined();
  });
});

describe('formErrorAnnouncement', () => {
  it('returns the first message of each invalid field in the given field order', () => {
    const announcement = formErrorAnnouncement(
      {
        'admin-session-duration': ['must be at least 15'],
        'admin-session-title': 'required',
      },
      ['admin-session-title', 'admin-session-duration'],
    );

    expect(announcement).toEqual([
      { fieldId: 'admin-session-title', message: 'required' },
      { fieldId: 'admin-session-duration', message: 'must be at least 15' },
    ]);
  });

  it('appends invalid fields missing from fieldOrder in stable key order', () => {
    const announcement = formErrorAnnouncement(
      {
        relationship_id: 'required',
        title: 'required',
        description: '',
      },
      ['title'],
    );

    expect(announcement).toEqual([
      { fieldId: 'title', message: 'required' },
      { fieldId: 'relationship_id', message: 'required' },
    ]);
  });

  it('uses only the first message of each field', () => {
    const announcement = formErrorAnnouncement(
      { title: ['first', 'second'] },
      ['title'],
    );

    expect(announcement).toEqual([{ fieldId: 'title', message: 'first' }]);
  });

  it('returns an empty list when there is nothing to announce', () => {
    expect(formErrorAnnouncement(null, ['title'])).toEqual([]);
    expect(formErrorAnnouncement({ title: '' }, ['title'])).toEqual([]);
    expect(formErrorAnnouncement({}, ['title'])).toEqual([]);
  });
});

// RF-010.2 (spec 010, T010-03): hidden month chips and day labels must be
// reachable/legible without a pointer and without color-only meaning.
import { formatWallDate, monthCellChips } from './scheduling';

describe('monthCellChips', () => {
  const items = ['a', 'b', 'c', 'd', 'e'];

  it('shows everything and hides nothing when under the limit', () => {
    expect(monthCellChips(items.slice(0, 3), 3, false)).toEqual({
      visible: ['a', 'b', 'c'],
      hiddenCount: 0,
    });
  });

  it('truncates at the limit while collapsed and reports the hidden count', () => {
    expect(monthCellChips(items, 3, false)).toEqual({
      visible: ['a', 'b', 'c'],
      hiddenCount: 2,
    });
  });

  it('shows every item with zero hidden once expanded', () => {
    expect(monthCellChips(items, 3, true)).toEqual({
      visible: items,
      hiddenCount: 0,
    });
  });

  it('handles a zero limit by hiding all items until expanded', () => {
    expect(monthCellChips(items, 0, false)).toEqual({ visible: [], hiddenCount: 5 });
    expect(monthCellChips(items, 0, true)).toEqual({ visible: items, hiddenCount: 0 });
  });
});

describe('formatWallDate', () => {
  it('formats a wall date in es with full weekday/month names', () => {
    expect(formatWallDate('2026-11-13', 'es')).toBe(
      new Intl.DateTimeFormat('es', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
        .format(new Date(Date.UTC(2026, 10, 13))),
    );
  });

  it('formats a wall date in en and pt without shifting the day', () => {
    expect(formatWallDate('2026-11-13', 'en', { weekday: 'short' })).toContain('Fri');
    expect(formatWallDate('2026-11-01', 'pt', { weekday: 'long' })).toMatch(/domingo/i);
  });

  it('never shifts the wall day regardless of the process timezone', () => {
    // Nov 1 2026 is a Sunday; a naive `new Date("2026-11-01")` + local format
    // could render Oct 31 in negative offsets. The helper must stay UTC-fixed.
    expect(formatWallDate('2026-11-01', 'en', { day: 'numeric', month: 'numeric', year: 'numeric', timeZone: 'UTC' }))
      .toContain('1');
    expect(formatWallDate('2026-11-01', 'en', { weekday: 'long' })).toBe('Sunday');
  });

  it('rejects malformed wall dates', () => {
    expect(() => formatWallDate('13-11-2026', 'es')).toThrow(TypeError);
    expect(() => formatWallDate('not-a-date', 'es')).toThrow(TypeError);
  });
});


describe('wallTimeToInstant (T016-06)', () => {
  it('converts a wall time in a fixed-offset zone to ISO UTC', () => {
    // America/Mexico_City has no DST (UTC-06 all year).
    expect(wallTimeToInstant('2026-11-16', '14:30', 'America/Mexico_City'))
      .toBe('2026-11-16T20:30:00Z');
  });

  it('handles a positive offset zone', () => {
    // Asia/Kolkata is UTC+05:30.
    expect(wallTimeToInstant('2026-11-16', '09:15', 'Asia/Kolkata'))
      .toBe('2026-11-16T03:45:00Z');
  });

  it('honors a DST-observing zone (Europe/Madrid winter vs summer)', () => {
    expect(wallTimeToInstant('2026-01-15', '12:00', 'Europe/Madrid'))
      .toBe('2026-01-15T11:00:00Z');
    expect(wallTimeToInstant('2026-07-15', '12:00', 'Europe/Madrid'))
      .toBe('2026-07-15T10:00:00Z');
  });

  it('resolves a spring-forward gap via the post-transition offset (earlier instant)', () => {
    // 2026-03-08 02:30 does not exist in America/New_York (gap 02:00–03:00).
    // Chosen resolution (same as zonedTimeToInstant): the last valid instant
    // before the gap — 06:30Z ≡ 01:30 EST.
    expect(wallTimeToInstant('2026-03-08', '02:30', 'America/New_York'))
      .toBe('2026-03-08T06:30:00Z');
  });

  it('resolves a fall-back ambiguous wall time to the first occurrence', () => {
    // 2026-11-01 01:30 exists twice in America/New_York (EDT then EST);
    // the helper keeps the earlier instant (EDT, UTC-04).
    expect(wallTimeToInstant('2026-11-01', '01:30', 'America/New_York'))
      .toBe('2026-11-01T05:30:00Z');
  });

  it('rejects malformed input', () => {
    expect(() => wallTimeToInstant('16-11-2026', '10:00', 'America/Mexico_City')).toThrow(TypeError);
    expect(() => wallTimeToInstant('2026-11-16', '25:00', 'America/Mexico_City')).toThrow(TypeError);
    expect(() => wallTimeToInstant('2026-11-16', '10:60', 'America/Mexico_City')).toThrow(TypeError);
    expect(() => wallTimeToInstant('2026-11-16', '10:00', 'Not/AZone')).toThrow(TypeError);
  });
});

describe('instantToWallTime (T016-06)', () => {
  it('returns the wall date and time of an instant in the zone', () => {
    expect(instantToWallTime('2026-11-16T20:30:00Z', 'America/Mexico_City'))
      .toEqual({ date: '2026-11-16', time: '14:30' });
  });

  it('rolls the date across midnight when the zone demands it', () => {
    expect(instantToWallTime('2026-11-16T02:00:00Z', 'Asia/Kolkata'))
      .toEqual({ date: '2026-11-16', time: '07:30' });
  });

  it('round-trips non-gap wall times', () => {
    for (const timezone of ['America/Mexico_City', 'Europe/Madrid', 'Asia/Kolkata', 'America/New_York']) {
      for (const [date, time] of [['2026-01-15', '09:45'], ['2026-07-20', '16:05'], ['2026-11-16', '14:30']]) {
        expect(instantToWallTime(wallTimeToInstant(date, time, timezone), timezone))
          .toEqual({ date, time });
      }
    }
  });
});

describe('isExistingWallTime (T016-06 rework)', () => {
  it('returns true for times that exist in the zone', () => {
    expect(isExistingWallTime('2026-03-08', '01:30', 'America/New_York')).toBe(true);
    expect(isExistingWallTime('2026-03-08', '03:30', 'America/New_York')).toBe(true);
    expect(isExistingWallTime('2026-11-16', '14:30', 'America/Mexico_City')).toBe(true);
  });

  it('returns false inside a spring-forward gap', () => {
    expect(isExistingWallTime('2026-03-08', '02:30', 'America/New_York')).toBe(false);
    expect(isExistingWallTime('2026-03-08', '02:00', 'America/New_York')).toBe(false);
  });

  it('returns true for ambiguous fall-back times (first occurrence counts)', () => {
    expect(isExistingWallTime('2026-11-01', '01:30', 'America/New_York')).toBe(true);
  });

  it('returns false on malformed input instead of throwing', () => {
    expect(isExistingWallTime('bad', '10:00', 'America/New_York')).toBe(false);
    expect(isExistingWallTime('2026-11-16', '99:00', 'America/New_York')).toBe(false);
    expect(isExistingWallTime('2026-11-16', '10:00', 'Not/AZone')).toBe(false);
  });
});
