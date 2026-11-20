import { type HTMLAttributes, type Ref } from 'react';
import { cn } from '@/lib/utils';

interface CardProps extends HTMLAttributes<HTMLDivElement> {
  padding?: 'sm' | 'md' | 'lg';
  ref?: Ref<HTMLDivElement>;
}

export function Card({ className, padding = 'md', children, ref, ...props }: CardProps) {
  return (
    <div
      ref={ref}
      className={cn(
        'rounded-xl border bg-white shadow-sm',
        {
          'p-4': padding === 'sm',
          'p-6': padding === 'md',
          'p-8': padding === 'lg',
        },
        className,
      )}
      {...props}
    >
      {children}
    </div>
  );
}

export function CardHeader({ className, children, ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div className={cn('mb-4', className)} {...props}>
      {children}
    </div>
  );
}

interface CardTitleProps extends HTMLAttributes<HTMLHeadingElement> {
  as?: 'h1' | 'h2' | 'h3' | 'h4';
}

export function CardTitle({ as: Tag = 'h3', className, children, ...props }: CardTitleProps) {
  return (
    <Tag className={cn('text-lg font-semibold text-gray-900', className)} {...props}>
      {children}
    </Tag>
  );
}
