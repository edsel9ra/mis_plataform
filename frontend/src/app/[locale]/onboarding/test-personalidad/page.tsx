'use client';

import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';
import { RequireAuth } from '@/components/auth/RequireAuth';
import { api } from '@/lib/api';

interface Question {
  id: number;
  text: string;
}

interface SelectOption {
  id: number;
  text: string;
}

export default function PersonalityTestPage() {
  const t = useTranslations();
  const router = useRouter();
  const { refreshUser } = useAuth();
  const [questions, setQuestions] = useState<Question[]>([]);
  const [select, setSelect] = useState<SelectOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [current, setCurrent] = useState(0);
  const [answers, setAnswers] = useState<Record<number, number>>({});
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    api.post('/personality/start-test').then(async (res: any) => {
      if (res?.assessment) {
        await refreshUser();
        router.replace('/dashboard');
        return;
      }
      if (!res?.questions?.length) {
        setError(true);
        return;
      }
      setQuestions(res.questions);
      setSelect(res.select ?? []);
    }).catch(() => {
      setError(true);
    }).finally(() => {
      setLoading(false);
    });
  }, []);

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
      router.push('/dashboard');
    } catch {
      alert(t('common.error'));
    } finally {
      setSubmitting(false);
    }
  };

  useEffect(() => {
    if (!loading && (error || totalQuestions === 0)) {
      router.replace('/dashboard');
    }
  }, [loading, error, totalQuestions, router]);

  if (loading) {
    return (
      <RequireAuth>
        <div className="flex h-screen items-center justify-center">{t('common.loading')}</div>
      </RequireAuth>
    );
  }

  if (error || totalQuestions === 0) {
    return null;
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
