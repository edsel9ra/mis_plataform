import type { MetadataRoute } from 'next';

export default function robots(): MetadataRoute.Robots {
  const baseUrl = process.env.NEXT_PUBLIC_URL || 'https://mis-platform.com';

  return {
    rules: [
      {
        userAgent: '*',
        allow: '/',
        disallow: [
          '/api/',
          '/admin/',
          '/dashboard/',
          '/profile/',
          '/sessions/',
          '/messages/',
          '/certificates/',
          '/recommendations/',
          '/onboarding/',
          '/en/admin/',
          '/en/dashboard/',
          '/en/profile/',
          '/en/sessions/',
          '/en/messages/',
          '/en/certificates/',
          '/en/recommendations/',
          '/en/onboarding/',
          '/pt/admin/',
          '/pt/dashboard/',
          '/pt/profile/',
          '/pt/sessions/',
          '/pt/messages/',
          '/pt/certificates/',
          '/pt/recommendations/',
          '/pt/onboarding/',
        ],
      },
    ],
    sitemap: `${baseUrl}/sitemap.xml`,
  };
}
