import { z } from 'zod';

export const sexOptions = ['M', 'F', 'N'] as const;
export const clientTypeOptions = ['personal', 'familiar', 'grupal', 'empresa'] as const;
export const localeOptions = ['es', 'en', 'pt'] as const;

const birthDatePattern = /^\d{4}-\d{2}-\d{2}$/;

export const loginSchema = z.object({
  email: z.string().trim().toLowerCase().email('auth.email_invalid'),
  password: z.string().min(1, 'auth.password_required'),
});

export const registerSchema = z.object({
  name: z.string().trim().min(2, 'auth.name_required').max(100, 'auth.name_max'),
  last_name: z.string().trim().max(100, 'auth.last_name_max').optional(),
  email: z.string().trim().toLowerCase().email('auth.email_invalid'),
  password: z
    .string()
    .min(8, 'auth.password_min')
    .regex(/[A-Z]/, 'auth.password_uppercase')
    .regex(/[a-z]/, 'auth.password_lowercase')
    .regex(/[0-9]/, 'auth.password_number'),
  sex: z.enum(sexOptions, { error: 'auth.sex_required' }),
  birth_date: z.string().regex(birthDatePattern, 'auth.birth_date_invalid'),
  client_type: z.enum(clientTypeOptions, { error: 'auth.client_type_required' }),
  plan_id: z.guid(),
  locale: z.enum(localeOptions),
});

export const forgotPasswordSchema = z.object({
  email: z.string().trim().toLowerCase().email('auth.email_invalid'),
});

const passwordRules = z
  .string()
  .min(8, 'auth.password_min')
  .regex(/[A-Z]/, 'auth.password_uppercase')
  .regex(/[a-z]/, 'auth.password_lowercase')
  .regex(/[0-9]/, 'auth.password_number');

export const resetPasswordSchema = z
  .object({
    token: z.string().min(1, 'auth.token_required'),
    email: z.string().trim().toLowerCase().email('auth.email_invalid'),
    password: passwordRules,
    password_confirmation: z.string().min(1, 'auth.password_confirmation_required'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'auth.password_mismatch',
    path: ['password_confirmation'],
  });

export type ForgotPasswordFormData = z.infer<typeof forgotPasswordSchema>;
export type ResetPasswordFormData = z.infer<typeof resetPasswordSchema>;
export type LoginFormData = z.infer<typeof loginSchema>;
export type RegisterFormData = z.infer<typeof registerSchema>;
export type ClientType = z.infer<typeof registerSchema>['client_type'];
export type Sex = z.infer<typeof registerSchema>['sex'];
