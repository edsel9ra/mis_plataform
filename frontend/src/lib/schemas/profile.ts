import { z } from 'zod';

export const profileSchema = z.object({
  name: z.string().min(2).max(100).optional(),
  last_name: z.string().max(100).optional(),
  timezone: z.string().max(50).optional(),
  locale: z.enum(['es', 'en', 'pt']).optional(),
});

export type ProfileFormData = z.infer<typeof profileSchema>;
