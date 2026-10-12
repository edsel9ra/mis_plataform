'use client';

import { useTranslations } from 'next-intl';
import { Badge } from '@/components/ui/badge';
import type { ActivitiesBlock } from '@/types/progress';
import { MetricValue } from './MetricValue';
import { ProgressCard } from './ProgressCard';

/**
 * Pending and overdue activities with an overdue badge; canceled/template
 * challenges are already excluded by the backend (RF-6, CA-6.5).
 */
export function ActivitiesCard({ block }: { block: ActivitiesBlock }) {
  const t = useTranslations();

  return (
    <ProgressCard title={t('progress.activities.title')} period={block.metric.period}>
      <dl className="flex flex-wrap gap-6">
        <div>
          <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">
            {t('progress.activities.pending')}
          </dt>
          <dd className="mt-1">
            <MetricValue metric={block.metric} size="lg" />
          </dd>
        </div>
        <div>
          <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">
            {t('progress.activities.overdue')}
          </dt>
          <dd className="mt-1 text-2xl font-semibold text-red-600">{block.overdue}</dd>
        </div>
        <div>
          <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">
            {t('progress.activities.completed')}
          </dt>
          <dd className="mt-1 text-2xl font-semibold text-slate-900">{block.completed}</dd>
        </div>
      </dl>

      {block.pending.length > 0 ? (
        <ul className="mt-4 space-y-2">
          {block.pending.map((item) => (
            <li
              key={`${item.type}-${item.id}`}
              className="flex items-center justify-between gap-3 rounded-lg border border-slate-100 px-3 py-2 text-sm"
            >
              <span className="min-w-0 truncate text-slate-700">{item.title ?? '—'}</span>
              {item.overdue ? (
                <Badge variant="danger">{t('progress.activities.overdue')}</Badge>
              ) : null}
            </li>
          ))}
        </ul>
      ) : (
        <p className="mt-3 text-sm text-slate-500">{t('progress.activities.empty')}</p>
      )}
    </ProgressCard>
  );
}
