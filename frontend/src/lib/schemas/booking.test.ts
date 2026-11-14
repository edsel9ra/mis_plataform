import { describe, expect, it } from 'vitest';

import { bookSessionSchema } from './booking';
import { SESSION_MAX_DURATION, SESSION_MIN_DURATION } from './session-actions';

const valid = {
  relationship_id: 'rel-1',
  title: '  Sesión de prueba ',
  description: '',
  duration_minutes: '60',
};

describe('bookSessionSchema (T017-03)', () => {
  it('accepts a valid booking and coerces duration', () => {
    const result = bookSessionSchema.safeParse(valid);

    expect(result.success).toBe(true);
    if (result.success) {
      expect(result.data.duration_minutes).toBe(60);
      expect(result.data.title).toBe('Sesión de prueba');
    }
  });

  it('requires relationship and title, bounded at 200 characters', () => {
    expect(bookSessionSchema.safeParse({ ...valid, relationship_id: '' }).success).toBe(false);
    expect(bookSessionSchema.safeParse({ ...valid, title: '   ' }).success).toBe(false);
    expect(bookSessionSchema.safeParse({ ...valid, title: 'x'.repeat(201) }).success).toBe(false);
    expect(bookSessionSchema.safeParse({ ...valid, title: 'x'.repeat(200) }).success).toBe(true);
  });

  it('enforces the shared session duration bounds', () => {
    expect(bookSessionSchema.safeParse({ ...valid, duration_minutes: String(SESSION_MIN_DURATION) }).success).toBe(true);
    expect(bookSessionSchema.safeParse({ ...valid, duration_minutes: String(SESSION_MAX_DURATION) }).success).toBe(true);
    expect(bookSessionSchema.safeParse({ ...valid, duration_minutes: String(SESSION_MIN_DURATION - 1) }).success).toBe(false);
    expect(bookSessionSchema.safeParse({ ...valid, duration_minutes: String(SESSION_MAX_DURATION + 1) }).success).toBe(false);
  });
});
