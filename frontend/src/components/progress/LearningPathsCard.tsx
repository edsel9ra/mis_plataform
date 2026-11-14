'use client';

import { useLocale, useTranslations } from 'next-intl';
import { Badge } from '@/components/ui/badge';
import { ProgressCard } from './ProgressCard';
import {
  formatMetricValue,
  formatRatio,
  localizedTitle,
  metricUi,
  moduleCompletionLabelKey,
} from '@/lib/progress';
import type { LearningPathProgress, MetricValue } from '@/types/progress';

interface LearningPathsQuery {
  data: LearningPathProgress[] | undefined;
  isLoading: boolean;
  isError: boolean;
}

/**
 * "Rutas de aprendizaje" block: per-path rate plus per-module activity rate
 * and completion state as TEXT — `no_data` and `incomplete` render as words,
 * never as a misleading 0% (RF-012.1-.6, RNF-012.3).
 */
export function LearningPathsCard({ query }: { query: LearningPathsQuery }) {
  const t = useTranslations();
  const locale = useLocale();

  if (query.isLoading || query.isError || !query.data?.length) {
    return null;
  }

  return (
    <ProgressCard title={t('progress.learning_paths.title')}>
      <ul className="space-y-4">
        {query.data.map((entry) => (
          <li key={entry.learning_path.id} className="rounded-xl border border-slate-100 p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h3 className="font-semibold text-slate-950">
                {localizedTitle(entry.learning_path.title, locale)}
              </h3>
              <PathRateBadge rate={entry.rate} />
            </div>
            <ul className="mt-3 space-y-2">
              {entry.modules.map((module) => (
                <li
                  key={module.id}
                  className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-sm"
                >
                  <span className="text-slate-700">
                    <span className="text-slate-500">{module.position}.</span> {module.title}
                  </span>
                  <span className="flex items-center gap-3 text-xs">
                    <ModuleActivityText rate={module.activity_rate} />
                    <Badge variant={module.completion.state === 'completed' ? 'success' : 'default'}>
                      {t(moduleCompletionLabelKey(module.completion.state))}
                    </Badge>
                  </span>
                </li>
              ))}
            </ul>
          </li>
        ))}
      </ul>
    </ProgressCard>
  );
}

function PathRateBadge({ rate }: { rate: MetricValue }) {
  const t = useTranslations();
  const ui = metricUi(rate.state);
  const value = formatMetricValue(rate);
  const ratio = formatRatio(rate.numerator, rate.denominator);

  if (!ui.showsNumber || value === null) {
    return <Badge variant="default">{ui.messageKey ? t(ui.messageKey) : t('progress.metric.no_data')}</Badge>;
  }

  return (
    <Badge variant="default">
      {value}{ratio ? ` (${ratio})` : ''}
    </Badge>
  );
}

function ModuleActivityText({ rate }: { rate: MetricValue }) {
  const t = useTranslations();
  const ui = metricUi(rate.state);
  const value = formatMetricValue(rate);

  if (!ui.showsNumber || value === null) {
    return (
      <span className="text-slate-500">
        {ui.messageKey ? t(ui.messageKey) : t('progress.metric.no_data')}
      </span>
    );
  }

  return (
    <span className="font-medium text-slate-700">
      {t('progress.learning_paths.activity_value', { value })}
    </span>
  );
}
