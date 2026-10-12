import { type LabelHTMLAttributes, type Ref } from 'react';
import { cn } from '@/lib/utils';

interface LabelProps extends LabelHTMLAttributes<HTMLLabelElement> {
  required?: boolean;
  ref?: Ref<HTMLLabelElement>;
}

export function Label({ className, children, required, ref, ...props }: LabelProps) {
  return (
    <label
      ref={ref}
      className={cn('block text-sm font-medium text-gray-700', className)}
      {...props}
    >
      {children}
      {required && <span className="ml-0.5 text-red-500">*</span>}
    </label>
  );
}
