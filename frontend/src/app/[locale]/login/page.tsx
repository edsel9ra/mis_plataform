'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { loginSchema, type LoginFormData } from '@/lib/schemas/auth';
import { useAuth } from '@/components/auth/AuthProvider';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert } from '@/components/ui/alert';
import { useState } from 'react';

export default function LoginPage() {
  const t = useTranslations();
  const { login } = useAuth();
  const router = useRouter();
  const [serverError, setServerError] = useState('');

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
  });

  const onSubmit = async (data: LoginFormData) => {
    setServerError('');
    try {
      await login(data.email, data.password);
      router.push('/dashboard');
    } catch (err) {
      setServerError(err instanceof Error ? err.message : t('common.error'));
    }
  };

  const handleOAuth = async (provider: string) => {
    setServerError('');
    try {
      const { url } = await api.get<{ url: string }>(`/auth/${provider}/redirect`);
      window.location.assign(url);
    } catch (err) {
      setServerError(err instanceof Error ? err.message : t('common.error'));
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="text-center text-2xl">{t('auth.login_title')}</CardTitle>
        </CardHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
          {serverError && (
            <Alert variant="error">{serverError}</Alert>
          )}

          <Input
            label={t('auth.email')}
            type="email"
            autoComplete="email"
            error={errors.email && t(errors.email.message!)}
            {...register('email')}
          />

          <Input
            label={t('auth.password')}
            type="password"
            autoComplete="current-password"
            error={errors.password && t(errors.password.message!)}
            {...register('password')}
          />

          <Button type="submit" loading={isSubmitting} className="w-full">
            {t('auth.login_button')}
          </Button>
        </form>

        <div className="relative my-6">
          <div className="absolute inset-0 flex items-center">
            <div className="w-full border-t" />
          </div>
          <div className="relative flex justify-center text-sm">
            <span className="bg-white px-2 text-gray-500">{t('auth.or_continue_with')}</span>
          </div>
        </div>

        <div className="grid grid-cols-3 gap-3">
          {['google', 'github', 'linkedin'].map((provider) => (
            <Button
              key={provider}
              type="button"
              variant="outline"
              onClick={() => void handleOAuth(provider)}
            >
              {provider}
            </Button>
          ))}
        </div>

        <p className="mt-6 text-center text-sm text-gray-600">
          {t('auth.no_account')}{' '}
          <Link href="/register" className="font-medium text-primary-600 hover:text-primary-500">
            {t('nav.register')}
          </Link>
        </p>
      </Card>
    </div>
  );
}
