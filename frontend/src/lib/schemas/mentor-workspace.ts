import { z } from 'zod';

export const MAX_MENTOR_FILE_SIZE_BYTES = 25 * 1024 * 1024;

const uploadedFile = z.custom<File>(
  (value) => typeof File !== 'undefined' && value instanceof File && value.size > 0,
  { message: 'Select a file.' },
).refine(
  (file) => file.size <= MAX_MENTOR_FILE_SIZE_BYTES,
  { message: 'File must be 25 MB or smaller.' },
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
  title: z.string().trim().min(3).max(160),
  description: z.string().trim().max(2000).optional(),
  content_file: uploadedFile.refine((file) => supportedMentorMediaTypes.has(file.type), { message: 'Select a supported file.' }),
  due_at: z.string().optional(),
});

export const mentorFeedbackSchema = z.object({
  note: z.string().trim().max(2000).optional(),
  feedback_file: uploadedFile.optional().refine(
    (file) => !file || (
      supportedMentorMediaTypes.has(file.type)
      && (file.type.startsWith('audio/') || file.type.startsWith('video/'))
    ),
    { message: 'Select a supported audio or video file.' },
  ),
}).refine(
  ({ note, feedback_file }) => Boolean(note?.trim() || feedback_file),
  { path: ['note'], message: 'Enter a note or select a file.' },
);

const optionalScore = z.preprocess(
  (value) => value === '' || value === null || value === undefined ? undefined : value,
  z.coerce.number().min(0).max(100).optional(),
);

export const mentorObservationSchema = z.object({
  focus: optionalScore,
  regulation: optionalScore,
  stress: optionalScore,
  energy: optionalScore,
  note: z.string().trim().max(2000).optional(),
});

export type MentorChallengeFormValues = z.infer<typeof mentorChallengeSchema>;
export type MentorFeedbackFormValues = z.infer<typeof mentorFeedbackSchema>;
export type MentorObservationFormValues = z.infer<typeof mentorObservationSchema>;
