import { describe, expect, it } from 'vitest';
import es from '@/i18n/messages/es/common.json';
import en from '@/i18n/messages/en/common.json';
import pt from '@/i18n/messages/pt/common.json';
import { CHECK_IN_SCALE, moodLabelKey, stressLabelKey } from './emotional-check-in';

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

describe('emotional check-in labels (T020.5)', () => {
  it('exposes the 1..5 scale', () => {
    expect([...CHECK_IN_SCALE]).toEqual([1, 2, 3, 4, 5]);
  });

  it('maps each mood score to an existing label key', () => {
    for (const score of CHECK_IN_SCALE) {
      const key = moodLabelKey(score);
      expect(key).toBe(`mentee.check_in.mood_levels.${score}`);
      expectKeyEverywhere(key);
    }
  });

  it('maps each stress score to an existing label key', () => {
    for (const score of CHECK_IN_SCALE) {
      const key = stressLabelKey(score);
      expect(key).toBe(`mentee.check_in.stress_levels.${score}`);
      expectKeyEverywhere(key);
    }
  });

  it('maps a null stress score to the not-set key', () => {
    const key = stressLabelKey(null);
    expect(key).toBe('mentee.check_in.stress_not_set');
    expectKeyEverywhere(key);
  });
});
