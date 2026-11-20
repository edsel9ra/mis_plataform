/**
 * Shared upload constraints for user media. Mirrors the server-side list in
 * `config/mentor.media.mimetypes` / `max_size_kb` (25 MB) used by both mentor
 * uploads and objective-evidence uploads.
 */
export const MAX_MEDIA_FILE_SIZE_BYTES = 25 * 1024 * 1024;

export const SUPPORTED_MEDIA_TYPES: ReadonlySet<string> = new Set([
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
