'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCompleteSession } from '@/hooks/use-sessions';
import { apiErrorMessage } from '@/lib/errors';
import { sessionActionErrorMessage, sessionActionErrorOutcome } from '@/lib/session-actions';

const TITLE_ID = 'session-complete-title';
const DESC_ID = 'session-complete-desc';

interface CompleteSessionDialogProps {
  sessionId: string;
  sessionTitle: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Called after the dialog closes on success, with the announcement text. */
  onSuccess: (message: string) => void;
}

interface SubmissionError {
  message: string;
  staleDetail: boolean;
}

export function CompleteSessionDialog({
  sessionId,
  sessionTitle,
  open,
  onOpenChange,
  onSuccess,
}: CompleteSessionDialogProps) {
  const t = useTranslations();
  const completeSession = useCompleteSession(sessionId);
  const [submissionError, setSubmissionError] = useState<SubmissionError | null>(null);

  useEffect(() => {
    if (!open) setSubmissionError(null);
  }, [open]);

  async function confirm() {
    setSubmissionError(null);
    try {
      await completeSession.mutateAsync(undefined);
      onOpenChange(false);
      onSuccess(t('session_actions.complete.success'));
    } catch (error) {
      const outcome = sessionActionErrorOutcome(error);
      setSubmissionError({
        message: sessionActionErrorMessage(outcome, t)
          ?? apiErrorMessage(error, (key) => t(key))
          ?? t('common.error'),
        staleDetail: outcome.staleDetail,
      });
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent aria-labelledby={TITLE_ID} aria-describedby={DESC_ID}>
        <DialogHeader>
          <DialogTitle id={TITLE_ID}>{t('session_actions.complete.title')}</DialogTitle>
        </DialogHeader>
        <p id={DESC_ID} className="text-sm text-gray-600">
          {sessionTitle}
          {' — '}
          {t('session_actions.complete.description')}
        </p>
        {submissionError && (
          <div className="mt-4 space-y-3">
            <Alert variant="error">{submissionError.message}</Alert>
            {submissionError.staleDetail && (
              <Alert variant="info">{t('session_actions.common.stale_notice')}</Alert>
            )}
          </div>
        )}
        <DialogFooter>
          <Button
            variant="outline"
            type="button"
            data-autofocus
            onClick={() => onOpenChange(false)}
          >
            {t('session_actions.common.dismiss')}
          </Button>
          <Button
            variant="primary"
            type="button"
            loading={completeSession.isPending}
            onClick={() => void confirm()}
          >
            {t('session_actions.complete.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
