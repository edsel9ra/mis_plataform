import { describe, expect, it } from 'vitest';
import esMessages from '../../i18n/messages/es/common.json';
import enMessages from '../../i18n/messages/en/common.json';
import ptMessages from '../../i18n/messages/pt/common.json';
import { MAX_MEDIA_FILE_SIZE_BYTES } from '../media-types';
import {
  objectiveEvidenceSchema,
  objectiveProposalSchema,
  objectiveSchema,
  objectiveStatusSchema,
  objectiveUpdateSchema,
} from './objective';

const catalogs = [esMessages, enMessages, ptMessages] as const;

function keyExists(key: string): boolean {
  return catalogs.every((catalog) => {
    let node: unknown = catalog;
    for (const part of key.split('.')) {
      if (typeof node !== 'object' || node === null || !(part in node)) return false;
      node = (node as Record<string, unknown>)[part];
    }
    return typeof node === 'string';
  });
}

const PREFIX = 'progress.validation.';

const fileOf = (size: number, type = 'application/pdf', name = 'doc.pdf') =>
  new File(['x'.repeat(Math.min(size, 64))], name, { type });
// Node's File ignores the size we want to fake; patch it on the instance.
const sized = (file: File, size: number) => Object.defineProperty(file, 'size', { value: size });

function expectKeyMessages(result: { success: boolean; error?: { issues: { message: string }[] } }) {
  expect(result.success).toBe(false);
  const issues = result.error?.issues ?? [];
  expect(issues.length).toBeGreaterThan(0);
  for (const issue of issues) {
    expect(issue.message.startsWith(PREFIX), `message "${issue.message}" is not a ${PREFIX}* key`).toBe(true);
    expect(keyExists(issue.message), `key "${issue.message}" missing in es/en/pt catalogs`).toBe(true);
  }
}

const baseIndividual = {
  scope: 'individual' as const,
  owner_id: '11111111-1111-4111-8111-111111111111',
  title: 'Read a book',
  success_criteria: 'Finish chapter one',
  due_at: '2026-11-01T10:00:00+00:00',
};

describe('objective schemas emit progress.validation.* keys', () => {
  it('objectiveSchema: every rule reports a key', () => {
    expectKeyMessages(objectiveSchema.safeParse({ ...baseIndividual, title: 'ab' }));
    expectKeyMessages(objectiveSchema.safeParse({ ...baseIndividual, title: 'x'.repeat(201) }));
    expectKeyMessages(objectiveSchema.safeParse({ ...baseIndividual, success_criteria: ' ' }));
    expectKeyMessages(objectiveSchema.safeParse({ ...baseIndividual, description: 'x'.repeat(20_001) }));
    expectKeyMessages(objectiveSchema.safeParse({ ...baseIndividual, due_at: '' }));
    expectKeyMessages(objectiveSchema.safeParse({ ...baseIndividual, due_at: 'not-a-date' }));
    expectKeyMessages(objectiveSchema.safeParse({ ...baseIndividual, owner_id: 'not-a-uuid' }));
    expectKeyMessages(objectiveSchema.safeParse({ ...baseIndividual, relationship_id: 'x' }));
    expectKeyMessages(objectiveSchema.safeParse({ ...baseIndividual, scope: 'collective' }));
    expectKeyMessages(objectiveSchema.safeParse({ ...baseIndividual, owner_type: 'cohort' }));
    expectKeyMessages(objectiveSchema.safeParse({ ...baseIndividual, scope: 'collective', owner_type: 'bogus' }));
  });

  it('objectiveUpdateSchema: every rule reports a key', () => {
    expectKeyMessages(objectiveUpdateSchema.safeParse({}));
    expectKeyMessages(objectiveUpdateSchema.safeParse({ title: 'ab' }));
    expectKeyMessages(objectiveUpdateSchema.safeParse({ due_at: 'not-a-date' }));
  });

  it('objectiveProposalSchema: every rule reports a key', () => {
    const proposal = {
      title: 'Read a book',
      success_criteria: 'Finish chapter one',
      due_at: '2026-11-01',
    };
    expectKeyMessages(objectiveProposalSchema.safeParse({ ...proposal, title: 'ab' }));
    expectKeyMessages(objectiveProposalSchema.safeParse({ ...proposal, title: 'x'.repeat(201) }));
    expectKeyMessages(objectiveProposalSchema.safeParse({ ...proposal, success_criteria: ' ' }));
    expectKeyMessages(objectiveProposalSchema.safeParse({ ...proposal, due_at: '' }));
    expectKeyMessages(objectiveProposalSchema.safeParse({ ...proposal, due_at: 'not-a-date' }));
    expectKeyMessages(objectiveProposalSchema.safeParse({ ...proposal, relationship_id: 'x' }));
  });

  it('objectiveEvidenceSchema: every rule reports a key', () => {
    expectKeyMessages(objectiveEvidenceSchema.safeParse({}));
    expectKeyMessages(objectiveEvidenceSchema.safeParse({ note: '   ' }));
    expectKeyMessages(objectiveEvidenceSchema.safeParse({ note: 'x'.repeat(20_001) }));
    expectKeyMessages(objectiveEvidenceSchema.safeParse({ media_asset_id: 'x' }));
  });

  it('objectiveEvidenceSchema accepts a note, a file or both', () => {
    const file = fileOf(64, 'text/plain', 'note.txt');
    expect(objectiveEvidenceSchema.safeParse({ note: 'progreso' }).success).toBe(true);
    expect(objectiveEvidenceSchema.safeParse({ file }).success).toBe(true);
    expect(objectiveEvidenceSchema.safeParse({ note: 'progreso', file }).success).toBe(true);
    expect(objectiveEvidenceSchema.safeParse({}).success).toBe(false);
  });

  it('objectiveEvidenceSchema rejects unsupported types and oversized files', () => {
    expectKeyMessages(objectiveEvidenceSchema.safeParse({ file: fileOf(64, 'application/x-msdownload', 'bad.exe') }));
    const big = sized(fileOf(64), MAX_MEDIA_FILE_SIZE_BYTES + 1);
    expectKeyMessages(objectiveEvidenceSchema.safeParse({ file: big }));
  });

  it('objectiveStatusSchema: invalid status reports a key', () => {
    expectKeyMessages(objectiveStatusSchema.safeParse({ status: 'bogus' }));
  });
});
