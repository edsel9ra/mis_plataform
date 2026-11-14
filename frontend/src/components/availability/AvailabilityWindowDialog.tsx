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
import {
  useCreateAvailabilityWindow,
  useUpdateAvailabilityWindow,
} from '@/hooks/use-availability';
import { apiErrorMessage } from '@/lib/errors';
import {
  groupWindowsByDay,
  windowServerFieldErrors,
  type AvailabilityWindow,
  type AvailabilityWindowPayload,
} from '@/lib/availability';
import {
  availabilityWindowSchema,
  type AvailabilityWindowFormData,
  type AvailabilityWindowFormInput,
} from '@/lib/schemas/availability';
import { buildTimeZoneOptions, sessionActionErrorOutcome } from '@/lib/session-actions';

const FIELD_IDS = {
  day_of_week: 'availability-window-day',
  start_time: 'availability-window-start',
  end_time: 'availability-window-end',
  timezone: 'availability-window-timezone',
} as const;
const TITLE_ID = 'availability-window-title';

interface AvailabilityWindowDialogProps {
  /** null → create; window → edit. */
  window: AvailabilityWindow | null;
  /** Viewer profile timezone; default on create. */
  viewerTimezone: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Called after success with {dayOfWeek, announcement}; the page focuses the day heading. */
  onSuccess: (dayOfWeek: number, announcement: string) => void;
}

interface SubmissionError {
  fields: Record<string, string>;
  message: string | null;
}

/** Zod issues carry i18n keys (`session_actions.*`/`availability.*`); server messages are already translated. */
function issueText(message: string | null | undefined, t: (key: string) => string): string | null {
  if (!message) return null;
  if (message.startsWith('session_actions.') || message.startsWith('availability.')) return t(message);

  return message;
}

export function AvailabilityWindowDialog({
  window,
  viewerTimezone,
  open,
  onOpenChange,
  onSuccess,
}: AvailabilityWindowDialogProps) {
  const t = useTranslations();
  const locale = useLocale();
  const createWindow = useCreateAvailabilityWindow();
  const updateWindow = useUpdateAvailabilityWindow(window?.id ?? '');
  const pending = createWindow.isPending || updateWindow.isPending;
  const [submissionError, setSubmissionError] = useState<SubmissionError | null>(null);

  const dayOptions = groupWindowsByDay([], locale).map((day) => ({
    value: String(day.dayOfWeek),
    label: day.label,
  }));
  const timeZoneOptions = buildTimeZoneOptions(viewerTimezone, window?.timezone);

  const form = useForm<AvailabilityWindowFormInput, unknown, AvailabilityWindowFormData>({
    resolver: zodResolver(availabilityWindowSchema),
    defaultValues: {
      day_of_week: window?.day_of_week ?? 1,
      start_time: window?.start_time?.slice(0, 5) ?? '',
      end_time: window?.end_time?.slice(0, 5) ?? '',
      timezone: window?.timezone ?? viewerTimezone,
    },
  });

  useEffect(() => {
    if (open) {
      form.reset({
        day_of_week: window?.day_of_week ?? 1,
        start_time: window?.start_time?.slice(0, 5) ?? '',
        end_time: window?.end_time?.slice(0, 5) ?? '',
        timezone: window?.timezone ?? viewerTimezone,
      });
      setSubmissionError(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  function fieldError(field: keyof AvailabilityWindowPayload) {
    const raw = form.formState.errors[field]?.message ?? submissionError?.fields[field] ?? null;

    return issueText(raw, t);
  }

  const summaryErrors: FormErrorItem[] = (
    Object.keys(FIELD_IDS) as (keyof typeof FIELD_IDS)[]
  ).flatMap((field) => {
    const message = fieldError(field);
    return message ? [{ fieldId: FIELD_IDS[field], message }] : [];
  });

  function dayLabel(dayOfWeek: number): string {
    return dayOptions.find((day) => day.value === String(dayOfWeek))?.label ?? '';
  }

  async function submit(values: AvailabilityWindowFormData) {
    setSubmissionError(null);
    const payload: AvailabilityWindowPayload = {
      day_of_week: values.day_of_week,
      start_time: values.start_time,
      end_time: values.end_time,
      timezone: values.timezone,
    };
    try {
      if (window) {
        await updateWindow.mutateAsync(payload);
      } else {
        await createWindow.mutateAsync(payload);
      }
      onOpenChange(false);
      onSuccess(
        values.day_of_week,
        t('availability.window.saved', {
          day: dayLabel(values.day_of_week),
          range: `${values.start_time}–${values.end_time}`,
        }),
      );
    } catch (error) {
      const outcome = sessionActionErrorOutcome(error);
      const mapped = windowServerFieldErrors(outcome.fieldErrors);
      setSubmissionError({
        fields: mapped.fields,
        message: mapped.formError
          ?? apiErrorMessage(error, (key) => t(key))
          ?? t('common.error'),
      });
    }
  }

  function clearServerField(field: keyof AvailabilityWindowPayload) {
    setSubmissionError((current) => {
      if (!current?.fields[field]) return current;
      const fields = { ...current.fields };
      delete fields[field];
      return { ...current, fields };
    });
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent aria-labelledby={TITLE_ID}>
        <DialogHeader>
          <DialogTitle id={TITLE_ID}>
            {window ? t('availability.window.edit_title') : t('availability.window.create_title')}
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
          <Select
            id={FIELD_IDS.day_of_week}
            label={t('availability.window.day')}
            required
            announceError={false}
            data-autofocus
            options={dayOptions}
            error={fieldError('day_of_week') ?? undefined}
            {...form.register('day_of_week', {
              onChange: () => clearServerField('day_of_week'),
            })}
          />
          <div className="grid grid-cols-2 gap-3">
            <Input
              id={FIELD_IDS.start_time}
              type="time"
              label={t('availability.window.start')}
              required
              announceError={false}
              error={fieldError('start_time') ?? undefined}
              {...form.register('start_time', {
                onChange: () => clearServerField('start_time'),
              })}
            />
            <Input
              id={FIELD_IDS.end_time}
              type="time"
              label={t('availability.window.end')}
              required
              announceError={false}
              error={fieldError('end_time') ?? undefined}
              {...form.register('end_time', {
                onChange: () => clearServerField('end_time'),
              })}
            />
          </div>
          <Select
            id={FIELD_IDS.timezone}
            label={t('availability.window.timezone')}
            required
            announceError={false}
            options={timeZoneOptions.map((zone) => ({ value: zone, label: zone }))}
            error={fieldError('timezone') ?? undefined}
            {...form.register('timezone', {
              onChange: () => clearServerField('timezone'),
            })}
          />
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
