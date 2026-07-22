import { cn } from '@/lib/utils';
import { AlertCircle, CheckCircle2, Info, AlertTriangle, type LucideIcon } from 'lucide-react';
import type { HTMLAttributes } from 'react';

const variants: Record<string, { icon: LucideIcon; classes: string }> = {
  error: { icon: AlertCircle, classes: 'bg-red-50 text-red-700 border-red-200' },
  success: { icon: CheckCircle2, classes: 'bg-green-50 text-green-700 border-green-200' },
  info: { icon: Info, classes: 'bg-blue-50 text-blue-700 border-blue-200' },
  warning: { icon: AlertTriangle, classes: 'bg-yellow-50 text-yellow-700 border-yellow-200' },
};

interface AlertProps extends HTMLAttributes<HTMLDivElement> {
  variant?: keyof typeof variants;
}

export function Alert({ className, variant = 'info', children, ...props }: AlertProps) {
  const { icon: Icon, classes } = variants[variant];

  return (
    <div
      className={cn('flex items-start gap-3 rounded-lg border p-4 text-sm', classes, className)}
      role="alert"
      {...props}
    >
      <Icon className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
      <div>{children}</div>
    </div>
  );
}
