'use client';

import { useTranslations } from 'next-intl';
import { hasMetricData } from '@/lib/progress';
import type { ObjectivesBlock, ProgressPeriod } from '@/types/progress';
import { MetricValue } from './MetricValue';
import { ProgressCard } from './ProgressCard';

/**
 * Objective achievement percentage with the achieved/total ratio visible to
 * reconcile the figure (RF-4, CA-4.3). "No data" when there are no objectives.
 */
export function ObjectiveAchievementCard({
  block,
  period,
}: {
  block: ObjectivesBlock;
  period?: ProgressPeriod | null;
}) {
  const t = useTranslations();
  const numerator = block.metric.numerator ?? 0;
  const denominator = block.metric.denominator ?? 0;
  const hasData = hasMetricData(block.metric.state) && denominator > 0;

  return (
    <ProgressCard title={t('progress.objectives.achievement_title')} period={period}>
      <MetricValue metric={block.metric} size="lg" />
      {hasData ? (
        <>
          <p className="mt-1 text-sm text-slate-500">
            {t('progress.objectives.ratio', { achieved: numerator, total: denominator })}
          </p>
          <p className="mt-2 text-sm text-slate-600">
            {t('progress.objectives.overdue_count', { count: block.overdue })}
          </p>
        </>
      ) : null}
    </ProgressCard>
  );
}
