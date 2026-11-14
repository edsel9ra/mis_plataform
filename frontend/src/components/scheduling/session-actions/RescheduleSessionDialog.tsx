'use client';

import { useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormErrorSummary, type FormErrorItem } from '@/components/ui/form-error-summary';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useRescheduleSession } from '@/hooks/use-sessions';
import { apiErrorMessage } from '@/lib/errors';
import {
  RESCHEDULE_MAX_DURATION,
  RESCHEDULE_MIN_DURATION,
  rescheduleSessionSchema,
  toReschedulePayload,
  type RescheduleSessionFormData,
  type RescheduleSessionFormInput,
} from '@/lib/schemas/session-actions';
import { instantToWallTime } from '@/lib/scheduling';
import {
  buildTimeZoneOptions,
  formatRescheduleWhen,
  rescheduleServerFieldErrors,
  sessionActionErrorMessage,
  sessionActionErrorOutcome,
} from '@/lib/session-actions';

const FIELD_IDS = {
  date: 'session-reschedule-date',
  time: 'session-reschedule-time',
  duration: 'session-reschedule-duration',
  timezone: 'session-reschedule-timezone',
  reason: 'session-reschedule-reason',
} as const;
const TITLE_ID = 'session-reschedule-title';
const DESC_ID = 'session-reschedule-desc';

interface RescheduleSessionDialogProps {
  sessionId: string;
  sessionTitle: string;
  /** Current start instant (ISO) used to prefill date/time in the chosen zone. */
  scheduledAt: string;
  durationMinutes: number;
  /** Session zone; falls back to the viewer zone when absent. */
  sessionTimezone?: string | null;
  viewerTimezone: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Called after the dialog closes on success, with the announcement text. */
  onSuccess: (message: string) => void;
}

type ServerFields = Partial<Record<'date' | 'duration' | 'timezone' | 'reason', string>>;

interface SubmissionError {
  fields: ServerFields;
  message: string | null;
  staleDetail: boolean;
}

export function RescheduleSessionDialog({
  sessionId,
  sessionTitle,
  scheduledAt,
  durationMinutes,
  sessionTimezone,
  viewerTimezone,
  open,
  onOpenChange,
  onSuccess,
}: RescheduleSessionDialogProps) {
  const t = useTranslations();
  const locale = useLocale();
  const rescheduleSession = useRescheduleSession(sessionId);
  const [submissionError, setSubmissionError] = useState<SubmissionError | null>(null);

  const defaultZone = sessionTimezone ?? viewerTimezone;
  const timeZoneOptions = buildTimeZoneOptions(sessionTimezone, viewerTimezone);
  const { date, time } = instantToWallTime(scheduledAt, defaultZone);

  const form = useForm<RescheduleSessionFormInput, unknown, RescheduleSessionFormData>({
    resolver: zodResolver(rescheduleSessionSchema),
    defaultValues: {
      date,
      time,
      duration_minutes: durationMinutes,
      timezone: defaultZone,
      reason: '',
    },
  });

  useEffect(() => {
    if (open) {
      const wall = instantToWallTime(scheduledAt, defaultZone);
      form.reset({
        date: wall.date,
        time: wall.time,
        duration_minutes: durationMinutes,
        timezone: defaultZone,
        reason: '',
      });
      setSubmissionError(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  function fieldError(field: 'date' | 'time' | 'duration_minutes' | 'timezone' | 'reason') {
    const zodMessage = form.formState.errors[field]?.message;
    const serverKey = field === 'duration_minutes' ? 'duration' : field;
    const serverMessage = serverKey === 'time' ? null : submissionError?.fields[serverKey];
    const raw = zodMessage ?? serverMessage ?? null;
    // Zod issues carry i18n keys; server messages arrive already translated.
    return raw?.startsWith('session_actions.') ? t(raw) : raw;
  }

  const errors: Record<'date' | 'time' | 'duration_minutes' | 'timezone' | 'reason', string | null | undefined> = {
    date: fieldError('date'),
    time: fieldError('time'),
    duration_minutes: fieldError('duration_minutes'),
    timezone: fieldError('timezone'),
    reason: fieldError('reason'),
  };

  const errorPairs: [string, string | null | undefined][] = [
    [FIELD_IDS.date, errors.date],
    [FIELD_IDS.time, errors.time],
    [FIELD_IDS.duration, errors.duration_minutes],
    [FIELD_IDS.timezone, errors.timezone],
    [FIELD_IDS.reason, errors.reason],
  ];
  const summaryErrors: FormErrorItem[] = errorPairs.flatMap(([fieldId, message]) =>
    (message ? [{ fieldId, message }] : []));

  async function submit(values: RescheduleSessionFormData) {
    setSubmissionError(null);
    try {
      const updated = await rescheduleSession.mutateAsync(toReschedulePayload(values));
      onOpenChange(false);
      const newStart = (updated as { scheduled_at?: string } | null)?.scheduled_at ?? scheduledAt;
      onSuccess(t('session_actions.reschedule.success', {
        when: formatRescheduleWhen(newStart, viewerTimezone, locale),
      }));
    } catch (error) {
      const outcome = sessionActionErrorOutcome(error);
      const mapped = rescheduleServerFieldErrors(outcome.fieldErrors);
      setSubmissionError({
        fields: mapped.fields,
        message: sessionActionErrorMessage(outcome, t)
          ?? mapped.formError
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
          <DialogTitle id={TITLE_ID}>{t('session_actions.reschedule.title')}</DialogTitle>
        </DialogHeader>
        <p id={DESC_ID} className="text-sm text-gray-600">
          {sessionTitle}
          {' — '}
          {t('session_actions.reschedule.description')}
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
          <div className="grid gap-4 sm:grid-cols-2">
            <Input
              id={FIELD_IDS.date}
              label={t('session_actions.reschedule.date')}
              type="date"
              required
              announceError={false}
              data-autofocus
              error={errors.date ?? undefined}
              {...form.register('date')}
            />
            <Input
              id={FIELD_IDS.time}
              label={t('session_actions.reschedule.time')}
              type="time"
              required
              announceError={false}
              error={errors.time ?? undefined}
              {...form.register('time')}
            />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <Input
              id={FIELD_IDS.duration}
              label={t('session_actions.reschedule.duration')}
              type="number"
              min={RESCHEDULE_MIN_DURATION}
              max={RESCHEDULE_MAX_DURATION}
              step={1}
              required
              announceError={false}
              error={errors.duration_minutes ?? undefined}
              {...form.register('duration_minutes')}
            />
            <Select
              id={FIELD_IDS.timezone}
              label={t('session_actions.reschedule.timezone')}
              required
              announceError={false}
              error={errors.timezone ?? undefined}
              options={timeZoneOptions.map((zone) => ({ value: zone, label: zone }))}
              {...form.register('timezone')}
            />
          </div>
          <Textarea
            id={FIELD_IDS.reason}
            label={t('session_actions.reschedule.reason')}
            rows={2}
            announceError={false}
            error={errors.reason ?? undefined}
            {...form.register('reason')}
          />
          <DialogFooter>
            <Button variant="outline" type="button" onClick={() => onOpenChange(false)}>
              {t('session_actions.common.dismiss')}
            </Button>
            <Button variant="primary" type="submit" loading={rescheduleSession.isPending}>
              {t('session_actions.reschedule.confirm')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
