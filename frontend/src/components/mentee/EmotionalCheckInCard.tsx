'use client';

import { useId, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useLocale, useTranslations } from 'next-intl';
import { ApiError } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { mapServerFieldErrors } from '@/lib/availability';
import { validationMessage } from '@/lib/validation-message';
import { CHECK_IN_SCALE, moodLabelKey, stressLabelKey } from '@/lib/emotional-check-in';
import {
  emotionalCheckInSchema,
  toEmotionalCheckInPayload,
  type EmotionalCheckInFormValues,
} from '@/lib/schemas/mentee';
import {
  useCreateEmotionalCheckIn,
  useEmotionalCheckIns,
} from '@/hooks/use-emotional-check-ins';
import type { Relationship } from '@/hooks/use-relationships';
import { useAuth } from '@/components/auth/AuthProvider';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';

const SERVER_FIELDS = new Set(['mood_score', 'stress_score', 'note']);

interface EmotionalCheckInCardProps {
  relationship: Relationship;
}

export function EmotionalCheckInCard({ relationship }: EmotionalCheckInCardProps) {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();
  const checkIns = useEmotionalCheckIns(relationship.id, relationship.features?.emotional_check_ins === true);
  const create = useCreateEmotionalCheckIn(relationship.id);
  const [feedback, setFeedback] = useState('');
  const [formError, setFormError] = useState<unknown>(null);

  const titleId = useId();
  const descId = useId();
  const moodErrorId = useId();
  const stressErrorId = useId();

  const form = useForm<EmotionalCheckInFormValues>({
    resolver: zodResolver(emotionalCheckInSchema),
    defaultValues: { mood_score: '', stress_score: '', note: '' },
  });
  const errors = form.formState.errors;
  const moodError = validationMessage(errors.mood_score?.message, t);
  const stressError = validationMessage(errors.stress_score?.message, t);

  async function submit(values: EmotionalCheckInFormValues) {
    setFeedback('');
    setFormError(null);

    try {
      await create.mutateAsync(toEmotionalCheckInPayload(values));
      form.reset({ mood_score: '', stress_score: '', note: '' });
      setFeedback(t('mentee.check_in.saved'));
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        const mapped = mapServerFieldErrors(error.errors ?? {}, SERVER_FIELDS);
        let focused = false;
        for (const field of ['mood_score', 'stress_score', 'note'] as const) {
          const message = mapped.fields[field];
          if (message) {
            form.setError(field, { type: 'server', message }, { shouldFocus: !focused });
            focused = true;
          }
        }
        if (mapped.formError) setFormError(mapped.formError);
        else if (!focused) setFormError(error);
      } else {
        setFormError(error);
      }
    }
  }

  function clearServerField(field: keyof EmotionalCheckInFormValues) {
    if (form.formState.errors[field]?.type === 'server') {
      form.clearErrors(field);
    }
  }

  const dateFormatter = new Intl.DateTimeFormat(locale, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: user?.timezone ?? 'UTC',
  });

  return (
    <Card className="border-0 shadow-sm">
      <section aria-labelledby={`${titleId} ${descId}`}>
        <h2 id={titleId} className="text-lg font-semibold text-gray-950">
          {t('mentee.check_in.title')}
        </h2>
        <p id={descId} className="mt-1 text-sm text-gray-500">
          {t('mentee.check_in.with_mentor', { name: relationship.mentor?.full_name ?? '' })}
        </p>
        <div role="status" className="sr-only">{feedback}</div>
        <form onSubmit={(event) => void form.handleSubmit(submit)(event)} className="mt-4 space-y-5">
          <fieldset aria-describedby={moodError ? moodErrorId : undefined}>
            <legend className="text-sm font-medium text-gray-700">{t('mentee.check_in.mood')}</legend>
            <div
              role="radiogroup"
              aria-invalid={moodError ? true : undefined}
              className="mt-2 flex flex-wrap gap-2"
            >
              {CHECK_IN_SCALE.map((score) => (
                <label
                  key={score}
                  className="flex min-h-6 cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-800 transition has-checked:border-primary-500 has-checked:bg-primary-50 has-focus-visible:ring-2 has-focus-visible:ring-primary-500 has-focus-visible:ring-offset-2"
                >
                  <input
                    type="radio"
                    value={String(score)}
                    className="h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500"
                    {...form.register('mood_score', { onChange: () => clearServerField('mood_score') })}
                  />
                  <span>{t(moodLabelKey(score))}</span>
                </label>
              ))}
            </div>
            {moodError && (
              <p id={moodErrorId} className="mt-1 flex items-center gap-1 text-sm text-red-600" role="alert">
                {moodError}
              </p>
            )}
          </fieldset>

          <fieldset aria-describedby={stressError ? stressErrorId : undefined}>
            <legend className="text-sm font-medium text-gray-700">{t('mentee.check_in.stress')}</legend>
            <div
              role="radiogroup"
              aria-invalid={stressError ? true : undefined}
              className="mt-2 flex flex-wrap gap-2"
            >
              <label className="flex min-h-6 cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-800 transition has-checked:border-primary-500 has-checked:bg-primary-50 has-focus-visible:ring-2 has-focus-visible:ring-primary-500 has-focus-visible:ring-offset-2">
                <input
                  type="radio"
                  value=""
                  className="h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500"
                  {...form.register('stress_score', { onChange: () => clearServerField('stress_score') })}
                />
                <span>{t('mentee.check_in.stress_none')}</span>
              </label>
              {CHECK_IN_SCALE.map((score) => (
                <label
                  key={score}
                  className="flex min-h-6 cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-800 transition has-checked:border-primary-500 has-checked:bg-primary-50 has-focus-visible:ring-2 has-focus-visible:ring-primary-500 has-focus-visible:ring-offset-2"
                >
                  <input
                    type="radio"
                    value={String(score)}
                    className="h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500"
                    {...form.register('stress_score', { onChange: () => clearServerField('stress_score') })}
                  />
                  <span>{t(stressLabelKey(score))}</span>
                </label>
              ))}
            </div>
            {stressError && (
              <p id={stressErrorId} className="mt-1 flex items-center gap-1 text-sm text-red-600" role="alert">
                {stressError}
              </p>
            )}
          </fieldset>

          <Textarea
            label={t('mentee.check_in.note')}
            rows={3}
            error={validationMessage(errors.note?.message, t)}
            {...form.register('note', { onChange: () => clearServerField('note') })}
          />

          {Boolean(formError) && (
            <Alert variant="error">
              {typeof formError === 'string' ? formError : apiErrorMessage(formError, (key) => t(key)) || t('mentee.check_in.error')}
            </Alert>
          )}
          <Button type="submit" loading={create.isPending} className="min-h-11">
            {t('mentee.check_in.submit')}
          </Button>
        </form>

        <h3 className="mt-6 text-base font-semibold text-gray-900">{t('mentee.check_in.recent')}</h3>
        {checkIns.isLoading ? (
          <p className="mt-2 text-sm text-gray-500">{t('mentee.check_in.loading')}</p>
        ) : checkIns.isError ? (
          <Alert variant="error" className="mt-2">
            {apiErrorMessage(checkIns.error, (key) => t(key)) || t('mentee.check_in.error')}
          </Alert>
        ) : (checkIns.data?.length ?? 0) === 0 ? (
          <p className="mt-2 text-sm text-gray-500">{t('mentee.check_in.empty')}</p>
        ) : (
          <ul className="mt-2 divide-y divide-gray-100">
            {checkIns.data!.map((checkIn) => (
              <li key={checkIn.id} className="py-3">
                <p className="text-sm font-medium text-gray-900">
                  {checkIn.checked_in_at ? dateFormatter.format(new Date(checkIn.checked_in_at)) : '—'}
                </p>
                <p className="mt-0.5 text-sm text-gray-600">
                  {checkIn.mood_score != null && (
                    <span className="mr-3">
                      {t('mentee.check_in.mood_value', { level: t(moodLabelKey(checkIn.mood_score)) })}
                    </span>
                  )}
                  <span>
                    {checkIn.stress_score != null
                      ? t('mentee.check_in.stress_value', { level: t(stressLabelKey(checkIn.stress_score)) })
                      : t('mentee.check_in.stress_not_set')}
                  </span>
                </p>
                {checkIn.note && (
                  <p className="mt-1 whitespace-pre-wrap text-sm text-gray-500">{checkIn.note}</p>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>
    </Card>
  );
}
