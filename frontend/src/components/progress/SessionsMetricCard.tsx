'use client';

import { useTranslations } from 'next-intl';
import type { SessionsBlock } from '@/types/progress';
import { MetricValue } from './MetricValue';
import { ProgressCard } from './ProgressCard';

/**
 * Sessions held in the current calendar month vs. upcoming scheduled sessions,
 * visually distinguished and period-labelled (RF-5).
 */
export function SessionsMetricCard({ block }: { block: SessionsBlock }) {
  const t = useTranslations();
  const scheduledCount = block.scheduled.length;

  return (
    <ProgressCard title={t('progress.sessions.title')} period={block.metric.period}>
      <div className="flex flex-wrap items-end gap-8">
        <div>
          <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
            {t('progress.sessions.realized')}
          </p>
          <MetricValue metric={block.metric} size="lg" className="mt-1" />
        </div>
        <div className="border-l border-dashed border-slate-200 pl-8">
          <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
            {t('progress.sessions.scheduled')}
          </p>
          <span className="mt-1 block text-3xl font-semibold text-primary-700">{scheduledCount}</span>
        </div>
      </div>
      {scheduledCount === 0 ? (
        <p className="mt-3 text-sm text-slate-500">{t('progress.sessions.empty')}</p>
      ) : null}
    </ProgressCard>
  );
}
