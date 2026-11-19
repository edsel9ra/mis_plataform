'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import { useRouter } from 'next/navigation';
import { useQueryClient } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';
import { RequireAuth } from '@/components/auth/RequireAuth';
import { ApiError, api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { queryKeys } from '@/lib/query-keys';
import { localePath } from '@/i18n/paths';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

interface Question {
  id: number;
  text: string;
}

interface SelectOption {
  id: number;
  text: string;
}

interface StartTestResponse {
  assessment?: unknown;
  questions?: Question[];
  select?: SelectOption[];
}

export default function PersonalityTestPage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const { user, isLoading: authLoading, authError, refreshUser } = useAuth();
  const queryClient = useQueryClient();
  const userId = user?.id;
  const [questions, setQuestions] = useState<Question[]>([]);
  const [select, setSelect] = useState<SelectOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [current, setCurrent] = useState(0);
  const [answers, setAnswers] = useState<Record<number, number>>({});
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [finished, setFinished] = useState(false);
  const fieldsetRef = useRef<HTMLFieldSetElement>(null);
  const firstRender = useRef(true);
  const finishHeadingRef = useRef<HTMLHeadingElement>(null);

  useEffect(() => {
    if (authLoading || authError || !userId) return;

    let isActive = true;
    const controller = new AbortController();

    setLoading(true);
    setError(false);

    api.post<StartTestResponse>('/personality/start-test', undefined, { signal: controller.signal }).then(async (res) => {
      if (!isActive) return;

      if (res?.assessment) {
        await refreshUser();
        if (!isActive) return;

        router.replace(localePath('/dashboard', locale));
        return;
      }

      if (!res?.questions?.length) {
        setError(true);
        return;
      }

      setCurrent(0);
      setAnswers({});
      setQuestions(res.questions);
      setSelect(res.select ?? []);
    }).catch((err) => {
      if (!isActive) return;
      if (err instanceof ApiError && err.status === 401) return;

      setError(true);
    }).finally(() => {
      if (isActive) setLoading(false);
    });

    return () => {
      isActive = false;
      controller.abort();
    };
  }, [authError, authLoading, locale, refreshUser, router, userId]);

  const totalQuestions = questions.length;
  const progress = totalQuestions > 0 ? (Object.keys(answers).length / totalQuestions) * 100 : 0;

  const handleAnswer = useCallback((value: number) => {
    if (totalQuestions === 0) return;
    setAnswers((prev) => ({ ...prev, [questions[current].id]: value }));
  }, [current, totalQuestions, questions]);

  const handleSubmit = async () => {
    setSubmitting(true);
    setSubmitError(null);
    try {
      const formatted = Object.entries(answers).map(([id_question, id_select]) => ({
        id_question: Number(id_question),
        id_select,
      }));

      await api.post('/personality/submit-answers', {
        test_version: 'ipip-neo-120',
        answers: formatted,
      });

      queryClient.invalidateQueries({ queryKey: queryKeys.recommendations });
      queryClient.invalidateQueries({ queryKey: ['personality-report'] });
      await refreshUser();
      router.push(localePath('/dashboard', locale));
    } catch (error) {
      const message = apiErrorMessage(error, (key) => t(key));
      setSubmitError(message || t('common.error'));
    } finally {
      setSubmitting(false);
    }
  };

  useEffect(() => {
    if (!userId || authError) return;

    if (!loading && (error || totalQuestions === 0)) {
      router.replace(localePath('/dashboard', locale));
    }
  }, [authError, loading, error, totalQuestions, router, locale, userId]);

  useEffect(() => {
    if (firstRender.current) {
      firstRender.current = false;
      return;
    }

    const radios = fieldsetRef.current?.querySelectorAll<HTMLInputElement>('input[type=radio]');
    if (!radios?.length) return;

    const checked = [...radios].find((radio) => radio.checked);
    (checked ?? radios[0]).focus();
  }, [current]);

  useEffect(() => {
    if (finished) finishHeadingRef.current?.focus();
  }, [finished]);

  if (loading) {
    return (
      <RequireAuth>
        <main className="flex h-screen flex-col items-center justify-center gap-4">
          <h1 className="text-2xl font-bold">{t('personality.test_title')}</h1>
          <p>{t('common.loading')}</p>
        </main>
      </RequireAuth>
    );
  }

  if (error || totalQuestions === 0) {
    return <RequireAuth>{null}</RequireAuth>;
  }

  if (finished && Object.keys(answers).length === totalQuestions) {
    return (
      <RequireAuth>
        <main className="flex min-h-screen items-center justify-center px-4">
          <div className="w-full max-w-lg text-center">
            <h1 ref={finishHeadingRef} tabIndex={-1} className="text-2xl font-bold focus:outline-none">{t('personality.results')}</h1>
            <p className="mt-4 text-gray-600">{t('personality.test_complete')}</p>
            <Button
              onClick={() => void handleSubmit()}
              loading={submitting}
              className="mt-8 px-6 py-3"
            >
              {submitting ? t('common.loading') : t('personality.see_results')}
            </Button>
            {submitError && <Alert className="mt-4 text-left" variant="error">{submitError}</Alert>}
          </div>
        </main>
      </RequireAuth>
    );
  }

  const isLastQuestion = current === totalQuestions - 1;

  return (
    <RequireAuth>
      <main className="mx-auto max-w-2xl px-4 py-12">
        <h1 className="mb-6 text-2xl font-bold">{t('personality.test_title')}</h1>
        <div className="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
          {t('personality.disclaimer')}
        </div>

        <div className="mb-8">
          <div className="flex items-center justify-between text-sm text-gray-600">
            <span>{t('personality.question')} {current + 1} {t('personality.of')} {totalQuestions}</span>
            <span>{Math.round(progress)}%</span>
          </div>
          <progress className="mt-2 h-2 w-full rounded-full bg-gray-200 appearance-none" value={progress} max="100" aria-label={t('personality.progress_label')} style={{ width: `${progress}%` }}>
            <div className="h-2 rounded-full bg-primary-600 transition-all" style={{ width: `${progress}%` }} />
          </progress>
        </div>

        <div className="rounded-xl border bg-white p-8 shadow-sm">
          <div className="mt-2 space-y-3">
            <fieldset ref={fieldsetRef}>
              <legend>
                <h2 className="text-xl font-medium text-gray-900">{questions[current].text}</h2>
              </legend>
              <div className="mt-8 space-y-3">
                {select.map(({ id, text }) => (
                  <label
                    key={id}
                    className="flex items-center gap-3 w-full rounded-lg border p-4 text-left transition hover:border-primary-400 hover:bg-primary-50 cursor-pointer"
                  >
                    <input
                      type="radio"
                      name="personality-answer"
                      value={id}
                      checked={answers[questions[current].id] === id}
                      onChange={() => handleAnswer(id)}
                      className="h-4 w-4 text-primary-600 border-gray-300 focus:ring-primary-500"
                    />
                    <span>{text}</span>
                  </label>
                ))}
              </div>
            </fieldset>
          </div>

          <div className="mt-6 flex justify-between">
            <Button
              variant="outline"
              onClick={() => setCurrent((c) => Math.max(0, c - 1))}
              disabled={current === 0}
            >
              {t('personality.previous')}
            </Button>
            <span className="self-center text-sm text-gray-500">
              {Object.keys(answers).length} {t('personality.answered')}
            </span>
            <Button
              onClick={() => (isLastQuestion ? setFinished(true) : setCurrent((c) => c + 1))}
              disabled={answers[questions[current].id] === undefined}
            >
              {t('common.next')}
            </Button>
          </div>
        </div>
      </main>
    </RequireAuth>
  );
}
