'use client';

import { useLocale, useTranslations } from 'next-intl';
import { RotateCcw } from 'lucide-react';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
  localizedTitle,
  mapElementStatusKey,
  mapElementTypeKey,
  moduleCompletionLabelKey,
} from '@/lib/progress';
import type { LearningMapElement, LearningMapPath } from '@/types/progress';
import { ModuleActivityText, PathRateBadge } from './LearningPathsCard';
import { ProgressCard } from './ProgressCard';

interface LearningMapQuery {
  data: LearningMapPath[] | undefined;
  isLoading: boolean;
  isError: boolean;
  refetch: () => void;
}

/**
 * Independent learning map view (RF-013.1–.4): each path renders its modules
 * as a plain ordered sequence — no stages or added groupings — and every
 * module lists only the elements with an explicitly registered link. Element
 * status is the source status verbatim, never derived.
 */
export function LearningMap({ query }: { query: LearningMapQuery }) {
  const t = useTranslations();
  const locale = useLocale();

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

  if (query.data.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
        <p className="text-sm text-slate-500">{t('progress.map.empty')}</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {query.data.map((path) => (
        <ProgressCard
          key={path.learning_path.id}
          title={localizedTitle(path.learning_path.title, locale)}
          action={<PathRateBadge rate={path.rate} />}
        >
          <ol className="space-y-4">
            {path.modules.map((module) => (
              <li key={module.id} className="rounded-xl border border-slate-100 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <h3 className="font-semibold text-slate-950">
                    <span className="text-slate-500">{module.position}.</span> {module.title}
                  </h3>
                  <span className="flex items-center gap-3 text-xs">
                    <ModuleActivityText rate={module.activity_rate} />
                    <Badge variant={module.completion.state === 'completed' ? 'success' : 'default'}>
                      {t(moduleCompletionLabelKey(module.completion.state))}
                    </Badge>
                  </span>
                </div>

                {module.elements.length > 0 ? (
                  <ul className="mt-3 space-y-1.5" aria-label={t('progress.map.elements')}>
                    {module.elements.map((element) => (
                      <MapElementRow key={element.id} element={element} />
                    ))}
                  </ul>
                ) : (
                  <p className="mt-3 text-xs text-slate-500">{t('progress.map.no_elements')}</p>
                )}
              </li>
            ))}
          </ol>
        </ProgressCard>
      ))}
    </div>
  );
}

function MapElementRow({ element }: { element: LearningMapElement }) {
  const t = useTranslations();
  const typeKey = mapElementTypeKey(element.type);
  const statusKey = mapElementStatusKey(element);

  return (
    <li className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-sm">
      <span className="flex min-w-0 items-center gap-2">
        <Badge variant="primary">{typeKey ? t(typeKey) : element.type}</Badge>
        <span className="truncate text-slate-700">{element.title}</span>
      </span>
      <span className="text-xs text-slate-500">
        {statusKey ? t(statusKey) : element.source_status || t('mentor_workspace.status_unknown')}
      </span>
    </li>
  );
}
