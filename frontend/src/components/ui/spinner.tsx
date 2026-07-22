import { cn } from '@/lib/utils';
import { Loader2 } from 'lucide-react';

interface SpinnerProps {
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

const sizes = { sm: 'h-4 w-4', md: 'h-8 w-8', lg: 'h-12 w-12' };

export function Spinner({ size = 'md', className }: SpinnerProps) {
  return (
    <Loader2
      className={cn('animate-spin text-primary-600', sizes[size], className)}
      aria-hidden="true"
    />
  );
}

export function PageSpinner() {
  return (
    <div className="flex min-h-[50vh] items-center justify-center" role="status">
      <Spinner size="lg" />
      <span className="sr-only">Loading...</span>
    </div>
  );
}
