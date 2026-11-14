import { useId, type InputHTMLAttributes, type Ref } from 'react';
import { AlertCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  /** When false the field error keeps no live role (the form summary announces). */
  announceError?: boolean;
  ref?: Ref<HTMLInputElement>;
}

export function Input({ className, label, error, announceError = true, id, ref, 'aria-describedby': describedBy, 'aria-invalid': ariaInvalid, ...props }: InputProps) {
  const generatedId = useId();
  const inputId = id ?? label?.toLowerCase().replace(/\s+/g, '-') ?? generatedId;
  const errorId = `${inputId}-error`;

  return (
    <div className="space-y-1">
      {label && (
        <label htmlFor={inputId} className="block text-sm font-medium text-gray-700">
          {label}
        </label>
      )}
      <input
        ref={ref}
        id={inputId}
        className={cn(
          'block w-full rounded-lg border bg-white px-3 py-2 text-gray-900 shadow-sm transition-colors',
          'focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500',
          'placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500',
          error
            ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
            : 'border-gray-300',
          className,
        )}
        aria-invalid={error ? (ariaInvalid ?? true) : ariaInvalid}
        aria-describedby={error ? cn(errorId, describedBy) : describedBy}
        {...props}
      />
      {error && (
        <p id={errorId} className="flex items-center gap-1 text-sm text-red-600" role={announceError ? 'alert' : undefined}>
          <AlertCircle className="h-4 w-4 shrink-0" aria-hidden="true" />
          {error}
        </p>
      )}
    </div>
  );
}
