'use client';

import Link from 'next/link';
import { useTranslations } from 'next-intl';

export default function HomePage() {
  const t = useTranslations();

  return (
    <div className="min-h-screen">
      <header className="border-b bg-white">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
          <h1 className="text-xl font-bold text-primary-600">{t('app.name')}</h1>
          <nav className="flex items-center gap-4">
            <Link href="/login" className="text-sm font-medium text-gray-600 hover:text-gray-900">
              {t('nav.login')}
            </Link>
            <Link
              href="/register"
              className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
            >
              {t('nav.register')}
            </Link>
          </nav>
        </div>
      </header>

      <main>
        <section className="bg-gradient-to-br from-primary-50 to-blue-100 px-4 py-24 sm:px-6 lg:px-8">
          <div className="mx-auto max-w-4xl text-center">
            <h2 className="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
              {t('app.tagline')}
            </h2>
            <p className="mt-6 text-lg leading-8 text-gray-600">
              Conectamos mentores y aprendices usando ciencia de datos y neurociencia para crear
              relaciones de mentoría altamente efectivas y personalizadas.
            </p>
            <div className="mt-10 flex items-center justify-center gap-4">
              <Link
                href="/register"
                className="rounded-lg bg-primary-600 px-8 py-3 text-lg font-medium text-white hover:bg-primary-700"
              >
                {t('nav.register')}
              </Link>
              <Link
                href="/mentors"
                className="rounded-lg border border-gray-300 bg-white px-8 py-3 text-lg font-medium text-gray-700 hover:bg-gray-50"
              >
                {t('nav.mentors')}
              </Link>
            </div>
          </div>
        </section>

        <section className="px-4 py-20 sm:px-6 lg:px-8">
          <div className="mx-auto max-w-7xl">
            <h3 className="text-center text-2xl font-bold text-gray-900">
              {t('client_types.select_title')}
            </h3>
            <div className="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
              {(['personal', 'familiar', 'grupal', 'empresa'] as const).map((type) => (
                <Link
                  key={type}
                  href={`/register?type=${type}`}
                  className="rounded-xl border bg-white p-6 shadow-sm transition hover:shadow-md"
                >
                  <h4 className="text-lg font-semibold text-gray-900">
                    {t(`client_types.${type}`)}
                  </h4>
                  <p className="mt-2 text-sm text-gray-600">
                    {t(`client_types.${type}_desc`)}
                  </p>
                </Link>
              ))}
            </div>
          </div>
        </section>
      </main>
    </div>
  );
}
