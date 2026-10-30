/**
 * Calendario puro (spec 009, RF-009.2; CA-009.2.1; RF-003.1/CA-3.5).
 *
 * `CalendarRange` deriva los límites de día/semana/mes en la zona horaria del
 * espectador y los convierte a instantes absolutos ISO con offset explícito,
 * respetando el DST: nunca se suman duraciones fijas (7×24 h).
 *
 * Regla contractual: la semana inicia lunes 00:00 local y termina lunes
 * siguiente 00:00 local, idéntica para es/en/pt (no depende del locale).
 * Formato compatible con `GET /sessions?from=&to=` (rango semiabierto con
 * offset `Z` o `±HH:MM`, ver backend `IndexSessionsRequest`).
 */

export type CalendarPeriod = 'day' | 'week' | 'month';

const PERIODS: readonly CalendarPeriod[] = ['day', 'week', 'month'];

interface DateParts {
  year: number;
  month: number; // 1–12
  day: number; // 1–31
}

interface WallTime extends DateParts {
  hour?: number;
  minute?: number;
  second?: number;
}

function requirePeriod(period: CalendarPeriod): void {
  if (!PERIODS.includes(period)) {
    throw new TypeError(`Unsupported calendar period: ${String(period)}`);
  }
}

function requireInstant(value: string, name: string): number {
  const instant = Date.parse(value);

  if (Number.isNaN(instant)) {
    throw new TypeError(`Invalid ${name}: ${value}`);
  }

  return instant;
}

/** Wall-clock parts of an instant in the given IANA zone. */
function wallPartsInZone(timezone: string, instantMs: number): DateParts & { weekday: number } {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: timezone,
    hour12: false,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    weekday: 'short',
  })
    .formatToParts(new Date(instantMs))
    .reduce<Record<string, string>>((acc, part) => {
      if (part.type !== 'literal') acc[part.type] = part.value;

      return acc;
    }, {});

  const weekdayLabels: Record<string, number> = {
    Sun: 0,
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6,
  };

  // `hour12: false` can yield the literal wall time "24" at midnight.
  return {
    year: Number(parts.year),
    month: Number(parts.month),
    day: Number(parts.day),
    weekday: weekdayLabels[parts.weekday ?? ''] ?? 0,
  };
}

/**
 * UTC offset (ms) of `timezone` at the given instant. Pure calendar offset:
 * the wall clock of the instant is recomputed as-if-UTC and compared.
 */
function zoneOffsetMs(timezone: string, instantMs: number): number {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: timezone,
    hour12: false,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
    .formatToParts(new Date(instantMs))
    .reduce<Record<string, string>>((acc, part) => {
      if (part.type !== 'literal') acc[part.type] = part.value;

      return acc;
    }, {});

  let hour = Number(parts.hour);
  if (hour === 24) hour = 0;

  const wallAsUtc = Date.UTC(
    Number(parts.year),
    Number(parts.month) - 1,
    Number(parts.day),
    hour,
    Number(parts.minute),
    Number(parts.second),
  );

  return wallAsUtc - Math.floor(instantMs / 1000) * 1000;
}

/** Converts a wall time in `timezone` to its absolute instant (ms). */
function zonedTimeToInstant(timezone: string, wall: WallTime): number {
  const guess = Date.UTC(
    wall.year,
    wall.month - 1,
    wall.day,
    wall.hour ?? 0,
    wall.minute ?? 0,
    wall.second ?? 0,
  );
  if (Number.isNaN(guess)) throw new TypeError(`Invalid wall time: ${JSON.stringify(wall)}`);

  // Resolve the offset at the target wall time; re-checking wards off both
  // DST-spring gaps and fall-back ambiguity at the boundaries.
  const offset = zoneOffsetMs(timezone, guess);
  const instant = guess - offset;
  const offsetAfter = zoneOffsetMs(timezone, instant);

  return offsetAfter === offset ? instant : guess - offsetAfter;
}

/** Calendar day addition over wall-clock parts (no fixed hour arithmetic). */
function addDays({ year, month, day }: DateParts, days: number): DateParts {
  const utc = new Date(Date.UTC(year, month - 1, day + days));

  return {
    year: utc.getUTCFullYear(),
    month: utc.getUTCMonth() + 1,
    day: utc.getUTCDate(),
  };
}

function firstOfMonth({ year, month }: DateParts): DateParts {
  return { year, month, day: 1 };
}

function nextMonth({ year, month }: DateParts): DateParts {
  return month === 12 ? { year: year + 1, month: 1, day: 1 } : { year, month: month + 1, day: 1 };
}

/** Formats an instant as ISO wall time with the zone's real offset (±HH:MM). */
function formatIsoWithOffset(timezone: string, instantMs: number): string {
  const offsetMs = zoneOffsetMs(timezone, instantMs);
  const wall = new Date(instantMs + offsetMs); // wall clock readable as UTC
  const iso = wall.toISOString().slice(0, 19); // YYYY-MM-DDTHH:MM:SS
  const sign = offsetMs < 0 ? '-' : '+';
  const abs = Math.abs(offsetMs);
  const offsetLabel = `${sign}${String(Math.floor(abs / 3600000)).padStart(2, '0')}:${String(
    (abs % 3600000) / 60000,
  ).padStart(2, '0')}`;

  return `${iso}${offsetLabel}`;
}

/**
 * Wall view of a session from the MIS source (`SessionResource`, T009-01).
 * Only fields the backend authorizes are read; the mapper never computes
 * permissions (RF-009.1, plan spec 009).
 */
export interface CalendarSessionInput {
  id: string;
  title: string;
  /** Persisted status of spec 006; the calendar never infers it from time. */
  status: string;
  /** Absolute instant of the start (ISO with offset, per SessionResource). */
  scheduled_at: string;
  duration_minutes: number;
  meet_link?: string | null;
  session_type?: string | null;
  relationship?: {
    source_type?: string | null;
    source?: { id?: string; type?: string | null; name?: string | null } | null;
  } | null;
  attendees?: Array<{
    id?: string;
    user?: { id?: string; name?: string | null; full_name?: string | null; avatar?: string | null } | null;
  } | null> | null;
}

/** State of the Google Meet link (contract of 008: link or pending, never Google directly). */
export type CalendarMeetState = 'available' | 'pending' | 'unavailable' | 'hidden';

export interface CalendarMeetInfo {
  state: CalendarMeetState;
  link: string | null;
}

/** A session projected into the viewer's zone for the calendar views (RF-009.1/2). */
export interface CalendarItem {
  id: string;
  title: string;
  status: string;
  sessionType: string | null;
  /** Absolute instants (ms); a fixed duration is a duration, not calendar math. */
  startInstant: number;
  endInstant: number;
  /** Viewer-local wall date `YYYY-MM-DD` (RF-003.1/CA-3.5). */
  localDate: string;
  timeZone: string;
  /** Locale-independent 24 h time with the viewer zone label, e.g. `21:30 GMT-5`. */
  startTimeLabel: string;
  durationMinutes: number;
  attendeeNames: string[];
  isCollective: boolean;
  collectiveName: string | null;
  meet: CalendarMeetInfo;
}

const COLLECTIVE_SOURCE_TYPES = new Set(['family_group', 'cohort', 'company']);
/** Formats a viewer沙 wall time with its zone abbreviation; 24 h, locale-free. */
function timeLabelInZone(timezone: string, instantMs: number): string {
  const time = new Intl.DateTimeFormat('en-GB', {
    timeZone: timezone,
    hour12: false,
    hour: '2-digit',
    minute: '2-digit',
    timeZoneName: 'short',
  }).format(new Date(instantMs));

  return time.replace(/^24:/, '00:');
}

/** Viewer-local `YYYY-MM-DD` for an absolute instant. */
export function calendarLocalDate(iso: string, timezone: string): string {
  const instant = requireInstant(iso, 'date');

  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date(instant));

  const date: Record<string, string> = {};
  for (const part of parts) {
    if (part.type !== 'literal') date[part.type] = part.value;
  }

  const value = `${date.year ?? ''}-${date.month ?? ''}-${date.day ?? ''}`;

  if (value === '--' || value === '-NaN-NaN') {
    throw new RangeError(`Invalid timezone: ${timezone}`);
  }

  return value;
}

function requireFiniteDuration(minutes: unknown): number {
  const value = Number(minutes);

  if (!Number.isFinite(value) || value < 0) {
    throw new TypeError(`Invalid duration_minutes: ${String(minutes)}`);
  }

  return value;
}

export const CalendarSessionMapper = {
  /**
   * Projects a persisted session into a `CalendarItem` for the viewer's zone.
   *
   * - Pure: state comes from the persisted status (006) and the Meet contract
   *   of 008; `now` is injected and never read from a global clock.
   * - It never decides permissions (RF-009.5); the backend already filtered
   *   what this viewer may see.
   */
  toCalendarItem(session: CalendarSessionInput, viewerTimezone: string, now: string): CalendarItem {
    const startInstant = requireInstant(session.scheduled_at, 'scheduled_at');
    const durationMinutes = requireFiniteDuration(session.duration_minutes);
    // Touch the zone; an invalid identifier must fail loudly here.
    calendarLocalDate(session.scheduled_at, viewerTimezone);

    const meet: CalendarMeetInfo = (() => {
      if (session.status === 'canceled') {
        // CA-7.4 (spec 007): the link stays hidden while canceled.
        return { state: 'hidden', link: null };
      }

      if (session.meet_link) {
        return { state: 'available', link: session.meet_link };
      }

      // 008: a confirmed session (`scheduled`, future start) gets its link
      // automatically; before that arrives the UI may only say "pending".
      if (session.status === 'scheduled' && startInstant > Date.parse(now)) {
        return { state: 'pending', link: null };
      }

      return { state: 'unavailable', link: null };
    })();

    const sourceType = session.relationship?.source_type ?? null;
    const isCollective = sourceType !== null && COLLECTIVE_SOURCE_TYPES.has(sourceType);

    const attendeeNames = (session.attendees ?? [])
      .map((attendee) => attendee?.user?.full_name ?? attendee?.user?.name ?? '')
      .filter((name): name is string => name.length > 0);

    return {
      id: session.id,
      title: session.title,
      status: session.status,
      sessionType: session.session_type ?? null,
      startInstant,
      endInstant: startInstant + durationMinutes * 60_000,
      localDate: calendarLocalDate(session.scheduled_at, viewerTimezone),
      timeZone: viewerTimezone,
      startTimeLabel: timeLabelInZone(viewerTimezone, startInstant),
      durationMinutes,
      attendeeNames,
      isCollective,
      collectiveName: isCollective ? (session.relationship?.source?.name ?? null) : null,
      meet,
    };
  },
};

/** Anchors the calendar navigation onto period boundaries (RF-009.2). */
export const CalendarNavigator = {
  /**
   * Anchor instant (ISO) of the period starting at the boundary `toIso` of a
   * semi-open range, i.e. the one after the current period.
   */
  next(period: CalendarPeriod, toIso: string, timezone: string): string {
    requirePeriod(period);

    const instant = requireInstant(toIso, 'boundary');

    return formatIsoWithOffset(timezone, instant);
  },

  /**
   * Anchor instant (ISO) of the full wall period immediately before the one
   * starting at `fromIso` (one local day/week/month back, never fixed hours).
   */
  previous(period: CalendarPeriod, fromIso: string, timezone: string): string {
    requirePeriod(period);

    const instant = requireInstant(fromIso, 'boundary');
    const local = wallPartsInZone(timezone, instant);

    const backWall =
      period === 'day'
        ? addDays(local, -1)
        : period === 'week'
          ? addDays(local, -7)
          : { ...addDays(firstOfMonth(local), -1), day: 1 };

    return formatIsoWithOffset(timezone, zonedTimeToInstant(timezone, { ...backWall, hour: 0 }));
  },
};

/** Wall dates `YYYY-MM-DD` of the `count` local days starting at Monday of `isoUnit`'s week/viewing first day. */
export function dayWallDates(firstWallDate: string, count: number): string[] {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(firstWallDate)) {
    throw new TypeError(`Invalid wall date: ${firstWallDate}`);
  }

  const [year, month, day] = firstWallDate.split('-').map(Number);

  return Array.from({ length: count }, (_, index) => {
    const wall = addDays({ year: year as number, month: month as number, day: day as number }, index);

    return `${String(wall.year).padStart(4, '0')}-${String(wall.month).padStart(2, '0')}-${String(wall.day).padStart(2, '0')}`;
  });
}

/**
 * Monday-start grid (6 wall weeks × 7 days, 42 wall dates `YYYY-MM-DD`)
 * covering the viewer-local month that contains `isoUnit`.
 */export function monthGrid(isoUnit: string, timezone: string): string[] {
  const instant = requireInstant(isoUnit, 'reference');
  const local = wallPartsInZone(timezone, instant);
  const first = firstOfMonth(local);
  const firstInstant = zonedTimeToInstant(timezone, { ...first, hour: 0 });
  const daysSinceMonday = (wallPartsInZone(timezone, firstInstant).weekday + 6) % 7;
  const gridStart = addDays(first, -daysSinceMonday);

  return Array.from({ length: 42 }, (_, index) => {
    const wall = addDays(gridStart, index);

    return `${String(wall.year).padStart(4, '0')}-${String(wall.month).padStart(2, '0')}-${String(wall.day).padStart(2, '0')}`;
  });
}

/** Derives the render state of the calendar view (RF-009.3). Pure selector. */
export type CalendarViewState = 'loading' | 'error' | 'empty' | 'ready';

export function calendarViewState(input: {
  isLoading: boolean;
  isError: boolean;
  itemCount: number;
}): CalendarViewState {
  if (input.isLoading) return 'loading';
  if (input.isError) return 'error';
  if (input.itemCount <= 0) return 'empty';
  return 'ready';
}

/** Groups calendar items into viewer-local days, sorted day then by start. */
export function groupCalendarItemsByDate(items: CalendarItem[]): Array<{ date: string; items: CalendarItem[] }> {
  const byDate = new Map<string, CalendarItem[]>();

  for (const item of items) {
    const bucket = byDate.get(item.localDate);
    if (bucket) bucket.push(item);
    else byDate.set(item.localDate, [item]);
  }

  return Array.from(byDate.entries())
    .sort(([left], [right]) => left.localeCompare(right))
    .map(([date, dayItems]) => ({
      date,
      items: dayItems.slice().sort((left, right) => left.startInstant - right.startInstant),
    }));
}

export const CalendarRange = {
  /**
   * Derives the semi-open `[from, to)` range of a calendar period.
   *
   * - `timezone`: IANA zone of the viewer (RF-003.1/CA-3.5).
   * - `now`: explicit instant; seeds the default anchor (no global clock).
   * - `anchor`: optional instant whose viewer-local date/week/month is used.
   */
  forPeriod(
    period: CalendarPeriod,
    { anchor, now, timezone }: { anchor?: string; now: string; timezone: string },
  ): { from: string; to: string } {
    requirePeriod(period);

    const anchorInstant = requireInstant(anchor ?? now, anchor ? 'anchor' : 'now');
    const local = wallPartsInZone(timezone, anchorInstant);

    let startWall: DateParts;
    let endWall: DateParts;

    if (period === 'day') {
      startWall = { ...local };
      endWall = addDays(local, 1);
    } else if (period === 'week') {
      const daysSinceMonday = (local.weekday + 6) % 7;
      const monday = addDays(local, -daysSinceMonday);
      startWall = monday;
      endWall = addDays(monday, 7); // next local Monday, never +7×24 h
    } else {
      startWall = firstOfMonth(local);
      endWall = nextMonth(local);
    }

    return {
      from: formatIsoWithOffset(timezone, zonedTimeToInstant(timezone, { ...startWall, hour: 0 })),
      to: formatIsoWithOffset(timezone, zonedTimeToInstant(timezone, { ...endWall, hour: 0 })),
    };
  },
};
