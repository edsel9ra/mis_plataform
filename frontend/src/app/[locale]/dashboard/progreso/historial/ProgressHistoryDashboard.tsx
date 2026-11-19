'use client';

import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { ArrowLeft } from 'lucide-react';
import { ProgressHistory } from '@/components/progress/ProgressHistory';
import { localePath } from '@/i18n/paths';
import { useProgressHistory } from '@/hooks/use-progress';

/** Client wrapper that loads the signed-in user's own progress history (RF-015.1). */
export function ProgressHistoryDashboard() {
  const t = useTranslations();
  const locale = useLocale();
  const query = useProgressHistory();

  return (
    <div className="space-y-6">
      <Link
        href={localePath('/dashboard/progreso', locale)}
        className="inline-flex items-center gap-2 py-1 text-sm font-semibold text-slate-600 hover:text-primary-700"
      >
        <ArrowLeft className="h-4 w-4" aria-hidden="true" /> {t('progress.history.back')}
      </Link>
      <ProgressHistory query={query} />
    </div>
  );
}
