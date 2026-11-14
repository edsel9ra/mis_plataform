'use client';

import { useMemo, useRef, useState } from 'react';
import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { cn } from '@/lib/utils';
import { localePath } from '@/i18n/paths';
import { sessionStatusLabel } from '@/lib/session-statuses';
import {
  calendarLocalDate,
  formatWallDate,
  groupCalendarItemsByDate,
  monthCellChips,
  monthGrid,
  type CalendarItem,
} from '@/lib/scheduling';

interface CalendarMonthViewProps {
  /** Any absolute instant (ISO) inside the viewed local month. */
  reference: string;
  /** Viewer timezone of the range (must match `CalendarRange`). */
  timezone: string;
  items: CalendarItem[];
  /** Explicit instant for today detection (pure derivation, no global clock). */
  now: string;
  chipsPerDay?: number;
}

const MAX_COMPACT_CHIPS = 3;

/**
 * Month view (RF-009.2, RF-010.2): 6 Monday-start wall weeks; sessions appear
 * on their viewer-local day as compact chips that still carry the textual
 * status (006) and open the authorized detail. Days beyond the compact limit
 * expand through a real button so every session is keyboard-reachable.
 */
export function CalendarMonthView({
  reference,
  timezone,
  items,
  now,
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
  const todayWallDate = useMemo(() => calendarLocalDate(now, timezone), [now, timezone]);
  const weekdayLabels = useMemo(
    () => grid.slice(0, 7).map((date) => formatWallDate(date, locale, { weekday: 'short' })),
    [grid, locale],
  );

  return (
    <section aria-label={t('calendar.period_month')}>
      <div className="grid grid-cols-7 gap-1" aria-hidden="true">
        {weekdayLabels.map((label) => (
          <p key={label} className="px-1 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
            {label}
          </p>
        ))}
      </div>
      <div className="mt-1 grid grid-cols-7 gap-1 rounded-2xl border border-gray-200 bg-white p-2 shadow-sm">
        {grid.map((date) => {
          const inMonth = date.startsWith(wallMonth.slice(0, wallMonth.lastIndexOf('-')));
          const dayItems = itemsByDate.get(date) ?? [];
          const dateLabel = formatWallDate(date, locale);

          return (
            <MonthCell
              key={date}
              wallDate={date}
              inMonth={inMonth}
              isToday={date === todayWallDate}
              items={dayItems}
              chipsPerDay={chipsPerDay}
              locale={locale}
              ariaLabel={`${dateLabel} — ${t('calendar.day_sessions_count', { count: dayItems.length })}`}
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
  isToday: boolean;
  items: CalendarItem[];
  chipsPerDay: number;
  locale: string;
  ariaLabel: string;
  translate: ReturnType<typeof useTranslations>;
}

function MonthCell({
  wallDate,
  inMonth,
  isToday,
  items,
  chipsPerDay,
  locale,
  ariaLabel,
  translate,
}: MonthCellProps) {
  const [expanded, setExpanded] = useState(false);
  const cellRef = useRef<HTMLDivElement>(null);
  const dayNumber = Number(wallDate.slice(8, 10));
  const { visible, hiddenCount } = monthCellChips(items, chipsPerDay, expanded);

  function toggleExpanded() {
    const next = !expanded;
    setExpanded(next);
    if (next) {
      // Move focus to the first newly revealed chip so keyboard users land
      // inside the expanded day instead of hunting for the hidden links.
      requestAnimationFrame(() => {
        const links = cellRef.current?.querySelectorAll<HTMLAnchorElement>('[data-month-chip]');
        links?.item(chipsPerDay)?.focus();
      });
    }
  }

  return (
    <div
      ref={cellRef}
      role="group"
      aria-label={ariaLabel}
      aria-current={isToday ? 'date' : undefined}
      className={cn(
        'min-h-16 space-y-1 rounded-lg p-1',
        inMonth ? 'bg-gray-50/60' : 'bg-white',
        isToday && 'ring-1 ring-primary-300',
      )}
    >
      <p
        className={cn(
          'px-1 text-xs font-semibold',
          inMonth ? 'text-gray-700' : 'text-gray-500',
        )}
      >
        <span aria-hidden="true">{dayNumber}</span>
      </p>
      {visible.map((item) => (
        <Link
          key={item.id}
          href={localePath(`/sessions/${item.id}`, locale)}
          data-month-chip
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
      {(hiddenCount > 0 || expanded) && items.length > chipsPerDay && (
        <button
          type="button"
          aria-expanded={expanded}
          onClick={toggleExpanded}
          className="block min-h-6 w-full rounded px-1 text-left text-[0.65rem] font-medium text-primary-600 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        >
          {expanded
            ? translate('calendar.show_fewer_sessions')
            : translate('calendar.show_more_sessions', { count: hiddenCount })}
        </button>
      )}
    </div>
  );
}
