'use client';

import { useId, type InputHTMLAttributes, type Ref } from 'react';
import { Upload } from 'lucide-react';
import { cn } from '@/lib/utils';

interface FileUploadFieldProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string;
  hint?: string;
  error?: string;
  ref?: Ref<HTMLInputElement>;
}

export function FileUploadField({ label, hint, error, className, id, ref, ...props }: FileUploadFieldProps) {
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const errorId = `${inputId}-error`;

  return (
    <div className="space-y-1">
      <label htmlFor={inputId} className="block text-sm font-medium text-gray-700">{label}</label>
      <div className="relative rounded-xl focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2">
        <input
          ref={ref}
          {...props}
          type="file"
          id={inputId}
          aria-label={label}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? errorId : undefined}
          className="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
        />
        <div
          aria-hidden="true"
          className={cn(
            'pointer-events-none flex w-full items-center gap-3 rounded-xl border border-dashed bg-slate-50 px-4 py-4 text-left text-sm text-slate-600 transition hover:border-primary-400 hover:bg-primary-50/40',
            error && 'border-red-300',
            props.disabled && 'cursor-not-allowed opacity-60',
            className,
          )}
        >
          <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-primary-600 shadow-sm">
            <Upload className="h-4 w-4" aria-hidden="true" />
          </span>
          <span className="min-w-0">
            <span className="block font-medium text-slate-800">{hint}</span>
            <span className="mt-0.5 block truncate text-xs text-slate-500">{props.accept || 'Any supported file'}</span>
          </span>
        </div>
      </div>
      {error && <p id={errorId} className="text-sm text-red-600" role="alert">{error}</p>}
    </div>
  );
}
