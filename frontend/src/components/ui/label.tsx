import { forwardRef, type LabelHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

interface LabelProps extends LabelHTMLAttributes<HTMLLabelElement> {
  required?: boolean;
}

export const Label = forwardRef<HTMLLabelElement, LabelProps>(
  ({ className, children, required, ...props }, ref) => (
    <label
      ref={ref}
      className={cn('block text-sm font-medium text-gray-700', className)}
      {...props}
    >
      {children}
      {required && <span className="ml-0.5 text-red-500">*</span>}
    </label>
  ),
);
Label.displayName = 'Label';
