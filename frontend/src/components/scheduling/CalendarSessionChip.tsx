'use client';

import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { CalendarDays, ExternalLink, Video } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { localePath } from '@/i18n/paths';
import { sessionStatusLabel, sessionStatusVariant } from '@/lib/session-statuses';
import type { CalendarItem } from '@/lib/scheduling';
import { cn } from '@/lib/utils';

interface CalendarSessionChipProps {
  item: CalendarItem;
  compact?: boolean;
  className?: string;
}

/**
 * One session in a calendar cell (RF-009.1/2): viewer-zone time, duration,
 * textual status (006), attendees (as authorized by the backend), collective
 * mark (RF-009.4) and the Meet contract of 008 (active link or pending).
 * The whole card opens the authorized detail; permissions are never computed
 * here (RF-009.5).
 */
export function CalendarSessionChip({
  item,
  compact = false,
  className,
}: CalendarSessionChipProps) {
  const t = useTranslations();
  const locale = useLocale();
  const detailHref = localePath(`/sessions/${item.id}`, locale);

  return (
    <div
      className={cn(
        'group relative rounded-2xl border border-gray-200 bg-white p-3 shadow-sm transition-colors hover:border-primary-300 hover:bg-primary-50/40',
        className,
      )}
    >
      <div className="flex items-start justify-between gap-2">
        <p className="font-semibold text-gray-950">{item.startTimeLabel}</p>
        <Badge variant={sessionStatusVariant(item.status)}>
          {sessionStatusLabel(item.status, t)}
        </Badge>
      </div>

      <Link
        href={detailHref}
        // One tab stop per session: stretched link whose name carries time,
        // title and status; the Meet anchor stays reachable via z-10.
        className="mt-1 block rounded-lg p-1 -m-1 text-sm font-semibold text-gray-950 underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 after:absolute after:inset-0"
      >
        <span className="sr-only">
          {item.startTimeLabel} — {sessionStatusLabel(item.status, t)} —{' '}
        </span>
        {item.title}
      </Link>

      <p className={cn('mt-1 text-xs text-gray-500', compact && 'hidden')}>
        {item.durationMinutes} {t('sessions.minutes')}
        {item.sessionType ? ` · ${t(`session_types.${item.sessionType}`)}` : ''}
      </p>

      {!compact && item.attendeeNames.length > 0 && (
        <p className="mt-1 text-xs text-gray-600">
          <span className="sr-only">{t('calendar.participants')}:</span>
          {item.attendeeNames.join(', ')}
        </p>
      )}

      {item.isCollective && (
        <Badge variant="default" className="mt-2 gap-1">
          <CalendarDays className="h-3 w-3" aria-hidden="true" />
          {item.collectiveName ?? t('calendar.collective_session')}
        </Badge>
      )}

      {compact && item.meet.state === 'available' && (
        <span className="sr-only">{t('calendar.meet_available')}</span>
      )}

      {!compact && (
        <p className="mt-2 text-xs">
          {item.meet.state === 'available' && item.meet.link && (
            <a
              href={item.meet.link}
              target="_blank"
              rel="noreferrer"
              className="relative z-10 inline-flex items-center gap-1 font-semibold text-primary-600 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
              <Video className="h-4 w-4" aria-hidden="true" />
              {t('sessions.join_meet')}
              <ExternalLink className="h-4 w-4" aria-hidden="true" />
            </a>
          )}
          {item.meet.state === 'pending' && (
            <span className="inline-flex items-center gap-1 text-gray-500">
              <Video className="h-4 w-4" aria-hidden="true" />
              {t('calendar.meet_pending')}
            </span>
          )}
          {item.meet.state === 'hidden' && (
            <span className="sr-only">{t('calendar.meet_hidden')}</span>
          )}
        </p>
      )}
    </div>
  );
}
