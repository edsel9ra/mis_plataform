'use client';

import { useTranslations } from 'next-intl';

export default function SessionsPage() {
  const t = useTranslations();

  return (
    <div className="mx-auto max-w-7xl px-4 py-8">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">{t('sessions.title')}</h1>
        <button className="rounded-lg bg-primary-600 px-4 py-2 text-sm text-white hover:bg-primary-700">
          {t('sessions.schedule')}
        </button>
      </div>

      <div className="mt-8 space-y-4">
        <p className="text-gray-500">{t('common.no_results')}</p>
      </div>
    </div>
  );
}
