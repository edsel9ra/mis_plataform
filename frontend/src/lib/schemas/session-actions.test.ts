import { describe, expect, it } from 'vitest';

import {
  RESCHEDULE_MAX_DURATION,
  RESCHEDULE_MIN_DURATION,
  attendanceSchema,
  cancelSessionSchema,
  rescheduleSessionSchema,
  toReschedulePayload,
} from './session-actions';

const validReschedule = {
  date: '2026-11-20',
  time: '10:30',
  duration_minutes: '60',
  timezone: 'America/Mexico_City',
  reason: '',
};

describe('rescheduleSessionSchema (T016-06)', () => {
  it('accepts a valid form and coerces the duration string', () => {
    const result = rescheduleSessionSchema.safeParse(validReschedule);

    expect(result.success).toBe(true);
    if (result.success) expect(result.data.duration_minutes).toBe(60);
  });

  it('rejects malformed dates and times', () => {
    expect(rescheduleSessionSchema.safeParse({ ...validReschedule, date: '20-11-2026' }).success).toBe(false);
    expect(rescheduleSessionSchema.safeParse({ ...validReschedule, time: '25:00' }).success).toBe(false);
  });

  it('enforces the backend duration bounds (15..480)', () => {
    for (const minutes of [RESCHEDULE_MIN_DURATION, RESCHEDULE_MAX_DURATION]) {
      expect(rescheduleSessionSchema.safeParse({ ...validReschedule, duration_minutes: String(minutes) }).success)
        .toBe(true);
    }
    for (const minutes of [RESCHEDULE_MIN_DURATION - 1, RESCHEDULE_MAX_DURATION + 1]) {
      expect(rescheduleSessionSchema.safeParse({ ...validReschedule, duration_minutes: String(minutes) }).success)
        .toBe(false);
    }
    expect(rescheduleSessionSchema.safeParse({ ...validReschedule, duration_minutes: '30.5' }).success).toBe(false);
  });

  it('rejects unknown or empty timezones', () => {
    expect(rescheduleSessionSchema.safeParse({ ...validReschedule, timezone: '' }).success).toBe(false);
    expect(rescheduleSessionSchema.safeParse({ ...validReschedule, timezone: 'Not/AZone' }).success).toBe(false);
  });
});

describe('cancelSessionSchema (T016-06)', () => {
  it('accepts a non-empty reason and trims it', () => {
    const result = cancelSessionSchema.safeParse({ reason: '  conflict de agenda  ' });

    expect(result.success).toBe(true);
    if (result.success) expect(result.data.reason).toBe('conflict de agenda');
  });

  it('rejects empty and whitespace-only reasons', () => {
    expect(cancelSessionSchema.safeParse({ reason: '' }).success).toBe(false);
    expect(cancelSessionSchema.safeParse({ reason: '   ' }).success).toBe(false);
  });
});

describe('attendanceSchema (T016-06)', () => {
  it('accepts attended and absent only', () => {
    expect(attendanceSchema.safeParse({ status: 'attended' }).success).toBe(true);
    expect(attendanceSchema.safeParse({ status: 'absent' }).success).toBe(true);
    expect(attendanceSchema.safeParse({ status: 'maybe' }).success).toBe(false);
  });
});

describe('toReschedulePayload (T016-06)', () => {
  it('converts the wall time to an ISO UTC instant ending in Z', () => {
    const payload = toReschedulePayload({
      date: '2026-11-20',
      time: '10:30',
      duration_minutes: 60,
      timezone: 'America/Mexico_City',
    });

    expect(payload).toEqual({
      scheduled_at: '2026-11-20T16:30:00Z',
      duration_minutes: 60,
      timezone: 'America/Mexico_City',
    });
  });

  it('includes a non-empty trimmed reason and omits an empty one', () => {
    expect(toReschedulePayload({
      date: '2026-11-20',
      time: '10:30',
      duration_minutes: 60,
      timezone: 'America/Mexico_City',
      reason: '  conflicto  ',
    }).reason).toBe('conflicto');

    expect('reason' in toReschedulePayload({
      date: '2026-11-20',
      time: '10:30',
      duration_minutes: 60,
      timezone: 'America/Mexico_City',
      reason: '   ',
    })).toBe(false);
  });
});

describe('rescheduleSessionSchema DST gap (T016-06 rework)', () => {
  const newYork = { timezone: 'America/New_York' };

  it('rejects a nonexistent time inside the spring-forward gap', () => {
    const result = rescheduleSessionSchema.safeParse({
      date: '2026-03-08',
      time: '02:30',
      duration_minutes: '60',
      ...newYork,
    });

    expect(result.success).toBe(false);
    if (!result.success) {
      const issue = result.error.issues.find((i) => i.path.join('.') === 'time');
      expect(issue?.message).toBe('session_actions.validation.time_nonexistent');
    }
  });

  it('accepts times just outside the gap and ambiguous fall-back times', () => {
    for (const [date, time] of [['2026-03-08', '01:30'], ['2026-03-08', '03:30'], ['2026-11-01', '01:30']]) {
      expect(rescheduleSessionSchema.safeParse({
        date, time, duration_minutes: '60', ...newYork,
      }).success, `${date} ${time}`).toBe(true);
    }
  });

  it('makes toReschedulePayload throw TypeError on a nonexistent time', () => {
    expect(() => toReschedulePayload({
      date: '2026-03-08',
      time: '02:30',
      duration_minutes: 60,
      timezone: 'America/New_York',
    })).toThrow(TypeError);
  });
});
