import { describe, expect, it } from 'vitest';

import { companySchema } from './company';
import { mentorObservationSchema } from './mentor-workspace';
import { objectiveSchema } from './objective';

describe('companySchema (zod 4)', () => {
  it('maps an invalid size to company.size_invalid', () => {
    const result = companySchema.safeParse({ legal_name: 'Acme Corp', size: 'huge' });

    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues[0]?.message).toBe('company.size_invalid');
    }
  });
});

describe('objectiveSchema (zod 4)', () => {
  const base = {
    scope: 'collective',
    owner_id: '01a18a9d-6733-7105-8cde-e9b5dab65627',
    title: 'Objetivo trimestral',
    success_criteria: 'Completar las sesiones',
    due_at: '2026-12-31',
  };

  it('requires owner_type for collective scope', () => {
    const result = objectiveSchema.safeParse(base);

    expect(result.success).toBe(false);
    if (!result.success) {
      const issue = result.error.issues.find((i) => i.path[0] === 'owner_type');
      expect(issue?.message).toBe('Select the responsible collective.');
    }
  });

  it('maps an invalid owner_id to Select the owner.', () => {
    const result = objectiveSchema.safeParse({ ...base, owner_id: 'not-a-uuid', owner_type: 'cohort' });

    expect(result.success).toBe(false);
    if (!result.success) {
      const issue = result.error.issues.find((i) => i.path[0] === 'owner_id');
      expect(issue?.message).toBe('Select the owner.');
    }
  });
});

describe('mentorObservationSchema (zod 4)', () => {
  it('maps an empty focus to undefined', () => {
    const result = mentorObservationSchema.safeParse({ focus: '' });

    expect(result.success).toBe(true);
    if (result.success) {
      expect(result.data.focus).toBeUndefined();
    }
  });

  it('coerces a numeric focus string', () => {
    const result = mentorObservationSchema.safeParse({ focus: '50' });

    expect(result.success).toBe(true);
    if (result.success) {
      expect(result.data.focus).toBe(50);
    }
  });

  it('rejects a focus above 100', () => {
    const result = mentorObservationSchema.safeParse({ focus: '150' });

    expect(result.success).toBe(false);
  });
});
