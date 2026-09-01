'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { ArrowLeft, Brain, Calendar, ClipboardCheck, Clock, UserRoundSearch, type LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardTitle } from '@/components/ui/card';
import { PageSpinner } from '@/components/ui/spinner';
import { useAuth } from '@/components/auth/AuthProvider';
import { localePath } from '@/i18n/paths';
import { apiErrorMessage } from '@/lib/errors';
import { useMentorUserPersonality, useMentorUserRecommendations } from '@/hooks/use-mentor-dashboard';
import { useMentorUserProfile } from '@/hooks/use-mentor-workspace';
import { MentorUserProfileTabs } from '@/components/mentor-workspace/MentorUserProfileTabs';

export default function MentorUserDetailPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user: mentor } = useAuth();
  const params = useParams<{ id: string }>();
  const [relationshipId, setRelationshipId] = useState<string | undefined>(undefined);

  useEffect(() => {
    setRelationshipId(new URLSearchParams(window.location.search).get('relationship_id') ?? '');
  }, []);

  const resolvedRelationshipId = relationshipId ?? '';
  const enabled = mentor?.role === 'mentor' && Boolean(resolvedRelationshipId);
  const personality = useMentorUserPersonality(params.id, resolvedRelationshipId, enabled);
  const recommendations = useMentorUserRecommendations(params.id, resolvedRelationshipId, enabled);
  const workspaceProfile = useMentorUserProfile(params.id, resolvedRelationshipId, enabled);

  if (relationshipId === undefined) return <PageSpinner />;

  if (!relationshipId) {
    return <ErrorState message={t('mentor_dashboard.relationship_required')} />;
  }

  if (personality.isLoading || recommendations.isLoading) return <PageSpinner />;
  if (personality.error || recommendations.error || !personality.data || !recommendations.data) {
    return (
      <ErrorState
        message={apiErrorMessage(personality.error ?? recommendations.error, (key) => t(key)) || t('mentor_dashboard.detail_error')}
      />
    );
  }

  const { user, assessment } = personality.data;
  const assignment = user.assignments?.find((item) => item.relationship_id === resolvedRelationshipId) ?? user.assignments?.[0];
  if (!assignment) {
    return <ErrorState message={t('mentor_dashboard.detail_error')} />;
  }
  const plan = workspaceProfile.data?.plan
    ?? workspaceProfile.data?.subscription?.plan
    ?? workspaceProfile.data?.assignment?.plan
    ?? assignment.plan;

  return (
    <div className="mx-auto max-w-7xl space-y-6">
      <Link href={localePath('/dashboard/mentor', locale)} className="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-primary-700">
        <ArrowLeft className="h-4 w-4" aria-hidden="true" /> {t('mentor_dashboard.back')}
      </Link>

      <header className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
          <div>
            <p className="text-sm font-medium text-primary-700">{assignment.context.name}</p>
            <h1 className="mt-1 text-3xl font-bold text-slate-950">{user.full_name}</h1>
            <p className="mt-2 text-sm text-slate-500">
              {assignment.plan?.name ?? t('mentor_dashboard.no_plan')} · {t(`client_types.${assignment.type}`)}
            </p>
          </div>
          <Badge variant={assessment ? 'success' : 'warning'} className="w-fit px-3 py-1.5">
            <Brain className="mr-1.5 h-4 w-4" aria-hidden="true" />
            {t(assessment ? 'mentor_dashboard.assessment_complete' : 'mentor_dashboard.assessment_pending')}
          </Badge>
        </div>
      </header>

      <MentorUserProfileTabs
        userId={params.id}
        relationshipId={resolvedRelationshipId}
        assessment={assessment}
        plan={plan}
        subscription={workspaceProfile.data?.subscription}
        entitlements={workspaceProfile.data?.entitlements ?? assignment.entitlements}
        locked={workspaceProfile.data?.assignment?.locked ?? assignment.locked}
      />

      <section className="grid gap-6 xl:grid-cols-3">
        <RecommendationSection icon={UserRoundSearch} title={t('mentor_dashboard.compatible_mentors')} empty={t('mentor_dashboard.no_compatible_mentors')}>
          {recommendations.data.recommended_mentors.map((recommendedMentor) => (
            <div key={recommendedMentor.id} className="rounded-2xl border border-slate-200 p-4">
              <div className="flex items-center justify-between gap-3">
                <span className="font-semibold text-slate-900">{recommendedMentor.full_name}</span>
                <Badge variant="primary">{Math.round(recommendedMentor.compatibility_score)}%</Badge>
              </div>
              <p className="mt-1 text-xs text-slate-500">{t('mentor_dashboard.active_assignments')}: {recommendedMentor.active_mentees_count}</p>
            </div>
          ))}
        </RecommendationSection>

        <RecommendationSection icon={Calendar} title={t('mentor_dashboard.upcoming_sessions')} empty={t('mentor_dashboard.no_upcoming_sessions')}>
          {recommendations.data.recommended_sessions.map((session) => (
            <div key={session.id} className="rounded-2xl border border-slate-200 p-4">
              <p className="font-semibold text-slate-900">{session.title}</p>
              <p className="mt-1 flex items-center gap-1.5 text-sm text-slate-500">
                <Clock className="h-4 w-4" aria-hidden="true" />
                {new Date(session.scheduled_at).toLocaleString(locale)} · {session.duration_minutes} {t('sessions.minutes')}
              </p>
            </div>
          ))}
        </RecommendationSection>

        <RecommendationSection icon={ClipboardCheck} title={t('mentor_dashboard.applicable_evaluations')} empty={t('mentor_dashboard.no_evaluations')}>
          {recommendations.data.recommended_evaluations.map((evaluation) => (
            <div key={evaluation.id} className="rounded-2xl border border-slate-200 p-4">
              <p className="font-semibold text-slate-900">{evaluation.title}</p>
              <p className="mt-1 line-clamp-2 text-sm text-slate-500">{evaluation.description}</p>
            </div>
          ))}
        </RecommendationSection>
      </section>
    </div>
  );
}

function RecommendationSection({ icon: Icon, title, empty, children }: {
  icon: LucideIcon;
  title: string;
  empty: string;
  children: React.ReactNode;
}) {
  const hasChildren = Array.isArray(children) ? children.length > 0 : Boolean(children);

  return (
    <Card>
      <div className="mb-4 flex items-center gap-2">
        <Icon className="h-5 w-5 text-primary-600" aria-hidden="true" />
        <CardTitle className="text-base">{title}</CardTitle>
      </div>
      <div className="space-y-3">{hasChildren ? children : <p className="text-sm text-slate-500">{empty}</p>}</div>
    </Card>
  );
}

function ErrorState({ message }: { message: string }) {
  const t = useTranslations();
  const locale = useLocale();

  return (
    <Card className="mx-auto max-w-xl py-12 text-center" role="alert">
      <p className="font-medium text-red-700">{message}</p>
      <Link href={localePath('/dashboard/mentor', locale)} className="mt-4 inline-flex text-sm font-semibold text-primary-700 hover:underline">
        {t('mentor_dashboard.back')}
      </Link>
    </Card>
  );
}
