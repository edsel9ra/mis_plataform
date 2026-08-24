import type { MetadataRoute } from 'next';
import { getSiteUrl } from '@/lib/site-url';

export default function robots(): MetadataRoute.Robots {
  const siteUrl = getSiteUrl();

  return {
    rules: [
      {
        userAgent: '*',
        allow: '/',
        disallow: [
          '/api',
          '/admin',
          '/dashboard',
          '/evaluations',
          '/profile',
          '/sessions',
          '/messages',
          '/certificates',
          '/recommendations',
          '/onboarding',
          '/learning-paths',
          '/login',
          '/en/admin',
          '/en/dashboard',
          '/en/evaluations',
          '/en/profile',
          '/en/sessions',
          '/en/messages',
          '/en/certificates',
          '/en/recommendations',
          '/en/onboarding',
          '/en/learning-paths',
          '/en/login',
          '/pt/admin',
          '/pt/dashboard',
          '/pt/evaluations',
          '/pt/profile',
          '/pt/sessions',
          '/pt/messages',
          '/pt/certificates',
          '/pt/recommendations',
          '/pt/onboarding',
          '/pt/learning-paths',
          '/pt/login',
        ],
      },
    ],
    ...(siteUrl ? { sitemap: new URL('/sitemap.xml', siteUrl).toString() } : {}),
  };
}
