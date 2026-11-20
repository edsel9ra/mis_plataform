import { z } from 'zod';
import { sexOptions } from './auth';
import { mapServerFieldErrors } from '@/lib/availability';

export const localeOptions = ['es', 'en', 'pt'] as const;

export const profileSchema = z.object({
  name: z.string().trim().min(2).max(100).optional(),
  last_name: z.string().trim().max(100).optional(),
  sex: z.enum(sexOptions).optional(),
  birth_date: z.string().optional(),
  timezone: z.string().trim().max(50).optional(),
  locale: z.enum(localeOptions).optional(),
});

export type ProfileFormData = z.infer<typeof profileSchema>;

export const profileDetailsSchema = z.object({
  name: z
    .string()
    .trim()
    .min(1, 'profile.validation.name_required')
    .max(100, 'profile.validation.name_max'),
  last_name: z.string().trim().max(100, 'profile.validation.last_name_max'),
  timezone: z.string().trim().min(1, 'profile.validation.timezone_required'),
});

export type ProfileDetailsFormValues = z.infer<typeof profileDetailsSchema>;

export function toProfileDetailsPayload(values: ProfileDetailsFormValues) {
  return {
    name: values.name,
    last_name: values.last_name || null,
    timezone: values.timezone,
  };
}

export type ProfileDetailsPayload = ReturnType<typeof toProfileDetailsPayload>;

const PROFILE_DETAILS_SERVER_FIELDS = new Set(['name', 'last_name', 'timezone']);

export function profileServerFieldErrors(fieldErrors: Record<string, string[]>) {
  return mapServerFieldErrors(fieldErrors, PROFILE_DETAILS_SERVER_FIELDS);
}

const AVATAR_SERVER_FIELDS = new Set(['avatar']);

export function avatarServerFieldErrors(fieldErrors: Record<string, string[]>) {
  return mapServerFieldErrors(fieldErrors, AVATAR_SERVER_FIELDS);
}

export const AVATAR_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'] as const;
export const AVATAR_MAX_BYTES = 2 * 1024 * 1024;

export const avatarSchema = z.object({
  avatar: z
    .instanceof(File, { message: 'profile.validation.avatar_required' })
    .refine((file) => (AVATAR_MIME_TYPES as readonly string[]).includes(file.type), {
      message: 'profile.validation.avatar_type',
    })
    .refine((file) => file.size <= AVATAR_MAX_BYTES, { message: 'profile.validation.avatar_size' }),
});

export type AvatarFormValues = z.infer<typeof avatarSchema>;
