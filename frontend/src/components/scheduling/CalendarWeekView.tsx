'use client';

import { useMemo } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { CalendarSessionChip } from './CalendarSessionChip';
import {
  calendarLocalDate,
  dayWallDates,
  formatWallDate,
  groupCalendarItemsByDate,
  type CalendarItem,
} from '@/lib/scheduling';

interface CalendarWeekViewProps {
  /** Absolute start instant (ISO) of the Monday that starts the week. */
  rangeFrom: string;
  /** Viewer timezone of the session items (must match `CalendarRange`). */
  timezone: string;
  items: CalendarItem[];
}

const WEEKDAYS_FORMAT = { weekday: 'short', day: 'numeric' } as const;

/**
 * Week view (RF-009.2, CA-009.2.1): the 7 local days from Monday 00:00 to the
 * next Monday 00:00 of the viewer zone, independent of the active locale.
 */
export function CalendarWeekView({ rangeFrom, timezone, items }: CalendarWeekViewProps) {
  const t = useTranslations();
  const locale = useLocale();

  const wallDates = useMemo(
    () => dayWallDates(calendarLocalDate(rangeFrom, timezone), 7),
    [rangeFrom, timezone],
  );

  const byDate = useMemo(() => groupCalendarItemsByDate(items), [items]);
  const itemsByDate = useMemo(
    () => new Map(byDate.map(({ date, items: dayItems }) => [date, dayItems])),
    [byDate],
  );

  return (
    <section aria-label={t('calendar.period_week')}>
      <div className="grid gap-3 md:grid-cols-3 xl:grid-cols-7">
        {wallDates.map((date) => (
          <div key={date} className="min-w-0 space-y-2 rounded-2xl border border-gray-200 bg-gray-50/60 p-2">
            <h2 className="text-xs font-semibold uppercase tracking-wide text-gray-500">
              <span className="sr-only">{formatWallDate(date, locale)}</span>
              <span aria-hidden="true">
                {formatWallDate(date, locale, WEEKDAYS_FORMAT)}
              </span>
            </h2>
            <div className="space-y-2">
              {(itemsByDate.get(date) ?? []).map((item) => (
                <CalendarSessionChip key={item.id} item={item} className="bg-white" />
              ))}
              {(itemsByDate.get(date) ?? []).length === 0 && (
                <p className="text-xs text-gray-500">{t('calendar.no_sessions_in_day')}</p>
              )}
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}
