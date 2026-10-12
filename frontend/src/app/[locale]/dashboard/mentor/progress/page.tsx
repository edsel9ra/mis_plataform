'use client';

import { useTranslations } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';
import { MenteeFlagsList } from '@/components/progress/MenteeFlagsList';

export default function MentorProgressPage() {
  const t = useTranslations();
  const { user } = useAuth();

  return (
    <div className="mx-auto max-w-7xl space-y-6">
      <header>
        <h1 className="text-3xl font-bold text-slate-950">{t('progress.mentor.title')}</h1>
        <p className="mt-2 text-sm text-slate-500">{t('progress.mentor.subtitle')}</p>
      </header>
      <MenteeFlagsList enabled={user?.role === 'mentor'} />
    </div>
  );
}
