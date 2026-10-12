'use client';

import { useTranslations } from 'next-intl';
import { RotateCcw } from 'lucide-react';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { ProgressBlockName, ProgressPanel as ProgressPanelData } from '@/types/progress';
import { ActivitiesCard } from './ActivitiesCard';
import { CollectiveProgressCard } from './CollectiveProgressCard';
import { CompetenciesCard } from './CompetenciesCard';
import { ObjectiveAchievementCard } from './ObjectiveAchievementCard';
import { ObjectivesBlock } from './ObjectivesBlock';
import { SessionsMetricCard } from './SessionsMetricCard';
import { UpcomingCommitmentsCard } from './UpcomingCommitmentsCard';

interface PanelQuery {
  data: ProgressPanelData | undefined;
  isLoading: boolean;
  isError: boolean;
  refetch: () => void;
}

interface ProgressPanelProps {
  query: PanelQuery;
  relationshipId?: string;
  showCollectives?: boolean;
}

const BLOCK_TITLE_KEY: Record<ProgressBlockName, string> = {
  objectives: 'progress.objectives.title',
  sessions: 'progress.sessions.title',
  activities: 'progress.activities.title',
  competencies: 'progress.competencies.title',
  commitments: 'progress.commitments.title',
};

/** A panel with no data in any block and no collectives (no active process). */
function isPanelEmpty(panel: ProgressPanelData): boolean {
  const blocksEmpty = [panel.objectives, panel.sessions, panel.activities, panel.commitments].every(
    (block) => block.state === 'no_data',
  );

  return blocksEmpty && panel.competencies.state === 'no_data' && panel.collectives.length === 0;
}

/**
 * Orchestrates the progress blocks with an `aria-live` loading region, a
 * `role="alert"` recoverable error with retry, and per-block incomplete
 * notices (RF-3, RF-13).
 */
export function ProgressPanel({ query, relationshipId, showCollectives = true }: ProgressPanelProps) {
  const t = useTranslations();

  if (query.isLoading) {
    return (
      <div role="status" aria-live="polite" className="flex flex-col items-center justify-center gap-3 py-16">
        <Spinner size="lg" />
        <p className="text-sm text-slate-500">{t('progress.loading')}</p>
      </div>
    );
  }

  if (query.isError || !query.data) {
    return (
      <Alert variant="error">
        <div className="space-y-3">
          <p>{t('progress.error')}</p>
          <Button type="button" size="sm" variant="outline" onClick={() => query.refetch()}>
            <RotateCcw className="h-4 w-4" aria-hidden="true" />
            {t('progress.retry')}
          </Button>
        </div>
      </Alert>
    );
  }

  const panel = query.data;
  const subjectId = panel.subject?.id ?? '';
  const canCreate = panel.viewer_role === 'mentor' || panel.viewer_role === 'support' || panel.viewer_role === 'admin';

  if (isPanelEmpty(panel)) {
    return (
      <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
        <h2 className="text-lg font-semibold text-slate-900">{t('progress.empty_state_title')}</h2>
        <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">{t('progress.empty_state_description')}</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {panel.incomplete_blocks.length > 0 ? (
        <div aria-live="polite" className="space-y-2">
          {panel.incomplete_blocks.map((block) => (
            <Alert key={block} variant="warning">
              {t(BLOCK_TITLE_KEY[block] ?? 'progress.title')}: {t('progress.incomplete_block')}
            </Alert>
          ))}
        </div>
      ) : null}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <ObjectiveAchievementCard block={panel.objectives} period={panel.objectives.metric.period} />
        <SessionsMetricCard block={panel.sessions} />
        <ActivitiesCard block={panel.activities} />
        <UpcomingCommitmentsCard block={panel.commitments} />
      </div>

      <ObjectivesBlock
        subjectId={subjectId}
        block={panel.objectives}
        period={panel.objectives.metric.period}
        canCreate={canCreate}
        relationshipId={relationshipId}
      />

      <CompetenciesCard block={panel.competencies} />

      {showCollectives && panel.collectives.length > 0 ? (
        <div className="grid gap-4 lg:grid-cols-2">
          {panel.collectives.map((collective) => (
            <CollectiveProgressCard
              key={`${collective.subject?.type}-${collective.subject?.id}`}
              collective={collective}
            />
          ))}
        </div>
      ) : null}
    </div>
  );
}
