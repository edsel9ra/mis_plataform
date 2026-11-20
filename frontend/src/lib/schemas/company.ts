import { z } from 'zod';

const optionalText = (max: number, message: string) => z.string().trim().max(max, message).optional();

export const companySchema = z.object({
  legal_name: z.string().trim().min(2, 'company.legal_name_min').max(200, 'company.legal_name_max'),
  tax_id: optionalText(50, 'company.tax_id_max'),
  industry: optionalText(100, 'company.industry_max'),
  size: z.enum(['startup', 'sme', 'enterprise'], { error: 'company.size_invalid' }).optional(),
  website: z.union([
    z.literal(''),
    z.string().trim().url('company.website_invalid').max(255, 'company.website_max'),
  ]).optional(),
  phone: optionalText(30, 'company.phone_max'),
});

export type CompanyFormData = z.infer<typeof companySchema>;

const optionalEmployeeField = (message: string) => z.string().trim().max(100, message);

export const inviteEmployeeSchema = z.object({
  email: z.string({ error: 'company.validation.email_required' })
    .trim()
    .min(1, 'company.validation.email_required')
    .email('company.validation.email_invalid')
    .max(255, 'company.validation.email_invalid'),
  name: z.string({ error: 'company.validation.name_required' })
    .trim()
    .min(1, 'company.validation.name_required')
    .max(100, 'company.validation.name_max'),
  last_name: optionalEmployeeField('company.validation.last_name_max'),
  position: optionalEmployeeField('company.validation.position_max'),
  department: optionalEmployeeField('company.validation.department_max'),
});

export type InviteEmployeeFormValues = z.infer<typeof inviteEmployeeSchema>;

export interface InviteEmployeePayload {
  email: string;
  name: string;
  last_name: string | null;
  position: string | null;
  department: string | null;
}

export function toInviteEmployeePayload(values: InviteEmployeeFormValues): InviteEmployeePayload {
  return {
    email: values.email.trim().toLowerCase(),
    name: values.name.trim(),
    last_name: values.last_name.trim() || null,
    position: values.position.trim() || null,
    department: values.department.trim() || null,
  };
}

export const updateEmployeeSchema = z.object({
  position: optionalEmployeeField('company.validation.position_max'),
  department: optionalEmployeeField('company.validation.department_max'),
});

export type UpdateEmployeeFormValues = z.infer<typeof updateEmployeeSchema>;

export interface UpdateEmployeePayload {
  position: string | null;
  department: string | null;
}

export function toUpdateEmployeePayload(values: UpdateEmployeeFormValues): UpdateEmployeePayload {
  return {
    position: values.position.trim() || null,
    department: values.department.trim() || null,
  };
}
