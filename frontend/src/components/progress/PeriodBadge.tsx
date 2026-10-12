'use client';

import { useTranslations } from 'next-intl';
import { CalendarRange } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { resolvePeriodLabel } from '@/lib/progress';
import type { ProgressPeriod } from '@/types/progress';

/**
 * Labels the period each indicator belongs to ("Current month" / "Current
 * state" / "Next 7 days"), reusing the localized label the backend resolved and
 * falling back to the i18n key (RF-14).
 */
export function PeriodBadge({ period, className }: { period: ProgressPeriod | null | undefined; className?: string }) {
  const t = useTranslations();
  const label = resolvePeriodLabel(period, (key) => t(key));

  if (!label) return null;

  return (
    <Badge variant="default" className={className} aria-label={label}>
      <CalendarRange className="mr-1 h-3 w-3" aria-hidden="true" />
      {label}
    </Badge>
  );
}
