'use client';

import { useLocale, useTranslations } from 'next-intl';
import { Calendar, ClipboardList, Target } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { sortCommitments } from '@/lib/progress';
import type { CommitmentItem, CommitmentsBlock } from '@/types/progress';
import { ProgressCard } from './ProgressCard';

const KIND_ICON = {
  session: Calendar,
  activity: ClipboardList,
  objective: Target,
} as const;

function formatWhen(iso: string, locale: string): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;

  return date.toLocaleString(locale, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZoneName: 'short',
  });
}

/**
 * Upcoming commitments (sessions + due dates) sorted by date with the timezone
 * reflected consistently; shows an empty state when there is nothing (RF-8).
 */
export function UpcomingCommitmentsCard({ block }: { block: CommitmentsBlock }) {
  const t = useTranslations();
  const locale = useLocale();
  const items: CommitmentItem[] = sortCommitments(block.items);

  return (
    <ProgressCard title={t('progress.commitments.title')} period={block.metric.period}>
      {items.length > 0 ? (
        <ul className="space-y-2">
          {items.map((item) => {
            const Icon = KIND_ICON[item.kind];

            return (
              <li
                key={`${item.kind}-${item.id}`}
                className="flex items-center gap-3 rounded-lg border border-slate-100 px-3 py-2"
              >
                <Icon className="h-4 w-4 shrink-0 text-primary-600" aria-hidden="true" />
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium text-slate-800">{item.title ?? '—'}</p>
                  <p className="text-xs text-slate-500">{formatWhen(item.at, locale)}</p>
                </div>
                <Badge variant="default">{t(`progress.commitments.kind.${item.kind}`)}</Badge>
              </li>
            );
          })}
        </ul>
      ) : (
        <p className="text-sm text-slate-500">{t('progress.commitments.empty')}</p>
      )}
    </ProgressCard>
  );
}
