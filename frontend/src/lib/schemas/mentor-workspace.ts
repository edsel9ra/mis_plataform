import { z } from 'zod';

import { MAX_MEDIA_FILE_SIZE_BYTES, SUPPORTED_MEDIA_TYPES } from '@/lib/media-types';
import { optionalFileInput } from '@/lib/schemas/file-input';

const V = (key: string) => `mentor_workspace.validation.${key}`;

export const MAX_MENTOR_FILE_SIZE_BYTES = MAX_MEDIA_FILE_SIZE_BYTES;

const uploadedFile = z.custom<File>(
  (value) => typeof File !== 'undefined' && value instanceof File && value.size > 0,
  { message: V('file_required') },
).refine(
  (file) => file.size <= MAX_MENTOR_FILE_SIZE_BYTES,
  { message: V('file_too_large') },
);

const supportedMentorMediaTypes = SUPPORTED_MEDIA_TYPES;

export const mentorChallengeSchema = z.object({
  title: z.string().trim().min(3, { error: V('title_min') }).max(160, { error: V('title_max') }),
  description: z.string().trim().max(2000, { error: V('text_max') }).optional(),
  content_file: uploadedFile.refine((file) => supportedMentorMediaTypes.has(file.type), { message: V('file_unsupported') }),
  due_at: z.string().optional(),
});

export const mentorFeedbackSchema = z.object({
  note: z.string().trim().max(2000, { error: V('text_max') }).optional(),
  feedback_file: optionalFileInput(uploadedFile).refine(
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
export type MentorFeedbackFormInput = z.input<typeof mentorFeedbackSchema>;
export type MentorFeedbackFormValues = z.infer<typeof mentorFeedbackSchema>;
export type MentorObservationFormInput = z.input<typeof mentorObservationSchema>;
export type MentorObservationFormValues = z.output<typeof mentorObservationSchema>;
