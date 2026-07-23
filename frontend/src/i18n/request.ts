import { getRequestConfig } from 'next-intl/server';
import { routing } from './routing';

export default getRequestConfig(async ({ requestLocale }) => {
  let locale = await requestLocale;
  if (!locale || !routing.locales.includes(locale as (typeof routing.locales)[number])) {
    locale = routing.defaultLocale;
  }

  const safeLocale = locale as (typeof routing.locales)[number];

  return {
    locale: safeLocale,
    messages: (await import(`./messages/${safeLocale}/common.json`)).default,
  };
});
