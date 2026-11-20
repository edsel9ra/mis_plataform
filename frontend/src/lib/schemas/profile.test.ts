import { describe, expect, it } from 'vitest';
import es from '@/i18n/messages/es/common.json';
import en from '@/i18n/messages/en/common.json';
import pt from '@/i18n/messages/pt/common.json';
import {
  avatarSchema,
  AVATAR_MAX_BYTES,
  AVATAR_MIME_TYPES,
  profileDetailsSchema,
  profileServerFieldErrors,
  toProfileDetailsPayload,
} from './profile';

function keyExists(catalog: object, dotted: string): boolean {
  return dotted.split('.').every((segment) => {
    if (catalog && typeof catalog === 'object' && segment in catalog) {
      catalog = (catalog as Record<string, unknown>)[segment] as object;
      return true;
    }
    return false;
  });
}

function expectKeyEverywhere(key: string) {
  for (const catalog of [es, en, pt]) {
    expect(keyExists(catalog, key), `${key} missing`).toBe(true);
  }
}

describe('profileDetailsSchema (T020.4)', () => {
  it('accepts a valid payload and trims values', () => {
    const parsed = profileDetailsSchema.parse({ name: '  Ana  ', last_name: '  Audit  ', timezone: 'Europe/Madrid' });
    expect(parsed).toEqual({ name: 'Ana', last_name: 'Audit', timezone: 'Europe/Madrid' });
  });

  it('requires a non-empty name with an i18n key', () => {
    const result = profileDetailsSchema.safeParse({ name: '   ', last_name: '', timezone: 'UTC' });
    expect(result.success).toBe(false);
    const key = result.error?.issues[0]?.message;
    expect(key).toBe('profile.validation.name_required');
    expectKeyEverywhere(key!);
  });

  it('rejects names over 100 characters', () => {
    const result = profileDetailsSchema.safeParse({ name: 'x'.repeat(101), last_name: '', timezone: 'UTC' });
    expect(result.success).toBe(false);
    expectKeyEverywhere(result.error!.issues[0].message);
  });

  it('rejects last names over 100 characters', () => {
    const result = profileDetailsSchema.safeParse({ name: 'Ana', last_name: 'x'.repeat(101), timezone: 'UTC' });
    expect(result.success).toBe(false);
    expectKeyEverywhere(result.error!.issues[0].message);
  });

  it('requires a timezone', () => {
    const result = profileDetailsSchema.safeParse({ name: 'Ana', last_name: '', timezone: ' ' });
    expect(result.success).toBe(false);
    const key = result.error?.issues[0]?.message;
    expect(key).toBe('profile.validation.timezone_required');
    expectKeyEverywhere(key!);
  });

  it('allows an empty last name', () => {
    expect(profileDetailsSchema.parse({ name: 'Ana', last_name: '', timezone: 'UTC' }).last_name).toBe('');
  });

  it('maps empty last name to null in the payload', () => {
    const values = profileDetailsSchema.parse({ name: 'Ana', last_name: '  ', timezone: 'UTC' });
    expect(toProfileDetailsPayload(values)).toEqual({ name: 'Ana', last_name: null, timezone: 'UTC' });
  });
});

describe('avatarSchema (T020.4)', () => {
  it('accepts each supported image type', () => {
    for (const type of AVATAR_MIME_TYPES) {
      const result = avatarSchema.safeParse({ avatar: new File(['x'], 'a', { type }) });
      expect(result.success, type).toBe(true);
    }
  });

  it('requires a File', () => {
    const result = avatarSchema.safeParse({ avatar: undefined });
    expect(result.success).toBe(false);
    expectKeyEverywhere(result.error!.issues[0].message);
  });

  it('rejects non-image types', () => {
    const result = avatarSchema.safeParse({ avatar: new File(['x'], 'a.txt', { type: 'text/plain' }) });
    expect(result.success).toBe(false);
    expectKeyEverywhere(result.error!.issues[0].message);
  });

  it('accepts exactly 2 MB and rejects 2 MB + 1', () => {
    const okFile = new File([new Uint8Array(AVATAR_MAX_BYTES)], 'a.png', { type: 'image/png' });
    const bigFile = new File([new Uint8Array(AVATAR_MAX_BYTES + 1)], 'a.png', { type: 'image/png' });
    expect(avatarSchema.safeParse({ avatar: okFile }).success).toBe(true);
    const result = avatarSchema.safeParse({ avatar: bigFile });
    expect(result.success).toBe(false);
    expectKeyEverywhere(result.error!.issues[0].message);
  });
});

describe('profileServerFieldErrors (T020.4)', () => {
  it('maps known fields and sends unknown keys to formError', () => {
    const mapped = profileServerFieldErrors({
      name: ['El nombre es obligatorio.'],
      timezone: ['La zona horaria no es válida.'],
      other_field: ['Error raro.'],
    });
    expect(mapped.fields).toEqual({ name: 'El nombre es obligatorio.', timezone: 'La zona horaria no es válida.' });
    expect(mapped.formError).toBe('Error raro.');
  });

  it('returns null formError when everything maps to fields', () => {
    const mapped = profileServerFieldErrors({ last_name: ['Muy largo.'] });
    expect(mapped.fields).toEqual({ last_name: 'Muy largo.' });
    expect(mapped.formError).toBeNull();
  });
});
