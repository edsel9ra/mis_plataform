import type { Metadata } from 'next';
import { cookies } from 'next/headers';
import { Fraunces, Instrument_Sans } from 'next/font/google';
import { NextIntlClientProvider } from 'next-intl';
import { getLocale, getTranslations } from 'next-intl/server';
import { AuthProvider } from '@/components/auth/AuthProvider';
import { RealtimeProvider } from '@/components/realtime/RealtimeProvider';
import { QueryProvider } from '@/lib/query-provider';
import { getSiteUrl } from '@/lib/site-url';
import type { RealtimeConfig } from '@/lib/realtime';
import './globals.css';

const siteUrl = getSiteUrl();
const authCookieName = process.env.AUTH_COOKIE_NAME || 'mis_auth_token';
const instrumentSans = Instrument_Sans({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-instrument',
});

const fraunces = Fraunces({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-fraunces',
  axes: ['SOFT', 'WONK', 'opsz'],
});

export async function generateMetadata(): Promise<Metadata> {
  const locale = await getLocale();
  const t = await getTranslations();
  const name = t('app.name');
  const description = t('app.tagline');
  const keywords = t.raw('app.keywords') as string[];
  const openGraphLocale = locale === 'en' ? 'en_US' : locale === 'pt' ? 'pt_BR' : 'es_ES';

  return {
    title: {
      template: `%s | ${name}`,
      default: name,
    },
    description,
    keywords,
    metadataBase: siteUrl,
    alternates: {
      canonical: '/',
      languages: {
        es: '/',
        en: '/en',
        pt: '/pt',
      },
    },
    openGraph: {
      title: name,
      description,
      ...(siteUrl ? { url: siteUrl.toString() } : {}),
      siteName: 'MIS',
      locale: openGraphLocale,
      alternateLocale: ['es_ES', 'en_US', 'pt_BR'].filter((value) => value !== openGraphLocale),
      type: 'website',
    },
    twitter: {
      card: 'summary_large_image',
      title: name,
      description,
    },
    robots: {
      index: true,
      follow: true,
    },
  };
}

export default async function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const locale = await getLocale();
  const t = await getTranslations();
  const initialHasAuthCookie = (await cookies()).has(authCookieName);

  const reverbConfig: RealtimeConfig | null = process.env.REVERB_APP_KEY
    ? {
        key: process.env.REVERB_APP_KEY,
        host: process.env.REVERB_PUBLIC_HOST || 'localhost',
        port: Number(process.env.REVERB_PUBLIC_PORT || 8080),
        scheme: process.env.REVERB_PUBLIC_SCHEME || 'http',
      }
    : null;

  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'EducationalOrganization',
    name: t('app.name'),
    description: t('app.tagline'),
    ...(siteUrl ? { url: siteUrl.toString() } : {}),
  };

  return (
    <html
      lang={locale}
      dir="ltr"
      data-scroll-behavior="smooth"
      className={`${instrumentSans.variable} ${fraunces.variable}`}
    >
      <head>
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, '\\u003c') }}
        />
      </head>
      <body className="min-h-screen bg-paper font-sans text-ink antialiased">
        <NextIntlClientProvider>
          <QueryProvider>
            <AuthProvider initialHasAuthCookie={initialHasAuthCookie}>
              <RealtimeProvider config={reverbConfig}>
                {children}
              </RealtimeProvider>
            </AuthProvider>
          </QueryProvider>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
