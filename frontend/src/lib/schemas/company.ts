import { z } from 'zod';

const optionalText = (max: number, message: string) => z.string().trim().max(max, message).optional();

export const companySchema = z.object({
  legal_name: z.string().trim().min(2, 'company.legal_name_min').max(200, 'company.legal_name_max'),
  tax_id: optionalText(50, 'company.tax_id_max'),
  industry: optionalText(100, 'company.industry_max'),
  size: z.enum(['startup', 'sme', 'enterprise'], { invalid_type_error: 'company.size_invalid' }).optional(),
  website: z.union([
    z.literal(''),
    z.string().trim().url('company.website_invalid').max(255, 'company.website_max'),
  ]).optional(),
  phone: optionalText(30, 'company.phone_max'),
});

export type CompanyFormData = z.infer<typeof companySchema>;
