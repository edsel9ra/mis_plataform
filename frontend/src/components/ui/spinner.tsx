'use client';

import { useTranslations } from 'next-intl';
import { cn } from '@/lib/utils';
import { ThinkingOrb } from '@/components/ui/thinking-orb';

interface SpinnerProps {
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

export function Spinner({ size = 'md', className }: SpinnerProps) {
  return (
    <ThinkingOrb size={size} className={cn('align-middle', className)} decorative />
  );
}

export function PageSpinner() {
  const t = useTranslations();

  return (
    <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4" role="status">
      <ThinkingOrb size="xl" decorative />
      <p className="text-sm font-medium text-slate-500">{t('common.loading')}</p>
    </div>
  );
}
