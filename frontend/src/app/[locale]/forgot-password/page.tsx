'use client';

import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { forgotPasswordSchema, type ForgotPasswordFormData } from '@/lib/schemas/auth';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert } from '@/components/ui/alert';
import { localePath } from '@/i18n/paths';
import { useState } from 'react';
import { Brain } from 'lucide-react';

export default function ForgotPasswordPage() {
  const t = useTranslations();
  const locale = useLocale();
  const [serverError, setServerError] = useState('');
  const [sent, setSent] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ForgotPasswordFormData>({
    resolver: zodResolver(forgotPasswordSchema),
    reValidateMode: 'onBlur',
    defaultValues: { email: '' },
  });

  const onSubmit = async (data: ForgotPasswordFormData) => {
    setServerError('');
    try {
      await api.post('/auth/forgot-password', { email: data.email });
      setSent(true);
    } catch (err) {
      setServerError(apiErrorMessage(err, (key) => t(key)) || t('common.error'));
    }
  };

  return (
    <main className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-8 sm:px-6 lg:px-8">
      <Card className="w-full max-w-md p-6 sm:p-8">
        <CardHeader className="mb-6 text-center">
          <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-white shadow-lg shadow-primary-100">
            <Brain className="h-8 w-8" aria-hidden="true" />
          </div>
          <CardTitle as="h1" className="text-2xl">{t('auth.forgot_password_title')}</CardTitle>
          <p className="mt-2 text-sm text-gray-500">{t('auth.forgot_password_subtitle')}</p>
        </CardHeader>

        {sent ? (
          <div className="space-y-5">
            <Alert variant="success">{t('auth.forgot_password_sent')}</Alert>
            <p className="text-center text-sm text-gray-600">
              <Link href={localePath('/login', locale)} className="font-semibold text-primary-600 hover:text-primary-500">
                {t('auth.back_to_login')}
              </Link>
            </p>
          </div>
        ) : (
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
            {serverError && <Alert variant="error">{serverError}</Alert>}

            <Input
              label={t('auth.email')}
              type="email"
              autoComplete="email"
              error={errors.email && t(errors.email.message!)}
              {...register('email')}
            />

            <Button type="submit" loading={isSubmitting} className="w-full py-3">
              {t('auth.forgot_password_button')}
            </Button>

            <p className="text-center text-sm text-gray-600">
              <Link href={localePath('/login', locale)} className="font-semibold text-primary-600 hover:text-primary-500">
                {t('auth.back_to_login')}
              </Link>
            </p>
          </form>
        )}
      </Card>
    </main>
  );
}
