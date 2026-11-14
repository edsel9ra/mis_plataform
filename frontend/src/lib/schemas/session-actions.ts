import { z } from 'zod';

import { isExistingWallTime, wallTimeToInstant } from '../scheduling';

/**
 * Schemas de las acciones de sesión (spec 016, T016-06).
 *
 * Los límites de duración replican `config/scheduling.php`
 * (`min_duration_minutes: 15`, `max_duration_minutes: 480`) del backend.
 */

export const RESCHEDULE_MIN_DURATION = 15;
export const RESCHEDULE_MAX_DURATION = 480;

/** Aliased session-level names for spec 017 (booking reuses the same bounds). */
export const SESSION_MIN_DURATION = RESCHEDULE_MIN_DURATION;
export const SESSION_MAX_DURATION = RESCHEDULE_MAX_DURATION;

const WALL_DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;
const WALL_TIME_PATTERN = /^([01]\d|2[0-3]):[0-5]\d$/;

const ianaTimezone = z
  .string()
  .trim()
  .min(1, 'session_actions.validation.timezone_required')
  .refine((value) => {
    try {
      new Intl.DateTimeFormat('en-US', { timeZone: value });

      return true;
    } catch {
      return false;
    }
  }, { message: 'session_actions.validation.timezone_invalid' });

export const rescheduleSessionSchema = z.object({
  date: z
    .string()
    .trim()
    .min(1, 'session_actions.validation.date_required')
    .regex(WALL_DATE_PATTERN, 'session_actions.validation.date_invalid'),
  time: z
    .string()
    .trim()
    .min(1, 'session_actions.validation.time_required')
    .regex(WALL_TIME_PATTERN, 'session_actions.validation.time_invalid'),
  duration_minutes: z.coerce
    .number('session_actions.validation.duration_invalid')
    .int('session_actions.validation.duration_invalid')
    .min(RESCHEDULE_MIN_DURATION, 'session_actions.validation.duration_invalid')
    .max(RESCHEDULE_MAX_DURATION, 'session_actions.validation.duration_invalid'),
  timezone: ianaTimezone,
  reason: z.string().trim().optional(),
}).superRefine((values, ctx) => {
  // CA-3.4: a reschedule is an explicit time change, never a silent shift.
  // A wall time inside a DST gap does not exist and must be rejected instead
  // of being silently moved to a neighboring instant.
  if (
    WALL_DATE_PATTERN.test(values.date)
    && WALL_TIME_PATTERN.test(values.time)
    && !isExistingWallTime(values.date, values.time, values.timezone)
  ) {
    ctx.addIssue({
      code: 'custom',
      path: ['time'],
      message: 'session_actions.validation.time_nonexistent',
    });
  }
});

export type RescheduleSessionFormInput = z.input<typeof rescheduleSessionSchema>;
export type RescheduleSessionFormData = z.infer<typeof rescheduleSessionSchema>;

export const cancelSessionSchema = z.object({
  // Backend `CancelSessionRequest`: required string, not blank, no max.
  reason: z.string().trim().min(1, 'session_actions.validation.reason_required'),
});

export type CancelSessionFormData = z.infer<typeof cancelSessionSchema>;

export const attendanceSchema = z.object({
  status: z.enum(['attended', 'absent'], { error: 'session_actions.validation.status_invalid' }),
});

export type AttendanceFormData = z.infer<typeof attendanceSchema>;

export interface ReschedulePayload {
  scheduled_at: string;
  duration_minutes: number;
  timezone: string;
  reason?: string;
}

/** Builds the PUT reschedule payload: the server reads `scheduled_at` as an instant (UTC `Z`). */
export function toReschedulePayload(values: RescheduleSessionFormData): ReschedulePayload {
  if (!isExistingWallTime(values.date, values.time, values.timezone)) {
    throw new TypeError(
      `Wall time ${values.date} ${values.time} does not exist in ${values.timezone}`,
    );
  }
  const payload: ReschedulePayload = {
    scheduled_at: wallTimeToInstant(values.date, values.time, values.timezone),
    duration_minutes: values.duration_minutes,
    timezone: values.timezone,
  };
  const reason = values.reason?.trim();
  if (reason) payload.reason = reason;

  return payload;
}
