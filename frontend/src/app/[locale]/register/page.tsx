'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import { useLocale, useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { localePath } from '@/i18n/paths';
import { Badge } from '@/components/ui/badge';
import { Alert } from '@/components/ui/alert';
import { PageSpinner } from '@/components/ui/spinner';
import { ArrowRight, Brain, Building2, Sparkles, User, Users } from 'lucide-react';
import type { ClientType } from '@/lib/schemas/auth';

interface Plan {
  id: string;
  client_type: ClientType;
  name: string | Record<string, string>;
  price_monthly: number | string;
  max_sessions_per_month?: number | null;
}

const types = [
  { key: 'personal', icon: User, color: 'from-blue-500 to-cyan-500', border: 'hover:border-blue-300' },
  { key: 'familiar', icon: Users, color: 'from-emerald-500 to-green-500', border: 'hover:border-emerald-300' },
  { key: 'grupal', icon: Sparkles, color: 'from-violet-500 to-purple-500', border: 'hover:border-violet-300' },
  { key: 'empresa', icon: Building2, color: 'from-amber-500 to-orange-500', border: 'hover:border-amber-300' },
] as const;

export default function RegisterPage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const [requestedType, setRequestedType] = useState<string | null | undefined>(undefined);

  useEffect(() => {
    setRequestedType(new URLSearchParams(window.location.search).get('type'));
  }, []);

  const hasValidRequestedType = types.some(({ key }) => key === requestedType);

  useEffect(() => {
    if (hasValidRequestedType && requestedType) {
      router.replace(localePath(`/register/${requestedType}`, locale));
    }
  }, [hasValidRequestedType, locale, requestedType, router]);

  const { data: plansData, error } = useQuery<Plan[] | { data: Plan[] }>({
    queryKey: ['plans'],
    queryFn: ({ signal }) => api.get<Plan[] | { data: Plan[] }>('/plans', undefined, { signal }),
  });

  const plans: Plan[] = Array.isArray(plansData) ? plansData as Plan[] : plansData?.data ?? [];

  if (requestedType === undefined || hasValidRequestedType) return <PageSpinner />;

  return (
    <div className="min-h-screen bg-gray-50 px-4 py-10 sm:px-6 lg:px-8">
      <div className="mx-auto max-w-6xl">
        <Link href={localePath('/', locale)} className="inline-flex items-center gap-3 text-gray-900">
          <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-600 text-white">
            <Brain className="h-6 w-6" aria-hidden="true" />
          </span>
          <span className="font-bold">{t('app.name')}</span>
        </Link>

        <div className="mt-14 grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-end">
          <section>
            <span className="inline-flex items-center gap-2 rounded-full bg-primary-50 px-3 py-1 text-sm font-medium text-primary-700">
              <Sparkles className="h-4 w-4" aria-hidden="true" />
              {t('dashboard.ai_badge')}
            </span>
            <h1 className="mt-5 text-4xl font-bold tracking-tight text-gray-950 sm:text-5xl">
              {t('client_types.select_title')}
            </h1>
            <p className="mt-5 max-w-xl text-lg leading-8 text-gray-600">
              {t('auth.register_subtitle')}
            </p>
          </section>

          <p className="rounded-2xl border border-primary-100 bg-white p-5 text-sm leading-6 text-gray-600 shadow-sm">
            {t('auth.register_flow_hint')}
          </p>
        </div>

        <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {error && <Alert className="sm:col-span-2 lg:col-span-4" variant="error">{apiErrorMessage(error, (key) => t(key)) || t('common.load_error')}</Alert>}
          {types.map(({ key, icon: Icon, color, border }) => {
            const plan = lowestPlanForType(plans, key);

            return (
            <Link
              key={key}
              href={localePath(`/register/${key}`, locale)}
              className={`group rounded-2xl border bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-xl ${border}`}
            >
              <span className={`flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br ${color} text-white shadow-lg`}>
                <Icon className="h-7 w-7" aria-hidden="true" />
              </span>
              <h2 className="mt-5 text-xl font-semibold text-gray-950">{t(`client_types.${key}`)}</h2>
              <p className="mt-2 min-h-12 text-sm leading-6 text-gray-600">{t(`client_types.${key}_desc`)}</p>
              <div className="mt-5 min-h-16 rounded-2xl bg-gray-50 p-4">
                {plan ? (
                  <>
                    <Badge variant="primary">{localizedText(plan.name, locale)}</Badge>
                    <p className="mt-2 text-sm text-gray-600">
                      {t('plans.from')}{' '}
                      <span className="font-semibold text-gray-950">{formatPrice(plan.price_monthly, locale)}</span>{' '}
                      {t('plans.per_month')}
                    </p>
                    {plan.max_sessions_per_month !== null && plan.max_sessions_per_month !== undefined && (
                      <p className="mt-1 text-xs text-gray-500">
                        {plan.max_sessions_per_month} {t('plans.sessions_month')}
                      </p>
                    )}
                  </>
                ) : (
                  <p className="text-sm text-gray-500">{t('plans.no_plan_for_type')}</p>
                )}
              </div>
              <span className="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-primary-600 group-hover:text-primary-700">
                {t('auth.continue_registration')}
                <ArrowRight className="h-4 w-4 transition group-hover:translate-x-1" aria-hidden="true" />
              </span>
            </Link>
            );
          })}
        </div>

        <p className="mt-10 text-center text-sm text-gray-600">
          {t('auth.has_account')}{' '}
          <Link href={localePath('/login', locale)} className="font-semibold text-primary-600 hover:text-primary-500">
            {t('nav.login')}
          </Link>
        </p>
      </div>
    </div>
  );
}

function lowestPlanForType(plans: Plan[], clientType: ClientType): Plan | undefined {
  return plans
    .filter((plan) => plan.client_type === clientType)
    .sort((a, b) => Number(a.price_monthly) - Number(b.price_monthly))[0];
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
