'use client';

import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '@/components/auth/AuthProvider';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { queryKeys } from '@/lib/query-keys';
import { localePath } from '@/i18n/paths';
import { useRelationships } from '@/hooks/use-relationships';
import { useSessions } from '@/hooks/use-sessions';
import { OceanRadarChart } from '@/components/charts/ocean-radar-chart';
import { PersonalPlansSection } from '@/components/mentor-workspace/PersonalPlansSection';
import { Badge } from '@/components/ui/badge';
import { Alert } from '@/components/ui/alert';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import {
  AlertTriangle,
  ArrowRight,
  BookOpen,
  Brain,
  Calendar,
  CheckCircle2,
  ClipboardCheck,
  Sparkles,
  Target,
  Users,
} from 'lucide-react';

interface TraitData {
  trait: string;
  score: number;
  level: string;
  needs_improvement: boolean;
}

type OceanKey = 'O' | 'C' | 'E' | 'A' | 'N';

interface SessionSummary {
  id: string;
  title: string;
  status: string;
  scheduled_at: string;
  duration_minutes: number;
}

interface RelationshipSummary {
  id: string;
  status: string;
}

interface LearningPath {
  id: string;
  title: string | Record<string, string>;
  description?: string | Record<string, string> | null;
  estimated_hours?: number | null;
  level?: string | null;
}

interface PersonalityReport {
  factors?: Partial<Record<OceanKey, number>>;
  interpretation?: Partial<Record<OceanKey, TraitData>>;
}

interface RecommendationsSummary {
  recommended_mentors?: unknown[];
  recommended_sessions?: unknown[];
  recommended_evaluations?: unknown[];
}

const OCEAN_ORDER: OceanKey[] = ['O', 'C', 'E', 'A', 'N'];

const TRAIT_COLORS: Record<OceanKey, { bar: string; bg: string }> = {
  O: { bar: 'bg-violet-500', bg: 'bg-violet-50' },
  C: { bar: 'bg-blue-500', bg: 'bg-blue-50' },
  E: { bar: 'bg-emerald-500', bg: 'bg-emerald-50' },
  A: { bar: 'bg-amber-500', bg: 'bg-amber-50' },
  N: { bar: 'bg-rose-500', bg: 'bg-rose-50' },
};

function scoreToPercent(score: number): number {
  return Math.max(0, Math.min(100, score));
}

export default function PersonalDashboard() {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();
  const hasCompletedPersonality = Boolean(user?.personality_assessment?.completed_at);
  const { data: sessionsData, isLoading: sessionsLoading, error: sessionsError } = useSessions();
  const { data: relationshipsData, error: relationshipsError } = useRelationships();
  const { data: learningPathsData, error: learningPathsError } = useQuery<LearningPath[] | { data: LearningPath[] }>({
    queryKey: ['learning-paths', user?.client_type],
    queryFn: ({ signal }) => api.get<LearningPath[] | { data: LearningPath[] }>('/learning-paths', { client_type: user?.client_type || undefined }, { signal }),
    enabled: !!user?.client_type,
  });
  const { data: personalityReport, isLoading: personalityLoading, error: personalityError } = useQuery<PersonalityReport>({
    queryKey: ['personality-report'],
    queryFn: ({ signal }) => api.get<PersonalityReport>('/personality/report', undefined, { signal }),
    enabled: hasCompletedPersonality,
  });
  const { data: recommendationsData, error: recommendationsError } = useQuery<RecommendationsSummary>({
    queryKey: queryKeys.recommendations,
    queryFn: ({ signal }) => api.get<RecommendationsSummary>('/personality/recommendations', undefined, { signal }),
    enabled: hasCompletedPersonality,
  });

  const sessions = sessionsData?.data ?? [];
  const relationships = relationshipsData?.data ?? [];
  const dashboardError = sessionsError ?? relationshipsError ?? learningPathsError ?? personalityError ?? recommendationsError;
  const learningPaths = Array.isArray(learningPathsData) ? learningPathsData : learningPathsData?.data ?? [];
  const activeRelationships = relationships.filter((relationship: RelationshipSummary) => relationship.status === 'active');
  const nextSession = sessions.find((session: SessionSummary) => ['scheduled', 'in_progress'].includes(session.status));
  const interpretation = personalityReport?.interpretation ?? {};
  const oceanChartData = OCEAN_ORDER.map((key) => {
    const interpreted = interpretation[key];
    const score = interpreted?.score ?? personalityReport?.factors?.[key];

    if (typeof score !== 'number') return null;

    return {
      key,
      trait: interpreted?.trait ?? oceanTraitName(key, t),
      score: scoreToPercent(score),
      level: interpreted?.level ?? oceanLevel(score, t),
      needs_improvement: interpreted?.needs_improvement ?? false,
    };
  }).filter((item): item is { key: OceanKey; trait: string; score: number; level: string; needs_improvement: boolean } => item !== null);
  const improvementAreas = oceanChartData.filter((item) => item.needs_improvement);
  const pendingTasks = (hasCompletedPersonality ? 0 : 1) + (activeRelationships.length > 0 ? 0 : 1);
  const profileReadiness = Math.min(
    100,
    (hasCompletedPersonality ? 45 : 15) +
      (activeRelationships.length > 0 ? 30 : 0) +
      (learningPaths.length > 0 ? 25 : 0),
  );

  const stats = [
    {
      label: t('dashboard.next_session'),
      value: sessionsLoading
        ? t('common.loading')
        : nextSession
        ? new Date(nextSession.scheduled_at).toLocaleDateString(locale, { month: 'short', day: 'numeric' })
        : t('dashboard.no_session_scheduled'),
      icon: Calendar,
      color: 'bg-blue-50 text-blue-700',
    },
    {
      label: t('dashboard.active_mentors'),
      value: activeRelationships.length,
      icon: Users,
      color: 'bg-emerald-50 text-emerald-700',
    },
    {
      label: t('dashboard.pending_tasks'),
      value: pendingTasks,
      icon: Target,
      color: 'bg-amber-50 text-amber-700',
    },
    {
      label: t('dashboard.profile_readiness'),
      value: `${profileReadiness}%`,
      icon: CheckCircle2,
      color: 'bg-violet-50 text-violet-700',
    },
  ];

  return (
    <div className="space-y-8 animate-fade-in">
      {dashboardError && (
        <Alert variant="error" role="alert">
          {apiErrorMessage(dashboardError, (key) => t(key)) || t('common.load_error')}
        </Alert>
      )}
      <section className="relative overflow-hidden rounded-3xl bg-[radial-gradient(circle_at_top_left,#1d4ed8_0,#111827_42%,#020617_100%)] p-6 text-white shadow-xl shadow-gray-200 animate-slide-up sm:p-8">
        <div className="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-primary-500/30 blur-3xl" aria-hidden="true" />
        <div className="absolute bottom-0 right-0 h-32 w-2/3 rounded-full bg-violet-500/10 blur-3xl" aria-hidden="true" />
        <div className="relative z-10 grid gap-8 lg:grid-cols-[1.2fr_0.8fr] lg:items-end">
          <div>
            <Badge className="bg-white/10 text-primary-100">
              <Sparkles className="mr-1 h-3 w-3" aria-hidden="true" />
              {t('dashboard.ai_badge')}
            </Badge>
            <h1 className="mt-5 text-3xl font-bold tracking-tight sm:text-4xl">
              {t('dashboard.welcome')}, {user?.name}
            </h1>
            <p className="mt-3 max-w-2xl text-gray-300">
              {t('dashboard.integral_subtitle')}
            </p>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/10 p-5 shadow-2xl shadow-slate-950/20 backdrop-blur transition-transform duration-300 hover:-translate-y-1">
            <p className="text-sm text-gray-300">{t('dashboard.profile_readiness')}</p>
            <p className="mt-2 text-4xl font-bold">{profileReadiness}%</p>
            <div className="mt-4 h-2 rounded-full bg-white/10">
              <div className="h-2 rounded-full bg-primary-400 transition-[width] duration-700 ease-out" style={{ width: `${profileReadiness}%` }} />
            </div>
          </div>
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {stats.map(({ label, value, icon: Icon, color }) => (
          <Card key={label} className="group border-0 bg-white/95 shadow-sm ring-1 ring-gray-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-gray-200/70">
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-sm font-medium text-gray-500">{label}</p>
                <p className="mt-2 text-2xl font-bold text-gray-950">{value}</p>
              </div>
              <span className={`rounded-2xl p-3 transition-transform duration-300 group-hover:scale-110 ${color}`}>
                <Icon className="h-5 w-5" aria-hidden="true" />
              </span>
            </div>
          </Card>
        ))}
      </section>

      <PersonalPlansSection />

      <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
        <div className="space-y-6">
          {personalityLoading ? (
            <Card className="border-0 shadow-sm">
              <p className="text-sm text-gray-500">{t('common.loading')}</p>
            </Card>
          ) : oceanChartData.length > 0 ? (
            <Card className="border-0 bg-white/95 shadow-sm ring-1 ring-slate-100 transition-all duration-300 hover:shadow-xl">
              <CardHeader>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex items-center gap-3">
                    <Brain className="h-6 w-6 text-primary-600" aria-hidden="true" />
                    <CardTitle>{t('dashboard.ocean_profile')}</CardTitle>
                  </div>
                  <div className="flex gap-1 rounded-full bg-slate-100 p-1" aria-hidden="true">
                    {OCEAN_ORDER.map((key) => (
                      <span key={key} className="flex h-7 w-7 items-center justify-center rounded-full bg-white text-xs font-bold text-slate-600 shadow-sm">
                        {key}
                      </span>
                    ))}
                  </div>
                </div>
              </CardHeader>
              <div className="grid gap-6 xl:grid-cols-[1.15fr_0.85fr] xl:items-center">
                <div className="h-96 min-h-[24rem] rounded-[1.75rem] border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-3 shadow-inner sm:h-[28rem] sm:p-5 xl:h-[34rem]">
                  <OceanRadarChart data={oceanChartData} />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                  {OCEAN_ORDER.map((key) => {
                    const data = oceanChartData.find((item) => item.key === key);
                    if (!data) return null;
                    const { bar, bg } = TRAIT_COLORS[key];
                    return (
                      <div key={key} className={`rounded-2xl border p-5 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md ${bg}`}>
                        <div className="flex items-center justify-between">
                          <span className="text-lg font-bold text-gray-800">{key}</span>
                          <span className="text-sm font-medium text-gray-600">{data.level}</span>
                        </div>
                        <p className="mt-1 min-h-10 text-sm text-gray-600">{data.trait}</p>
                        <div className="mt-4 h-2 rounded-full bg-white/70">
                          <div className={`h-2 rounded-full ${bar} transition-[width] duration-700 ease-out`} style={{ width: `${data.score}%` }} />
                        </div>
                        <p className="mt-2 text-right text-sm font-semibold text-gray-700">
                          {Math.round(data.score)}
                        </p>
                      </div>
                    );
                  })}
                </div>
              </div>
            </Card>
          ) : (
            <Card className="border-amber-200 bg-amber-50 shadow-sm">
              <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-start gap-4">
                  <AlertTriangle className="mt-1 h-6 w-6 shrink-0 text-amber-600" aria-hidden="true" />
                  <div>
                    <h2 className="text-lg font-bold text-amber-950">{t('dashboard.complete_test_title')}</h2>
                    <p className="mt-1 text-sm text-amber-800">{t('dashboard.complete_test_desc')}</p>
                  </div>
                </div>
                <Link
                  href={localePath('/onboarding/test-personalidad', locale)}
                  className="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                >
                  {t('personality.banner_link')}
                </Link>
              </div>
            </Card>
          )}

          {improvementAreas.length > 0 && (
            <Card className="border-amber-200 bg-amber-50 shadow-sm">
              <div className="flex items-start gap-4">
                <AlertTriangle className="mt-1 h-6 w-6 shrink-0 text-amber-600" aria-hidden="true" />
                <div className="min-w-0">
                  <CardTitle className="text-amber-950">{t('personality.improvement_title')}</CardTitle>
                  <p className="mt-1 text-sm text-amber-800">{t('personality.improvement_description')}</p>
                  <ul className="mt-4 grid gap-3 sm:grid-cols-2">
                    {improvementAreas.map((area) => (
                      <li key={area.key} className="rounded-xl border border-amber-200 bg-white/70 p-3">
                        <div className="flex items-center justify-between gap-3">
                          <span className="font-bold text-amber-950">{area.key}</span>
                          <span className="text-sm font-semibold text-amber-900">
                            {t('personality.improvement_score', { score: formatScore(area.score, locale) })}
                          </span>
                        </div>
                        <p className="mt-1 text-sm text-amber-900">{area.trait}</p>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            </Card>
          )}

          <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            {[
              { href: '/recommendations', label: t('nav.recommendations'), desc: t('dashboard.view_recommendations'), icon: Sparkles },
              { href: '/evaluations', label: t('nav.evaluations'), desc: t('dashboard.complete_evaluations'), icon: ClipboardCheck },
              { href: '/mentors', label: t('nav.mentors'), desc: t('dashboard.explore_mentors'), icon: Users },
              { href: '/sessions', label: t('nav.sessions'), desc: t('dashboard.plan_session'), icon: Calendar },
              { href: '/learning-paths', label: t('nav.learning'), desc: t('dashboard.continue_learning'), icon: BookOpen },
            ].map(({ href, label, desc, icon: Icon }) => (
              <Link key={href} href={localePath(href, locale)} className="group rounded-2xl border bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-xl focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
                <Icon className="h-6 w-6 text-primary-600" aria-hidden="true" />
                <h3 className="mt-4 font-semibold text-gray-950">{label}</h3>
                <p className="mt-2 text-sm text-gray-500">{desc}</p>
                <ArrowRight className="mt-4 h-4 w-4 text-primary-600 transition-transform duration-300 group-hover:translate-x-1" aria-hidden="true" />
              </Link>
            ))}
          </section>
        </div>

        <aside className="space-y-6">
          <Card className="border-0 shadow-sm">
            <CardHeader>
              <CardTitle>{t('dashboard.suggested_next_steps')}</CardTitle>
            </CardHeader>
            <div className="space-y-3">
              {[
                { done: hasCompletedPersonality, text: t('dashboard.complete_test_title') },
                { done: activeRelationships.length > 0, text: t('dashboard.explore_mentors') },
                { done: learningPaths.length > 0, text: t('dashboard.continue_learning') },
              ].map((item) => (
                <div key={item.text} className="flex items-start gap-3 rounded-xl border p-3 transition-colors duration-200 hover:border-primary-100 hover:bg-primary-50/40">
                  <CheckCircle2 className={`mt-0.5 h-5 w-5 shrink-0 ${item.done ? 'text-emerald-600' : 'text-gray-300'}`} aria-hidden="true" />
                  <span className="text-sm text-gray-700">{item.text}</span>
                </div>
              ))}
            </div>
          </Card>

          <Card className="border-0 shadow-sm">
            <CardHeader>
              <CardTitle>{t('dashboard.recommendations_ready')}</CardTitle>
            </CardHeader>
            {hasCompletedPersonality ? (
              <div className="space-y-3">
                {[
                  { label: t('dashboard.recommended_mentors'), value: recommendationsData?.recommended_mentors?.length ?? 0 },
                  { label: t('dashboard.recommended_sessions'), value: recommendationsData?.recommended_sessions?.length ?? 0 },
                  { label: t('dashboard.recommended_evaluations'), value: recommendationsData?.recommended_evaluations?.length ?? 0 },
                ].map((item) => (
                  <div key={item.label} className="flex items-center justify-between rounded-xl border p-3 transition-colors duration-200 hover:border-primary-100 hover:bg-primary-50/40">
                    <span className="text-sm text-gray-600">{item.label}</span>
                    <span className="text-lg font-bold text-gray-950">{item.value}</span>
                  </div>
                ))}
                <Link href={localePath('/recommendations', locale)} className="inline-flex text-sm font-semibold text-primary-600 hover:text-primary-700">
                  {t('dashboard.view_recommendations')}
                </Link>
              </div>
            ) : (
              <p className="text-sm text-gray-500">{t('dashboard.complete_test_desc')}</p>
            )}
          </Card>

          <Card className="border-0 shadow-sm">
            <CardHeader>
              <CardTitle>{t('dashboard.learning_paths_active')}</CardTitle>
            </CardHeader>
            <div className="space-y-3">
              {learningPaths.slice(0, 3).map((path) => (
                  <div key={path.id} className="rounded-xl border p-3 transition-colors duration-200 hover:border-primary-100 hover:bg-primary-50/40">
                    <p className="font-medium text-gray-950">{localizedText(path.title, locale)}</p>
                  {path.description && (
                    <p className="mt-1 line-clamp-2 text-sm text-gray-500">{localizedText(path.description, locale)}</p>
                  )}
                </div>
              ))}
              {learningPaths.length === 0 && (
                <p className="text-sm text-gray-500">{t('common.no_results')}</p>
              )}
            </div>
          </Card>
        </aside>
      </div>
    </div>
  );
}

function localizedText(value: string | Record<string, string>, locale: string): string {
  if (typeof value === 'string') return value;

  return value[locale] ?? value.es ?? value.en ?? value.pt ?? '';
}

function oceanTraitName(key: OceanKey, t: ReturnType<typeof useTranslations>): string {
  const names: Record<OceanKey, string> = {
    O: t('personality.openness'),
    C: t('personality.conscientiousness'),
    E: t('personality.extraversion'),
    A: t('personality.agreeableness'),
    N: t('personality.neuroticism'),
  };

  return names[key];
}

function oceanLevel(score: number, t: ReturnType<typeof useTranslations>): string {
  if (score >= 70) return t('personality.high');
  if (score >= 40) return t('personality.medium');

  return t('personality.low');
}

function formatScore(score: number, locale: string): string {
  return new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(score);
}
