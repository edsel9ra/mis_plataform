'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { ArrowLeft, LockKeyhole } from 'lucide-react';
import { useAuth } from '@/components/auth/AuthProvider';
import { Alert } from '@/components/ui/alert';
import { ProgressPanel } from '@/components/progress/ProgressPanel';
import { localePath } from '@/i18n/paths';
import { ApiError } from '@/lib/api';
import { useMentorProgressPanel } from '@/hooks/use-progress';

export default function MentorMenteeProgressPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();
  const params = useParams<{ id: string }>();
  const [relationshipId, setRelationshipId] = useState<string | undefined>(undefined);

  useEffect(() => {
    setRelationshipId(new URLSearchParams(window.location.search).get('relationship_id') ?? '');
  }, []);

  const enabled = user?.role === 'mentor' && Boolean(params.id);
  const query = useMentorProgressPanel(params.id, enabled);
  const status = query.error instanceof ApiError ? query.error.status : 0;

  return (
    <div className="mx-auto max-w-7xl space-y-6">
      <Link
        href={localePath('/dashboard/mentor/progress', locale)}
        className="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-primary-700"
      >
        <ArrowLeft className="h-4 w-4" aria-hidden="true" /> {t('progress.mentor.back')}
      </Link>

      <h1 className="text-3xl font-bold text-slate-950">{t('progress.viewer.mentor')}</h1>

      {status === 403 ? (
        <Alert variant="warning">
          <span className="inline-flex items-center gap-2">
            <LockKeyhole className="h-4 w-4" aria-hidden="true" />
            {t('progress.mentor.workspace_locked')}
          </span>
        </Alert>
      ) : (
        <ProgressPanel query={query} relationshipId={relationshipId} showCollectives={false} />
      )}
    </div>
  );
}
