'use client';

import { useTranslations } from 'next-intl';
import { Lock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { CompetenciesBlock } from '@/types/progress';
import { ProgressCard } from './ProgressCard';

/**
 * Competencies with Skills and Personality (OCEAN) kept as separate sources
 * (P9/P15). OCEAN can be `blocked` (no entitlement) or `no_data` (not
 * completed), never surfaced as zeros (RF-7, CA-7.4).
 */
export function CompetenciesCard({ block }: { block: CompetenciesBlock }) {
  const t = useTranslations();
  const ocean = block.ocean;

  return (
    <ProgressCard title={t('progress.competencies.title')}>
      <div className="grid gap-5 sm:grid-cols-2">
        <section aria-label={t('progress.competencies.skills_title')}>
          <h4 className="text-sm font-semibold text-slate-900">{t('progress.competencies.skills_title')}</h4>
          <p className="mt-0.5 text-xs text-slate-400">{t('progress.competencies.source_skills')}</p>
          {block.skills.length > 0 ? (
            <ul className="mt-2 flex flex-wrap gap-2">
              {block.skills.map((skill) => (
                <li key={skill.id ?? skill.tag}>
                  <Badge variant="primary">
                    {skill.tag ?? '—'}
                    {skill.level != null ? ` · ${skill.level}` : ''}
                  </Badge>
                </li>
              ))}
            </ul>
          ) : (
            <p className="mt-2 text-sm text-slate-500">{t('progress.competencies.skills_empty')}</p>
          )}
        </section>

        <section aria-label={t('progress.competencies.personality_title')}>
          <h4 className="text-sm font-semibold text-slate-900">{t('progress.competencies.personality_title')}</h4>
          <p className="mt-0.5 text-xs text-slate-400">{t('progress.competencies.source_personality')}</p>

          {ocean.state === 'value' && ocean.factors ? (
            <>
              <dl className="mt-2 flex flex-wrap gap-3">
                {Object.entries(ocean.factors).map(([factor, score]) => (
                  <div key={factor} className="rounded-lg bg-slate-50 px-2.5 py-1 text-center">
                    <dt className="text-xs font-semibold uppercase text-slate-500">{factor}</dt>
                    <dd className="text-sm font-semibold text-slate-900">{Math.round(Number(score))}</dd>
                  </div>
                ))}
              </dl>
              {ocean.assessed_at ? (
                <p className="mt-2 text-xs text-slate-400">
                  {t('progress.competencies.assessed_at', {
                    date: new Date(ocean.assessed_at).toLocaleDateString(),
                  })}
                </p>
              ) : null}
            </>
          ) : ocean.state === 'blocked' ? (
            <p className="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-slate-400">
              <Lock className="h-4 w-4" aria-hidden="true" />
              {t('progress.competencies.ocean_blocked')}
            </p>
          ) : (
            <p className="mt-2 text-sm text-slate-500">{t('progress.competencies.ocean_no_data')}</p>
          )}
        </section>
      </div>
    </ProgressCard>
  );
}
