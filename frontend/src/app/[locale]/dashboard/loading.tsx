'use client';

import { useTranslations } from 'next-intl';
import { ThinkingOrb } from '@/components/ui/thinking-orb';

export default function DashboardLoading() {
  const t = useTranslations();

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex h-48 items-center justify-center rounded-3xl bg-slate-950 text-white shadow-xl shadow-slate-200">
        <div className="flex flex-col items-center gap-3">
          <ThinkingOrb size="xl" tone="inverse" label={t('dashboard.loading')} />
          <p className="text-sm font-medium text-slate-300">{t('dashboard.loading')}</p>
        </div>
      </div>
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {Array.from({ length: 4 }).map((_, index) => (
          <div key={index} className="h-32 rounded-xl bg-slate-200 animate-pulse" />
        ))}
      </div>
      <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
        <div className="h-[34rem] rounded-xl bg-slate-200 animate-pulse" />
        <div className="space-y-4">
          <div className="h-40 rounded-xl bg-slate-200 animate-pulse" />
          <div className="h-48 rounded-xl bg-slate-200 animate-pulse" />
        </div>
      </div>
    </div>
  );
}
