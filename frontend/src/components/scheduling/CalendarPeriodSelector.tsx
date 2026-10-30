'use client';

import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useTranslations } from 'next-intl';
import { cn } from '@/lib/utils';
import type { CalendarPeriod } from '@/lib/scheduling';

interface CalendarPeriodSelectorProps {
  period: CalendarPeriod;
  heading: string;
  onPeriodChange: (period: CalendarPeriod) => void;
  onPrevious: () => void;
  onNext: () => void;
  onToday: () => void;
}

/**
 * Period switch (day/week/month) with prev/next wall-period navigation and a
 * "today" reset (RF-009.2). Labels are translated; boundaries stay pure.
 */
export function CalendarPeriodSelector({
  period,
  heading,
  onPeriodChange,
  onPrevious,
  onNext,
  onToday,
}: CalendarPeriodSelectorProps) {
  const t = useTranslations();

  const periods: Array<{ value: CalendarPeriod; label: string }> = [
    { value: 'day', label: t('calendar.period_day') },
    { value: 'week', label: t('calendar.period_week') },
    { value: 'month', label: t('calendar.period_month') },
  ];

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div
        className="inline-flex rounded-xl border border-gray-200 bg-white p-1 shadow-sm"
        role="group"
        aria-label={t('calendar.period_selector')}
      >
        {periods.map(({ value, label }) => (
          <button
            key={value}
            type="button"
            onClick={() => onPeriodChange(value)}
            aria-pressed={period === value}
            aria-label={t('calendar.period_option', { period: label })}
            className={cn(
              'rounded-lg px-4 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
              period === value
                ? 'bg-primary-600 text-white hover:bg-primary-700'
                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
            )}
          >
            {label}
          </button>
        ))}
      </div>

      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={onPrevious}
          aria-label={t('common.previous')}
          className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm transition-colors hover:bg-gray-50 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        >
          <ChevronLeft className="h-4 w-4" aria-hidden="true" />
        </button>
        <button
          type="button"
          onClick={onToday}
          className="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        >
          {t('calendar.today')}
        </button>
        <button
          type="button"
          onClick={onNext}
          aria-label={t('common.next')}
          className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm transition-colors hover:bg-gray-50 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        >
          <ChevronRight className="h-4 w-4" aria-hidden="true" />
        </button>
        <p className="ml-2 text-sm font-semibold text-gray-950" aria-live="polite">
          {heading}
        </p>
      </div>
    </div>
  );
}
