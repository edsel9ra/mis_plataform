'use client';

import type { ReactNode } from 'react';
import { Card, CardTitle } from '@/components/ui/card';
import { PeriodBadge } from './PeriodBadge';
import type { ProgressPeriod } from '@/types/progress';

/** Shared block/card chrome: title, optional period badge and header action. */
export function ProgressCard({
  title,
  period,
  action,
  children,
  className,
}: {
  title: string;
  period?: ProgressPeriod | null;
  action?: ReactNode;
  children: ReactNode;
  className?: string;
}) {
  return (
    <Card className={className}>
      <div className="mb-4 flex items-start justify-between gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <CardTitle className="text-base">{title}</CardTitle>
          {period ? <PeriodBadge period={period} /> : null}
        </div>
        {action}
      </div>
      {children}
    </Card>
  );
}
