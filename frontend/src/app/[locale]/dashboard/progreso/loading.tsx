'use client';

import { useTranslations } from 'next-intl';

export default function ProgressLoading() {
  const t = useTranslations();

  return (
    <div className="mx-auto max-w-7xl space-y-6" role="status" aria-live="polite">
      <span className="sr-only">{t('progress.loading')}</span>
      <div className="h-10 w-56 rounded-lg bg-slate-200 animate-pulse" />
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {Array.from({ length: 4 }).map((_, index) => (
          <div key={index} className="h-32 rounded-xl bg-slate-200 animate-pulse" />
        ))}
      </div>
      <div className="h-80 rounded-xl bg-slate-200 animate-pulse" />
      <div className="h-48 rounded-xl bg-slate-200 animate-pulse" />
    </div>
  );
}
