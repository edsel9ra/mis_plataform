import { z } from 'zod';

const CHECK_IN_VALUES = ['1', '2', '3', '4', '5'];

export const emotionalCheckInSchema = z.object({
  mood_score: z.string({ error: 'mentee.validation.mood_required' })
    .min(1, 'mentee.validation.mood_required')
    .refine((value) => CHECK_IN_VALUES.includes(value), {
      message: 'mentee.validation.mood_required',
    }),
  stress_score: z.string().refine(
    (value) => value === '' || CHECK_IN_VALUES.includes(value),
    { message: 'mentee.validation.stress_invalid' },
  ),
  note: z.string().trim().max(10000, 'mentee.validation.note_max'),
});

export type EmotionalCheckInFormValues = z.infer<typeof emotionalCheckInSchema>;

export interface EmotionalCheckInPayload {
  mood_score: number;
  stress_score: number | null;
  note: string | null;
}

export function toEmotionalCheckInPayload(values: EmotionalCheckInFormValues): EmotionalCheckInPayload {
  const note = values.note.trim();

  return {
    mood_score: Number(values.mood_score),
    stress_score: values.stress_score === '' ? null : Number(values.stress_score),
    note: note === '' ? null : note,
  };
}
