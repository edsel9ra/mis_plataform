import { describe, expect, it } from 'vitest';
import esMessages from '../../i18n/messages/es/common.json';
import enMessages from '../../i18n/messages/en/common.json';
import ptMessages from '../../i18n/messages/pt/common.json';
import {
  MAX_MENTOR_FILE_SIZE_BYTES,
  mentorChallengeSchema,
  mentorFeedbackSchema,
  mentorObservationSchema,
} from './mentor-workspace';

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

const PREFIX = 'mentor_workspace.validation.';

function expectKeyMessages(result: { success: boolean; error?: { issues: { message: string }[] } }) {
  expect(result.success).toBe(false);
  const issues = result.error?.issues ?? [];
  expect(issues.length).toBeGreaterThan(0);
  for (const issue of issues) {
    expect(issue.message.startsWith(PREFIX), `message "${issue.message}" is not a ${PREFIX}* key`).toBe(true);
    expect(keyExists(issue.message), `key "${issue.message}" missing in es/en/pt catalogs`).toBe(true);
  }
}

const fileOf = (size: number, type = 'application/pdf') =>
  new File(['x'.repeat(Math.min(size, 64))], 'doc.pdf', { type });
// Node's File ignores the size we want to fake; patch it on the instance.
const sized = (file: File, size: number) => Object.defineProperty(file, 'size', { value: size });

describe('mentor workspace schemas emit mentor_workspace.validation.* keys', () => {
  it('mentorChallengeSchema: every rule reports a key', () => {
    const goodFile = fileOf(64);
    expectKeyMessages(mentorChallengeSchema.safeParse({ title: 'ab', content_file: goodFile }));
    expectKeyMessages(mentorChallengeSchema.safeParse({ title: 'x'.repeat(161), content_file: goodFile }));
    expectKeyMessages(mentorChallengeSchema.safeParse({ title: 'Valid title', content_file: goodFile, description: 'x'.repeat(2001) }));
    expectKeyMessages(mentorChallengeSchema.safeParse({ title: 'Valid title' }));
    const big = sized(fileOf(64), MAX_MENTOR_FILE_SIZE_BYTES + 1);
    expectKeyMessages(mentorChallengeSchema.safeParse({ title: 'Valid title', content_file: big }));
    const bad = fileOf(64, 'application/x-msdownload');
    expectKeyMessages(mentorChallengeSchema.safeParse({ title: 'Valid title', content_file: bad }));
  });

  it('mentorFeedbackSchema: every rule reports a key', () => {
    expectKeyMessages(mentorFeedbackSchema.safeParse({}));
    expectKeyMessages(mentorFeedbackSchema.safeParse({ note: '   ' }));
    expectKeyMessages(mentorFeedbackSchema.safeParse({ note: 'x'.repeat(2001) }));
    const bad = fileOf(64, 'application/pdf');
    expectKeyMessages(mentorFeedbackSchema.safeParse({ feedback_file: bad }));
    const big = sized(fileOf(64, 'audio/mpeg'), MAX_MENTOR_FILE_SIZE_BYTES + 1);
    expectKeyMessages(mentorFeedbackSchema.safeParse({ feedback_file: big }));
  });

  it('mentorObservationSchema: every rule reports a key', () => {
    expectKeyMessages(mentorObservationSchema.safeParse({ focus: 'abc' }));
    expectKeyMessages(mentorObservationSchema.safeParse({ focus: -1 }));
    expectKeyMessages(mentorObservationSchema.safeParse({ regulation: 101 }));
    expectKeyMessages(mentorObservationSchema.safeParse({ note: 'x'.repeat(2001) }));
  });
});
