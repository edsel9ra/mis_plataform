'use client';

import { useEffect, useRef, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { allowedObjectiveTransitions, objectiveStatusBadgeVariant } from '@/lib/progress';
import { useTransitionObjectiveStatus } from '@/hooks/use-progress';
import type { Objective, ObjectiveStatus } from '@/types/progress';

/**
 * Offers only valid status transitions and shows the change history (RF-2).
 * Controls are hidden when the viewer cannot transition the objective.
 */
export function ObjectiveStatusControl({ objective }: { objective: Objective }) {
  const t = useTranslations();
  const locale = useLocale();
  const mutation = useTransitionObjectiveStatus();
  const canTransition = objective.permissions?.transition;
  const transitions = allowedObjectiveTransitions(objective.status);
  const controlRef = useRef<HTMLDivElement>(null);
  const prevStatusRef = useRef(objective.status);
  const [announcement, setAnnouncement] = useState('');

  // The activated transition button unmounts when the status changes; keep
  // focus inside the control instead of losing it to <body>.
  useEffect(() => {
    if (prevStatusRef.current === objective.status) return;
    prevStatusRef.current = objective.status;
    if (!controlRef.current?.contains(document.activeElement)) {
      controlRef.current?.focus();
    }
  }, [objective.status]);

  async function transition(status: ObjectiveStatus) {
    try {
      await mutation.mutateAsync({ id: objective.id, status });
      setAnnouncement(t('progress.feedback.status_updated', {
        status: t(`progress.objectives.status.${status}`),
      }));
    } catch {
      // Surfaced below.
    }
  }

  return (
    <div ref={controlRef} tabIndex={-1} className="space-y-3 outline-none">
      <div role="status" className="sr-only">{announcement}</div>
      <div className="flex flex-wrap items-center gap-2">
        <Badge variant={objectiveStatusBadgeVariant(objective.status)}>
          {t(`progress.objectives.status.${objective.status}`)}
        </Badge>

        {canTransition
          ? transitions.map((status: ObjectiveStatus) => (
              <Button
                key={status}
                type="button"
                size="sm"
                variant="outline"
                loading={mutation.isPending && mutation.variables?.status === status}
                onClick={() => void transition(status)}
              >
                {t(`progress.objectives.status.${status}`)}
              </Button>
            ))
          : null}
      </div>

      {mutation.error ? <Alert variant="error">{t('progress.status_control.error')}</Alert> : null}

      <div>
        <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">
          {t('progress.status_control.history_title')}
        </h3>
        {objective.history && objective.history.length > 0 ? (
          <ul className="mt-2 space-y-1.5">
            {objective.history.map((entry) => (
              <li key={entry.id} className="text-xs text-slate-600">
                {entry.old_status ? (
                  <span>
                    {t(`progress.objectives.status.${entry.old_status}`)} →{' '}
                  </span>
                ) : null}
                {entry.new_status ? (
                  <span className="font-medium text-slate-800">
                    {t(`progress.objectives.status.${entry.new_status}`)}
                  </span>
                ) : null}
                {entry.causer_name ? (
                  <span> {t('progress.status_control.changed_by', { name: entry.causer_name })}</span>
                ) : null}
                {entry.created_at ? (
                  <span className="text-slate-600"> · {new Date(entry.created_at).toLocaleDateString(locale)}</span>
                ) : null}
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-1 text-xs text-slate-600">{t('progress.status_control.no_history')}</p>
        )}
      </div>
    </div>
  );
}
