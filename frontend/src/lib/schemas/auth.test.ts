import { describe, expect, it } from 'vitest';

import { forgotPasswordSchema, registerSchema, resetPasswordSchema } from './auth';

describe('forgotPasswordSchema', () => {
  it('accepts a valid email', () => {
    const result = forgotPasswordSchema.safeParse({ email: 'User@Example.com ' });

    expect(result.success).toBe(true);
    expect(result.data?.email).toBe('user@example.com');
  });

  it('rejects an invalid email', () => {
    const result = forgotPasswordSchema.safeParse({ email: 'not-an-email' });

    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues[0]?.message).toBe('auth.email_invalid');
    }
  });
});

describe('resetPasswordSchema', () => {
  const valid = {
    token: 'abc123',
    email: 'user@example.com',
    password: 'Password123',
    password_confirmation: 'Password123',
  };

  it('accepts a valid payload', () => {
    const result = resetPasswordSchema.safeParse(valid);

    expect(result.success).toBe(true);
  });

  it('rejects mismatched confirmations', () => {
    const result = resetPasswordSchema.safeParse({
      ...valid,
      password_confirmation: 'Different123',
    });

    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues[0]?.path).toContain('password_confirmation');
      expect(result.error.issues[0]?.message).toBe('auth.password_mismatch');
    }
  });

  it('rejects weak passwords', () => {
    const result = resetPasswordSchema.safeParse({
      ...valid,
      password: 'weak',
      password_confirmation: 'weak',
    });

    expect(result.success).toBe(false);
  });

  it('rejects a missing token', () => {
    const result = resetPasswordSchema.safeParse({ ...valid, token: '' });

    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues[0]?.message).toBe('auth.token_required');
    }
  });
});

describe('registerSchema (zod 4)', () => {
  const valid = {
    name: 'Ana',
    last_name: 'Lopez',
    email: 'ana@example.com',
    password: 'Password123',
    sex: 'F',
    birth_date: '1990-01-01',
    client_type: 'personal',
    plan_id: '01a18a9d-6733-7105-8cde-e9b5dab65627',
    locale: 'es',
  };

  it('maps a missing sex to auth.sex_required', () => {
    const result = registerSchema.safeParse({ ...valid, sex: '' });

    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues[0]?.message).toBe('auth.sex_required');
    }
  });

  it('maps an invalid client_type to auth.client_type_required', () => {
    const result = registerSchema.safeParse({ ...valid, client_type: 'x' });

    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues[0]?.message).toBe('auth.client_type_required');
    }
  });

  it('accepts a UUIDv7 and an all-zero-variant plan_id, rejects a non-uuid', () => {
    expect(registerSchema.safeParse(valid).success).toBe(true);
    expect(
      registerSchema.safeParse({ ...valid, plan_id: '00000000-0000-0000-0000-000000000001' }).success,
    ).toBe(true);
    expect(registerSchema.safeParse({ ...valid, plan_id: 'not-a-uuid' }).success).toBe(false);
  });
});
