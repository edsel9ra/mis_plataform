'use client';

import { useLocale, useTranslations } from 'next-intl';
import { CalendarSessionChip } from './CalendarSessionChip';
import type { CalendarItem } from '@/lib/scheduling';

interface CalendarDayViewProps {
  /** Absolute start instant (ISO) of the day from `CalendarRange`. */
  rangeFrom: string;
  items: CalendarItem[];
}

/** Day view (RF-009.2): every authorized session of the single local day. */
export function CalendarDayView({ rangeFrom, items }: CalendarDayViewProps) {
  const t = useTranslations();
  const locale = useLocale();

  return (
    <section aria-label={t('calendar.period_day')} className="space-y-3">
      <h2 className="text-sm font-semibold text-gray-950">
        {new Intl.DateTimeFormat(locale, {
          weekday: 'long',
          day: 'numeric',
          month: 'long',
          year: 'numeric',
        }).format(new Date(rangeFrom))}
      </h2>
      {items.length === 0 ? (
        <p className="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500">
          {t('calendar.no_sessions_in_day')}
        </p>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {items.map((item) => (
            <CalendarSessionChip key={item.id} item={item} />
          ))}
        </div>
      )}
    </section>
  );
}
