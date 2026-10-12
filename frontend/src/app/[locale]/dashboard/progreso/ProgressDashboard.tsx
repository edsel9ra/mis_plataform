'use client';

import { ProgressPanel } from '@/components/progress/ProgressPanel';
import { useProgressPanel } from '@/hooks/use-progress';

/** Client wrapper that loads the signed-in user's own progress panel (RF-3, RF-11). */
export function ProgressDashboard() {
  const query = useProgressPanel();

  return <ProgressPanel query={query} showCollectives />;
}
