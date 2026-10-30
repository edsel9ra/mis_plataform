import type { BadgeProps } from '@/components/ui/badge';

type StatusVariant = NonNullable<BadgeProps['variant']>;

/** Textual variants for the persisted statuses of spec 006 (CA-4.2 padre). */
export function sessionStatusVariant(status: string): StatusVariant {
  if (status === 'scheduled') return 'primary';
  if (status === 'in_progress') return 'warning';
  if (status === 'completed') return 'success';
  if (status === 'canceled') return 'danger';
  if (status === 'not_completed') return 'danger';

  return 'default';
}

/** Translates a persisted textual status; never derives it from time (006). */
export function sessionStatusLabel(
  status: string,
  translate: LabelScope,
): string {
  const labels: Record<string, string> = {
    scheduled: translate('session_statuses.scheduled'),
    in_progress: translate('session_statuses.in_progress'),
    completed: translate('session_statuses.completed'),
    canceled: translate('session_statuses.canceled'),
    cancelled: translate('session_statuses.cancelled'),
    not_completed: translate('session_statuses.not_completed'),
  };

  return labels[status] ?? translate('common.not_applicable');
}

type LabelScope = (key: string) => string;
