'use client';

import { useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslations } from 'next-intl';
import { ApiError } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { validationMessage } from '@/lib/validation-message';
import { canonicalTimeZone, profileTimeZoneOptions } from '@/lib/time-zones';
import {
  profileDetailsSchema,
  profileServerFieldErrors,
  toProfileDetailsPayload,
  type ProfileDetailsFormValues,
} from '@/lib/schemas/profile';
import { useUpdateProfileDetails } from '@/hooks/use-profile';
import { useAuth } from '@/components/auth/AuthProvider';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';

interface ProfileDetailsFormProps {
  initialValues: { name: string; last_name: string | null; timezone: string | null };
}

const SERVER_FIELDS: (keyof ProfileDetailsFormValues)[] = ['name', 'last_name', 'timezone'];

export function ProfileDetailsForm({ initialValues }: ProfileDetailsFormProps) {
  const t = useTranslations();
  const { refreshUser } = useAuth();
  const update = useUpdateProfileDetails();
  const [feedback, setFeedback] = useState('');
  const [formError, setFormError] = useState<unknown>(null);
  const timeZoneOptions = useMemo(() => profileTimeZoneOptions(initialValues.timezone), [initialValues.timezone]);

  const form = useForm<ProfileDetailsFormValues>({
    resolver: zodResolver(profileDetailsSchema),
    defaultValues: {
      name: initialValues.name ?? '',
      last_name: initialValues.last_name ?? '',
      timezone: canonicalTimeZone(initialValues.timezone ?? 'UTC'),
    },
  });
  const errors = form.formState.errors;

  async function submit(values: ProfileDetailsFormValues) {
    setFeedback('');
    setFormError(null);

    try {
      await update.mutateAsync(toProfileDetailsPayload(values));
      await refreshUser();
      form.reset({ name: values.name, last_name: values.last_name, timezone: values.timezone });
      setFeedback(t('profile.details.saved'));
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        const mapped = profileServerFieldErrors(error.errors ?? {});
        let focused = false;
        for (const field of SERVER_FIELDS) {
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

  function clearServerField(field: keyof ProfileDetailsFormValues) {
    if (form.formState.errors[field]?.type === 'server') {
      form.clearErrors(field);
    }
  }

  return (
    <Card className="border-0 shadow-sm">
      <h2 className="text-lg font-semibold text-gray-950">{t('profile.details.title')}</h2>
      <p className="mt-1 text-sm text-gray-500">{t('profile.details.description')}</p>
      <div role="status" className="sr-only">{feedback}</div>
      <form onSubmit={(event) => void form.handleSubmit(submit)(event)} className="mt-4 space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <Input
            label={t('profile.details.name')}
            autoComplete="given-name"
            error={validationMessage(errors.name?.message, t)}
            {...form.register('name', { onChange: () => clearServerField('name') })}
          />
          <Input
            label={t('profile.details.last_name')}
            autoComplete="family-name"
            error={validationMessage(errors.last_name?.message, t)}
            {...form.register('last_name', { onChange: () => clearServerField('last_name') })}
          />
        </div>
        <div className="max-w-sm">
          <Select
            label={t('profile.details.timezone')}
            options={timeZoneOptions}
            error={validationMessage(errors.timezone?.message, t)}
            {...form.register('timezone', { onChange: () => clearServerField('timezone') })}
          />
        </div>
        {Boolean(formError) && (
          <Alert variant="error">
            {typeof formError === 'string' ? formError : apiErrorMessage(formError, (key) => t(key)) || t('profile.details.error')}
          </Alert>
        )}
        <Button type="submit" loading={update.isPending} className="min-h-11">
          {t('profile.details.submit')}
        </Button>
      </form>
    </Card>
  );
}
