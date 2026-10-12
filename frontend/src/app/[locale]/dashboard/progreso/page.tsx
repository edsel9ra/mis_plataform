import { getTranslations } from 'next-intl/server';
import { ProgressDashboard } from './ProgressDashboard';

export default async function ProgressPage() {
  const t = await getTranslations();

  return (
    <div className="mx-auto max-w-7xl space-y-6">
      <header>
        <h1 className="text-3xl font-bold text-slate-950">{t('progress.title')}</h1>
        <p className="mt-2 text-sm text-slate-500">{t('progress.subtitle')}</p>
      </header>
      <ProgressDashboard />
    </div>
  );
}
