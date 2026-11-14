'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { CancelSessionDialog } from '@/components/scheduling/session-actions/CancelSessionDialog';
import { CompleteSessionDialog } from '@/components/scheduling/session-actions/CompleteSessionDialog';
import { RescheduleSessionDialog } from '@/components/scheduling/session-actions/RescheduleSessionDialog';
import {
  sessionActionsPresentation,
  type SessionActionName,
  type SessionAvailableActions,
} from '@/lib/session-actions';

interface SessionActionsPanelProps {
  sessionId: string;
  sessionTitle: string;
  scheduledAt: string;
  durationMinutes: number;
  sessionTimezone?: string | null;
  viewerTimezone: string;
  availableActions: SessionAvailableActions;
  /** Called after a successful action, with the announcement text. */
  onSuccess: (message: string) => void;
}

export function SessionActionsPanel({
  sessionId,
  sessionTitle,
  scheduledAt,
  durationMinutes,
  sessionTimezone,
  viewerTimezone,
  availableActions,
  onSuccess,
}: SessionActionsPanelProps) {
  const t = useTranslations();
  const [openDialog, setOpenDialog] = useState<SessionActionName | null>(null);

  const { allowed, blocked } = sessionActionsPresentation(availableActions);
  if (allowed.length === 0 && blocked.length === 0) return null;

  const triggers: Record<SessionActionName, { label: string; variant: 'primary' | 'outline' | 'danger' }> = {
    reschedule: { label: t('session_actions.reschedule.button'), variant: 'outline' },
    complete: { label: t('session_actions.complete.button'), variant: 'primary' },
    cancel: { label: t('session_actions.cancel.button'), variant: 'danger' },
  };

  return (
    <section aria-labelledby="session-actions-title" className="border-t border-gray-100 p-4">
      <h2 id="session-actions-title" className="sr-only">
        {t('session_actions.actions.title')}
      </h2>
      {allowed.length > 0 && (
        <div className="flex flex-wrap gap-3">
          {allowed.map((action) => (
            <Button
              key={action}
              type="button"
              variant={triggers[action].variant}
              onClick={() => setOpenDialog(action)}
            >
              {triggers[action].label}
            </Button>
          ))}
        </div>
      )}
      {blocked.length > 0 && (
        <ul className={`space-y-1 text-sm text-gray-600${allowed.length > 0 ? ' mt-3' : ''}`}>
          {blocked.map(({ action, reasonKey }) => (
            <li key={action}>
              {t('session_actions.actions.blocked_item', {
                action: t(`session_actions.actions.names.${action}`),
                reason: t(reasonKey),
              })}
            </li>
          ))}
        </ul>
      )}

      {/* Dialogs stay mounted while open even if a refetch flips available_actions. */}
      <RescheduleSessionDialog
        sessionId={sessionId}
        sessionTitle={sessionTitle}
        scheduledAt={scheduledAt}
        durationMinutes={durationMinutes}
        sessionTimezone={sessionTimezone}
        viewerTimezone={viewerTimezone}
        open={openDialog === 'reschedule'}
        onOpenChange={(open) => setOpenDialog(open ? 'reschedule' : null)}
        onSuccess={onSuccess}
      />
      <CancelSessionDialog
        sessionId={sessionId}
        sessionTitle={sessionTitle}
        late={availableActions.cancel.late}
        open={openDialog === 'cancel'}
        onOpenChange={(open) => setOpenDialog(open ? 'cancel' : null)}
        onSuccess={onSuccess}
      />
      <CompleteSessionDialog
        sessionId={sessionId}
        sessionTitle={sessionTitle}
        open={openDialog === 'complete'}
        onOpenChange={(open) => setOpenDialog(open ? 'complete' : null)}
        onSuccess={onSuccess}
      />
    </section>
  );
}
