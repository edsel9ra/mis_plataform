'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useLocale, useTranslations } from 'next-intl';
import { ArrowLeft, LockKeyhole } from 'lucide-react';
import { useAuth } from '@/components/auth/AuthProvider';
import { Alert } from '@/components/ui/alert';
import { ProgressHistory } from '@/components/progress/ProgressHistory';
import { localePath } from '@/i18n/paths';
import { ApiError } from '@/lib/api';
import { useMentorProgressHistory } from '@/hooks/use-progress';

/** Mentor view of a mentee's progress history — authorized server-side (RF-015.4). */
export default function MentorMenteeHistoryPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();
  const params = useParams<{ id: string }>();

  const enabled = user?.role === 'mentor' && Boolean(params.id);
  const query = useMentorProgressHistory(params.id, enabled);
  const status = query.error instanceof ApiError ? query.error.status : 0;

  return (
    <div className="mx-auto max-w-7xl space-y-6">
      <Link
        href={localePath(`/dashboard/mentor/progress/${params.id}`, locale)}
        className="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-primary-700"
      >
        <ArrowLeft className="h-4 w-4" aria-hidden="true" /> {t('progress.history.back')}
      </Link>

      <h1 className="text-3xl font-bold text-slate-950">{t('progress.history.title_mentor')}</h1>

      {status === 403 ? (
        <Alert variant="warning">
          <span className="inline-flex items-center gap-2">
            <LockKeyhole className="h-4 w-4" aria-hidden="true" />
            {t('progress.mentor.workspace_locked')}
          </span>
        </Alert>
      ) : (
        <ProgressHistory query={query} />
      )}
    </div>
  );
}
