import { z } from 'zod';

import { SESSION_MAX_DURATION, SESSION_MIN_DURATION } from './session-actions';

/**
 * Schema de reserva (spec 017, T017-03). Los límites replican
 * `config/scheduling.php`; se reutiliza la clave de duración de
 * `session_actions.validation` porque el mensaje es idéntico.
 */

export const bookSessionSchema = z.object({
  relationship_id: z.string().trim().min(1, 'booking.validation.relationship_required'),
  title: z
    .string()
    .trim()
    .min(1, 'booking.validation.title_required')
    .max(200, 'booking.validation.title_too_long'),
  description: z.string().trim().optional(),
  duration_minutes: z.coerce
    .number('session_actions.validation.duration_invalid')
    .int('session_actions.validation.duration_invalid')
    .min(SESSION_MIN_DURATION, 'session_actions.validation.duration_invalid')
    .max(SESSION_MAX_DURATION, 'session_actions.validation.duration_invalid'),
});

export type BookSessionFormInput = z.input<typeof bookSessionSchema>;
export type BookSessionFormData = z.infer<typeof bookSessionSchema>;

/** Duración suelta (paso 1 del flujo de reserva, antes de elegir hueco). */
export const bookingDurationSchema = bookSessionSchema.shape.duration_minutes;
