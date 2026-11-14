import { hasLocale } from 'next-intl';
import { getRequestConfig } from 'next-intl/server';
import { routing } from './routing';

import esMessages from './messages/es/common.json';

function lookupMessage(messages: unknown, key: string): string | undefined {
  if (!messages || typeof messages !== 'object') return undefined;
  const value = key.split('.').reduce<unknown>((acc, part) => {
    if (acc && typeof acc === 'object' && part in (acc as Record<string, unknown>)) {
      return (acc as Record<string, unknown>)[part];
    }
    return undefined;
  }, messages);
  return typeof value === 'string' ? value : undefined;
}

export default getRequestConfig(async ({ requestLocale }) => {
  const requested = await requestLocale;
  const locale = hasLocale(routing.locales, requested)
    ? requested
    : routing.defaultLocale;

  const messages = (await import(`./messages/${locale}/common.json`)).default;

  return {
    locale,
    messages,
    getMessageFallback: ({ namespace, key }) => {
      const fullKey = namespace ? `${namespace}.${key}` : key;
      const fallback = lookupMessage(esMessages, fullKey);
      // Nunca exponer la clave cruda: si no hay fallback en es, cadena vacía.
      return fallback ?? '';
    },
  };
});
