'use client';

import { useLocale, useTranslations } from 'next-intl';
import { EyeOff } from 'lucide-react';
import { Alert } from '@/components/ui/alert';
import {
  COLLECTIVE_PRIVACY_THRESHOLD,
  formatAggregateMean,
  formatAggregateSum,
  formatPercent,
  learningSuppressionMessageKey,
  localizedTitle,
} from '@/lib/progress';
import type {
  AggregateLearningMetric,
  CollectiveLearningAggregate,
  CollectiveProgress,
} from '@/types/progress';
import { ProgressCard } from './ProgressCard';

/**
 * Collective aggregate: average + achieved/total sum + contributing base.
 * A suppressed aggregate shows only the reason — never figures or base size
 * (RF-10, RF-11, CA-11.3). The optional `learning` block (spec 013) renders
 * with its own independent suppression gate.
 */
export function CollectiveProgressCard({ collective }: { collective: CollectiveProgress }) {
  const t = useTranslations();
  const name = collective.subject?.name ?? t('progress.collective.title');
  const typeLabel = collective.subject
    ? t(`progress.collective.types.${collective.subject.type}`)
    : null;

  let baseline;
  if (collective.suppressed) {
    baseline = (
      /* Suppression is announced to assistive tech (RNF-6): no figures, no base. */
      <Alert variant="info" role="status" aria-live="polite">
        <span className="inline-flex items-center gap-2">
          <EyeOff className="h-4 w-4 shrink-0" aria-hidden="true" />
          {t('progress.collective.suppressed', { threshold: COLLECTIVE_PRIVACY_THRESHOLD })}
        </span>
      </Alert>
    );
  } else if (collective.state === 'no_data' || !collective.metrics) {
    baseline = <p className="text-sm text-slate-500">{t('progress.collective.no_data')}</p>;
  } else {
    const mean = formatAggregateMean(collective);
    const sum = formatAggregateSum(collective);

    baseline = (
      <>
        {typeLabel ? <p className="-mt-2 mb-3 text-xs text-slate-400">{typeLabel}</p> : null}
        <div className="flex flex-wrap items-end gap-8">
          <div>
            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
              {t('progress.collective.mean')}
            </p>
            <span className="mt-1 block text-3xl font-semibold text-slate-900">{mean ?? '—'}</span>
          </div>
          <div>
            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
              {t('progress.collective.sum')}
            </p>
            <span className="mt-1 block text-2xl font-semibold text-slate-900">{sum ?? '—'}</span>
          </div>
        </div>
        {collective.base?.contributing_members != null ? (
          <p className="mt-3 text-sm text-slate-500">
            {t('progress.collective.contributing', { count: collective.base.contributing_members })}
          </p>
        ) : null}
      </>
    );
  }

  return (
    <ProgressCard title={name}>
      {baseline}
      {collective.learning ? <CollectiveLearningSection learning={collective.learning} /> : null}
    </ProgressCard>
  );
}

/**
 * Learning aggregate block (RF-013.5–.8): server-computed averages only —
 * no mean is ever computed here, and a suppressed block/metric renders a
 * translated reason with no counts, sizes, numerators or denominators.
 */
function CollectiveLearningSection({ learning }: { learning: CollectiveLearningAggregate }) {
  const t = useTranslations();
  const locale = useLocale();

  if (learning.state === 'suppressed') {
    return (
      <div className="mt-4 border-t border-slate-100 pt-4">
        <h3 className="text-sm font-semibold text-slate-900">{t('progress.collective.learning.title')}</h3>
        <Alert variant="info" role="status" aria-live="polite" className="mt-2">
          <span className="inline-flex items-center gap-2">
            <EyeOff className="h-4 w-4 shrink-0" aria-hidden="true" />
            {t(learningSuppressionMessageKey(learning.suppression_reason))}
          </span>
        </Alert>
      </div>
    );
  }

  const activity = learning.activity_rate ?? null;
  const rates = learning.rates ?? [];

  return (
    <div className="mt-4 border-t border-slate-100 pt-4">
      <h3 className="text-sm font-semibold text-slate-900">{t('progress.collective.learning.title')}</h3>
      <dl className="mt-2 space-y-2 text-sm">
        <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
          <dt className="text-slate-700">{t('progress.collective.learning.activity_rate')}</dt>
          <dd><LearningMetricValue metric={activity} /></dd>
        </div>
        {rates.length > 0 ? (
          <>
            <dt className="pt-1 text-xs font-medium uppercase tracking-wide text-slate-500">
              {t('progress.collective.learning.path_rates')}
            </dt>
            {rates.map((rate) => (
              <div
                key={`${rate.path_id}-${rate.version_key}`}
                className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1"
              >
                <dd className="text-slate-700">{localizedTitle(rate.title, locale)}</dd>
                <dd><LearningMetricValue metric={rate.rate} /></dd>
              </div>
            ))}
          </>
        ) : null}
      </dl>
    </div>
  );
}

function LearningMetricValue({ metric }: { metric: AggregateLearningMetric | null }) {
  const t = useTranslations();

  if (!metric) {
    return <span className="font-medium text-slate-900">—</span>;
  }

  if (metric.state === 'suppressed') {
    return (
      <span className="text-slate-500">
        {t(learningSuppressionMessageKey(metric.suppression_reason))}
      </span>
    );
  }

  const value = formatPercent(metric.value);

  return <span className="font-medium text-slate-900">{value ?? '—'}</span>;
}
