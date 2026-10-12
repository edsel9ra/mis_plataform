import { describe, expect, it } from 'vitest';

import { localePath } from './paths';

describe('localePath', () => {
  it('leaves default-locale paths unprefixed', () => {
    expect(localePath('/dashboard', 'es')).toBe('/dashboard');
  });

  it.each(['en', 'pt'])('prefixes paths for the %s locale', (locale) => {
    expect(localePath('/dashboard?tab=next#session', locale)).toBe(
      `/${locale}/dashboard?tab=next#session`,
    );
  });
});
