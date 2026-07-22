'use client';

import Link from 'next/link';
import { useTranslations } from 'next-intl';

export default function RegisterPage() {
  const t = useTranslations();

  const types = [
    { key: 'personal', icon: '👤', color: 'border-blue-200 hover:border-blue-400' },
    { key: 'familiar', icon: '👨‍👩‍👧‍👦', color: 'border-green-200 hover:border-green-400' },
    { key: 'grupal', icon: '👥', color: 'border-purple-200 hover:border-purple-400' },
    { key: 'empresa', icon: '🏢', color: 'border-amber-200 hover:border-amber-400' },
  ] as const;

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <div className="w-full max-w-3xl">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-gray-900">{t('client_types.select_title')}</h1>
        </div>

        <div className="mt-12 grid gap-6 sm:grid-cols-2">
          {types.map(({ key, icon, color }) => (
            <Link
              key={key}
              href={`/register/${key}`}
              className={`rounded-xl border-2 bg-white p-8 text-center shadow-sm transition ${color}`}
            >
              <span className="text-4xl">{icon}</span>
              <h3 className="mt-4 text-xl font-semibold text-gray-900">{t(`client_types.${key}`)}</h3>
              <p className="mt-2 text-sm text-gray-600">{t(`client_types.${key}_desc`)}</p>
            </Link>
          ))}
        </div>

        <p className="mt-8 text-center text-sm text-gray-600">
          {t('auth.has_account')}{' '}
          <Link href="/login" className="font-medium text-primary-600 hover:text-primary-500">
            {t('nav.login')}
          </Link>
        </p>
      </div>
    </div>
  );
}
