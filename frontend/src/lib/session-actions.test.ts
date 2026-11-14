import { describe, expect, it } from 'vitest';

import enMessages from '../i18n/messages/en/common.json';
import esMessages from '../i18n/messages/es/common.json';
import ptMessages from '../i18n/messages/pt/common.json';
import { ApiError } from './api';
import {
  BLOCK_REASON_CODES,
  actionReasonKey,
  attendanceTargetsPresentation,
  parseAvailableActions,
  sessionActionErrorMessage,
  sessionActionErrorOutcome,
  sessionActionInvalidationKeys,
  attendanceMarkLabelKey,
  buildTimeZoneOptions,
  formatRescheduleWhen,
  rescheduleServerFieldErrors,
  sessionActionsPresentation,
  type SessionAvailableActions,
} from './session-actions';

describe('actionReasonKey (T016-06)', () => {
  it('maps every known reason code to its catalog key', () => {
    for (const code of BLOCK_REASON_CODES) {
      expect(actionReasonKey(code)).toBe(`session_actions.reasons.${code}`);
    }
  });

  it('maps unknown non-null reasons to the generic key', () => {
    expect(actionReasonKey('surprise_reason')).toBe('session_actions.reasons.unknown');
  });

  it('returns null for nullish reasons', () => {
    expect(actionReasonKey(null)).toBeNull();
    expect(actionReasonKey(undefined)).toBeNull();
  });
});

describe('parseAvailableActions (T016-06)', () => {
  const valid: SessionAvailableActions = {
    reschedule: { allowed: true, reason: null },
    cancel: { allowed: false, reason: 'terminal_status', late: false },
    complete: { allowed: false, reason: 'not_ended' },
    attendance: [
      {
        user_id: 'u-1',
        self: true,
        allowed: false,
        reason: 'already_confirmed_by_member',
        current_status: 'attended',
      },
    ],
  };

  it('returns a well-formed payload as-is', () => {
    expect(parseAvailableActions(valid)).toEqual(valid);
  });

  it('returns null for missing or malformed payloads', () => {
    for (const raw of [null, undefined, 42, 'x', [], {}, { reschedule: true }]) {
      expect(parseAvailableActions(raw)).toBeNull();
    }
  });

  it('drops malformed attendance entries but keeps valid ones', () => {
    const parsed = parseAvailableActions({
      ...valid,
      attendance: [
        valid.attendance[0],
        { user_id: 42, self: 'yes', allowed: 'no' },
        null,
        'junk',
      ],
    });

    expect(parsed?.attendance).toEqual(valid.attendance);
  });

  it('normalizes missing cancel.late to false', () => {
    const parsed = parseAvailableActions({
      ...valid,
      cancel: { allowed: true, reason: null },
    });

    expect(parsed?.cancel).toEqual({ allowed: true, reason: null, late: false });
  });
});

describe('attendanceTargetsPresentation (T016-06)', () => {
  const attendees = [
    { id: 'att-1', user: { id: 'u-2', name: 'Member Two', full_name: 'Member Dos', avatar: null, role: 'mentee' } },
    { id: 'att-2', user: { id: 'u-1', name: 'Self', full_name: 'Yo Mismo', avatar: null, role: 'mentee' } },
    { id: 'att-3', user: { id: 'u-3', name: 'Member Three', full_name: 'Member Tres', avatar: null, role: 'mentee' } },
  ];

  const entries = [
    { user_id: 'u-2', self: false, allowed: true, reason: null, current_status: 'not_confirmed' },
    { user_id: 'u-1', self: true, allowed: false, reason: 'not_ended', current_status: 'attended' },
    { user_id: 'u-3', self: false, allowed: false, reason: 'not_chargeable', current_status: 'absent' },
  ];

  it('orders self first and keeps server order for the rest', () => {
    const presentation = attendanceTargetsPresentation(entries, attendees);

    expect(presentation.map((p) => p.userId)).toEqual(['u-1', 'u-2', 'u-3']);
  });

  it('joins display names from the session detail attendees', () => {
    const presentation = attendanceTargetsPresentation(entries, attendees);

    expect(presentation.map((p) => p.name)).toEqual(['Yo Mismo', 'Member Dos', 'Member Tres']);
  });

  it('maps each entry to its reason key and status key', () => {
    const [self, member, chargeable] = attendanceTargetsPresentation(entries, attendees);

    expect(self.reasonKey).toBe('session_actions.reasons.not_ended');
    expect(member.reasonKey).toBeNull();
    expect(chargeable.reasonKey).toBe('session_actions.reasons.not_chargeable');
    expect(self.currentStatusKey).toBe('session_actions.attendance_status.attended');
    expect(member.currentStatusKey).toBe('session_actions.attendance_status.not_confirmed');
    expect(chargeable.currentStatusKey).toBe('session_actions.attendance_status.absent');
  });

  it('leaves the name null when no attendee row matches', () => {
    const presentation = attendanceTargetsPresentation(entries, []);

    expect(presentation.map((p) => p.name)).toEqual([null, null, null]);
  });
});

describe('session_actions i18n catalogs (T016-06)', () => {
  const catalogs: Record<string, Record<string, Record<string, unknown>>> = {
    es: esMessages.session_actions,
    en: enMessages.session_actions,
    pt: ptMessages.session_actions,
  };

  it('has a reason message for every code plus unknown in all locales', () => {
    for (const [locale, catalog] of Object.entries(catalogs)) {
      for (const code of [...BLOCK_REASON_CODES, 'unknown']) {
        expect(catalog.reasons?.[code], `${locale} session_actions.reasons.${code}`)
          .toBeTruthy();
      }
    }
  });

  it('has every attendance status message in all locales', () => {
    for (const [locale, catalog] of Object.entries(catalogs)) {
      for (const status of ['not_confirmed', 'attended', 'absent']) {
        expect(catalog.attendance_status?.[status], `${locale} session_actions.attendance_status.${status}`)
          .toBeTruthy();
      }
    }
  });
});

describe('attendanceTargetsPresentation unknown status (T016-06 rework)', () => {
  it('maps an unknown current_status to a null status key', () => {
    const [target] = attendanceTargetsPresentation(
      [{ user_id: 'u-1', self: true, allowed: false, reason: null, current_status: 'mystery' }],
      [],
    );

    expect(target.currentStatus).toBe('mystery');
    expect(target.currentStatusKey).toBeNull();
  });
});

describe('sessionActionInvalidationKeys (T016-07)', () => {
  it('covers the detail and every sessions-root query', () => {
    const keys = sessionActionInvalidationKeys('session-1');

    expect(keys).toEqual([['sessions', 'session-1'], ['sessions']]);
  });

  it('prefix-matches ranged calendar queries under the sessions root', () => {
    const rangeKey = ['sessions', 'range', { from: 'a', to: 'b', includeCollectives: false }];
    const listKey = ['sessions', { page: 1, per_page: 20 }];
    const root = sessionActionInvalidationKeys('x')[1];

    for (const key of [rangeKey, listKey]) {
      const prefix = root as readonly unknown[];
      expect(key.slice(0, prefix.length)).toEqual([...prefix]);
    }
  });
});

describe('sessionActionErrorOutcome (T016-07)', () => {
  const err = (status: number, errors?: Record<string, string[]>, code: 'http' | 'aborted' | 'timeout' | 'network' = 'http') =>
    new ApiError('fail', status, errors, code);

  it('classifies a 422 carrying only the session key as a domain rejection', () => {
    const outcome = sessionActionErrorOutcome(err(422, { session: ['La sesión ya está cerrada.'] }));

    expect(outcome).toEqual({
      kind: 'domain',
      fieldErrors: { session: ['La sesión ya está cerrada.'] },
      message: 'La sesión ya está cerrada.',
      staleDetail: true,
    });
  });

  it('classifies a 422 with field keys as validation, keeping session field errors', () => {
    const outcome = sessionActionErrorOutcome(err(422, {
      scheduled_at: ['La fecha no es válida.'],
      session: ['Estado no elegible.'],
    }));

    expect(outcome.kind).toBe('validation');
    expect(outcome.fieldErrors).toEqual({
      scheduled_at: ['La fecha no es válida.'],
      session: ['Estado no elegible.'],
    });
    expect(outcome.staleDetail).toBe(true);
    expect(outcome.message).toBeNull();
  });

  it('classifies a plain field-only 422 without stale detail', () => {
    const outcome = sessionActionErrorOutcome(err(422, { reason: ['Obligatorio.'] }));

    expect(outcome.kind).toBe('validation');
    expect(outcome.staleDetail).toBe(false);
  });

  it('classifies 403 and 404 as forbidden/not_found with stale detail', () => {
    expect(sessionActionErrorOutcome(err(403))).toMatchObject({ kind: 'forbidden', staleDetail: true, message: null });
    expect(sessionActionErrorOutcome(err(404))).toMatchObject({ kind: 'not_found', staleDetail: true, message: null });
  });

  it('classifies network failures and server errors', () => {
    expect(sessionActionErrorOutcome(err(0, undefined, 'network'))).toMatchObject({ kind: 'network', staleDetail: false });
    expect(sessionActionErrorOutcome(err(0, undefined, 'timeout'))).toMatchObject({ kind: 'network' });
    expect(sessionActionErrorOutcome(err(500))).toMatchObject({ kind: 'server', staleDetail: false });
  });

  it('classifies aborted and non-ApiError as unknown', () => {
    expect(sessionActionErrorOutcome(err(0, undefined, 'aborted')).kind).toBe('unknown');
    expect(sessionActionErrorOutcome(new Error('boom')).kind).toBe('unknown');
    expect(sessionActionErrorOutcome('nope').kind).toBe('unknown');
  });
});

describe('sessionActionErrorMessage (T016-08)', () => {
  const t = (key: string) => `t:${key}`;

  it('keeps the server message for domain rejections', () => {
    expect(sessionActionErrorMessage({ kind: 'domain', message: 'Mensaje del servidor' }, t))
      .toBe('Mensaje del servidor');
  });

  it('maps forbidden and not_found to their translated notices', () => {
    expect(sessionActionErrorMessage({ kind: 'forbidden', message: null }, t))
      .toBe('t:session_actions.common.forbidden');
    expect(sessionActionErrorMessage({ kind: 'not_found', message: null }, t))
      .toBe('t:session_actions.common.not_found');
  });

  it('maps network/server/unknown to the shared error keys and validation to null', () => {
    expect(sessionActionErrorMessage({ kind: 'network', message: null }, t)).toBe('t:common.network_error');
    expect(sessionActionErrorMessage({ kind: 'server', message: null }, t)).toBe('t:common.server_error');
    expect(sessionActionErrorMessage({ kind: 'unknown', message: null }, t)).toBe('t:common.error');
    expect(sessionActionErrorMessage({ kind: 'validation', message: null }, t)).toBeNull();
  });
});

describe('rescheduleServerFieldErrors (T016-09)', () => {
  it('maps server field keys to form fields', () => {
    const r = rescheduleServerFieldErrors({
      scheduled_at: ['No cumple el aviso mínimo'],
      duration_minutes: ['Duración inválida'],
      timezone: ['Zona inválida'],
      reason: ['Motivo inválido'],
    });
    expect(r.fields).toEqual({
      date: 'No cumple el aviso mínimo',
      duration: 'Duración inválida',
      timezone: 'Zona inválida',
      reason: 'Motivo inválido',
    });
    expect(r.formError).toBeNull();
  });

  it('sends session and unknown keys to the form-level error', () => {
    const r = rescheduleServerFieldErrors({
      session: ['La sesión ya empezó'],
      relationship_id: ['nope'],
    });
    expect(r.fields).toEqual({});
    expect(r.formError).toBe('La sesión ya empezó');
  });

  it('keeps field errors and form error together', () => {
    const r = rescheduleServerFieldErrors({
      scheduled_at: ['bad slot'],
      session_type: ['bad type'],
    });
    expect(r.fields.date).toBe('bad slot');
    expect(r.formError).toBe('bad type');
  });

  it('returns empty fields and null form error for empty input', () => {
    expect(rescheduleServerFieldErrors({})).toEqual({ fields: {}, formError: null });
  });
});

describe('buildTimeZoneOptions (T016-09)', () => {
  it('includes the session and viewer zones plus the full Intl list', () => {
    const options = buildTimeZoneOptions('America/New_York', 'America/Mexico_City');
    expect(options).toContain('America/New_York');
    expect(options).toContain('America/Mexico_City');
    expect(options.length).toBeGreaterThan(100);
  });

  it('puts the preferred zones first and dedupes them', () => {
    const options = buildTimeZoneOptions('America/New_York', 'America/New_York');
    expect(options[0]).toBe('America/New_York');
    expect(options.filter((z) => z === 'America/New_York')).toHaveLength(1);
  });

  it('falls back to preferred zones plus UTC when Intl.supportedValuesOf is missing', () => {
    const original = Intl.supportedValuesOf;
    // @ts-expect-error simulate old runtime
    Intl.supportedValuesOf = undefined;
    try {
      expect(buildTimeZoneOptions('America/New_York', 'America/Mexico_City'))
        .toEqual(['America/New_York', 'America/Mexico_City', 'UTC']);
      expect(buildTimeZoneOptions(null, 'America/Mexico_City'))
        .toEqual(['America/Mexico_City', 'UTC']);
    } finally {
      Intl.supportedValuesOf = original;
    }
  });
});

describe('formatRescheduleWhen (T016-09)', () => {
  it('formats the instant in the viewer zone and locale', () => {
    const out = formatRescheduleWhen('2026-03-20T15:00:00Z', 'America/Mexico_City', 'es');
    expect(out).toContain('2026');
    expect(out.toLowerCase()).toContain('mar');
  });

  it('shows the same instant at different wall times per zone', () => {
    const mx = formatRescheduleWhen('2026-03-20T15:00:00Z', 'America/Mexico_City', 'en');
    const ny = formatRescheduleWhen('2026-03-20T15:00:00Z', 'America/New_York', 'en');
    expect(mx).toContain('9:00');
    expect(ny).toContain('11:00');
  });
});

describe('attendanceMarkLabelKey (T016-09)', () => {
  it('builds the self and member label keys', () => {
    expect(attendanceMarkLabelKey(true, 'attended')).toBe('session_actions.attendance.mark_self_attended');
    expect(attendanceMarkLabelKey(true, 'absent')).toBe('session_actions.attendance.mark_self_absent');
    expect(attendanceMarkLabelKey(false, 'attended')).toBe('session_actions.attendance.mark_attended');
    expect(attendanceMarkLabelKey(false, 'absent')).toBe('session_actions.attendance.mark_absent');
  });
});

describe('sessionActionsPresentation (T016-10)', () => {
  it('lists allowed actions in canonical order and hides forbidden', () => {
    const p = sessionActionsPresentation({
      reschedule: { allowed: true, reason: null },
      cancel: { allowed: false, reason: 'forbidden', late: false },
      complete: { allowed: true, reason: null },
      attendance: [],
    });
    expect(p.allowed).toEqual(['reschedule', 'complete']);
    expect(p.blocked).toEqual([]);
  });

  it('shows non-forbidden reasons with their key', () => {
    const p = sessionActionsPresentation({
      reschedule: { allowed: false, reason: 'insufficient_notice' },
      cancel: { allowed: false, reason: 'terminal_status', late: true },
      complete: { allowed: false, reason: 'terminal_status' },
      attendance: [],
    });
    expect(p.allowed).toEqual([]);
    expect(p.blocked).toEqual([
      { action: 'reschedule', reasonKey: 'session_actions.reasons.insufficient_notice' },
      { action: 'cancel', reasonKey: 'session_actions.reasons.terminal_status' },
      { action: 'complete', reasonKey: 'session_actions.reasons.terminal_status' },
    ]);
  });

  it('returns empty presentation for null/missing actions', () => {
    expect(sessionActionsPresentation(null)).toEqual({ allowed: [], blocked: [] });
    expect(sessionActionsPresentation(undefined)).toEqual({ allowed: [], blocked: [] });
  });

  it('maps unknown reasons to the unknown key', () => {
    const p = sessionActionsPresentation({
      reschedule: { allowed: false, reason: 'brand_new_reason' },
      cancel: { allowed: true, reason: null, late: false },
      complete: { allowed: false, reason: 'forbidden' },
      attendance: [],
    });
    expect(p.allowed).toEqual(['cancel']);
    expect(p.blocked).toEqual([
      { action: 'reschedule', reasonKey: 'session_actions.reasons.unknown' },
    ]);
  });
});
