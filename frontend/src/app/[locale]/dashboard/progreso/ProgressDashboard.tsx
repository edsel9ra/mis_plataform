'use client';

import { LearningPathsCard } from '@/components/progress/LearningPathsCard';
import { ProgressPanel } from '@/components/progress/ProgressPanel';
import { useLearningPathProgress, useProgressPanel } from '@/hooks/use-progress';

/** Client wrapper that loads the signed-in user's own progress panel (RF-3, RF-11). */
export function ProgressDashboard() {
  const query = useProgressPanel();
  const learningPaths = useLearningPathProgress();

  return (
    <div className="space-y-6">
      <ProgressPanel query={query} showCollectives />
      <LearningPathsCard query={learningPaths} />
    </div>
  );
}
