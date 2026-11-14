/**
 * Tipos y presentación pura de `available_actions` (spec 016, RF-016.6).
 *
 * El detalle `GET /sessions/{id}` reporta por acción si está permitida y, si
 * no, un motivo de dominio (`BlockReason`). Este módulo normaliza la forma
 * cruda del servidor y la traduce a claves i18n `session_actions.*`.
 */

import type { ApiError } from './api';
import { queryKeys } from './query-keys';

export const BLOCK_REASON_CODES = [
  'terminal_status',
  'already_started',
  'insufficient_notice',
  'invalid_schedule',
  'not_ended',
  'window_closed',
  'not_started',
  'not_registered',
  'excluded',
  'forbidden',
  'not_chargeable',
  'already_confirmed_by_member',
] as const;

export type BlockReasonCode = (typeof BLOCK_REASON_CODES)[number];

export const ATTENDANCE_STATUSES = ['not_confirmed', 'attended', 'absent'] as const;

export type AttendanceStatus = (typeof ATTENDANCE_STATUSES)[number];

const ATTENDANCE_STATUS_CODES = new Set<string>(ATTENDANCE_STATUSES);

export interface ActionAvailability {
  allowed: boolean;
  reason: BlockReasonCode | (string & {}) | null;
}

export interface CancelAvailability extends ActionAvailability {
  late: boolean;
}

export interface AttendanceEntry {
  user_id: string;
  self: boolean;
  allowed: boolean;
  reason: ActionAvailability['reason'];
  current_status: AttendanceStatus | (string & {});
}

export interface SessionAvailableActions {
  reschedule: ActionAvailability;
  cancel: CancelAvailability;
  complete: ActionAvailability;
  attendance: AttendanceEntry[];
}

const REASON_CODES = new Set<string>(BLOCK_REASON_CODES);

/**
 * `session_actions.reasons.<code>` para los motivos conocidos;
 * `session_actions.reasons.unknown` para los desconocidos; null si no hay.
 */
export function actionReasonKey(reason: string | null | undefined): string | null {
  if (reason === null || reason === undefined) return null;

  return REASON_CODES.has(reason)
    ? `session_actions.reasons.${reason}`
    : 'session_actions.reasons.unknown';
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function parseAction(value: unknown): ActionAvailability | null {
  if (!isRecord(value) || typeof value.allowed !== 'boolean') return null;
  const reason = value.reason;

  return {
    allowed: value.allowed,
    reason: typeof reason === 'string' ? reason : null,
  };
}

function parseAttendanceEntry(value: unknown): AttendanceEntry | null {
  if (!isRecord(value)) return null;
  const { user_id, self, allowed, reason, current_status } = value;
  if (typeof user_id !== 'string' || typeof self !== 'boolean' || typeof allowed !== 'boolean') {
    return null;
  }

  return {
    user_id,
    self,
    allowed,
    reason: typeof reason === 'string' ? reason : null,
    current_status: typeof current_status === 'string' ? current_status : 'not_confirmed',
  };
}

/** Defensive parse of the raw `available_actions` payload; null on any structural miss. */
export function parseAvailableActions(raw: unknown): SessionAvailableActions | null {
  if (!isRecord(raw)) return null;

  const reschedule = parseAction(raw.reschedule);
  const cancel = parseAction(raw.cancel);
  const complete = parseAction(raw.complete);
  if (!reschedule || !cancel || !complete) return null;
  if (!Array.isArray(raw.attendance)) return null;

  const attendance = raw.attendance
    .map(parseAttendanceEntry)
    .filter((entry): entry is AttendanceEntry => entry !== null);

  return {
    reschedule,
    cancel: { ...cancel, late: cancel.allowed && (raw.cancel as Record<string, unknown>).late === true },
    complete,
    attendance,
  };
}

/** Attendee rows as shipped by the session detail (`attendees[].user`). */
export interface SessionAttendeeLike {
  user?: { id?: string | null; name?: string | null; full_name?: string | null } | null;
}

export interface AttendanceTargetPresentation {
  userId: string;
  self: boolean;
  name: string | null;
  allowed: boolean;
  reasonKey: string | null;
  currentStatus: string;
  currentStatusKey: string | null;
}

/**
 * Ordered presentation list for the attendance panel: self first, then the
 * remaining entries in server order, with display names joined from the
 * session detail's attendee data.
 */
export function attendanceTargetsPresentation(
  entries: AttendanceEntry[],
  attendees: readonly (SessionAttendeeLike | null | undefined)[],
): AttendanceTargetPresentation[] {
  const nameByUserId = new Map<string, string>();
  for (const attendee of attendees) {
    const user = attendee?.user;
    const name = user?.full_name ?? user?.name ?? null;
    if (typeof user?.id === 'string' && name) {
      nameByUserId.set(user.id, name);
    }
  }

  const ordered = [...entries].sort((a, b) => Number(b.self) - Number(a.self));

  return ordered.map((entry) => ({
    userId: entry.user_id,
    self: entry.self,
    name: nameByUserId.get(entry.user_id) ?? null,
    allowed: entry.allowed,
    reasonKey: actionReasonKey(entry.reason),
    currentStatus: entry.current_status,
    currentStatusKey: ATTENDANCE_STATUS_CODES.has(entry.current_status)
      ? `session_actions.attendance_status.${entry.current_status}`
      : null,
  }));
}

/**
 * Query keys invalidated after any session action mutation (RF-016.9):
 * the detail key plus the `sessions` root prefix, which also covers the
 * ranged calendar keys and paginated lists.
 */
export function sessionActionInvalidationKeys(sessionId: string): readonly (readonly unknown[])[] {
  return [queryKeys.session(sessionId), queryKeys.sessionsRoot];
}

export type SessionActionErrorKind =
  | 'validation'
  | 'domain'
  | 'forbidden'
  | 'not_found'
  | 'network'
  | 'server'
  | 'unknown';

export interface SessionActionErrorOutcome {
  kind: SessionActionErrorKind;
  fieldErrors: Record<string, string[]>;
  /** Server-provided (already translated) domain message; null otherwise. */
  message: string | null;
  /** True when the session state may have changed server-side: refresh the detail. */
  staleDetail: boolean;
}

function isApiErrorLike(error: unknown): error is ApiError {
  return error instanceof Error && error.name === 'ApiError';
}

/**
 * Classifies a session-action rejection for the UI (RF-016.10). A 422 whose
 * only error key is `session` is a domain rejection (eligibility/state) and
 * carries a server-translated message; field-keyed 422s are plain validation.
 */
export function sessionActionErrorOutcome(error: unknown): SessionActionErrorOutcome {
  const base: SessionActionErrorOutcome = {
    kind: 'unknown',
    fieldErrors: {},
    message: null,
    staleDetail: false,
  };
  if (!isApiErrorLike(error)) return base;
  if (error.code === 'network' || error.code === 'timeout') return { ...base, kind: 'network' };
  if (error.code === 'aborted') return base;

  const fieldErrors = error.errors ?? {};

  if (error.status === 422) {
    const keys = Object.keys(fieldErrors);
    if (keys.length > 0 && keys.every((key) => key === 'session')) {
      return {
        kind: 'domain',
        fieldErrors,
        message: fieldErrors.session?.[0] ?? null,
        staleDetail: true,
      };
    }

    return { kind: 'validation', fieldErrors, message: null, staleDetail: 'session' in fieldErrors };
  }

  if (error.status === 403) return { ...base, fieldErrors, kind: 'forbidden', staleDetail: true };
  if (error.status === 404) return { ...base, fieldErrors, kind: 'not_found', staleDetail: true };
  if (error.status >= 500) return { ...base, fieldErrors, kind: 'server' };

  return { ...base, fieldErrors };
}

/**
 * Hours before the start below which a cancel is "late" — mirrors the backend
 * `ChangeRules::DEFAULT_MINIMUM_NOTICE_MINUTES` (120 minutes).
 */
export const LATE_CANCEL_NOTICE_HOURS = 2;

type Translate = (key: string) => string;

/**
 * Maps a classified action error to a UI-ready message: the domain kind keeps
 * the server's own (translated) text; the rest resolve to i18n keys.
 * `validation` returns null — its field errors render on the fields/summary.
 */
export function sessionActionErrorMessage(
  outcome: Pick<SessionActionErrorOutcome, 'kind' | 'message'>,
  t: Translate,
): string | null {
  switch (outcome.kind) {
    case 'domain':
      return outcome.message;
    case 'forbidden':
      return t('session_actions.common.forbidden');
    case 'not_found':
      return t('session_actions.common.not_found');
    case 'network':
      return t('common.network_error');
    case 'server':
      return t('common.server_error');
    case 'validation':
      return null;
    default:
      return t('common.error');
  }
}

export interface RescheduleFieldErrorMap {
  fields: Partial<Record<'date' | 'duration' | 'timezone' | 'reason', string>>;
  formError: string | null;
}

const RESCHEDULE_SERVER_FIELD_MAP: Record<string, keyof RescheduleFieldErrorMap['fields']> = {
  scheduled_at: 'date',
  duration_minutes: 'duration',
  timezone: 'timezone',
  reason: 'reason',
};

/** Maps reschedule server field errors to form fields; anything else is form-level. */
export function rescheduleServerFieldErrors(
  fieldErrors: Record<string, string[]>,
): RescheduleFieldErrorMap {
  const fields: RescheduleFieldErrorMap['fields'] = {};
  let formError: string | null = null;
  for (const [key, messages] of Object.entries(fieldErrors)) {
    const message = messages[0];
    if (message === undefined) continue;
    const target = RESCHEDULE_SERVER_FIELD_MAP[key];
    if (target) fields[target] = message;
    else if (formError === null) formError = message;
  }
  return { fields, formError };
}

/** Time zone choices for the reschedule dialog: preferred zones first, then the full IANA list. */
export function buildTimeZoneOptions(
  ...preferred: (string | null | undefined)[]
): string[] {
  const preferredZones = [...new Set(preferred.filter((z): z is string => Boolean(z)))];
  let all: string[] = [];
  try {
    if (typeof Intl.supportedValuesOf === 'function') {
      all = Intl.supportedValuesOf('timeZone');
    }
  } catch {
    all = [];
  }
  const rest = all.filter((z) => !preferredZones.includes(z));
  const options = [...preferredZones, ...rest];
  if (!options.includes('UTC')) options.push('UTC');
  return options;
}

/** Formats an instant for the reschedule success message, in the viewer's zone and locale. */
export function formatRescheduleWhen(iso: string, timeZone: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    timeZone,
    timeZoneName: 'short',
  }).format(new Date(iso));
}

/** i18n key for the attendance mark-button aria-label. */
export function attendanceMarkLabelKey(self: boolean, status: 'attended' | 'absent'): string {
  if (self) return `session_actions.attendance.mark_self_${status}`;
  return status === 'attended'
    ? 'session_actions.attendance.mark_attended'
    : 'session_actions.attendance.mark_absent';
}

export type SessionActionName = 'reschedule' | 'cancel' | 'complete';

export interface SessionActionsPresentation {
  allowed: SessionActionName[];
  blocked: { action: SessionActionName; reasonKey: string }[];
}

const SESSION_ACTION_ORDER: SessionActionName[] = ['reschedule', 'cancel', 'complete'];

/**
 * Panel presentation for the detail's available_actions: allowed actions get a
 * trigger; `forbidden` is hidden entirely; any other reason is listed with its
 * translated reason so the user understands why the action is unavailable.
 */
export function sessionActionsPresentation(
  actions: SessionAvailableActions | null | undefined,
): SessionActionsPresentation {
  const presentation: SessionActionsPresentation = { allowed: [], blocked: [] };
  if (!actions) return presentation;
  for (const action of SESSION_ACTION_ORDER) {
    const entry = actions[action];
    if (entry.allowed) {
      presentation.allowed.push(action);
      continue;
    }
    if (entry.reason === 'forbidden' || entry.reason === null) continue;
    presentation.blocked.push({
      action,
      reasonKey: actionReasonKey(entry.reason) ?? 'session_actions.reasons.unknown',
    });
  }
  return presentation;
}
