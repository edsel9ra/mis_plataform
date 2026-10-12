'use client';

import { useTranslations } from 'next-intl';
import { EyeOff } from 'lucide-react';
import { Alert } from '@/components/ui/alert';
import {
  COLLECTIVE_PRIVACY_THRESHOLD,
  formatAggregateMean,
  formatAggregateSum,
} from '@/lib/progress';
import type { CollectiveProgress } from '@/types/progress';
import { ProgressCard } from './ProgressCard';

/**
 * Collective aggregate: average + achieved/total sum + contributing base.
 * A suppressed aggregate shows only the reason — never figures or base size
 * (RF-10, RF-11, CA-11.3).
 */
export function CollectiveProgressCard({ collective }: { collective: CollectiveProgress }) {
  const t = useTranslations();
  const name = collective.subject?.name ?? t('progress.collective.title');
  const typeLabel = collective.subject
    ? t(`progress.collective.types.${collective.subject.type}`)
    : null;

  if (collective.suppressed) {
    return (
      <ProgressCard title={name}>
        {/* Suppression is announced to assistive tech (RNF-6): no figures, no base. */}
        <Alert variant="info" role="status" aria-live="polite">
          <span className="inline-flex items-center gap-2">
            <EyeOff className="h-4 w-4 shrink-0" aria-hidden="true" />
            {t('progress.collective.suppressed', { threshold: COLLECTIVE_PRIVACY_THRESHOLD })}
          </span>
        </Alert>
      </ProgressCard>
    );
  }

  if (collective.state === 'no_data' || !collective.metrics) {
    return (
      <ProgressCard title={name}>
        <p className="text-sm text-slate-500">{t('progress.collective.no_data')}</p>
      </ProgressCard>
    );
  }

  const mean = formatAggregateMean(collective);
  const sum = formatAggregateSum(collective);

  return (
    <ProgressCard title={name}>
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
    </ProgressCard>
  );
}
