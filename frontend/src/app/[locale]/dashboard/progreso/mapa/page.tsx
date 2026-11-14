import { getTranslations } from 'next-intl/server';
import { LearningMapDashboard } from './LearningMapDashboard';

export default async function LearningMapPage() {
  const t = await getTranslations();

  return (
    <div className="mx-auto max-w-7xl space-y-6">
      <header>
        <h1 className="text-3xl font-bold text-slate-950">{t('progress.map.title')}</h1>
        <p className="mt-2 text-sm text-slate-500">{t('progress.map.subtitle')}</p>
      </header>
      <LearningMapDashboard />
    </div>
  );
}
