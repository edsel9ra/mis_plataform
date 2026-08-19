import { z } from 'zod';

const optionalText = (max: number) => z.string().trim().max(max).optional();

export const companySchema = z.object({
  legal_name: z.string().trim().min(2).max(200),
  tax_id: optionalText(50),
  industry: optionalText(100),
  size: z.enum(['startup', 'sme', 'enterprise']).optional(),
  website: z.union([z.literal(''), z.string().trim().url().max(255)]).optional(),
  phone: optionalText(30),
});

export type CompanyFormData = z.infer<typeof companySchema>;
