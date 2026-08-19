import { routing } from './routing';

export function localePath(path: string, locale: string): string {
  return locale === routing.defaultLocale ? path : `/${locale}${path}`;
}
