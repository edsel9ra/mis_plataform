'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { ArrowRight, ClipboardCheck, Clock, FileQuestion, Users, type LucideIcon } from 'lucide-react';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { localePath } from '@/i18n/paths';
import { AppShell } from '@/components/layout/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';
import { Badge } from '@/components/ui/badge';
import { Alert } from '@/components/ui/alert';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { PageSpinner } from '@/components/ui/spinner';
import { PaginationControls } from '@/components/ui/pagination';

interface EvaluationResult {
  id: string;
  score: number;
  passed: boolean;
  completed_at: string;
}

interface MentorEvaluationResult extends EvaluationResult {
  user: {
    id: string;
    name: string;
    last_name: string | null;
    full_name?: string;
    email: string;
  };
  assessment: Evaluation;
  relationship: {
    id: string;
    type: string;
    status: string;
  };
}

interface Evaluation {
  id: string;
  slug: string;
  title: Record<string, string>;
  short_name: Record<string, string> | null;
  description: Record<string, string> | null;
  category: string | null;
  client_types: string[] | null;
  questions_count: number;
  passing_score: number;
  time_limit_minutes: number | null;
  user_result?: EvaluationResult | null;
}

export default function EvaluationsPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();
  const isMentor = user?.role === 'mentor';
  const [evaluationPage, setEvaluationPage] = useState(1);
  const [mentorResultPage, setMentorResultPage] = useState(1);
  const perPage = 12;

  const { data, isLoading, isFetching, error } = useQuery<PaginatedResponse<Evaluation>>({
    queryKey: ['evaluations', user?.client_type, { page: evaluationPage, per_page: perPage }],
    queryFn: async ({ signal }) => {
      const response = await api.get<RawPaginatedResponse<Evaluation>>('/evaluations', {
        page: evaluationPage,
        per_page: perPage,
      }, { signal });

      return normalizePaginatedResponse(response, { page: evaluationPage, perPage });
    },
    enabled: !!user,
  });

  const { data: mentorResultData, isLoading: mentorResultsLoading, isFetching: mentorResultsFetching, error: mentorResultsError } = useQuery<PaginatedResponse<MentorEvaluationResult>>({
    queryKey: ['mentor-evaluation-results', { page: mentorResultPage, per_page: perPage }],
    queryFn: async ({ signal }) => {
      const response = await api.get<RawPaginatedResponse<MentorEvaluationResult>>('/mentor/evaluation-results', {
        page: mentorResultPage,
        per_page: perPage,
      }, { signal });

      return normalizePaginatedResponse(response, { page: mentorResultPage, perPage });
    },
    enabled: isMentor,
  });

  if (isLoading || (isMentor && mentorResultsLoading)) {
    return (
      <AppShell>
        <PageSpinner />
      </AppShell>
    );
  }

  if (error || mentorResultsError) {
    return (
      <AppShell>
        <Alert variant="error">
          {apiErrorMessage(error ?? mentorResultsError, (key) => t(key)) || t('common.load_error')}
        </Alert>
      </AppShell>
    );
  }

  const evaluations = data?.data ?? [];
  const mentorResults = mentorResultData?.data ?? [];
  const completed = evaluations.filter((evaluation) => evaluation.user_result).length;

  return (
    <AppShell>
      <div className="space-y-8 animate-fade-in">
        <section className="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-primary-800 p-6 text-white shadow-xl sm:p-8">
          <Badge className="bg-white/10 text-primary-100">
            <ClipboardCheck className="mr-1 h-3 w-3" aria-hidden="true" />
            {t('evaluations.title')}
          </Badge>
          <div className="mt-5 grid gap-6 lg:grid-cols-[1fr_0.65fr] lg:items-end">
            <div>
              <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">{t('evaluations.title')}</h1>
              <p className="mt-3 max-w-2xl text-slate-300">{t('evaluations.subtitle')}</p>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                <p className="text-sm text-slate-300">{t('evaluations.available')}</p>
                <p className="mt-2 text-3xl font-bold">{data?.meta.total ?? evaluations.length}</p>
              </div>
              <div className="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                <p className="text-sm text-slate-300">{t('evaluations.completed')}</p>
                <p className="mt-2 text-3xl font-bold">{completed}</p>
              </div>
            </div>
          </div>
        </section>

        {isMentor && (
          <section className="space-y-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <h2 className="text-xl font-bold text-slate-950">{t('evaluations.mentor_results_title')}</h2>
                <p className="text-sm text-slate-500">{t('evaluations.mentor_results_subtitle')}</p>
              </div>
                <Badge variant="primary">{mentorResultData?.meta.total ?? mentorResults.length}</Badge>
            </div>

            {mentorResults.length === 0 ? (
              <Card className="border-dashed border-slate-200 text-sm text-slate-500">
                {t('evaluations.mentor_results_empty')}
              </Card>
            ) : (
              <div className={`grid gap-4 md:grid-cols-2 xl:grid-cols-3 ${mentorResultsFetching ? 'opacity-60' : ''}`} aria-busy={mentorResultsFetching}>
                {mentorResults.map((result) => (
                  <Card key={result.id} className="border-0 shadow-sm ring-1 ring-slate-100">
                    <CardHeader>
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <CardTitle className="text-base">{mentorResultUserName(result)}</CardTitle>
                          <p className="mt-1 text-sm text-slate-500">{result.user.email}</p>
                        </div>
                        <Badge variant={result.passed ? 'success' : 'warning'}>
                          {Math.round(result.score)}%
                        </Badge>
                      </div>
                    </CardHeader>
                    <div className="space-y-3 text-sm">
                      <div className="flex items-start gap-2 text-slate-600">
                        <ClipboardCheck className="mt-0.5 h-4 w-4 shrink-0 text-primary-600" aria-hidden="true" />
                        <span>{localizedText(result.assessment.title, locale)}</span>
                      </div>
                      <div className="flex items-center gap-2 text-slate-600">
                        <Users className="h-4 w-4 shrink-0 text-primary-600" aria-hidden="true" />
                        <span>{t('evaluations.relationship')}: {t(`client_types.${result.relationship.type}`)}</span>
                      </div>
                      <div className="flex items-center gap-2 text-slate-600">
                        <Clock className="h-4 w-4 shrink-0 text-primary-600" aria-hidden="true" />
                        <span>{new Date(result.completed_at).toLocaleDateString(locale)}</span>
                      </div>
                    </div>
                  </Card>
                ))}
              </div>
            )}
            {mentorResultData && (
              <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <PaginationControls
                  meta={mentorResultData.meta}
                  page={mentorResultPage}
                  onPageChange={setMentorResultPage}
                  isLoading={mentorResultsFetching}
                  ariaLabel={t('common.pagination')}
                  previousLabel={t('common.previous')}
                  nextLabel={t('common.next')}
                  pageLabel={t('common.page')}
                  ofLabel={t('common.of')}
                />
              </div>
            )}
          </section>
        )}

        {evaluations.length === 0 ? (
          <Card className="border-dashed border-slate-200 text-center">
            <FileQuestion className="mx-auto h-10 w-10 text-slate-400" aria-hidden="true" />
            <h2 className="mt-4 text-lg font-bold text-slate-950">{t('evaluations.empty_title')}</h2>
            <p className="mt-2 text-sm text-slate-500">{t('evaluations.empty_desc')}</p>
          </Card>
        ) : (
          <div className={`grid gap-5 md:grid-cols-2 xl:grid-cols-3 ${isFetching ? 'opacity-60' : ''}`} aria-busy={isFetching}>
            {evaluations.map((evaluation) => {
              const result = evaluation.user_result;

              return (
                <Card key={evaluation.id} className="group border-0 shadow-sm ring-1 ring-slate-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl">
                  <CardHeader>
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <CardTitle>{localizedText(evaluation.title, locale)}</CardTitle>
                        {evaluation.category && <p className="mt-1 text-sm text-slate-500">{evaluationCategoryLabel(evaluation.category, t)}</p>}
                      </div>
                      {result ? (
                        <Badge variant={result.passed ? 'success' : 'warning'}>
                          {result.passed ? t('evaluations.passed') : t('evaluations.failed')}
                        </Badge>
                      ) : (
                        <Badge variant="primary">{t('common.pending')}</Badge>
                      )}
                    </div>
                  </CardHeader>

                  <p className="line-clamp-3 min-h-16 text-sm leading-6 text-slate-500">
                    {localizedText(evaluation.description, locale)}
                  </p>

                  <div className="mt-5 grid grid-cols-2 gap-3 text-sm">
                    <InfoItem icon={FileQuestion} label={`${evaluation.questions_count} ${t('evaluations.questions')}`} />
                    <InfoItem icon={Clock} label={`${evaluation.time_limit_minutes ?? 0} ${t('common.minutes_short')}`} />
                  </div>

                  {result && (
                    <div className="mt-5 rounded-2xl bg-slate-50 p-4">
                      <div className="flex items-center justify-between text-sm">
                        <span className="font-medium text-slate-600">{t('evaluations.score')}</span>
                        <span className="text-lg font-bold text-slate-950">{Math.round(result.score)}%</span>
                      </div>
                      <div className="mt-2 h-2 rounded-full bg-slate-200">
                        <div className="h-2 rounded-full bg-primary-500" style={{ width: `${Math.min(100, result.score)}%` }} />
                      </div>
                    </div>
                  )}

                  <Link
                    href={localePath(`/evaluations/${evaluation.id}`, locale)}
                    className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-700 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                  >
                    {result ? t('evaluations.view_result') : t('evaluations.start')}
                    <ArrowRight className="h-4 w-4" aria-hidden="true" />
                  </Link>
                </Card>
              );
            })}
          </div>
        )}
        {data && (
          <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <PaginationControls
              meta={data.meta}
              page={evaluationPage}
              onPageChange={setEvaluationPage}
              isLoading={isFetching}
              ariaLabel={t('common.pagination')}
              previousLabel={t('common.previous')}
              nextLabel={t('common.next')}
              pageLabel={t('common.page')}
              ofLabel={t('common.of')}
            />
          </div>
        )}
      </div>
    </AppShell>
  );
}

function InfoItem({ icon: Icon, label }: { icon: LucideIcon; label: string }) {
  return (
    <div className="flex items-center gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-slate-600">
      <Icon className="h-4 w-4 text-primary-600" aria-hidden="true" />
      <span>{label}</span>
    </div>
  );
}

function localizedText(value: Record<string, string> | null | undefined, locale: string): string {
  if (!value) return '';

  return value[locale] ?? value.es ?? value.en ?? value.pt ?? '';
}

function mentorResultUserName(result: MentorEvaluationResult): string {
  return result.user.full_name || [result.user.name, result.user.last_name].filter(Boolean).join(' ') || result.user.email;
}

function evaluationCategoryLabel(category: string, t: ReturnType<typeof useTranslations>): string {
  const labels: Record<string, string> = {
    bienestar: t('evaluation_categories.wellbeing'),
    desarrollo: t('evaluation_categories.development'),
    profesional: t('evaluation_categories.professional'),
    familiar: t('evaluation_categories.family'),
    empresa: t('evaluation_categories.business'),
  };

  return labels[category] ?? t('common.not_applicable');
}
