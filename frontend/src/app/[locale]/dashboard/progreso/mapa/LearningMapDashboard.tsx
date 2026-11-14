'use client';

import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { ArrowLeft } from 'lucide-react';
import { LearningMap } from '@/components/progress/LearningMap';
import { localePath } from '@/i18n/paths';
import { useLearningMap } from '@/hooks/use-progress';

/** Client wrapper that loads the signed-in user's own learning map (RF-013.1). */
export function LearningMapDashboard() {
  const t = useTranslations();
  const locale = useLocale();
  const query = useLearningMap();

  return (
    <div className="space-y-6">
      <Link
        href={localePath('/dashboard/progreso', locale)}
        className="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-primary-700"
      >
        <ArrowLeft className="h-4 w-4" aria-hidden="true" /> {t('progress.map.back')}
      </Link>
      <LearningMap query={query} />
    </div>
  );
}
