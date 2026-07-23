'use client';

import { useTranslations } from 'next-intl';
import { RequireAuth } from '@/components/auth/RequireAuth';

export default function CertificatesPage() {
  const t = useTranslations();

  return (
    <RequireAuth>
      <div className="mx-auto max-w-7xl px-4 py-8">
      <h1 className="text-2xl font-bold text-gray-900">{t('nav.certificates')}</h1>
      <div className="mt-8 space-y-4">
        <p className="text-gray-500">{t('common.no_results')}</p>
      </div>
      </div>
    </RequireAuth>
  );
}
