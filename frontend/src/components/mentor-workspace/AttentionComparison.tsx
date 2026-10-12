'use client';

import { useTranslations } from 'next-intl';
import { Eye, UserRound } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { asNumber } from '@/lib/mentor-workspace';
import type { AttentionObservation } from '@/types/mentor-workspace';

const dimensions = [
  { key: 'focus', label: 'attention_focus' },
  { key: 'regulation', label: 'emotional_regulation' },
  { key: 'stress', label: 'stress_load' },
  { key: 'energy', label: 'energy_level' },
] as const;

export function AttentionComparison({
  selfReport,
  mentorObservation,
}: {
  selfReport?: AttentionObservation | null;
  mentorObservation?: AttentionObservation | null;
}) {
  const t = useTranslations();

  return (
    <div className="grid gap-4 lg:grid-cols-2" aria-label={t('mentor_workspace.attention_comparison')}>
      <ObservationCard
        title={t('mentor_workspace.mentee_self_report')}
        description={t('mentor_workspace.mentee_self_report_description')}
        icon={UserRound}
        observation={selfReport}
      />
      <ObservationCard
        title={t('mentor_workspace.mentor_observation')}
        description={t('mentor_workspace.mentor_observation_description')}
        icon={Eye}
        observation={mentorObservation}
      />
    </div>
  );
}

function ObservationCard({
  title,
  description,
  icon: Icon,
  observation,
}: {
  title: string;
  description: string;
  icon: typeof UserRound;
  observation?: AttentionObservation | null;
}) {
  const t = useTranslations();

  return (
    <Card className="border-slate-200 bg-white">
      <div className="flex items-start gap-3">
        <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
          <Icon className="h-5 w-5" aria-hidden="true" />
        </span>
        <div>
          <h3 className="font-semibold text-slate-950">{title}</h3>
          <p className="mt-1 text-xs leading-5 text-slate-500">{description}</p>
        </div>
      </div>
      {!observation ? (
        <p className="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">{t('mentor_workspace.observation_empty')}</p>
      ) : (
        <>
          <div className="mt-5 grid gap-3 sm:grid-cols-2">
            {dimensions.map(({ key, label }) => {
              const value = toPercentage(key, asNumber(observation[key] ?? observation.scores?.[key] ?? observation.dimensions?.[key]));
              return (
                <div key={key} className="rounded-xl border border-slate-100 bg-slate-50 p-3">
                  <div className="flex items-center justify-between gap-3">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t(`mentor_workspace.${label}`)}</span>
                    <span className="font-bold text-slate-950">{value === null ? '—' : `${Math.round(value)}%`}</span>
                  </div>
                  <div className="mt-2 h-1.5 rounded-full bg-slate-200">
                    <div className="h-1.5 rounded-full bg-primary-500" style={{ width: `${value === null ? 0 : Math.max(0, Math.min(100, value))}%` }} />
                  </div>
                </div>
              );
            })}
          </div>
          {(observation.note || observation.notes) && (
            <p className="mt-4 border-t border-slate-100 pt-4 text-sm leading-6 text-slate-600">{observation.note || observation.notes}</p>
          )}
        </>
      )}
    </Card>
  );
}

function toPercentage(key: (typeof dimensions)[number]['key'], value: number | null): number | null {
  if (value === null) return null;
  if (key === 'focus' && value > 0 && value <= 5) return value * 20;

  return value;
}
