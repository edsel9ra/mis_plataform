'use client';

import { useMemo } from 'react';
import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { cn } from '@/lib/utils';
import { localePath } from '@/i18n/paths';
import { sessionStatusLabel } from '@/lib/session-statuses';
import {
  calendarLocalDate,
  groupCalendarItemsByDate,
  monthGrid,
  type CalendarItem,
} from '@/lib/scheduling';

interface CalendarMonthViewProps {
  /** Any absolute instant (ISO) inside the viewed local month. */
  reference: string;
  /** Viewer timezone of the range (must match `CalendarRange`). */
  timezone: string;
  items: CalendarItem[];
  chipsPerDay?: number;
}

const MAX_COMPACT_CHIPS = 3;

/**
 * Month view (RF-009.2): 6 Monday-start wall weeks; sessions appear on their
 * viewer-local day as compact chips that still carry the textual status
 * (006) and open the authorized detail.
 */
export function CalendarMonthView({
  reference,
  timezone,
  items,
  chipsPerDay = MAX_COMPACT_CHIPS,
}: CalendarMonthViewProps) {
  const t = useTranslations();
  const locale = useLocale();

  const grid = useMemo(() => monthGrid(reference, timezone), [reference, timezone]);
  const byDate = useMemo(() => groupCalendarItemsByDate(items), [items]);
  const itemsByDate = useMemo(
    () => new Map(byDate.map(({ date, items: dayItems }) => [date, dayItems])),
    [byDate],
  );
  const wallMonth = useMemo(() => calendarLocalDate(reference, timezone), [reference, timezone]);

  return (
    <section aria-label={t('calendar.period_month')}>
      <div className="grid grid-cols-7 gap-1 rounded-2xl border border-gray-200 bg-white p-2 shadow-sm">
        {grid.map((date) => {
          const inMonth = date.startsWith(wallMonth.slice(0, wallMonth.lastIndexOf('-')));

          return (
            <MonthCell
              key={date}
              wallDate={date}
              inMonth={inMonth}
              items={itemsByDate.get(date) ?? []}
              chipsPerDay={chipsPerDay}
              locale={locale}
              translate={t}
            />
          );
        })}
      </div>
    </section>
  );
}

interface MonthCellProps {
  wallDate: string;
  inMonth: boolean;
  items: CalendarItem[];
  chipsPerDay: number;
  locale: string;
  translate: ReturnType<typeof useTranslations>;
}

function MonthCell({
  wallDate,
  inMonth,
  items,
  chipsPerDay,
  locale,
  translate,
}: MonthCellProps) {
  const dayNumber = Number(wallDate.slice(8, 10));

  return (
    <div
      className={cn(
        'min-h-16 space-y-1 rounded-lg p-1',
        inMonth ? 'bg-gray-50/60' : 'bg-white opacity-60',
      )}
    >
      <p
        className={cn(
          'px-1 text-xs font-semibold',
          inMonth ? 'text-gray-700' : 'text-gray-400',
        )}
      >
        <span className="sr-only">{wallDate}</span>
        <span aria-hidden="true">{dayNumber}</span>
      </p>
      {items.slice(0, chipsPerDay).map((item) => (
        <Link
          key={item.id}
          href={localePath(`/sessions/${item.id}`, locale)}
          className="block rounded border border-primary-100 bg-primary-50 px-1 py-0.5 text-[0.65rem] leading-4 text-gray-900 hover:bg-primary-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        >
          <span className="block truncate font-semibold">
            {item.startTimeLabel}
            {' · '}
            {sessionStatusLabel(item.status, translate)}
          </span>
          <span className="block truncate">{item.title}</span>
        </Link>
      ))}
      {items.length > chipsPerDay && (
        <p className="px-1 text-[0.65rem] text-gray-500">
          +{String(items.length - chipsPerDay)} {translate('calendar.more_sessions')}
        </p>
      )}
    </div>
  );
}
