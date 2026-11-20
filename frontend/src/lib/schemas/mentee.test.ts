import { describe, expect, it } from 'vitest';
import es from '@/i18n/messages/es/common.json';
import en from '@/i18n/messages/en/common.json';
import pt from '@/i18n/messages/pt/common.json';
import { emotionalCheckInSchema, toEmotionalCheckInPayload } from './mentee';

function expectKeyEverywhere(key: string) {
  for (const catalog of [es, en, pt]) {
    const value = key.split('.').reduce<unknown>((node, part) => {
      if (node && typeof node === 'object' && part in node) {
        return (node as Record<string, unknown>)[part];
      }
      return undefined;
    }, catalog);
    expect(value, `missing ${key}`).toBeTypeOf('string');
  }
}

describe('emotionalCheckInSchema (T020.5)', () => {
  it('requires a mood score between 1 and 5 with an i18n key', () => {
    for (const missing of [undefined, '', '0', '6', 'abc', '3.5']) {
      const result = emotionalCheckInSchema.safeParse({ mood_score: missing, stress_score: '', note: '' });
      expect(result.success, `mood=${missing}`).toBe(false);
      expectKeyEverywhere(result.error!.issues[0].message);
    }
  });

  it('accepts every mood score 1-5', () => {
    for (const mood of ['1', '2', '3', '4', '5']) {
      const result = emotionalCheckInSchema.safeParse({ mood_score: mood, stress_score: '', note: '' });
      expect(result.success, mood).toBe(true);
    }
  });

  it('allows an empty or 1-5 stress score and rejects others', () => {
    for (const stress of ['', '1', '5']) {
      expect(
        emotionalCheckInSchema.safeParse({ mood_score: '2', stress_score: stress, note: '' }).success,
        `stress=${stress}`,
      ).toBe(true);
    }
    for (const stress of ['0', '6', 'x']) {
      expect(
        emotionalCheckInSchema.safeParse({ mood_score: '2', stress_score: stress, note: '' }).success,
        `stress=${stress}`,
      ).toBe(false);
    }
  });

  it('trims the note, allows empty, caps at 10000 characters', () => {
    expect(
      emotionalCheckInSchema.parse({ mood_score: '4', stress_score: '', note: '  ' }).note,
    ).toBe('');
    expect(
      emotionalCheckInSchema.safeParse({ mood_score: '4', stress_score: '', note: 'x'.repeat(10000) }).success,
    ).toBe(true);
    const tooLong = emotionalCheckInSchema.safeParse({ mood_score: '4', stress_score: '', note: 'x'.repeat(10001) });
    expect(tooLong.success).toBe(false);
    expectKeyEverywhere(tooLong.error!.issues[0].message);
  });

  it('converts form values to the API payload', () => {
    const values = emotionalCheckInSchema.parse({ mood_score: '4', stress_score: '', note: '  un día difícil  ' });
    expect(toEmotionalCheckInPayload(values)).toEqual({
      mood_score: 4,
      stress_score: null,
      note: 'un día difícil',
    });
    const minimal = emotionalCheckInSchema.parse({ mood_score: '1', stress_score: '2', note: '  ' });
    expect(toEmotionalCheckInPayload(minimal)).toEqual({
      mood_score: 1,
      stress_score: 2,
      note: null,
    });
  });
});
