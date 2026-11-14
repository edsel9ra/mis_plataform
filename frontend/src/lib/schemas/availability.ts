import { z } from 'zod';

/**
 * Schemas del editor de disponibilidad (spec 017, T017-03).
 * `day_of_week`: contrato del backend PHP `w` — 0 domingo … 6 sábado.
 * Claves i18n `availability.validation.*`; se reutilizan las de
 * `session_actions.validation.*` donde el mensaje es idéntico.
 */

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

const wallTime = z
  .string()
  .trim()
  .min(1, 'session_actions.validation.time_required')
  .regex(WALL_TIME_PATTERN, 'session_actions.validation.time_invalid');

const wallDate = z
  .string()
  .trim()
  .min(1, 'session_actions.validation.date_required')
  .regex(WALL_DATE_PATTERN, 'session_actions.validation.date_invalid');

export const availabilityWindowSchema = z.object({
  day_of_week: z.coerce
    .number('availability.validation.day_of_week_required')
    .int('availability.validation.day_of_week_invalid')
    .min(0, 'availability.validation.day_of_week_invalid')
    .max(6, 'availability.validation.day_of_week_invalid'),
  start_time: wallTime,
  end_time: wallTime,
  timezone: ianaTimezone,
}).superRefine((values, ctx) => {
  if (
    WALL_TIME_PATTERN.test(values.start_time)
    && WALL_TIME_PATTERN.test(values.end_time)
    && values.end_time <= values.start_time
  ) {
    ctx.addIssue({
      code: 'custom',
      path: ['end_time'],
      message: 'availability.validation.end_time_order',
    });
  }
});

export type AvailabilityWindowFormInput = z.input<typeof availabilityWindowSchema>;
export type AvailabilityWindowFormData = z.infer<typeof availabilityWindowSchema>;

// Form fields send '' when the optional value is absent: normalize to null.
const blankToNull = (value: unknown): unknown => (value === '' ? null : value);

const optionalWallTime = z.preprocess(
  blankToNull,
  z
    .string()
    .trim()
    .regex(WALL_TIME_PATTERN, 'session_actions.validation.time_invalid')
    .nullable()
    .optional(),
);

export const availabilityExceptionSchema = z.object({
  mode: z.enum(['block', 'open'], { error: 'availability.validation.mode_invalid' }),
  start_date: wallDate,
  end_date: z.preprocess(blankToNull, wallDate.nullable().optional()),
  start_time: optionalWallTime,
  end_time: optionalWallTime,
  timezone: ianaTimezone,
  reason: z.string().trim().optional(),
}).superRefine((values, ctx) => {
  if (
    values.end_date !== null
    && values.end_date !== undefined
    && WALL_DATE_PATTERN.test(values.end_date)
    && WALL_DATE_PATTERN.test(values.start_date)
    && values.end_date < values.start_date
  ) {
    ctx.addIssue({
      code: 'custom',
      path: ['end_date'],
      message: 'availability.validation.end_date_order',
    });
  }

  // Franja: ambas horas o ninguna (día completo); fin posterior al inicio.
  const startTime = values.start_time ?? null;
  const endTime = values.end_time ?? null;
  if ((startTime === null) !== (endTime === null)) {
    const target = startTime === null ? 'start_time' : 'end_time';
    ctx.addIssue({ code: 'custom', path: [target], message: 'availability.validation.times_paired' });
  } else if (
    startTime !== null
    && endTime !== null
    && WALL_TIME_PATTERN.test(startTime)
    && WALL_TIME_PATTERN.test(endTime)
    && endTime <= startTime
  ) {
    ctx.addIssue({
      code: 'custom',
      path: ['end_time'],
      message: 'availability.validation.end_time_order',
    });
  }
});

export type AvailabilityExceptionFormInput = z.input<typeof availabilityExceptionSchema>;
export type AvailabilityExceptionFormData = z.infer<typeof availabilityExceptionSchema>;

/** Builds the POST/PUT exception payload: absent paired times and dates become nulls. */
export function toExceptionPayload(values: AvailabilityExceptionFormData) {
  return {
    mode: values.mode,
    start_date: values.start_date,
    end_date: values.end_date ?? null,
    start_time: values.start_time ?? null,
    end_time: values.end_time ?? null,
    timezone: values.timezone,
    ...(values.reason?.trim() ? { reason: values.reason.trim() } : {}),
  };
}
