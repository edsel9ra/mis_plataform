'use client';

import { useLocale, useTranslations } from 'next-intl';
import { LockKeyhole } from 'lucide-react';
import { AppShell } from '@/components/layout/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { PageSpinner, Spinner } from '@/components/ui/spinner';
import { ChallengePathModuleSelect } from '@/components/progress/ChallengePathModuleSelect';
import { ApiError } from '@/lib/api';
import { challengeStatusKey } from '@/lib/progress';
import { useMenteeChallenges } from '@/hooks/use-challenges';
import { useLearningPathProgress } from '@/hooks/use-progress';
import { useRelationships } from '@/hooks/use-relationships';
import type { MentorChallenge } from '@/types/mentor-workspace';
import type { Relationship } from '@/hooks/use-relationships';
import type { LearningPathProgress } from '@/types/progress';

/**
 * «Mis retos» — the mentee's own challenge list with the module link
 * selector (spec 012, T012.9). Mentee-only: other roles see a "not
 * available" state and the page issues no challenge API calls.
 */
export default function ChallengesPage() {
  const t = useTranslations();
  const { user, isLoading: authLoading } = useAuth();
  const isMentee = user?.role === 'mentee';

  const relationships = useRelationships(1, 50, isMentee);
  const learningPaths = useLearningPathProgress(isMentee);

  const activePersonal = (relationships.data?.data ?? []).filter(
    (rel) => rel.type === 'personal' && rel.status === 'active',
  );

  return (
    <AppShell>
      <div className="mx-auto max-w-5xl space-y-6">
        <header>
          <h1 className="text-3xl font-bold text-slate-950">{t('challenges.title')}</h1>
          <p className="mt-2 text-sm text-slate-500">{t('challenges.subtitle')}</p>
        </header>

        {authLoading || (isMentee && relationships.isLoading) ? (
          <PageSpinner />
        ) : !isMentee ? (
          <Card>
            <p className="flex items-center gap-2 text-sm text-slate-600">
              <LockKeyhole className="h-4 w-4 text-slate-500" aria-hidden="true" />
              {t('challenges.mentee_only')}
            </p>
          </Card>
        ) : relationships.isError ? (
          <Alert variant="error">
            <div className="space-y-3">
              <p>{t('challenges.load_error')}</p>
              <Button type="button" size="sm" variant="outline" onClick={() => relationships.refetch()}>
                {t('progress.retry')}
              </Button>
            </div>
          </Alert>
        ) : activePersonal.length === 0 ? (
          <Card><p className="text-sm text-slate-500">{t('challenges.no_relationships')}</p></Card>
        ) : (
          activePersonal.map((rel) => (
            <RelationshipChallenges key={rel.id} relationship={rel} paths={learningPaths.data} />
          ))
        )}
      </div>
    </AppShell>
  );
}

function RelationshipChallenges({
  relationship,
  paths,
}: {
  relationship: Relationship;
  paths: LearningPathProgress[] | undefined;
}) {
  const t = useTranslations();
  const locale = useLocale();
  const query = useMenteeChallenges(relationship.id);
  const error = query.error instanceof ApiError ? query.error : null;

  return (
    <section aria-labelledby={`rel-${relationship.id}`}>
      <h2 id={`rel-${relationship.id}`} className="mb-3 text-lg font-semibold text-slate-900">
        {t('challenges.with_mentor', { name: relationship.mentor.full_name })}
      </h2>
      {query.isLoading ? (
        <Card>
          <p className="flex items-center gap-2 text-sm text-slate-500">
            <Spinner size="sm" /> {t('common.loading')}
          </p>
        </Card>
      ) : error?.status === 403 ? (
        <Alert variant="warning">
          <span className="inline-flex items-center gap-2">
            <LockKeyhole className="h-4 w-4" aria-hidden="true" />
            {t('mentor_workspace.locked_description', { feature: t('mentor_workspace.feature_challenges') })}
          </span>
        </Alert>
      ) : query.isError ? (
        <Alert variant="error">
          <div className="space-y-3">
            <p>{t('challenges.load_error')}</p>
            <Button type="button" size="sm" variant="outline" onClick={() => query.refetch()}>
              {t('progress.retry')}
            </Button>
          </div>
        </Alert>
      ) : (query.data ?? []).length === 0 ? (
        <Card><p className="text-sm text-slate-500">{t('challenges.empty')}</p></Card>
      ) : (
        <ul className="space-y-3">
          {(query.data ?? []).filter((c) => c.status !== 'template').map((challenge) => (
            <ChallengeCard
              key={challenge.id}
              challenge={challenge}
              paths={paths}
              locale={locale}
            />
          ))}
        </ul>
      )}
    </section>
  );
}

function ChallengeCard({
  challenge,
  paths,
  locale,
}: {
  challenge: MentorChallenge;
  paths: LearningPathProgress[] | undefined;
  locale: string;
}) {
  const t = useTranslations();

  return (
    <li>
    <Card className="border-slate-200">
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          <h3 className="font-semibold text-slate-950">{challenge.title}</h3>
          {challenge.description ? (
            <p className="mt-1 text-sm leading-6 text-slate-600">{challenge.description}</p>
          ) : null}
        </div>
        <Badge variant={challenge.status === 'completed' ? 'success' : 'default'}>
          {t(challengeStatusKey(challenge.status))}
        </Badge>
      </div>
      {challenge.due_at ? (
        <p className="mt-2 text-xs text-slate-500">
          {t('challenges.due_at')}: {new Date(challenge.due_at).toLocaleDateString(locale)}
        </p>
      ) : null}
      <div className="mt-4 border-t border-slate-100 pt-3">
        <ChallengePathModuleSelect
          challengeId={challenge.id}
          currentModuleId={challenge.path_module_id}
          paths={paths}
        />
      </div>
    </Card>
    </li>
  );
}
