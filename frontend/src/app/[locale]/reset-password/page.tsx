'use client';

import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useLocale, useTranslations } from 'next-intl';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { resetPasswordSchema, type ResetPasswordFormData } from '@/lib/schemas/auth';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert } from '@/components/ui/alert';
import { localePath } from '@/i18n/paths';
import { Suspense, useState } from 'react';
import { Brain } from 'lucide-react';

function ResetPasswordForm() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const searchParams = useSearchParams();
  const [serverError, setServerError] = useState('');
  const [done, setDone] = useState(false);

  const token = searchParams.get('token') ?? '';
  const email = searchParams.get('email') ?? '';

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ResetPasswordFormData>({
    resolver: zodResolver(resetPasswordSchema),
    reValidateMode: 'onBlur',
    defaultValues: {
      token,
      email,
      password: '',
      password_confirmation: '',
    },
  });

  const linkValid = token.length > 0 && email.length > 0;

  const onSubmit = async (data: ResetPasswordFormData) => {
    setServerError('');
    try {
      await api.post('/auth/reset-password', {
        token: data.token,
        email: data.email,
        password: data.password,
        password_confirmation: data.password_confirmation,
      });
      setDone(true);
      router.push(localePath('/login', locale));
    } catch (err) {
      setServerError(apiErrorMessage(err, (key) => t(key)) || t('common.error'));
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-8 sm:px-6 lg:px-8">
      <Card className="w-full max-w-md p-6 sm:p-8">
        <CardHeader className="mb-6 text-center">
          <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-white shadow-lg shadow-primary-100">
            <Brain className="h-8 w-8" aria-hidden="true" />
          </div>
          <CardTitle className="text-2xl">{t('auth.reset_password_title')}</CardTitle>
          <p className="mt-2 text-sm text-gray-500">{t('auth.reset_password_subtitle')}</p>
        </CardHeader>

        {done ? (
          <Alert variant="success">{t('auth.reset_password_success')}</Alert>
        ) : !linkValid ? (
          <div className="space-y-5">
            <Alert variant="error">{t('auth.reset_password_invalid_link')}</Alert>
            <p className="text-center text-sm text-gray-600">
              <Link href={localePath('/forgot-password', locale)} className="font-semibold text-primary-600 hover:text-primary-500">
                {t('auth.forgot_password')}
              </Link>
            </p>
          </div>
        ) : (
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
            {serverError && <Alert variant="error">{serverError}</Alert>}

            <input type="hidden" {...register('token')} />

            <Input
              label={t('auth.email')}
              type="email"
              autoComplete="email"
              error={errors.email && t(errors.email.message!)}
              {...register('email')}
            />

            <Input
              label={t('auth.new_password')}
              type="password"
              autoComplete="new-password"
              error={errors.password && t(errors.password.message!)}
              {...register('password')}
            />

            <Input
              label={t('auth.confirm_password')}
              type="password"
              autoComplete="new-password"
              error={errors.password_confirmation && t(errors.password_confirmation.message!)}
              {...register('password_confirmation')}
            />

            <Button type="submit" loading={isSubmitting} className="w-full py-3">
              {t('auth.reset_password_button')}
            </Button>

            <p className="text-center text-sm text-gray-600">
              <Link href={localePath('/login', locale)} className="font-semibold text-primary-600 hover:text-primary-500">
                {t('auth.back_to_login')}
              </Link>
            </p>
          </form>
        )}
      </Card>
    </div>
  );
}

export default function ResetPasswordPage() {
  return (
    <Suspense>
      <ResetPasswordForm />
    </Suspense>
  );
}
