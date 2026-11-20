import { z } from 'zod';

const V = (key: string) => `mentor_workspace.validation.${key}`;

export const MAX_MENTOR_FILE_SIZE_BYTES = 25 * 1024 * 1024;

const uploadedFile = z.custom<File>(
  (value) => typeof File !== 'undefined' && value instanceof File && value.size > 0,
  { message: V('file_required') },
).refine(
  (file) => file.size <= MAX_MENTOR_FILE_SIZE_BYTES,
  { message: V('file_too_large') },
);

const supportedMentorMediaTypes = new Set([
  'image/jpeg',
  'image/png',
  'image/webp',
  'application/pdf',
  'text/plain',
  'text/csv',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'audio/mpeg',
  'audio/wav',
  'audio/x-wav',
  'audio/mp4',
  'audio/webm',
  'video/mp4',
  'video/quicktime',
  'video/webm',
]);

export const mentorChallengeSchema = z.object({
  title: z.string().trim().min(3, { error: V('title_min') }).max(160, { error: V('title_max') }),
  description: z.string().trim().max(2000, { error: V('text_max') }).optional(),
  content_file: uploadedFile.refine((file) => supportedMentorMediaTypes.has(file.type), { message: V('file_unsupported') }),
  due_at: z.string().optional(),
});

export const mentorFeedbackSchema = z.object({
  note: z.string().trim().max(2000, { error: V('text_max') }).optional(),
  feedback_file: uploadedFile.optional().refine(
    (file) => !file || (
      supportedMentorMediaTypes.has(file.type)
      && (file.type.startsWith('audio/') || file.type.startsWith('video/'))
    ),
    { message: V('media_unsupported') },
  ),
}).refine(
  ({ note, feedback_file }) => Boolean(note?.trim() || feedback_file),
  { path: ['note'], message: V('note_or_file') },
);

const optionalScore = z.preprocess(
  (value) => value === '' || value === null || value === undefined ? undefined : value,
  z.coerce.number({ error: V('score_invalid') }).min(0, { error: V('score_range') }).max(100, { error: V('score_range') }).optional(),
);

export const mentorObservationSchema = z.object({
  focus: optionalScore,
  regulation: optionalScore,
  stress: optionalScore,
  energy: optionalScore,
  note: z.string().trim().max(2000, { error: V('text_max') }).optional(),
});

export type MentorChallengeFormValues = z.infer<typeof mentorChallengeSchema>;
export type MentorFeedbackFormValues = z.infer<typeof mentorFeedbackSchema>;
export type MentorObservationFormInput = z.input<typeof mentorObservationSchema>;
export type MentorObservationFormValues = z.output<typeof mentorObservationSchema>;
