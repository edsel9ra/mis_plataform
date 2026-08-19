'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useLocale, useTranslations } from 'next-intl';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { loginSchema, type LoginFormData } from '@/lib/schemas/auth';
import { useAuth } from '@/components/auth/AuthProvider';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert } from '@/components/ui/alert';
import { localePath } from '@/i18n/paths';
import { useState } from 'react';
import { Brain, ShieldCheck, Sparkles } from 'lucide-react';

const oauthProviders = ['google', 'github', 'linkedin'] as const;
const platformAdminRoles = new Set(['super_admin', 'admin']);

export default function LoginPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { login } = useAuth();
  const router = useRouter();
  const [serverError, setServerError] = useState('');

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
    reValidateMode: 'onBlur',
    defaultValues: {
      email: '',
      password: '',
    },
  });

  const onSubmit = async (data: LoginFormData) => {
    setServerError('');
    try {
      const user = await login(data.email, data.password);
      router.push(localePath(platformAdminRoles.has(user.role) ? '/admin' : '/dashboard', locale));
    } catch (err) {
      setServerError(err instanceof Error ? err.message : t('common.error'));
    }
  };

  const handleOAuth = async (provider: string) => {
    setServerError('');
    try {
      const { url } = await api.get<{ url: string }>(`/auth/${provider}/redirect`, { locale });
      window.location.assign(url);
    } catch (err) {
      setServerError(err instanceof Error ? err.message : t('common.error'));
    }
  };

  return (
    <div className="min-h-screen overflow-hidden bg-gray-950 px-4 py-8 text-white sm:px-6 lg:px-8">
      <div className="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(37,99,235,0.28),_transparent_34%),radial-gradient(circle_at_bottom_right,_rgba(124,58,237,0.28),_transparent_30%)]" />
      <div className="relative mx-auto flex min-h-[calc(100vh-4rem)] max-w-6xl items-center">
        <div className="grid w-full gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
          <section className="hidden lg:block">
            <Link href={localePath('/', locale)} className="inline-flex items-center gap-3">
              <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-600 shadow-lg shadow-primary-950/40">
                <Brain className="h-7 w-7" aria-hidden="true" />
              </span>
              <span className="text-lg font-bold">{t('app.name')}</span>
            </Link>

            <div className="mt-16 max-w-xl">
              <span className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 text-sm text-primary-100 backdrop-blur">
                <Sparkles className="h-4 w-4" aria-hidden="true" />
                {t('dashboard.ai_badge')}
              </span>
              <h1 className="mt-6 text-5xl font-bold tracking-tight text-white">
                {t('app.tagline')}
              </h1>
              <p className="mt-6 text-lg leading-8 text-gray-300">
                {t('auth.login_subtitle')}
              </p>
            </div>

            <div className="mt-10 grid max-w-xl gap-4 sm:grid-cols-2">
              <div className="rounded-2xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <ShieldCheck className="h-6 w-6 text-primary-300" aria-hidden="true" />
                <p className="mt-3 text-sm font-medium text-white">{t('auth.secure_access')}</p>
                <p className="mt-1 text-sm text-gray-300">{t('auth.secure_access_desc')}</p>
              </div>
              <div className="rounded-2xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <Brain className="h-6 w-6 text-violet-300" aria-hidden="true" />
                <p className="mt-3 text-sm font-medium text-white">{t('personality.test_title')}</p>
                <p className="mt-1 text-sm text-gray-300">{t('personality.test_desc')}</p>
              </div>
            </div>
          </section>

          <Card className="w-full border-white/10 bg-white p-6 shadow-2xl shadow-black/30 sm:p-8">
            <CardHeader className="mb-6 text-center">
              <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-white shadow-lg shadow-primary-100">
                <Brain className="h-8 w-8" aria-hidden="true" />
              </div>
              <CardTitle className="text-2xl">{t('auth.login_title')}</CardTitle>
              <p className="mt-2 text-sm text-gray-500">{t('auth.login_subtitle')}</p>
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

              <Button type="submit" loading={isSubmitting} className="w-full py-3">
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
              {oauthProviders.map((provider) => (
                <Button
                  key={provider}
                  type="button"
                  variant="outline"
                  onClick={() => void handleOAuth(provider)}
                  className="capitalize"
                >
                  {provider}
                </Button>
              ))}
            </div>

            <p className="mt-6 text-center text-sm text-gray-600">
              {t('auth.no_account')}{' '}
              <Link href={localePath('/register', locale)} className="font-semibold text-primary-600 hover:text-primary-500">
                {t('nav.register')}
              </Link>
            </p>
          </Card>
        </div>
      </div>
    </div>
  );
}
