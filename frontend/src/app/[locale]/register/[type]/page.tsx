'use client';

import { useRouter, useParams } from 'next/navigation';
import Link from 'next/link';
import { useTranslations } from 'next-intl';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { registerSchema, type RegisterFormData } from '@/lib/schemas/auth';
import { useAuth } from '@/components/auth/AuthProvider';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert } from '@/components/ui/alert';
import { useState } from 'react';

const validTypes = ['personal', 'familiar', 'grupal', 'empresa'] as const;

export default function RegisterFormPage() {
  const t = useTranslations();
  const { register: authRegister } = useAuth();
  const router = useRouter();
  const params = useParams();
  const clientType = params.type as string;
  const [serverError, setServerError] = useState('');

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<RegisterFormData>({
    resolver: zodResolver(registerSchema),
    defaultValues: { client_type: validTypes.includes(clientType as any) ? clientType as RegisterFormData['client_type'] : 'personal' },
  });

  if (!validTypes.includes(clientType as any)) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Card>
          <Alert variant="error">{t('auth.invalid_client_type')}</Alert>
        </Card>
      </div>
    );
  }

  const onSubmit = async (data: RegisterFormData) => {
    setServerError('');
    try {
      await authRegister(data);
      router.push('/onboarding/test-personalidad');
    } catch (err) {
      setServerError(err instanceof Error ? err.message : t('common.error'));
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="text-center text-2xl">
            {t('auth.register_title')} — {t(`client_types.${clientType}`)}
          </CardTitle>
        </CardHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
          {serverError && <Alert variant="error">{serverError}</Alert>}

          <div className="grid grid-cols-2 gap-4">
            <Input
              label={t('auth.name')}
              error={errors.name && t(errors.name.message!)}
              {...register('name')}
            />
            <Input
              label={t('auth.last_name')}
              error={errors.last_name && t(errors.last_name.message!)}
              {...register('last_name')}
            />
          </div>

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
            autoComplete="new-password"
            error={errors.password && t(errors.password.message!)}
            {...register('password')}
          />

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1">
              <label className="block text-sm font-medium text-gray-700">{t('auth.sex')}</label>
              <select
                className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                {...register('sex')}
              >
                <option value="">--</option>
                <option value="M">{t('auth.sex_m')}</option>
                <option value="F">{t('auth.sex_f')}</option>
                <option value="N">{t('auth.sex_n')}</option>
              </select>
              {errors.sex && (
                <p className="text-sm text-red-600">{t(errors.sex.message!)}</p>
              )}
            </div>
            <Input
              label={t('auth.birth_date')}
              type="date"
              error={errors.birth_date && t(errors.birth_date.message!)}
              {...register('birth_date')}
            />
          </div>

          <Button type="submit" loading={isSubmitting} className="w-full">
            {t('auth.register_button')}
          </Button>
        </form>

        <p className="mt-6 text-center text-sm text-gray-600">
          {t('auth.has_account')}{' '}
          <Link href="/login" className="font-medium text-primary-600 hover:text-primary-500">
            {t('nav.login')}
          </Link>
        </p>
      </Card>
    </div>
  );
}
