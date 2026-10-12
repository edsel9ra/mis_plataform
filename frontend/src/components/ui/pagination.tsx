import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { PaginationMeta } from '@/lib/pagination';

interface PaginationProps {
  meta: PaginationMeta;
  page: number;
  onPageChange: (page: number) => void;
  isLoading?: boolean;
  ariaLabel: string;
  previousLabel: string;
  nextLabel: string;
  pageLabel: string;
  ofLabel: string;
}

export function PaginationControls({
  meta,
  page,
  onPageChange,
  isLoading = false,
  ariaLabel,
  previousLabel,
  nextLabel,
  pageLabel,
  ofLabel,
}: PaginationProps) {
  if (meta.last_page <= 1) return null;

  const currentPage = Math.min(Math.max(page, 1), meta.last_page);

  return (
    <nav
      className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3"
      aria-label={ariaLabel}
      aria-busy={isLoading}
    >
      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={currentPage <= 1 || isLoading}
        onClick={() => onPageChange(currentPage - 1)}
        aria-label={previousLabel}
      >
        <ChevronLeft className="h-4 w-4" aria-hidden="true" />
        <span className="hidden sm:inline">{previousLabel}</span>
      </Button>
      <span className="text-center text-sm text-slate-600" aria-live="polite">
        {pageLabel} {currentPage} {ofLabel} {meta.last_page}
      </span>
      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={currentPage >= meta.last_page || isLoading}
        onClick={() => onPageChange(currentPage + 1)}
        aria-label={nextLabel}
      >
        <span className="hidden sm:inline">{nextLabel}</span>
        <ChevronRight className="h-4 w-4" aria-hidden="true" />
      </Button>
    </nav>
  );
}
