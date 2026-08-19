import type { Metadata } from 'next';
import { cookies } from 'next/headers';
import { Inter } from 'next/font/google';
import { NextIntlClientProvider } from 'next-intl';
import { getMessages, getLocale } from 'next-intl/server';
import { AuthProvider } from '@/components/auth/AuthProvider';
import { QueryProvider } from '@/lib/query-provider';
import './globals.css';

const baseUrl = process.env.NEXT_PUBLIC_URL || 'https://mis-platform.com';
const authCookieName = process.env.AUTH_COOKIE_NAME || 'mis_auth_token';
const inter = Inter({ subsets: ['latin'], weight: ['400', '500', '600', '700'] });

export const metadata: Metadata = {
  title: {
    template: '%s | MIS - Mentorías Integrales Sistémicas',
    default: 'MIS - Mentorías Integrales Sistémicas',
  },
  description:
    'Plataforma de mentoría basada en neurociencia con test de personalidad Big Five y recomendaciones personalizadas.',
  keywords: ['mentoría', 'mentoring', 'coaching', 'Big Five', 'neurociencia', 'desarrollo personal'],
  metadataBase: new URL(baseUrl),
  alternates: {
    canonical: '/',
    languages: {
      es: '/',
      en: '/en',
      pt: '/pt',
    },
  },
  openGraph: {
    title: 'MIS - Mentorías Integrales Sistémicas',
    description:
      'Plataforma de mentoría basada en neurociencia con test de personalidad Big Five y recomendaciones personalizadas.',
    url: baseUrl,
    siteName: 'MIS',
    locale: 'es_ES',
    alternateLocale: ['en_US', 'pt_BR'],
    type: 'website',
  },
  twitter: {
    card: 'summary_large_image',
    title: 'MIS - Mentorías Integrales Sistémicas',
    description:
      'Plataforma de mentoría basada en neurociencia con test de personalidad Big Five y recomendaciones personalizadas.',
  },
  robots: {
    index: true,
    follow: true,
  },
};

export default async function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const locale = await getLocale();
  const messages = await getMessages();
  const initialHasAuthCookie = (await cookies()).has(authCookieName);

  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'EducationalOrganization',
    name: 'MIS - Mentorías Integrales Sistémicas',
    description:
      'Plataforma de mentoría basada en neurociencia con test de personalidad Big Five y recomendaciones personalizadas.',
    url: baseUrl,
  };

  return (
    <html lang={locale} dir="ltr" data-scroll-behavior="smooth">
      <head>
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, '\\u003c') }}
        />
      </head>
      <body className={`${inter.className} min-h-screen bg-gray-50 antialiased`}>
        <NextIntlClientProvider messages={messages}>
          <QueryProvider>
            <AuthProvider initialHasAuthCookie={initialHasAuthCookie}>
              {children}
            </AuthProvider>
          </QueryProvider>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
