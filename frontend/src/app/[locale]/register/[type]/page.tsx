'use client';

import { useRouter, useParams } from 'next/navigation';
import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { clientTypeOptions, registerSchema, type RegisterFormData } from '@/lib/schemas/auth';
import { useAuth } from '@/components/auth/AuthProvider';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert } from '@/components/ui/alert';
import { localePath } from '@/i18n/paths';
import { ArrowLeft, Brain, Building2, CheckCircle2, Sparkles, User, Users } from 'lucide-react';

const validTypes = clientTypeOptions;
const personalEmailDomains = new Set([
  'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com',
  'icloud.com', 'msn.com', 'aol.com', 'protonmail.com', 'zoho.com',
  'ymail.com', 'mail.com', 'gmx.com', 'me.com', 'mac.com', 'yahoo.es',
  'hotmail.es', 'gmail.es', 'terra.com', 'telefonica.net',
]);

const clientTypeDetails = {
  personal: { icon: User, color: 'from-blue-500 to-cyan-500' },
  familiar: { icon: Users, color: 'from-emerald-500 to-green-500' },
  grupal: { icon: Sparkles, color: 'from-violet-500 to-purple-500' },
  empresa: { icon: Building2, color: 'from-amber-500 to-orange-500' },
} as const;

interface Plan {
  id: string;
  client_type: RegisterFormData['client_type'];
  name: string | Record<string, string>;
  description?: string | Record<string, string> | null;
  features?: string[] | null;
  price_monthly: number | string;
  max_sessions_per_month?: number | null;
  max_members?: number | null;
  max_mentors?: number | null;
}

const emptyPlans: Plan[] = [];

function isClientType(value: string): value is RegisterFormData['client_type'] {
  return validTypes.includes(value as RegisterFormData['client_type']);
}

function detectEmailType(email: string): 'personal' | 'corporate' | null {
  const domain = email.split('@')[1]?.toLowerCase();
  if (!domain) return null;

  return personalEmailDomains.has(domain) ? 'personal' : 'corporate';
}

export default function RegisterFormPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { register: authRegister } = useAuth();
  const router = useRouter();
  const params = useParams<{ type?: string | string[] }>();
  const clientType = Array.isArray(params.type) ? params.type[0] : params.type ?? '';
  const normalizedClientType = isClientType(clientType) ? clientType : 'personal';
  const [serverError, setServerError] = useState('');

  const {
    register,
    handleSubmit,
    control,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<RegisterFormData>({
    resolver: zodResolver(registerSchema),
    reValidateMode: 'onBlur',
    defaultValues: {
      name: '',
      last_name: '',
      email: '',
      password: '',
      sex: 'N',
      birth_date: '',
      client_type: normalizedClientType,
      plan_id: '',
      locale: locale as RegisterFormData['locale'],
    },
  });

  const { data: plansData, isLoading: plansLoading } = useQuery<Plan[]>({
    queryKey: ['plans', normalizedClientType],
    queryFn: () => api.get<Plan[]>('/plans', { client_type: normalizedClientType }),
    enabled: isClientType(clientType),
  });

  const emailValue = useWatch({ control, name: 'email', defaultValue: '' });
  const selectedPlanId = useWatch({ control, name: 'plan_id', defaultValue: '' });
  const emailType = detectEmailType(emailValue ?? '');
  const selectedDetails = clientTypeDetails[normalizedClientType];
  const SelectedIcon = selectedDetails.icon;
  const plans = plansData ?? emptyPlans;

  useEffect(() => {
    if (!selectedPlanId && plans.length > 0) {
      setValue('plan_id', plans[0].id);
    }
  }, [plans, selectedPlanId, setValue]);

  if (!isClientType(clientType)) {
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
      router.push(localePath('/onboarding/test-personalidad', locale));
    } catch (err) {
      setServerError(err instanceof Error ? err.message : t('common.error'));
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 px-4 py-10 sm:px-6 lg:px-8">
      <div className="mx-auto max-w-6xl">
        <Link href={localePath('/register', locale)} className="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900">
          <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          {t('auth.change_client_type')}
        </Link>

        <div className="mt-8 grid gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-start">
          <aside className="rounded-3xl bg-gray-950 p-8 text-white shadow-2xl shadow-gray-200">
            <div className={`flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br ${selectedDetails.color} shadow-lg`}>
              <SelectedIcon className="h-9 w-9" aria-hidden="true" />
            </div>
            <h1 className="mt-8 text-4xl font-bold tracking-tight">
              {t('auth.register_title')}
            </h1>
            <p className="mt-4 text-lg text-gray-300">
              {t(`client_types.${normalizedClientType}`)} · {t(`client_types.${normalizedClientType}_desc`)}
            </p>

            <div className="mt-8 space-y-4">
              {[
                t('auth.register_step_profile'),
                t('auth.register_step_personality'),
                t('auth.register_step_recommendations'),
              ].map((step) => (
                <div key={step} className="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/10 p-4">
                  <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-primary-300" aria-hidden="true" />
                  <span className="text-sm text-gray-100">{step}</span>
                </div>
              ))}
            </div>
          </aside>

          <Card className="border-0 p-6 shadow-xl shadow-gray-200 sm:p-8">
            <CardHeader className="mb-6">
              <div className="flex items-center gap-3">
                <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                  <Brain className="h-6 w-6" aria-hidden="true" />
                </span>
                <div>
                  <CardTitle>{t('auth.basic_information')}</CardTitle>
                  <p className="mt-1 text-sm text-gray-500">{t('auth.register_subtitle')}</p>
                </div>
              </div>
            </CardHeader>

            <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
              <input type="hidden" {...register('client_type')} />
              <input type="hidden" {...register('locale')} />

              {serverError && <Alert variant="error">{serverError}</Alert>}

              <div className="grid gap-4 sm:grid-cols-2">
                <Input
                  label={t('auth.name')}
                  autoComplete="given-name"
                  error={errors.name && t(errors.name.message!)}
                  {...register('name')}
                />
                <Input
                  label={t('auth.last_name')}
                  autoComplete="family-name"
                  error={errors.last_name && t(errors.last_name.message!)}
                  {...register('last_name')}
                />
              </div>

              <div className="space-y-3">
                <Input
                  label={t('auth.email')}
                  type="email"
                  autoComplete="email"
                  error={errors.email && t(errors.email.message!)}
                  {...register('email')}
                />

                {emailType && (
                  <div className="flex items-start gap-3 rounded-xl border border-primary-100 bg-primary-50 p-4 text-sm text-primary-800">
                    {emailType === 'corporate' ? (
                      <Building2 className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                    ) : (
                      <User className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                    )}
                    <div>
                      <p className="font-semibold">
                        {emailType === 'corporate' ? t('auth.email_corporate_detected') : t('auth.email_personal_detected')}
                      </p>
                      <p className="mt-1 text-primary-700">
                        {emailType === 'corporate' ? t('auth.email_corporate_hint') : t('auth.email_personal_hint')}
                      </p>
                    </div>
                  </div>
                )}
              </div>

              <Input
                label={t('auth.password')}
                type="password"
                autoComplete="new-password"
                error={errors.password && t(errors.password.message!)}
                {...register('password')}
              />

              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1">
                  <Select
                    label={t('auth.sex')}
                    placeholder="--"
                    options={[
                      { value: 'M', label: t('auth.sex_m') },
                      { value: 'F', label: t('auth.sex_f') },
                      { value: 'N', label: t('auth.sex_n') },
                    ]}
                    error={errors.sex && t(errors.sex.message!)}
                    {...register('sex')}
                  />
                </div>
                <Input
                  label={t('auth.birth_date')}
                  type="date"
                  error={errors.birth_date && t(errors.birth_date.message!)}
                  {...register('birth_date')}
                />
              </div>

              <fieldset className="space-y-3">
                <legend className="text-sm font-semibold text-gray-900">{t('plans.select_title')}</legend>
                {plansLoading && <p className="text-sm text-gray-500">{t('common.loading')}</p>}
                {!plansLoading && plans.length === 0 && (
                  <p className="rounded-xl border border-dashed border-gray-300 p-4 text-sm text-gray-500">
                    {t('plans.no_plan_for_type')}
                  </p>
                )}
                <div className="grid gap-3 sm:grid-cols-2">
                  {plans.map((plan) => {
                    const isSelected = selectedPlanId === plan.id;

                    return (
                      <label
                        key={plan.id}
                        className={`cursor-pointer rounded-2xl border p-4 transition ${
                          isSelected ? 'border-primary-500 bg-primary-50 shadow-sm' : 'border-gray-200 bg-white hover:border-primary-200'
                        }`}
                      >
                        <input type="radio" value={plan.id} className="sr-only" {...register('plan_id')} />
                        <span className="flex items-start justify-between gap-3">
                          <span>
                            <span className="block font-semibold text-gray-950">{localizedText(plan.name, locale)}</span>
                            {plan.description && (
                              <span className="mt-1 block text-sm leading-6 text-gray-500">{localizedText(plan.description, locale)}</span>
                            )}
                          </span>
                          <span className="rounded-full bg-white px-3 py-1 text-sm font-semibold text-primary-700 shadow-sm">
                            {formatPrice(plan.price_monthly, locale)}
                          </span>
                        </span>
                        <span className="mt-3 flex flex-wrap gap-2 text-xs text-gray-600">
                          {plan.max_sessions_per_month !== null && plan.max_sessions_per_month !== undefined && (
                            <span className="rounded-full bg-gray-100 px-2.5 py-1">{plan.max_sessions_per_month} {t('plans.sessions_month')}</span>
                          )}
                          {plan.max_members !== null && plan.max_members !== undefined && (
                            <span className="rounded-full bg-gray-100 px-2.5 py-1">{plan.max_members} {t('plans.members')}</span>
                          )}
                          {plan.max_mentors !== null && plan.max_mentors !== undefined && (
                            <span className="rounded-full bg-gray-100 px-2.5 py-1">{plan.max_mentors} {t('plans.mentors')}</span>
                          )}
                        </span>
                        {(plan.features ?? []).length > 0 && (
                          <ul className="mt-3 space-y-1 text-sm text-gray-600">
                            {(plan.features ?? []).slice(0, 3).map((feature) => (
                              <li key={feature} className="flex items-start gap-2">
                                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true" />
                                {feature}
                              </li>
                            ))}
                          </ul>
                        )}
                      </label>
                    );
                  })}
                </div>
                <p className="text-xs text-gray-500">{t('plans.trial_hint')}</p>
              </fieldset>

              <Button type="submit" loading={isSubmitting} className="w-full py-3">
                {t('auth.register_button')}
              </Button>
            </form>

            <p className="mt-6 text-center text-sm text-gray-600">
              {t('auth.has_account')}{' '}
              <Link href={localePath('/login', locale)} className="font-semibold text-primary-600 hover:text-primary-500">
                {t('nav.login')}
              </Link>
            </p>
          </Card>
        </div>
      </div>
    </div>
  );
}

function localizedText(value: string | Record<string, string>, locale: string): string {
  if (typeof value === 'string') return value;

  return value[locale] ?? value.es ?? value.en ?? value.pt ?? '';
}

function formatPrice(value: number | string, locale: string): string {
  const amount = Number(value);
  if (!Number.isFinite(amount)) return String(value);

  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: amount % 1 === 0 ? 0 : 2,
  }).format(amount);
}
