'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormErrorSummary, type FormErrorItem } from '@/components/ui/form-error-summary';
import { Textarea } from '@/components/ui/textarea';
import { useCancelSession } from '@/hooks/use-sessions';
import { apiErrorMessage } from '@/lib/errors';
import {
  LATE_CANCEL_NOTICE_HOURS,
  sessionActionErrorMessage,
  sessionActionErrorOutcome,
} from '@/lib/session-actions';
import { cancelSessionSchema, type CancelSessionFormData } from '@/lib/schemas/session-actions';

const REASON_FIELD_ID = 'session-cancel-reason';
const TITLE_ID = 'session-cancel-title';
const DESC_ID = 'session-cancel-desc';

interface CancelSessionDialogProps {
  sessionId: string;
  sessionTitle: string;
  /** True when the reported availability marks this as a late cancellation. */
  late: boolean;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Called after the dialog closes on success, with the announcement text. */
  onSuccess: (message: string) => void;
}

interface SubmissionError {
  fieldErrors: Record<string, string[]>;
  message: string | null;
  staleDetail: boolean;
}

export function CancelSessionDialog({
  sessionId,
  sessionTitle,
  late,
  open,
  onOpenChange,
  onSuccess,
}: CancelSessionDialogProps) {
  const t = useTranslations();
  const cancelSession = useCancelSession(sessionId);
  const [submissionError, setSubmissionError] = useState<SubmissionError | null>(null);

  const form = useForm<CancelSessionFormData>({
    resolver: zodResolver(cancelSessionSchema),
    defaultValues: { reason: '' },
  });

  useEffect(() => {
    if (!open) {
      form.reset();
      setSubmissionError(null);
    }
  }, [open, form]);

  const rawReasonError = form.formState.errors.reason?.message
    ?? submissionError?.fieldErrors.reason?.[0]
    ?? null;
  // Zod issues carry i18n keys; server field errors arrive already translated.
  const reasonError = rawReasonError?.startsWith('session_actions.')
    ? t(rawReasonError)
    : rawReasonError;

  const summaryErrors: FormErrorItem[] = reasonError
    ? [{ fieldId: REASON_FIELD_ID, message: reasonError }]
    : [];

  async function submit(values: CancelSessionFormData) {
    setSubmissionError(null);
    try {
      await cancelSession.mutateAsync({ reason: values.reason });
      onOpenChange(false);
      onSuccess(t('session_actions.cancel.success'));
    } catch (error) {
      const outcome = sessionActionErrorOutcome(error);
      setSubmissionError({
        fieldErrors: outcome.fieldErrors,
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
          <DialogTitle id={TITLE_ID}>{t('session_actions.cancel.title')}</DialogTitle>
        </DialogHeader>
        <p id={DESC_ID} className="text-sm text-gray-600">
          {sessionTitle}
          {' — '}
          {t('session_actions.cancel.description')}
        </p>
        <form
          onSubmit={(event) => void form.handleSubmit(submit)(event)}
          className="mt-4 space-y-4"
          noValidate
        >
          <FormErrorSummary title={t('common.validation_error')} errors={summaryErrors} />
          {submissionError?.message && (
            <Alert variant="error">{submissionError.message}</Alert>
          )}
          {submissionError?.staleDetail && (
            <Alert variant="info">{t('session_actions.common.stale_notice')}</Alert>
          )}
          {late && (
            <Alert variant="warning" role="note">
              {t('session_actions.cancel.late_warning', { hours: LATE_CANCEL_NOTICE_HOURS })}
            </Alert>
          )}
          <Textarea
            id={REASON_FIELD_ID}
            label={t('session_actions.cancel.reason_label')}
            rows={3}
            required
            announceError={false}
            data-autofocus
            error={reasonError ?? undefined}
            {...form.register('reason', {
              onChange: () => {
                setSubmissionError((current) =>
                  current?.fieldErrors.reason
                    ? { ...current, fieldErrors: {} }
                    : current,
                );
              },
            })}
          />
          <DialogFooter>
            <Button variant="outline" type="button" onClick={() => onOpenChange(false)}>
              {t('session_actions.common.dismiss')}
            </Button>
            <Button variant="danger" type="submit" loading={cancelSession.isPending}>
              {t('session_actions.cancel.confirm')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
