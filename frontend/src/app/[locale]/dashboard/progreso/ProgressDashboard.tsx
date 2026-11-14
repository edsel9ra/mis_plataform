'use client';

import { useLocale } from 'next-intl';
import { LearningPathsCard } from '@/components/progress/LearningPathsCard';
import { ProgressPanel } from '@/components/progress/ProgressPanel';
import { localePath } from '@/i18n/paths';
import { useLearningPathProgress, useProgressPanel } from '@/hooks/use-progress';

/** Client wrapper that loads the signed-in user's own progress panel (RF-3, RF-11). */
export function ProgressDashboard() {
  const locale = useLocale();
  const query = useProgressPanel();
  const learningPaths = useLearningPathProgress();

  return (
    <div className="space-y-6">
      <ProgressPanel query={query} showCollectives />
      <LearningPathsCard
        query={learningPaths}
        mapHref={localePath('/dashboard/progreso/mapa', locale)}
      />
    </div>
  );
}
