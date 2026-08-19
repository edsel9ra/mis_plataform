import { z } from 'zod';
import { sexOptions } from './auth';

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
