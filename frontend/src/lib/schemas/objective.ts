import { z } from 'zod';

/**
 * Objective form schemas (spec 001, T-093, RF-1).
 *
 * CA-1.3 requires title, success criteria, owner/collective and a due date at
 * creation. `scope` and its owner are validated together: an individual
 * objective has a titular (no collective type) and a collective one requires
 * the collective type.
 *
 * Every visible rule emits a `progress.validation.*` i18n key (SH-11); forms
 * translate it with `validationMessage` before rendering.
 */

export const OBJECTIVE_SCOPES = ['individual', 'collective'] as const;
export const COLLECTIVE_OWNER_TYPES = ['family_group', 'cohort', 'company'] as const;
export const OBJECTIVE_STATUSES = ['not_started', 'in_progress', 'achieved', 'abandoned'] as const;

const V = (key: string) => `progress.validation.${key}`;

const requiredText = (max: number, min = 1, minKey = 'text_required', maxKey = 'text_max') =>
  z.string().trim().min(min, { error: V(minKey) }).max(max, { error: V(maxKey) });
const optionalText = (max: number) =>
  z.string().trim().max(max, { error: V('text_max') }).optional();

/** Required due date: non-empty and parseable (CA-1.3). */
const requiredDate = z
  .string()
  .trim()
  .min(1, { error: V('due_required') })
  .refine((value) => !Number.isNaN(Date.parse(value)), { message: V('date_invalid') });

const optionalDate = z
  .string()
  .trim()
  .refine((value) => value === '' || !Number.isNaN(Date.parse(value)), { message: V('date_invalid') })
  .optional();

const scope = z.enum(OBJECTIVE_SCOPES, { error: V('text_required') });
const ownerType = z.enum(COLLECTIVE_OWNER_TYPES, { error: V('collective_required') });

export const objectiveSchema = z
  .object({
    scope,
    owner_id: z.guid({ error: V('owner_required') }),
    owner_type: ownerType.optional(),
    relationship_id: z.guid({ error: V('invalid_id') }).optional(),
    title: requiredText(200, 3, 'title_min', 'title_max'),
    description: optionalText(20_000),
    success_criteria: requiredText(20_000),
    due_at: requiredDate,
  })
  .superRefine((value, ctx) => {
    if (value.scope === 'collective' && !value.owner_type) {
      ctx.addIssue({
        code: 'custom',
        path: ['owner_type'],
        message: V('collective_required'),
      });
    }

    if (value.scope === 'individual' && value.owner_type) {
      ctx.addIssue({
        code: 'custom',
        path: ['owner_type'],
        message: V('individual_no_collective'),
      });
    }
  });

/**
 * Owner/scope are immutable after creation; this schema only edits the
 * descriptive fields (backend `UpdateObjectiveRequest`).
 */
export const objectiveUpdateSchema = z
  .object({
    title: requiredText(200, 3, 'title_min', 'title_max').optional(),
    description: optionalText(20_000),
    success_criteria: requiredText(20_000).optional(),
    due_at: optionalDate,
  })
  .refine((value) => Object.keys(value).length > 0, { message: V('nothing_to_update') });

/** A mentee proposes an objective: no scope/owner/status (backend enforces it). */
export const objectiveProposalSchema = z.object({
  title: requiredText(200, 3, 'title_min', 'title_max'),
  description: optionalText(20_000),
  success_criteria: requiredText(20_000),
  due_at: requiredDate,
  relationship_id: z.guid({ error: V('invalid_id') }).optional(),
});

/** Evidence needs a note or an already-uploaded media asset. */
export const objectiveEvidenceSchema = z
  .object({
    note: optionalText(20_000),
    media_asset_id: z.guid({ error: V('invalid_id') }).optional(),
  })
  .refine(({ note, media_asset_id }) => Boolean(note?.trim() || media_asset_id), {
    path: ['note'],
    message: V('evidence_required'),
  });

export const objectiveStatusSchema = z.object({
  status: z.enum(OBJECTIVE_STATUSES, { error: V('status_invalid') }),
});

export type ObjectiveFormValues = z.infer<typeof objectiveSchema>;
export type ObjectiveUpdateFormValues = z.infer<typeof objectiveUpdateSchema>;
export type ObjectiveProposalFormValues = z.infer<typeof objectiveProposalSchema>;
export type ObjectiveEvidenceFormValues = z.infer<typeof objectiveEvidenceSchema>;
export type ObjectiveStatusFormValues = z.infer<typeof objectiveStatusSchema>;
