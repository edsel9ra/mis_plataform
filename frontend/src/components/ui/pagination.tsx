import { useEffect, useRef } from 'react';
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
  const prevRef = useRef<HTMLButtonElement>(null);
  const nextRef = useRef<HTMLButtonElement>(null);
  const lastActionRef = useRef<'prev' | 'next' | null>(null);

  const currentPage = Math.min(Math.max(page, 1), meta.last_page);
  const prevDisabled = currentPage <= 1;
  const nextDisabled = currentPage >= meta.last_page;

  // When the button that was just activated becomes disabled (e.g. "Next" on
  // the last page), move focus to the sibling control instead of losing it.
  useEffect(() => {
    if (lastActionRef.current === 'next' && nextDisabled && !prevDisabled) {
      prevRef.current?.focus();
    } else if (lastActionRef.current === 'prev' && prevDisabled && !nextDisabled) {
      nextRef.current?.focus();
    }
    lastActionRef.current = null;
  }, [currentPage, prevDisabled, nextDisabled]);

  if (meta.last_page <= 1) return null;

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
        ref={prevRef}
        disabled={prevDisabled}
        loading={isLoading}
        onClick={() => {
          lastActionRef.current = 'prev';
          onPageChange(currentPage - 1);
        }}
        aria-label={previousLabel}
      >
        <ChevronLeft className="h-4 w-4" aria-hidden="true" />
        <span className="hidden sm:inline">{previousLabel}</span>
      </Button>
      <span className="text-center text-sm text-slate-600" role="status">
        {pageLabel} {meta.current_page} {ofLabel} {meta.last_page}
      </span>
      <Button
        type="button"
        variant="outline"
        size="sm"
        ref={nextRef}
        disabled={nextDisabled}
        loading={isLoading}
        onClick={() => {
          lastActionRef.current = 'next';
          onPageChange(currentPage + 1);
        }}
        aria-label={nextLabel}
      >
        <span className="hidden sm:inline">{nextLabel}</span>
        <ChevronRight className="h-4 w-4" aria-hidden="true" />
      </Button>
    </nav>
  );
}
