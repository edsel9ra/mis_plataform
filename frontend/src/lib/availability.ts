/**
 * Tipos y presentación pura del editor de disponibilidad (spec 017, T017-03).
 *
 * `day_of_week` sigue el contrato del backend (`AvailabilityRules::windowContains`
 * compara con `format('w')` de PHP): **0 = domingo, 1 = lunes, …, 6 = sábado**.
 * Las funciones puras no contienen cadenas traducidas: devuelven claves/datos
 * que la UI interpreta.
 */

export interface AvailabilityWindow {
  id: string;
  day_of_week: number;
  start_time: string;
  end_time: string;
  timezone: string;
}

export interface AvailabilityWindowPayload {
  day_of_week: number;
  start_time: string;
  end_time: string;
  timezone: string;
}

export interface ConflictingSession {
  id: string;
  title: string;
  scheduled_at: string;
  duration_minutes: number;
}

export interface AvailabilityException {
  id: string;
  mode: 'block' | 'open' | (string & {});
  start_date: string;
  end_date: string | null;
  start_time: string | null;
  end_time: string | null;
  timezone: string;
  reason: string | null;
  conflicting_sessions?: ConflictingSession[];
}

export interface AvailabilityExceptionPayload {
  mode: 'block' | 'open';
  start_date: string;
  end_date?: string | null;
  start_time?: string | null;
  end_time?: string | null;
  timezone: string;
  reason?: string;
}

export interface AvailabilitySlot {
  start: string;
  end: string;
  timezone: string;
}

// Backend `day_of_week`: PHP 'w' — 0 domingo … 6 sábado. Presentación lunes→domingo.
const PRESENTATION_DAY_ORDER = [1, 2, 3, 4, 5, 6, 0] as const;

// 2024-01-01 was a Monday: index i labels the (i+1)-th presentation day.
const WEEKDAY_REFERENCE_UTC = Date.UTC(2024, 0, 1);

export interface WindowsByDay {
  dayOfWeek: number;
  label: string;
  windows: AvailabilityWindow[];
}

export function groupWindowsByDay(windows: AvailabilityWindow[], locale: string): WindowsByDay[] {
  const formatter = new Intl.DateTimeFormat(locale, { weekday: 'long', timeZone: 'UTC' });

  return PRESENTATION_DAY_ORDER.map((dayOfWeek, index) => ({
    dayOfWeek,
    label: formatter.format(new Date(WEEKDAY_REFERENCE_UTC + index * 86_400_000)),
    windows: windows
      .filter((window) => window.day_of_week === dayOfWeek)
      .sort((a, b) => a.start_time.localeCompare(b.start_time)),
  }));
}

/**
 * Vigentes y futuras primero: oculta las excepciones cuyo último día
 * (`end_date ?? start_date`) es anterior a `today` (`YYYY-MM-DD`).
 */
export function sortExceptions<T extends AvailabilityException>(exceptions: T[], today: string): T[] {
  return exceptions
    .filter((exception) => (exception.end_date ?? exception.start_date) >= today)
    .sort((a, b) => {
      const byDate = a.start_date.localeCompare(b.start_date);
      if (byDate !== 0) return byDate;

      return (a.start_time ?? '').localeCompare(b.start_time ?? '');
    });
}

export interface ExceptionPeriodParts {
  /** `single` cuando solo hay `start_date`; `range` con fecha de fin. */
  kind: 'single' | 'range';
  startDate: string;
  endDate: string | null;
  /** `null` para excepciones de día completo; la franja `H:i` en otro caso. */
  band: { startTime: string; endTime: string } | null;
}

export function exceptionPeriodParts(exception: AvailabilityException): ExceptionPeriodParts {
  const band = exception.start_time !== null && exception.end_time !== null
    ? { startTime: exception.start_time, endTime: exception.end_time }
    : null;

  return {
    kind: exception.end_date !== null ? 'range' : 'single',
    startDate: exception.start_date,
    endDate: exception.end_date,
    band,
  };
}

function exceptionDateLabel(date: string, locale: string): string {
  const parsed = new Date(`${date}T00:00:00Z`);

  return new Intl.DateTimeFormat(locale, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(parsed);
}

export interface ExceptionItemPresentation {
  /** i18n key suffix `availability.exceptions.mode_*` for the mode badge. */
  modeKey: 'mode_block' | 'mode_open';
  /** Localized date (or date range) text, e.g. `24 dic 2026` / `24 dic – 26 dic 2026`. */
  periodText: string;
  /** `{start}–{end}` for banded exceptions; null when all-day. */
  bandText: string | null;
}

export function exceptionItemPresentation(
  exception: AvailabilityException,
  locale: string,
): ExceptionItemPresentation {
  const parts = exceptionPeriodParts(exception);
  const start = exceptionDateLabel(parts.startDate, locale);
  const periodText = parts.endDate === null || parts.endDate === parts.startDate
    ? start
    : `${start} – ${exceptionDateLabel(parts.endDate, locale)}`;

  return {
    modeKey: exception.mode === 'open' ? 'mode_open' : 'mode_block',
    periodText,
    bandText: parts.band === null ? null : `${parts.band.startTime}–${parts.band.endTime}`,
  };
}

export interface ConflictSessionView {
  id: string;
  /** `{title} — {localized date, time and zone in the viewer's zone}`. */
  label: string;
}

export function conflictSessionsPresentation(
  sessions: ConflictingSession[],
  viewerTimezone: string,
  locale: string,
): ConflictSessionView[] {
  const formatter = new Intl.DateTimeFormat(locale, {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: viewerTimezone,
    timeZoneName: 'short',
  });

  return sessions.map((session) => ({
    id: session.id,
    label: `${session.title} — ${formatter.format(new Date(session.scheduled_at))}`,
  }));
}

export interface FieldErrorMap {
  fields: Record<string, string>;
  /** Primer mensaje con clave desconocida; se muestra a nivel de formulario. */
  formError: string | null;
}

export function mapServerFieldErrors(
  fieldErrors: Record<string, string[]>,
  knownKeys: ReadonlySet<string>,
): FieldErrorMap {
  const fields: Record<string, string> = {};
  let formError: string | null = null;

  for (const [key, messages] of Object.entries(fieldErrors)) {
    const message = messages[0];
    if (message === undefined) continue;
    if (knownKeys.has(key)) fields[key] = message;
    else if (formError === null) formError = message;
  }

  return { fields, formError };
}

const WINDOW_SERVER_FIELDS = new Set(['day_of_week', 'start_time', 'end_time', 'timezone']);

export function windowServerFieldErrors(fieldErrors: Record<string, string[]>): FieldErrorMap {
  return mapServerFieldErrors(fieldErrors, WINDOW_SERVER_FIELDS);
}

const EXCEPTION_SERVER_FIELDS = new Set([
  'mode', 'start_date', 'end_date', 'start_time', 'end_time', 'timezone', 'reason',
]);

export function exceptionServerFieldErrors(fieldErrors: Record<string, string[]>): FieldErrorMap {
  return mapServerFieldErrors(fieldErrors, EXCEPTION_SERVER_FIELDS);
}
