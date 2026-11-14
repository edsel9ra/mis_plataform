import { AlertCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface FormErrorItem {
  fieldId: string;
  message: string;
}

interface FormErrorSummaryProps {
  title: string;
  errors: FormErrorItem[];
  className?: string;
}

/**
 * Announced form-level error summary (spec 010, RF-010.3): the live region
 * wrapper is always mounted so late error insertions are announced by
 * assistive technology; a visible list links each error to its field.
 */
export function FormErrorSummary({ title, errors, className }: FormErrorSummaryProps) {
  return (
    <div role="status" aria-live="polite" aria-atomic="true">
      {errors.length > 0 && (
        <div
          className={cn(
            'rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700',
            className,
          )}
        >
          <div className="flex items-center gap-2 font-medium">
            <AlertCircle className="h-4 w-4 shrink-0" aria-hidden="true" />
            {title}
          </div>
          <ul className="mt-2 list-inside list-disc space-y-1">
            {errors.map(({ fieldId, message }) => (
              <li key={fieldId}>
                <a
                  href={`#${fieldId}`}
                  className="underline underline-offset-2 hover:text-red-800"
                  onClick={(event) => {
                    event.preventDefault();
                    document.getElementById(fieldId)?.focus();
                  }}
                >
                  {message}
                </a>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
