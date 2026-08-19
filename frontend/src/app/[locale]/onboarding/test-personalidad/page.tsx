'use client';

import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { useLocale, useTranslations } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';
import { RequireAuth } from '@/components/auth/RequireAuth';
import { ApiError, api } from '@/lib/api';
import { localePath } from '@/i18n/paths';

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
  const userId = user?.id;
  const [questions, setQuestions] = useState<Question[]>([]);
  const [select, setSelect] = useState<SelectOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [current, setCurrent] = useState(0);
  const [answers, setAnswers] = useState<Record<number, number>>({});
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (authLoading || authError || !userId) return;

    let isActive = true;

    setLoading(true);
    setError(false);

    api.post<StartTestResponse>('/personality/start-test').then(async (res) => {
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
    };
  }, [authError, authLoading, locale, refreshUser, router, userId]);

  const totalQuestions = questions.length;
  const progress = totalQuestions > 0 ? (Object.keys(answers).length / totalQuestions) * 100 : 0;

  const handleAnswer = useCallback((value: number) => {
    if (totalQuestions === 0) return;
    setAnswers((prev) => ({ ...prev, [questions[current].id]: value }));
    if (current < totalQuestions - 1) {
      setCurrent((c) => c + 1);
    }
  }, [current, totalQuestions, questions]);

  const handleSubmit = async () => {
    setSubmitting(true);
    try {
      const formatted = Object.entries(answers).map(([id_question, id_select]) => ({
        id_question: Number(id_question),
        id_select,
      }));

      await api.post('/personality/submit-answers', {
        test_version: 'ipip-neo-120',
        answers: formatted,
      });

      await refreshUser();
      router.push(localePath('/dashboard', locale));
    } catch {
      alert(t('common.error'));
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

  if (loading) {
    return (
      <RequireAuth>
        <div className="flex h-screen items-center justify-center">{t('common.loading')}</div>
      </RequireAuth>
    );
  }

  if (error || totalQuestions === 0) {
    return <RequireAuth>{null}</RequireAuth>;
  }

  if (Object.keys(answers).length === totalQuestions) {
    return (
      <RequireAuth>
        <div className="flex min-h-screen items-center justify-center px-4">
          <div className="w-full max-w-lg text-center">
            <h2 className="text-2xl font-bold">{t('personality.results')}</h2>
            <p className="mt-4 text-gray-600">{t('personality.test_complete')}</p>
            <button
              onClick={handleSubmit}
              disabled={submitting}
              className="mt-8 rounded-lg bg-primary-600 px-6 py-3 text-white hover:bg-primary-700"
            >
              {submitting ? t('common.loading') : t('personality.see_results')}
            </button>
          </div>
        </div>
      </RequireAuth>
    );
  }

  return (
    <RequireAuth>
      <div className="mx-auto max-w-2xl px-4 py-12">
        <div className="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
          {t('personality.disclaimer')}
        </div>

        <div className="mb-8">
          <div className="flex items-center justify-between text-sm text-gray-600">
            <span>{t('personality.question')} {current + 1} {t('personality.of')} {totalQuestions}</span>
            <span>{Math.round(progress)}%</span>
          </div>
          <div className="mt-2 h-2 rounded-full bg-gray-200">
            <div className="h-2 rounded-full bg-primary-600 transition-all" style={{ width: `${progress}%` }} />
          </div>
        </div>

        <div className="rounded-xl border bg-white p-8 shadow-sm">
          <h2 className="text-xl font-medium text-gray-900">{questions[current].text}</h2>

          <div className="mt-8 space-y-3">
            {select.map(({ id, text }) => (
              <button
                key={id}
                onClick={() => handleAnswer(id)}
                className="w-full rounded-lg border p-4 text-left transition hover:border-primary-400 hover:bg-primary-50"
              >
                {text}
              </button>
            ))}
          </div>

          <div className="mt-6 flex justify-between">
            <button
              onClick={() => setCurrent((c) => Math.max(0, c - 1))}
              disabled={current === 0}
              className="rounded-lg border px-4 py-2 text-sm disabled:opacity-50"
            >
              {t('personality.previous')}
            </button>
            <span className="self-center text-sm text-gray-500">
              {Object.keys(answers).length} {t('personality.answered')}
            </span>
          </div>
        </div>
      </div>
    </RequireAuth>
  );
}
