'use client';

import type { ReactNode } from 'react';
import { LockKeyhole, ShieldCheck } from 'lucide-react';
import { useTranslations } from 'next-intl';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { canAccessWorkspaceFeature, formatPlanName } from '@/lib/mentor-workspace';
import type {
  ActiveSubscription,
  MentorWorkspaceFeature,
  WorkspaceEntitlements,
  WorkspacePlan,
} from '@/types/mentor-workspace';

interface WorkspaceFeatureGateProps {
  feature: MentorWorkspaceFeature;
  plan?: WorkspacePlan | null;
  subscription?: ActiveSubscription | null;
  entitlements?: WorkspaceEntitlements | null;
  locked?: boolean;
  children: ReactNode;
  className?: string;
}

export function WorkspaceFeatureGate({
  feature,
  plan,
  subscription,
  entitlements,
  locked = false,
  children,
  className,
}: WorkspaceFeatureGateProps) {
  if (!locked && canAccessWorkspaceFeature(feature, plan, entitlements, subscription)) return <>{children}</>;

  return <LockedFeatureCard feature={feature} plan={plan} className={className} />;
}

export function LockedFeatureCard({
  feature,
  plan,
  className,
}: {
  feature: MentorWorkspaceFeature;
  plan?: WorkspacePlan | null;
  className?: string;
}) {
  const t = useTranslations();
  const featureTitle = t(`mentor_workspace.feature_${feature}`);

  return (
    <Card className={cn('border-dashed border-slate-300 bg-slate-50/80', className)}>
      <div className="flex items-start gap-4">
        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-200 text-slate-500">
          <LockKeyhole className="h-5 w-5" aria-hidden="true" />
        </span>
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="font-semibold text-slate-900">{featureTitle}</h3>
            <Badge variant="default">{t('mentor_workspace.locked_badge')}</Badge>
          </div>
          <p className="mt-2 text-sm leading-6 text-slate-600">
            {t('mentor_workspace.locked_description', { feature: featureTitle })}
          </p>
          <p className="mt-3 flex items-center gap-2 text-xs font-medium text-slate-500">
            <ShieldCheck className="h-4 w-4" aria-hidden="true" />
            {plan ? t('mentor_workspace.plan_not_entitled', { plan: formatPlanName(plan) }) : t('mentor_workspace.active_plan_required')}
          </p>
        </div>
      </div>
    </Card>
  );
}
