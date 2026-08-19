'use client';

import { useQuery } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import Link from 'next/link';
import { api } from '@/lib/api';
import { localePath } from '@/i18n/paths';
import { AppShell } from '@/components/layout/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { PageSpinner } from '@/components/ui/spinner';
import {
  Users,
  Calendar,
  ClipboardCheck,
  AlertTriangle,
  BarChart3,
  Star,
  Brain,
} from 'lucide-react';

interface Recommendations {
  message?: string;
  recommended_mentors: MentorRecommendation[];
  recommended_sessions: SessionRecommendation[];
  recommended_evaluations: EvaluationRecommendation[];
  user_ocean: Record<string, number> | null;
}

interface MentorRecommendation {
  id: string;
  name?: string;
  last_name?: string | null;
  full_name?: string;
  email?: string;
  client_type?: string | null;
  compatibility_score?: number;
  active_mentees_count?: number;
  personality_assessment?: {
    results?: { factors?: Record<string, number> };
  } | null;
}

interface SessionRecommendation {
  id: string;
  title: string;
  status: string;
  scheduled_at: string;
  relationship?: {
    mentor?: { name?: string; last_name?: string | null; full_name?: string } | null;
  } | null;
}

interface EvaluationRecommendation {
  id: string;
  title: string;
  description: string;
  reason: string;
  type: string;
}

const OCEAN_COLORS: Record<string, string> = {
  O: 'bg-violet-500',
  C: 'bg-blue-500',
  E: 'bg-emerald-500',
  A: 'bg-amber-500',
  N: 'bg-rose-500',
};

export default function RecommendationsPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();

  const { data, isLoading, error } = useQuery<Recommendations>({
    queryKey: ['personality-recommendations'],
    queryFn: ({ signal }) => api.get<Recommendations>('/personality/recommendations', undefined, { signal }),
    enabled: !!user,
    retry: 1,
  });

  if (isLoading) {
    return (
      <AppShell>
        <PageSpinner />
      </AppShell>
    );
  }

  if (error || !data || !data.user_ocean) {
    return (
      <AppShell>
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <AlertTriangle className="h-12 w-12 text-amber-500" aria-hidden="true" />
          <h2 className="mt-4 text-xl font-bold text-gray-900">{t('recommendations.complete_test_title')}</h2>
          <p className="mt-2 text-gray-500">{t('recommendations.complete_test_desc')}</p>
          <Link
            href={localePath('/onboarding/test-personalidad', locale)}
            className="mt-6 inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
          >
            {t('personality.banner_link')}
          </Link>
        </div>
      </AppShell>
    );
  }

  return (
    <AppShell>
      <div>
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">{t('recommendations.title')}</h1>
        <p className="mt-1 text-gray-500">
          {t('recommendations.subtitle')}
        </p>
      </div>

      {data.user_ocean && (
        <Card className="mb-8">
          <CardHeader>
            <div className="flex items-center gap-3">
              <Brain className="h-6 w-6 text-primary-600" aria-hidden="true" />
              <CardTitle>{t('dashboard.ocean_profile')}</CardTitle>
            </div>
          </CardHeader>
          <div className="grid gap-4 sm:grid-cols-5">
            {Object.entries(data.user_ocean).map(([key, val]) => (
              <div key={key} className="text-center">
                <span className="text-lg font-bold text-gray-800">{key}</span>
                <p className="text-xs text-gray-500">{oceanName(key, t)}</p>
                <div className="mt-2 h-2 rounded-full bg-gray-100">
                  <div
                    className={`h-2 rounded-full ${OCEAN_COLORS[key] || 'bg-gray-400'}`}
                    style={{ width: `${Math.min(100, val)}%` }}
                  />
                </div>
                <span className="mt-1 text-sm font-semibold text-gray-700">{Math.round(val)}</span>
              </div>
            ))}
          </div>
        </Card>
      )}

      <div className="space-y-8">
        <section>
          <div className="mb-4 flex items-center gap-2">
            <Users className="h-5 w-5 text-primary-600" aria-hidden="true" />
            <h2 className="text-xl font-bold text-gray-900">{t('recommendations.mentors_title')}</h2>
          </div>

          {!hasData(data.recommended_mentors) ? (
            <p className="text-sm text-gray-400">
              {t('recommendations.no_mentors')}
            </p>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {data.recommended_mentors.map((mentor) => (
                <Card key={mentor.id}>
                  <CardHeader>
                    <div className="flex items-start justify-between">
                      <div>
                        <CardTitle className="text-base">
                          {mentorName(mentor)}
                        </CardTitle>
                        {mentor.email && <p className="text-sm text-gray-500">{mentor.email}</p>}
                        {mentor.client_type && (
                          <Badge variant="primary" className="mt-2">
                            {t(`client_types.${mentor.client_type}`)}
                          </Badge>
                        )}
                      </div>
                      {mentor.compatibility_score && (
                        <div className="flex items-center gap-1 rounded-full bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700">
                          <Star className="h-3 w-3" aria-hidden="true" />
                          {mentor.compatibility_score}%
                        </div>
                      )}
                    </div>
                  </CardHeader>
                  <div className="space-y-2">
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-500">{t('mentors.active_mentees')}</span>
                      <span className="font-medium">{mentor.active_mentees_count ?? 0}</span>
                    </div>
                    {mentor.personality_assessment?.results?.factors && (
                      <div className="flex flex-wrap gap-1">
                        {Object.entries(mentor.personality_assessment.results.factors).map(([k, v]) => (
                          <span key={k} className="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">
                            {k}: {Math.round(v)}
                          </span>
                        ))}
                      </div>
                    )}
                    <Link
                      href={localePath(`/mentors/${mentor.id}`, locale)}
                      className="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                    >
                      {t('mentors.view_profile')}
                    </Link>
                  </div>
                </Card>
              ))}
            </div>
          )}
        </section>

        <section>
          <div className="mb-4 flex items-center gap-2">
            <Calendar className="h-5 w-5 text-primary-600" aria-hidden="true" />
            <h2 className="text-xl font-bold text-gray-900">{t('recommendations.sessions_title')}</h2>
          </div>

          {!hasData(data.recommended_sessions) ? (
            <p className="text-sm text-gray-400">
              {t('recommendations.no_sessions')}
            </p>
          ) : (
            <div className="space-y-3">
              {data.recommended_sessions.map((session) => (
                <Card key={session.id}>
                  <div className="flex items-center justify-between">
                    <div>
                      <CardTitle className="text-base">{session.title}</CardTitle>
                      <p className="text-sm text-gray-500">
                        {sessionMentorName(session)} - {new Date(session.scheduled_at).toLocaleDateString(locale)}
                      </p>
                    </div>
                    <Badge variant={
                      session.status === 'scheduled' ? 'primary' :
                      session.status === 'in_progress' ? 'warning' : 'default'
                    }>
                      {session.status}
                    </Badge>
                  </div>
                </Card>
              ))}
            </div>
          )}

          {!hasData(data.recommended_sessions) && hasData(data.recommended_mentors) && (
            <Link
              href={localePath('/mentors', locale)}
              className="mt-3 inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
            >
              {t('dashboard.explore_mentors')}
            </Link>
          )}
        </section>

        <section>
          <div className="mb-4 flex items-center gap-2">
            <ClipboardCheck className="h-5 w-5 text-primary-600" aria-hidden="true" />
            <h2 className="text-xl font-bold text-gray-900">{t('recommendations.evaluations_title')}</h2>
          </div>

          {!hasData(data.recommended_evaluations) ? (
            <p className="text-sm text-gray-400">
              {t('recommendations.no_evaluations')}
            </p>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2">
              {data.recommended_evaluations.map((evalItem) => (
                  <Card key={evalItem.id}>
                    <CardHeader>
                      <div className="flex items-start gap-3">
                        <BarChart3 className="mt-1 h-5 w-5 shrink-0 text-primary-600" aria-hidden="true" />
                        <div>
                        <CardTitle className="text-base">{evalItem.title}</CardTitle>
                        <p className="mt-1 text-sm text-gray-500">{evalItem.description}</p>
                        <p className="mt-1 text-xs text-primary-600 italic">{evalItem.reason}</p>
                        </div>
                      </div>
                    </CardHeader>
                    <Link
                      href={localePath(`/evaluations/${evalItem.id}`, locale)}
                      className="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                    >
                      {t('evaluations.start')}
                    </Link>
                  </Card>
                ))}
            </div>
          )}
        </section>
      </div>
      </div>
    </AppShell>
  );
}

function hasData<T>(arr: T[] | undefined): arr is T[] {
  return Boolean(arr && arr.length > 0);
}

function mentorName(mentor: MentorRecommendation): string {
  return mentor.full_name || [mentor.name, mentor.last_name].filter(Boolean).join(' ') || mentor.email || '';
}

function sessionMentorName(session: SessionRecommendation): string {
  const mentor = session.relationship?.mentor;
  if (!mentor) return '';

  return mentor.full_name || [mentor.name, mentor.last_name].filter(Boolean).join(' ');
}

function oceanName(key: string, t: ReturnType<typeof useTranslations>): string {
  const names: Record<string, string> = {
    O: t('personality.openness'),
    C: t('personality.conscientiousness'),
    E: t('personality.extraversion'),
    A: t('personality.agreeableness'),
    N: t('personality.neuroticism'),
  };

  return names[key] ?? key;
}
