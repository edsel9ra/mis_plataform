'use client';

import { useTranslations } from 'next-intl';
import { CircleHelp, CircleSlash, Lock, TriangleAlert, type LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { formatMetricValue, metricUi, type MetricTone } from '@/lib/progress';
import type { MetricValue as MetricValueType } from '@/types/progress';

interface MetricValueProps {
  metric: MetricValueType | null | undefined;
  /** Explicit text (e.g. a ratio) rendered instead of the auto-formatted value. */
  valueOverride?: string | null;
  size?: 'md' | 'lg';
  className?: string;
}

const TONE_ICON: Record<MetricTone, LucideIcon> = {
  value: CircleHelp,
  zero: CircleHelp,
  empty: CircleHelp,
  incomplete: TriangleAlert,
  suppressed: CircleSlash,
  blocked: Lock,
};

const TONE_TEXT: Record<MetricTone, string> = {
  value: 'text-slate-900',
  zero: 'text-slate-900',
  empty: 'text-slate-400',
  incomplete: 'text-amber-600',
  suppressed: 'text-slate-400',
  blocked: 'text-slate-400',
};

/**
 * Renders a normalized metric: a real number (`0` included) when there is data,
 * or a distinguishable empty state (text + icon + color, never color alone) with
 * an `aria-label` when the source has no data, is suppressed or blocked (RF-13).
 */
export function MetricValue({ metric, valueOverride, size = 'md', className }: MetricValueProps) {
  const t = useTranslations();
  const state = metric?.state ?? 'no_data';
  const ui = metricUi(state);
  const text = valueOverride ?? formatMetricValue(metric);

  if (ui.showsNumber && text !== null) {
    return (
      <span
        className={cn(
          'inline-flex items-center gap-1 font-semibold text-slate-900',
          size === 'lg' ? 'text-3xl' : 'text-base',
          className,
        )}
      >
        {text}
        {state === 'incomplete' && (
          <TriangleAlert
            className="h-4 w-4 text-amber-500"
            aria-label={t('progress.metric.incomplete')}
          />
        )}
      </span>
    );
  }

  const Icon = TONE_ICON[ui.tone];
  const label = t(ui.messageKey ?? 'progress.metric.no_data');

  return (
    <span
      className={cn('inline-flex items-center gap-1.5 text-sm font-medium', TONE_TEXT[ui.tone], className)}
      aria-label={label}
    >
      <Icon className="h-4 w-4 shrink-0" aria-hidden="true" />
      {label}
    </span>
  );
}
