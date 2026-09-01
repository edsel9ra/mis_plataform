'use client';

import { useTranslations } from 'next-intl';
import { FileText, LibraryBig } from 'lucide-react';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { MentorChallengeForm } from '@/components/mentor-workspace/MentorChallengeForm';
import { useMentorChallenges } from '@/hooks/use-mentor-workspace';
import type { MentorChallenge } from '@/types/mentor-workspace';

export default function MentorChallengesPage() {
  const t = useTranslations();
  const query = useMentorChallenges();

  return (
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.8fr)]">
      <Card>
        <CardHeader>
          <div className="flex items-center gap-3">
            <LibraryBig className="h-6 w-6 text-primary-600" aria-hidden="true" />
            <div>
              <CardTitle>{t('mentor_workspace.challenge_library')}</CardTitle>
              <p className="mt-1 text-sm text-slate-500">{t('mentor_workspace.challenge_library_description')}</p>
            </div>
          </div>
        </CardHeader>
        {query.isLoading ? <p className="text-sm text-slate-500">{t('common.loading')}</p> : query.error ? <Alert variant="error">{t('mentor_workspace.feature_load_error')}</Alert> : query.data?.length ? (
          <div className="space-y-3">
            {query.data.map((challenge) => <ChallengeLibraryItem key={challenge.id} challenge={challenge} />)}
          </div>
        ) : <p className="text-sm text-slate-500">{t('mentor_workspace.no_challenges')}</p>}
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t('mentor_workspace.create_challenge')}</CardTitle>
          <p className="mt-1 text-sm text-slate-500">{t('mentor_workspace.create_challenge_description')}</p>
        </CardHeader>
        <MentorChallengeForm />
      </Card>
    </div>
  );
}

function ChallengeLibraryItem({ challenge }: { challenge: MentorChallenge }) {
  const t = useTranslations();
  const contentUrl = challenge.content_url || challenge.content_file_url;

  return (
    <div className="rounded-2xl border border-slate-100 p-4 transition hover:border-primary-200 hover:bg-primary-50/20">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <h2 className="font-semibold text-slate-950">{challenge.title}</h2>
          {challenge.description && <p className="mt-1 text-sm leading-6 text-slate-600">{challenge.description}</p>}
        </div>
        <Badge variant="default">{t('mentor_workspace.library_item')}</Badge>
      </div>
      {contentUrl ? <a href={contentUrl} target="_blank" rel="noreferrer" className="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:underline"><FileText className="h-4 w-4" aria-hidden="true" />{challenge.content_name || t('mentor_workspace.open_content')}</a> : <p className="mt-3 text-xs text-slate-500">{t('mentor_workspace.content_pending')}</p>}
    </div>
  );
}
