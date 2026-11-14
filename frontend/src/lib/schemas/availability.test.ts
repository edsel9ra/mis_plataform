import { describe, expect, it } from 'vitest';

import {
  availabilityExceptionSchema,
  availabilityWindowSchema,
  toExceptionPayload,
} from './availability';

const issues = (result: ReturnType<typeof availabilityWindowSchema.safeParse>) =>
  result.success ? [] : result.error.issues.map((issue) => ({ path: issue.path, message: issue.message }));

describe('availabilityWindowSchema (T017-03)', () => {
  const valid = {
    day_of_week: '2',
    start_time: '09:00',
    end_time: '13:00',
    timezone: 'America/Mexico_City',
  };

  it('accepts a valid window and coerces the weekday', () => {
    const result = availabilityWindowSchema.safeParse(valid);

    expect(result.success).toBe(true);
    if (result.success) expect(result.data.day_of_week).toBe(2);
  });

  it('bounds the weekday to 0..6 (0 = domingo)', () => {
    expect(availabilityWindowSchema.safeParse({ ...valid, day_of_week: '0' }).success).toBe(true);
    expect(availabilityWindowSchema.safeParse({ ...valid, day_of_week: '7' }).success).toBe(false);
    expect(availabilityWindowSchema.safeParse({ ...valid, day_of_week: '-1' }).success).toBe(false);
  });

  it('rejects end_time not after start_time on the end_time field', () => {
    const result = issues(availabilityWindowSchema.safeParse({ ...valid, end_time: '09:00' }));

    expect(result).toContainEqual({
      path: ['end_time'],
      message: 'availability.validation.end_time_order',
    });
  });

  it('rejects invalid times and timezones', () => {
    const badTime = issues(availabilityWindowSchema.safeParse({ ...valid, start_time: '9:00' }));

    expect(badTime[0]?.message).toBe('session_actions.validation.time_invalid');
    expect(availabilityWindowSchema.safeParse({ ...valid, timezone: 'Mars/Olympus' }).success).toBe(false);
  });
});

describe('availabilityExceptionSchema (T017-03)', () => {
  const valid = {
    mode: 'block',
    start_date: '2026-12-24',
    end_date: '',
    start_time: '',
    end_time: '',
    timezone: 'UTC',
    reason: '',
  };

  it('accepts an all-day single-date exception, normalizing blanks to null', () => {
    const result = availabilityExceptionSchema.safeParse(valid);

    expect(result.success).toBe(true);
    if (result.success) {
      expect(result.data.end_date).toBeNull();
      expect(result.data.start_time).toBeNull();
    }
  });

  it('rejects an end_date before start_date', () => {
    const result = availabilityExceptionSchema.safeParse({ ...valid, end_date: '2026-12-20' });

    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues[0]).toMatchObject({
        path: ['end_date'],
        message: 'availability.validation.end_date_order',
      });
    }
  });

  it('requires both times or neither, on the missing one', () => {
    const onlyStart = availabilityExceptionSchema.safeParse({ ...valid, start_time: '10:00' });

    expect(onlyStart.success).toBe(false);
    if (!onlyStart.success) {
      expect(onlyStart.error.issues[0]).toMatchObject({
        path: ['end_time'],
        message: 'availability.validation.times_paired',
      });
    }
  });

  it('rejects a band that does not end after it starts, and invalid mode', () => {
    const band = availabilityExceptionSchema.safeParse({
      ...valid, start_time: '12:00', end_time: '12:00',
    });

    expect(band.success).toBe(false);
    expect(availabilityExceptionSchema.safeParse({ ...valid, mode: 'other' }).success).toBe(false);
  });

  it('builds the server payload with nulls for absent optionals', () => {
    const parsed = availabilityExceptionSchema.parse({
      ...valid, end_date: '2026-12-26', start_time: '10:00', end_time: '12:00', reason: '  viaje ',
    });

    expect(toExceptionPayload(parsed)).toEqual({
      mode: 'block',
      start_date: '2026-12-24',
      end_date: '2026-12-26',
      start_time: '10:00',
      end_time: '12:00',
      timezone: 'UTC',
      reason: 'viaje',
    });
  });
});
