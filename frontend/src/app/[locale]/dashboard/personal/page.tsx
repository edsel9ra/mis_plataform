'use client';

import { useState, useEffect } from 'react';
import { useTranslations } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';
import { api } from '@/lib/api';

interface TraitData {
  trait: string;
  score: number;
  level: string;
}

type OceanKey = 'O' | 'C' | 'E' | 'A' | 'N';

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
  const { user } = useAuth();
  const [interpretation, setInterpretation] = useState<Record<OceanKey, TraitData> | null>(null);

  useEffect(() => {
    if (user?.personality_assessment?.completed_at) {
      api.get('/personality/report').then((res: any) => {
        if (res?.interpretation) {
          setInterpretation(res.interpretation);
        }
      }).catch(() => {});
    }
  }, [user?.personality_assessment?.completed_at]);

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">{t('dashboard.welcome')}, {user?.name}</h1>

      <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <div className="rounded-xl border bg-white p-6 shadow-sm">
          <h3 className="text-sm font-medium text-gray-500">{t('dashboard.next_session')}</h3>
          <p className="mt-2 text-2xl font-bold text-gray-900">—</p>
        </div>
        <div className="rounded-xl border bg-white p-6 shadow-sm">
          <h3 className="text-sm font-medium text-gray-500">{t('dashboard.active_mentors')}</h3>
          <p className="mt-2 text-2xl font-bold text-gray-900">0</p>
        </div>
        <div className="rounded-xl border bg-white p-6 shadow-sm">
          <h3 className="text-sm font-medium text-gray-500">{t('dashboard.pending_tasks')}</h3>
          <p className="mt-2 text-2xl font-bold text-gray-900">0</p>
        </div>
        <div className="rounded-xl border bg-white p-6 shadow-sm">
          <h3 className="text-sm font-medium text-gray-500">{t('dashboard.my_progress')}</h3>
          <p className="mt-2 text-2xl font-bold text-gray-900">0%</p>
        </div>
      </div>

      {interpretation && (
        <div className="mt-8">
          <h2 className="text-xl font-bold text-gray-900">{t('personality.results')}</h2>
          <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            {OCEAN_ORDER.map((key) => {
              const data = interpretation[key];
              if (!data) return null;
              const pct = scoreToPercent(data.score);
              const { bar, bg } = TRAIT_COLORS[key];
              return (
                <div key={key} className={`rounded-xl border p-5 shadow-sm ${bg}`}>
                  <div className="flex items-center justify-between">
                    <span className="text-lg font-bold text-gray-800">{key}</span>
                    <span className="text-sm font-medium text-gray-600">{data.level}</span>
                  </div>
                  <p className="mt-1 text-sm text-gray-600">{data.trait}</p>
                  <div className="mt-4">
                    <div className="h-2 rounded-full bg-white/60">
                      <div
                        className={`h-2 rounded-full ${bar} transition-all`}
                        style={{ width: `${pct}%` }}
                      />
                    </div>
                  </div>
                  <p className="mt-2 text-right text-sm font-semibold text-gray-700">
                    {Math.round(data.score)}
                  </p>
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
