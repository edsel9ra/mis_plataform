'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormErrorSummary, type FormErrorItem } from '@/components/ui/form-error-summary';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
  useCreateAvailabilityException,
  useUpdateAvailabilityException,
} from '@/hooks/use-availability';
import { apiErrorMessage } from '@/lib/errors';
import {
  exceptionServerFieldErrors,
  type AvailabilityException,
  type AvailabilityExceptionPayload,
} from '@/lib/availability';
import {
  availabilityExceptionSchema,
  toExceptionPayload,
  type AvailabilityExceptionFormData,
  type AvailabilityExceptionFormInput,
} from '@/lib/schemas/availability';
import { buildTimeZoneOptions, sessionActionErrorOutcome } from '@/lib/session-actions';

const FIELD_IDS = {
  mode: 'availability-exception-mode-block',
  start_date: 'availability-exception-start-date',
  end_date: 'availability-exception-end-date',
  start_time: 'availability-exception-start-time',
  end_time: 'availability-exception-end-time',
  timezone: 'availability-exception-timezone',
  reason: 'availability-exception-reason',
} as const;
const TITLE_ID = 'availability-exception-title';
const ALL_DAY_ID = 'availability-exception-all-day';

interface AvailabilityExceptionDialogProps {
  /** null → create; exception → edit. */
  exception: AvailabilityException | null;
  viewerTimezone: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Called after success with the saved resource (may carry conflicting_sessions). */
  onSuccess: (saved: AvailabilityException) => void;
}

interface SubmissionError {
  fields: Record<string, string>;
  message: string | null;
}

function issueText(message: string | null | undefined, t: (key: string) => string): string | null {
  if (!message) return null;
  if (message.startsWith('session_actions.') || message.startsWith('availability.')) return t(message);

  return message;
}

export function AvailabilityExceptionDialog({
  exception,
  viewerTimezone,
  open,
  onOpenChange,
  onSuccess,
}: AvailabilityExceptionDialogProps) {
  const t = useTranslations();
  const createException = useCreateAvailabilityException();
  const updateException = useUpdateAvailabilityException(exception?.id ?? '');
  const pending = createException.isPending || updateException.isPending;
  const [submissionError, setSubmissionError] = useState<SubmissionError | null>(null);
  const [allDay, setAllDay] = useState<boolean>(exception ? exception.start_time === null : true);

  const timeZoneOptions = buildTimeZoneOptions(viewerTimezone, exception?.timezone);

  const form = useForm<AvailabilityExceptionFormInput, unknown, AvailabilityExceptionFormData>({
    resolver: zodResolver(availabilityExceptionSchema),
    defaultValues: {
      mode: exception?.mode === 'open' ? 'open' : 'block',
      start_date: exception?.start_date ?? '',
      end_date: exception?.end_date ?? '',
      start_time: exception?.start_time?.slice(0, 5) ?? '',
      end_time: exception?.end_time?.slice(0, 5) ?? '',
      timezone: exception?.timezone ?? viewerTimezone,
      reason: exception?.reason ?? '',
    },
  });

  useEffect(() => {
    if (open) {
      form.reset({
        mode: exception?.mode === 'open' ? 'open' : 'block',
        start_date: exception?.start_date ?? '',
        end_date: exception?.end_date ?? '',
        start_time: exception?.start_time?.slice(0, 5) ?? '',
        end_time: exception?.end_time?.slice(0, 5) ?? '',
        timezone: exception?.timezone ?? viewerTimezone,
        reason: exception?.reason ?? '',
      });
      setAllDay(exception ? exception.start_time === null : true);
      setSubmissionError(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  function fieldError(field: keyof AvailabilityExceptionPayload) {
    const raw = form.formState.errors[field]?.message ?? submissionError?.fields[field] ?? null;

    return issueText(raw, t);
  }

  const summaryErrors: FormErrorItem[] = (
    Object.keys(FIELD_IDS) as (keyof typeof FIELD_IDS)[]
  ).flatMap((field) => {
    const message = fieldError(field);
    return message ? [{ fieldId: FIELD_IDS[field], message }] : [];
  });

  function clearServerField(field: keyof AvailabilityExceptionPayload) {
    setSubmissionError((current) => {
      if (!current?.fields[field]) return current;
      const fields = { ...current.fields };
      delete fields[field];
      return { ...current, fields };
    });
  }

  async function submit(values: AvailabilityExceptionFormData) {
    setSubmissionError(null);
    const payload = toExceptionPayload({
      ...values,
      start_time: allDay ? null : values.start_time,
      end_time: allDay ? null : values.end_time,
    });
    try {
      const saved = exception
        ? await updateException.mutateAsync(payload)
        : await createException.mutateAsync(payload);
      onOpenChange(false);
      onSuccess(saved);
    } catch (error) {
      const outcome = sessionActionErrorOutcome(error);
      const mapped = exceptionServerFieldErrors(outcome.fieldErrors);
      setSubmissionError({
        fields: mapped.fields,
        message: mapped.formError
          ?? apiErrorMessage(error, (key) => t(key))
          ?? t('common.error'),
      });
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent aria-labelledby={TITLE_ID}>
        <DialogHeader>
          <DialogTitle id={TITLE_ID}>
            {exception
              ? t('availability.exceptions.edit_title')
              : t('availability.exceptions.create_title')}
          </DialogTitle>
        </DialogHeader>
        <form
          onSubmit={(event) => void form.handleSubmit(submit)(event)}
          className="mt-4 space-y-4"
          noValidate
        >
          <FormErrorSummary title={t('common.validation_error')} errors={summaryErrors} />
          {submissionError?.message && (
            <Alert variant="error">{submissionError.message}</Alert>
          )}

          <fieldset className="space-y-2">
            <legend className="block text-sm font-medium text-gray-700">
              {t('availability.exceptions.mode_legend')}
            </legend>
            {(['block', 'open'] as const).map((mode) => (
              <div key={mode} className="flex items-start gap-2">
                <input
                  id={`availability-exception-mode-${mode}`}
                  type="radio"
                  value={mode}
                  className="mt-1 h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500"
                  data-autofocus={mode === 'block' || undefined}
                  {...form.register('mode')}
                />
                <label htmlFor={`availability-exception-mode-${mode}`} className="text-sm">
                  <span className="font-medium text-gray-900">
                    {t(`availability.exceptions.mode_${mode}`)}
                  </span>
                  <span className="block text-gray-500">
                    {t(`availability.exceptions.mode_${mode}_help`)}
                  </span>
                </label>
              </div>
            ))}
          </fieldset>

          <div className="grid grid-cols-2 gap-3">
            <Input
              id={FIELD_IDS.start_date}
              type="date"
              label={t('availability.exceptions.start_date')}
              required
              announceError={false}
              error={fieldError('start_date') ?? undefined}
              {...form.register('start_date', { onChange: () => clearServerField('start_date') })}
            />
            <div>
              <Input
                id={FIELD_IDS.end_date}
                type="date"
                label={t('availability.exceptions.end_date')}
                announceError={false}
                error={fieldError('end_date') ?? undefined}
                {...form.register('end_date', { onChange: () => clearServerField('end_date') })}
              />
              <p className="mt-1 text-xs text-gray-500">
                {t('availability.exceptions.end_date_hint')}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <input
              id={ALL_DAY_ID}
              type="checkbox"
              checked={allDay}
              className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
              onChange={(event) => {
                setAllDay(event.target.checked);
                if (event.target.checked) {
                  form.setValue('start_time', '');
                  form.setValue('end_time', '');
                }
              }}
            />
            <label htmlFor={ALL_DAY_ID} className="text-sm font-medium text-gray-700">
              {t('availability.exceptions.all_day')}
            </label>
          </div>

          {!allDay && (
            <div className="grid grid-cols-2 gap-3">
              <Input
                id={FIELD_IDS.start_time}
                type="time"
                label={t('availability.window.start')}
                announceError={false}
                error={fieldError('start_time') ?? undefined}
                {...form.register('start_time', { onChange: () => clearServerField('start_time') })}
              />
              <Input
                id={FIELD_IDS.end_time}
                type="time"
                label={t('availability.window.end')}
                announceError={false}
                error={fieldError('end_time') ?? undefined}
                {...form.register('end_time', { onChange: () => clearServerField('end_time') })}
              />
            </div>
          )}

          <Select
            id={FIELD_IDS.timezone}
            label={t('availability.window.timezone')}
            required
            announceError={false}
            options={timeZoneOptions.map((zone) => ({ value: zone, label: zone }))}
            error={fieldError('timezone') ?? undefined}
            {...form.register('timezone', { onChange: () => clearServerField('timezone') })}
          />

          <div>
            <Textarea
              id={FIELD_IDS.reason}
              label={t('availability.exceptions.reason')}
              rows={2}
              announceError={false}
              error={fieldError('reason') ?? undefined}
              {...form.register('reason', { onChange: () => clearServerField('reason') })}
            />
            <p className="mt-1 text-xs text-gray-500">{t('availability.exceptions.reason_hint')}</p>
          </div>

          <DialogFooter>
            <Button variant="outline" type="button" onClick={() => onOpenChange(false)}>
              {t('session_actions.common.dismiss')}
            </Button>
            <Button variant="primary" type="submit" loading={pending}>
              {t('availability.window.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
