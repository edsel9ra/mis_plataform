'use client';

import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { AlertTriangle, ChevronRight, Clock, LockKeyhole } from 'lucide-react';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { localePath } from '@/i18n/paths';
import { useMentorProgressOverview } from '@/hooks/use-progress';

/**
 * Mentor portfolio with overdue / no-activity / pending flags, linking to each
 * mentee's progress panel (RF-9, CA-9.4).
 */
export function MenteeFlagsList({ enabled = true }: { enabled?: boolean }) {
  const t = useTranslations();
  const locale = useLocale();
  const { data, isLoading, isError } = useMentorProgressOverview(enabled);

  if (isLoading) {
    return (
      <div className="flex justify-center py-10" role="status" aria-live="polite">
        <Spinner size="lg" />
      </div>
    );
  }

  if (isError) {
    return <Alert variant="error">{t('progress.error')}</Alert>;
  }

  const items = data ?? [];

  if (items.length === 0) {
    return <Card className="py-12 text-center text-sm text-slate-500">{t('progress.mentor.empty')}</Card>;
  }

  return (
    <ul className="grid gap-4 md:grid-cols-2">
      {items.map((flag) => (
        <li key={flag.relationship_id ?? flag.mentee?.id}>
          <Link
            href={localePath(
              `/dashboard/mentor/progress/${flag.mentee?.id}?relationship_id=${flag.relationship_id ?? ''}`,
              locale,
            )}
            className="block rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-primary-300 hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
          >
            <div className="flex items-center justify-between gap-3">
              <span className="truncate font-semibold text-slate-900">{flag.mentee?.name ?? '—'}</span>
              <ChevronRight className="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
            </div>

            <div className="mt-3 flex flex-wrap gap-2">
              {flag.objectives_overdue > 0 ? (
                <Badge variant="danger">
                  <AlertTriangle className="mr-1 h-3 w-3" aria-hidden="true" />
                  {t('progress.mentor.overdue_flag', { count: flag.objectives_overdue })}
                </Badge>
              ) : null}
              {flag.objectives_pending > 0 ? (
                <Badge variant="warning">{t('progress.mentor.pending_flag', { count: flag.objectives_pending })}</Badge>
              ) : null}
              {flag.no_activity ? (
                <Badge variant="default">
                  <Clock className="mr-1 h-3 w-3" aria-hidden="true" />
                  {t('progress.mentor.no_activity_flag')}
                </Badge>
              ) : null}
              {flag.workspace_locked ? (
                <Badge variant="default">
                  <LockKeyhole className="mr-1 h-3 w-3" aria-hidden="true" />
                  {t('progress.mentor.workspace_locked')}
                </Badge>
              ) : null}
            </div>

            <p className="mt-3 text-xs text-slate-500">
              {flag.last_activity_at
                ? t('progress.mentor.last_activity', { date: new Date(flag.last_activity_at).toLocaleDateString(locale) })
                : t('progress.mentor.no_activity_ever')}
            </p>
          </Link>
        </li>
      ))}
    </ul>
  );
}
