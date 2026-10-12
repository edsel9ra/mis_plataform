'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { ArrowLeft, CheckCircle2, Clock, FileQuestion, RotateCcw } from 'lucide-react';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { localePath } from '@/i18n/paths';
import { AppShell } from '@/components/layout/AppShell';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { PageSpinner } from '@/components/ui/spinner';

interface EvaluationQuestion {
  id?: string | number;
  text: string;
  options: string[];
  dimension?: string;
}

interface EvaluationResult {
  id: string;
  answers: { question_id: string | number; option_index: number }[];
  score: number;
  passed: boolean;
  time_spent_seconds: number | null;
  completed_at: string;
}

interface Evaluation {
  id: string;
  title: Record<string, string>;
  description: Record<string, string> | null;
  questions: EvaluationQuestion[];
  questions_count: number;
  passing_score: number;
  time_limit_minutes: number | null;
  user_result?: EvaluationResult | null;
}

export default function EvaluationDetailPage() {
  const t = useTranslations();
  const locale = useLocale();
  const params = useParams<{ id: string }>();
  const queryClient = useQueryClient();
  const evaluationId = params.id;
  const [current, setCurrent] = useState(0);
  const [answers, setAnswers] = useState<Record<string, number>>({});
  const [startedAt, setStartedAt] = useState(() => Date.now());
  const [submittedResult, setSubmittedResult] = useState<EvaluationResult | null>(null);
  const [isRetaking, setIsRetaking] = useState(false);
  const [submitError, setSubmitError] = useState<unknown>(null);

  const { data: evaluation, isLoading, error } = useQuery<Evaluation>({
    queryKey: ['evaluation', evaluationId],
    queryFn: ({ signal }) => api.get<Evaluation>(`/evaluations/${evaluationId}`, undefined, { signal }),
    enabled: !!evaluationId,
  });

  const submitMutation = useMutation({
    mutationFn: () => api.post<EvaluationResult>(`/evaluations/${evaluationId}/submit`, {
      answers: Object.entries(answers).map(([question_id, option_index]) => ({ question_id, option_index })),
      time_spent_seconds: Math.round((Date.now() - startedAt) / 1000),
    }),
    onSuccess: (result) => {
      setSubmittedResult(result);
      setIsRetaking(false);
      setSubmitError(null);
      queryClient.invalidateQueries({ queryKey: ['evaluations'] });
      queryClient.invalidateQueries({ queryKey: ['evaluation', evaluationId] });
    },
    onError: (error) => setSubmitError(error),
  });

  useEffect(() => {
    if (!evaluation || isRetaking) return;

    setSubmittedResult(evaluation.user_result ?? null);
  }, [evaluation, isRetaking]);

  if (isLoading) {
    return (
      <AppShell>
        <PageSpinner />
      </AppShell>
    );
  }

  if (error || !evaluation) {
    return (
      <AppShell>
        <Alert variant="error">{t('common.error')}</Alert>
      </AppShell>
    );
  }

  const questions = evaluation.questions ?? [];
  const question = questions[current];
  const completed = Object.keys(answers).length;
  const progress = questions.length > 0 ? (completed / questions.length) * 100 : 0;
  const result = isRetaking ? null : submittedResult;

  function answerQuestion(optionIndex: number) {
    if (!question) return;

    const questionId = String(question.id ?? current + 1);
    setAnswers((prev) => ({ ...prev, [questionId]: optionIndex }));

    if (current < questions.length - 1) {
      setCurrent((value) => value + 1);
    }
  }

  function startRetake() {
    setAnswers({});
    setCurrent(0);
    setStartedAt(Date.now());
    setIsRetaking(true);
    setSubmitError(null);
  }

  if (result) {
    return (
      <AppShell>
        <div className="mx-auto max-w-3xl space-y-6 animate-fade-in">
          <Link href={localePath('/evaluations', locale)} className="inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700">
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            {t('evaluations.back')}
          </Link>

          <Card className="border-0 shadow-sm ring-1 ring-slate-100">
            <CardHeader>
              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <CardTitle className="text-2xl">{t('evaluations.result_title')}</CardTitle>
                  <p className="mt-1 text-sm text-slate-500">{t('evaluations.result_desc')}</p>
                </div>
                <Badge variant={result.passed ? 'success' : 'warning'}>
                  {result.passed ? t('evaluations.passed') : t('evaluations.failed')}
                </Badge>
              </div>
            </CardHeader>

            <div className="rounded-3xl bg-gradient-to-br from-primary-50 to-slate-50 p-6 text-center">
              <p className="text-sm font-medium text-slate-500">{localizedText(evaluation.title, locale)}</p>
              <p className="mt-4 text-6xl font-black tracking-tight text-slate-950">{Math.round(result.score)}%</p>
              <div className="mx-auto mt-5 h-3 max-w-md rounded-full bg-white shadow-inner" role="progressbar" aria-valuenow={Math.round(Math.min(100, result.score))} aria-valuemin={0} aria-valuemax={100}>
                <div className="h-3 rounded-full bg-primary-500" style={{ width: `${Math.min(100, result.score)}%` }} />
              </div>
              <p className="mt-3 text-sm text-slate-500">
                {t('evaluations.passing_score')}: {evaluation.passing_score}%
              </p>
            </div>

            <div className="mt-6 grid gap-3 sm:grid-cols-3">
              <ResultPill label={t('evaluations.score')} value={`${Math.round(result.score)}%`} />
              <ResultPill label={t('sessions.date')} value={new Date(result.completed_at).toLocaleDateString(locale)} />
               <ResultPill label={t('evaluations.time_limit')} value={formatSeconds(result.time_spent_seconds, t)} />
            </div>

            <div className="mt-6 flex flex-col gap-3 sm:flex-row">
              <Button type="button" onClick={startRetake} variant="outline">
                <RotateCcw className="h-4 w-4" aria-hidden="true" />
                {t('evaluations.retake')}
              </Button>
              <Link href={localePath('/evaluations', locale)} className="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-700">
                {t('evaluations.back')}
              </Link>
            </div>
          </Card>
        </div>
      </AppShell>
    );
  }

  return (
    <AppShell>
      <div className="mx-auto max-w-3xl space-y-6 animate-fade-in">
        <Link href={localePath('/evaluations', locale)} className="inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700">
          <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          {t('evaluations.back')}
        </Link>

        <Card className="border-0 shadow-sm ring-1 ring-slate-100">
          <CardHeader>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <CardTitle className="text-2xl">{localizedText(evaluation.title, locale)}</CardTitle>
                <p className="mt-2 text-sm leading-6 text-slate-500">{localizedText(evaluation.description, locale)}</p>
              </div>
              <div className="flex flex-wrap gap-2">
                <Badge variant="primary">
                  <FileQuestion className="mr-1 h-3 w-3" aria-hidden="true" />
                  {questions.length} {t('evaluations.questions')}
                </Badge>
                <Badge>
                  <Clock className="mr-1 h-3 w-3" aria-hidden="true" />
                   {evaluation.time_limit_minutes ?? 0} {t('common.minutes_short')}
                </Badge>
              </div>
            </div>
          </CardHeader>

          <div className="mb-8">
            <div className="flex items-center justify-between text-sm text-slate-600">
              <span>{t('personality.question')} {current + 1} {t('personality.of')} {questions.length}</span>
              <span>{Math.round(progress)}%</span>
            </div>
            <div className="mt-2 h-2 rounded-full bg-slate-100" role="progressbar" aria-valuenow={Math.round(progress)} aria-valuemin={0} aria-valuemax={100}>
              <div className="h-2 rounded-full bg-primary-600 transition-all" style={{ width: `${progress}%` }} />
            </div>
          </div>

          {question ? (
            <div className="rounded-2xl border border-slate-100 bg-slate-50 p-5 sm:p-6">
              <h2 className="text-xl font-semibold text-slate-950">{question.text}</h2>
              {question.dimension && <p className="mt-2 text-sm text-slate-500">{question.dimension}</p>}

              <div className="mt-6 space-y-3">
                {question.options.map((option, index) => {
                  const questionId = String(question.id ?? current + 1);
                  const selected = answers[questionId] === index;

                  return (
                    <button
                      key={`${questionId}-${index}`}
                      type="button"
                      onClick={() => answerQuestion(index)}
                      aria-pressed={selected}
                      className={`flex w-full items-center gap-3 rounded-xl border p-4 text-left transition ${
                        selected ? 'border-primary-400 bg-primary-50 text-primary-950' : 'border-slate-200 bg-white hover:border-primary-300 hover:bg-primary-50/50'
                      }`}
                    >
                      <span className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full border ${selected ? 'border-primary-500 bg-primary-600 text-white' : 'border-slate-300'}`}>
                        {selected && <CheckCircle2 className="h-4 w-4" aria-hidden="true" />}
                      </span>
                      <span className="text-sm font-medium">{option}</span>
                    </button>
                  );
                })}
              </div>

              <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Button type="button" variant="outline" disabled={current === 0} onClick={() => setCurrent((value) => Math.max(0, value - 1))}>
                  {t('common.previous')}
                </Button>
                <span className="text-sm text-slate-500">{completed} {t('evaluations.answered')}</span>
                <Button type="button" disabled={completed < questions.length} loading={submitMutation.isPending} onClick={() => submitMutation.mutate()}>
                  {t('evaluations.submit')}
                </Button>
              </div>

              {Boolean(submitMutation.isError || submitError) && (
                <Alert variant="error" className="mt-4">
                  {apiErrorMessage(submitError ?? submitMutation.error, (key) => t(key)) || t('common.error')}
                </Alert>
              )}
            </div>
          ) : (
            <Alert variant="warning">{t('common.no_results')}</Alert>
          )}
        </Card>
      </div>
    </AppShell>
  );
}

function ResultPill({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
      <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-1 font-semibold text-slate-950">{value}</p>
    </div>
  );
}

function localizedText(value: Record<string, string> | null | undefined, locale: string): string {
  if (!value) return '';

  return value[locale] ?? value.es ?? value.en ?? value.pt ?? '';
}

function formatSeconds(value: number | null, t: (key: string) => string): string {
  if (!value) return `0 ${t('common.minutes_short')}`;

  const minutes = Math.floor(value / 60);
  const seconds = value % 60;

  return `${minutes} ${t('common.minutes_short')} ${seconds} ${t('common.seconds_short')}`;
}
