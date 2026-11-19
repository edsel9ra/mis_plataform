'use client';

import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { History } from 'lucide-react';
import { LearningPathsCard } from '@/components/progress/LearningPathsCard';
import { ProgressPanel } from '@/components/progress/ProgressPanel';
import { localePath } from '@/i18n/paths';
import { useLearningPathProgress, useProgressPanel } from '@/hooks/use-progress';

/** Client wrapper that loads the signed-in user's own progress panel (RF-3, RF-11). */
export function ProgressDashboard() {
  const t = useTranslations();
  const locale = useLocale();
  const query = useProgressPanel();
  const learningPaths = useLearningPathProgress();

  return (
    <div className="space-y-6">
      <Link
        href={localePath('/dashboard/progreso/historial', locale)}
        className="inline-flex items-center gap-2 py-1 text-sm font-semibold text-slate-600 hover:text-primary-700"
      >
        <History className="h-4 w-4" aria-hidden="true" /> {t('progress.history.view_history')}
      </Link>
      <ProgressPanel query={query} showCollectives />
      <LearningPathsCard
        query={learningPaths}
        mapHref={localePath('/dashboard/progreso/mapa', locale)}
      />
    </div>
  );
}
