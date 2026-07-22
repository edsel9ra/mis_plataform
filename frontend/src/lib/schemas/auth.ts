import { z } from 'zod';

export const loginSchema = z.object({
  email: z.string().email('auth.email_invalid'),
  password: z.string().min(1, 'auth.password_required'),
});

export const registerSchema = z.object({
  name: z.string().min(2, 'auth.name_required').max(100),
  last_name: z.string().max(100).optional(),
  email: z.string().email('auth.email_invalid'),
  password: z
    .string()
    .min(8, 'auth.password_min')
    .regex(/[A-Z]/, 'auth.password_uppercase')
    .regex(/[a-z]/, 'auth.password_lowercase')
    .regex(/[0-9]/, 'auth.password_number'),
  sex: z.enum(['M', 'F', 'N'], { required_error: 'auth.sex_required' }),
  birth_date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'auth.birth_date_invalid'),
  client_type: z.enum(['personal', 'familiar', 'grupal', 'empresa']),
});

export type LoginFormData = z.infer<typeof loginSchema>;
export type RegisterFormData = z.infer<typeof registerSchema>;
