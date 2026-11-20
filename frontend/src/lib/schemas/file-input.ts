import { z } from 'zod';

/**
 * `form.register('file')` hands the raw `input.files` (a FileList, possibly
 * empty) to the resolver. An empty list would fail the field schema and block
 * note-only submissions, so optional file fields normalize first (PR #9
 * review): an array-like that is not a Blob collapses to its first item or
 * undefined; anything else passes through untouched.
 */
export function normalizeFileInput(value: unknown): unknown {
  if (
    value !== null
    && typeof value === 'object'
    && !(typeof Blob !== 'undefined' && value instanceof Blob)
    && typeof (value as { length?: unknown }).length === 'number'
  ) {
    return (value as Record<number, unknown>)[0] ?? undefined;
  }

  return value;
}

/** Optional file field that tolerates FileList values coming from RHF. */
export function optionalFileInput<S extends z.ZodType>(schema: S) {
  return z.preprocess(normalizeFileInput, schema.optional());
}
