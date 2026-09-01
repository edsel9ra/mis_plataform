'use client';

import { useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Check, LockKeyhole, ShieldCheck, Sparkles, type LucideIcon } from 'lucide-react';
import { useAuth } from '@/components/auth/AuthProvider';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { WorkspaceFeatureGate } from '@/components/mentor-workspace/PlanAccess';
import { useActiveSubscription, usePersonalPlans, useStartSubscription } from '@/hooks/use-mentor-workspace';
import { getPlanTier } from '@/lib/mentor-workspace';
import { apiErrorMessage } from '@/lib/errors';
import type { MentorWorkspaceFeature, WorkspacePlan } from '@/types/mentor-workspace';

type PlanFilter = 'all' | 'basic' | 'premium';

const workspaceFeatures: Array<{ key: MentorWorkspaceFeature; icon: LucideIcon }> = [
  { key: 'ocean', icon: Sparkles },
  { key: 'neurocognitive', icon: ShieldCheck },
  { key: 'systemic_map', icon: ShieldCheck },
  { key: 'challenges', icon: Check },
  { key: 'feedback', icon: Sparkles },
];

export function PersonalPlansSection() {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();
  const [filter, setFilter] = useState<PlanFilter>('all');
  const enabled = user?.client_type === 'personal';
  const plansQuery = usePersonalPlans(enabled);
  const subscriptionQuery = useActiveSubscription(enabled);
  const startSubscription = useStartSubscription();
  const plans = plansQuery.data ?? [];
  const subscription = subscriptionQuery.data;
  const currentPlan = subscription?.plan ?? plans.find((plan) => plan.id === subscription?.plan_id) ?? null;
  const visiblePlans = plans.filter((plan) => filter === 'all' || getPlanTier(plan) === filter);

  async function selectPlan(planId: string) {
    try {
      await startSubscription.mutateAsync(planId);
    } catch {
      // The mutation error is rendered below.
    }
  }

  if (!enabled) return null;

  return (
    <section className="space-y-5" aria-labelledby="personal-plans-title">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p className="text-sm font-semibold uppercase tracking-[0.18em] text-primary-700">{t('mentor_workspace.personal_eyebrow')}</p>
          <h2 id="personal-plans-title" className="mt-1 text-2xl font-bold text-slate-950">{t('mentor_workspace.plans_title')}</h2>
          <p className="mt-1 max-w-2xl text-sm text-slate-500">{t('mentor_workspace.plans_subtitle')}</p>
        </div>
        <div className="flex w-full gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1 sm:w-auto" role="group" aria-label={t('mentor_workspace.plan_filter_label')}>
          {(['all', 'basic', 'premium'] as PlanFilter[]).map((value) => (
            <button
              key={value}
              type="button"
              aria-pressed={filter === value}
              onClick={() => setFilter(value)}
              className={`whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition ${filter === value ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900'}`}
            >
              {t(`mentor_workspace.plan_filter_${value}`)}
            </button>
          ))}
        </div>
      </div>

      {(plansQuery.error || subscriptionQuery.error) && (
        <Alert variant="error">
          {t('mentor_workspace.plan_load_error')}
        </Alert>
      )}

      <Card className="border-0 bg-slate-950 text-white shadow-xl shadow-slate-200">
        <div className="flex flex-col justify-between gap-5 md:flex-row md:items-center">
          <div className="flex items-start gap-4">
            <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary-500/20 text-primary-200">
              {currentPlan ? <ShieldCheck className="h-6 w-6" aria-hidden="true" /> : <LockKeyhole className="h-6 w-6" aria-hidden="true" />}
            </span>
            <div>
              <p className="text-sm text-slate-400">{t('mentor_workspace.current_plan')}</p>
              <h3 className="mt-1 text-xl font-bold">{currentPlan?.name ?? t('mentor_workspace.no_active_plan')}</h3>
              <p className="mt-1 text-sm text-slate-300">
                {currentPlan ? t('mentor_workspace.plan_access_ready') : t('mentor_workspace.plan_access_locked')}
              </p>
            </div>
          </div>
          {currentPlan && subscription?.status && (
            <Badge className="w-fit bg-emerald-400/15 text-emerald-200">{t(`mentor_workspace.status_${subscription.status}`)}</Badge>
          )}
        </div>
      </Card>

      {plansQuery.isLoading ? (
        <Card><p className="text-sm text-slate-500">{t('common.loading')}</p></Card>
      ) : visiblePlans.length === 0 ? (
        <Card><p className="text-sm text-slate-500">{t('plans.no_plan_for_type')}</p></Card>
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          {visiblePlans.map((plan) => (
            <PersonalPlanCard
              key={plan.id}
              plan={plan}
              active={plan.id === currentPlan?.id}
              locale={locale}
              canSelect={!currentPlan}
              isSelecting={startSubscription.isPending && startSubscription.variables === plan.id}
              onSelect={() => void selectPlan(plan.id)}
            />
          ))}
        </div>
      )}

      {startSubscription.error && <Alert variant="error">{apiErrorMessage(startSubscription.error, (key) => t(key))}</Alert>}

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {workspaceFeatures.map(({ key, icon: Icon }) => (
          <WorkspaceFeatureGate key={key} feature={key} plan={currentPlan} subscription={subscription} entitlements={currentPlan?.entitlements}>
            <AvailableFeatureCard feature={key} icon={Icon} />
          </WorkspaceFeatureGate>
        ))}
      </div>

      {!currentPlan && (
        <div className="rounded-2xl border border-primary-100 bg-primary-50 p-5">
          <p className="font-semibold text-primary-950">{t('mentor_workspace.choose_plan_title')}</p>
          <p className="mt-1 text-sm text-primary-800">{t('mentor_workspace.choose_plan_description')}</p>
        </div>
      )}
    </section>
  );
}

function AvailableFeatureCard({ feature, icon: Icon }: { feature: MentorWorkspaceFeature; icon: LucideIcon }) {
  const t = useTranslations();

  return (
    <Card className="border-emerald-100 bg-emerald-50/60">
      <div className="flex items-start justify-between gap-4">
        <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-emerald-700 shadow-sm">
          <Icon className="h-5 w-5" aria-hidden="true" />
        </span>
        <Badge variant="success">{t('mentor_workspace.active_plan')}</Badge>
      </div>
      <h3 className="mt-4 font-semibold text-slate-950">{t(`mentor_workspace.feature_${feature}`)}</h3>
      <p className="mt-1 text-sm leading-6 text-slate-600">{t('mentor_workspace.plan_access_ready')}</p>
    </Card>
  );
}

function PersonalPlanCard({
  plan,
  active,
  locale,
  canSelect,
  isSelecting,
  onSelect,
}: {
  plan: WorkspacePlan;
  active: boolean;
  locale: string;
  canSelect: boolean;
  isSelecting: boolean;
  onSelect: () => void;
}) {
  const t = useTranslations();
  const tier = getPlanTier(plan);
  const features = plan.features?.length ? plan.features : [
    t('mentor_workspace.feature_ocean'),
    t('mentor_workspace.feature_neurocognitive'),
  ];

  return (
    <Card className={`relative overflow-hidden transition ${active ? 'border-primary-300 ring-2 ring-primary-100' : 'border-slate-200'}`}>
      {active && <div className="absolute right-0 top-0 rounded-bl-xl bg-primary-600 px-3 py-1.5 text-xs font-bold text-white">{t('mentor_workspace.active_plan')}</div>}
      <CardHeader>
        <div className="flex items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <CardTitle>{plan.name}</CardTitle>
              {tier !== 'unknown' && <Badge variant={tier === 'premium' ? 'primary' : 'default'}>{t(`mentor_workspace.plan_tier_${tier}`)}</Badge>}
            </div>
            <p className="mt-2 text-sm text-slate-500">{plan.description || t('mentor_workspace.plan_default_description')}</p>
          </div>
          {!active && <LockKeyhole className="h-5 w-5 shrink-0 text-slate-300" aria-hidden="true" />}
        </div>
      </CardHeader>
      <p className="text-2xl font-bold text-slate-950">
        {typeof plan.price_monthly === 'number' ? `${plan.price_monthly.toLocaleString(locale, { minimumFractionDigits: 0 })}` : t('common.not_applicable')}
        <span className="ml-1 text-sm font-normal text-slate-500">{t('plans.per_month')}</span>
      </p>
      <ul className="mt-5 space-y-3">
        {features.map((feature) => (
          <li key={feature} className="flex items-start gap-2 text-sm text-slate-600">
            <Check className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true" />
            <span>{feature}</span>
          </li>
        ))}
      </ul>
      {canSelect && (
        <Button type="button" size="sm" className="mt-6 w-full" loading={isSelecting} onClick={onSelect}>
          {t('mentor_workspace.choose_plan_cta')}
        </Button>
      )}
    </Card>
  );
}
