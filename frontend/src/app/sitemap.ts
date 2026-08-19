import type { MetadataRoute } from 'next';

export default function sitemap(): MetadataRoute.Sitemap {
  const baseUrl = process.env.NEXT_PUBLIC_URL || 'https://mis-platform.com';
  const locales = ['es', 'en', 'pt'] as const;

  const routes = [
    { path: '', priority: 1.0 },
    { path: '/register', priority: 0.7 },
    { path: '/register/personal', priority: 0.6 },
    { path: '/register/familiar', priority: 0.6 },
    { path: '/register/grupal', priority: 0.6 },
    { path: '/register/empresa', priority: 0.6 },
    { path: '/mentors', priority: 0.6 },
  ];

  return locales.flatMap((locale) => routes.map(({ path, priority }) => {
    const localePrefix = locale === 'es' ? '' : `/${locale}`;

    return {
      url: `${baseUrl}${localePrefix}${path}`,
      lastModified: new Date(),
      changeFrequency: path === '' ? 'weekly' : 'monthly',
      priority,
    } satisfies MetadataRoute.Sitemap[number];
  }));
}
