import { describe, expect, it } from 'vitest';
import es from '@/i18n/messages/es/common.json';
import en from '@/i18n/messages/en/common.json';
import pt from '@/i18n/messages/pt/common.json';
import { inviteEmployeeSchema, toInviteEmployeePayload } from './company';

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

describe('inviteEmployeeSchema (T020.6)', () => {
  const valid = {
    email: 'nuevo@example.com',
    name: 'Nueva',
    last_name: 'Empleada',
    position: 'Analista',
    department: 'Operaciones',
  };

  it('requires an email with an i18n key', () => {
    for (const email of [undefined, '', '   ']) {
      const result = inviteEmployeeSchema.safeParse({ ...valid, email });
      expect(result.success, `email=${email}`).toBe(false);
      expectKeyEverywhere(result.error!.issues[0].message);
    }
  });

  it('rejects an invalid email with an i18n key', () => {
    const result = inviteEmployeeSchema.safeParse({ ...valid, email: 'no-es-email' });
    expect(result.success).toBe(false);
    expect(result.error!.issues[0].message).toBe('company.validation.email_invalid');
    expectKeyEverywhere('company.validation.email_invalid');
  });

  it('requires a trimmed name and caps it at 100 chars', () => {
    for (const name of [undefined, '', '   ']) {
      const result = inviteEmployeeSchema.safeParse({ ...valid, name });
      expect(result.success, `name=${name}`).toBe(false);
      expectKeyEverywhere(result.error!.issues[0].message);
    }
    expect(inviteEmployeeSchema.parse({ ...valid, name: '  Nueva  ' }).name).toBe('Nueva');
    const tooLong = inviteEmployeeSchema.safeParse({ ...valid, name: 'x'.repeat(101) });
    expect(tooLong.success).toBe(false);
    expectKeyEverywhere(tooLong.error!.issues[0].message);
    expect(inviteEmployeeSchema.safeParse({ ...valid, name: 'x'.repeat(100) }).success).toBe(true);
  });

  it('trims optional fields and caps them at 100 chars', () => {
    for (const field of ['last_name', 'position', 'department'] as const) {
      expect(inviteEmployeeSchema.parse({ ...valid, [field]: '  ' })[field]).toBe('');
      expect(inviteEmployeeSchema.parse({ ...valid, [field]: '  texto  ' })[field]).toBe('texto');
      const tooLong = inviteEmployeeSchema.safeParse({ ...valid, [field]: 'x'.repeat(101) });
      expect(tooLong.success, field).toBe(false);
      expectKeyEverywhere(tooLong.error!.issues[0].message);
      expect(inviteEmployeeSchema.safeParse({ ...valid, [field]: 'x'.repeat(100) }).success).toBe(true);
    }
  });

  it('converts values to the API payload (lower email, null optionals)', () => {
    const values = inviteEmployeeSchema.parse({
      email: '  NUEVO@Example.COM ',
      name: '  Nueva ',
      last_name: ' ',
      position: '',
      department: '  Operaciones ',
    });
    expect(toInviteEmployeePayload(values)).toEqual({
      email: 'nuevo@example.com',
      name: 'Nueva',
      last_name: null,
      position: null,
      department: 'Operaciones',
    });
  });
});
