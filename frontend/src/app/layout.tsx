import type { Metadata } from 'next';
import { NextIntlClientProvider } from 'next-intl';
import { getMessages, getLocale } from 'next-intl/server';
import { AuthProvider } from '@/components/auth/AuthProvider';
import { QueryProvider } from '@/lib/query-provider';
import './globals.css';

const baseUrl = process.env.NEXT_PUBLIC_URL || 'https://mis-platform.com';

export const metadata: Metadata = {
  title: {
    template: '%s | MIS - Mentorías Integrales Sistémicas',
    default: 'MIS - Mentorías Integrales Sistémicas',
  },
  description:
    'Plataforma de mentoría basada en neurociencia con test de personalidad Big Five, matching inteligente y certificaciones blockchain.',
  keywords: ['mentoría', 'mentoring', 'coaching', 'Big Five', 'neurociencia', 'desarrollo personal'],
  metadataBase: new URL(baseUrl),
  openGraph: {
    title: 'MIS - Mentorías Integrales Sistémicas',
    description:
      'Plataforma de mentoría basada en neurociencia con test de personalidad Big Five, matching inteligente y certificaciones blockchain.',
    url: baseUrl,
    siteName: 'MIS',
    locale: 'es_ES',
    type: 'website',
  },
  twitter: {
    card: 'summary_large_image',
    title: 'MIS - Mentorías Integrales Sistémicas',
    description:
      'Plataforma de mentoría basada en neurociencia con test de personalidad Big Five, matching inteligente y certificaciones blockchain.',
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

  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'EducationalOrganization',
    name: 'MIS - Mentorías Integrales Sistémicas',
    description:
      'Plataforma de mentoría basada en neurociencia con test de personalidad Big Five, matching inteligente y certificaciones blockchain.',
    url: baseUrl,
  };

  return (
    <html lang={locale} dir="ltr" data-scroll-behavior="smooth">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
          rel="stylesheet"
        />
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
        />
      </head>
      <body className="min-h-screen bg-gray-50 font-sans antialiased">
        <NextIntlClientProvider messages={messages}>
          <QueryProvider>
            <AuthProvider>
              {children}
            </AuthProvider>
          </QueryProvider>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
