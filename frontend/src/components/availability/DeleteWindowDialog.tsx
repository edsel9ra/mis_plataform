'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useDeleteAvailabilityWindow } from '@/hooks/use-availability';
import { apiErrorMessage } from '@/lib/errors';
import type { AvailabilityWindow } from '@/lib/availability';

const TITLE_ID = 'availability-window-delete-title';
const DESC_ID = 'availability-window-delete-desc';

interface DeleteWindowDialogProps {
  /** The window to delete; the dialog only renders when set. */
  window: AvailabilityWindow | null;
  dayLabel: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Called after success; the page focuses the day heading. */
  onSuccess: (dayOfWeek: number) => void;
}

export function DeleteWindowDialog({
  window,
  dayLabel,
  open,
  onOpenChange,
  onSuccess,
}: DeleteWindowDialogProps) {
  const t = useTranslations();
  const deleteWindow = useDeleteAvailabilityWindow(window?.id ?? '');
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (open) setError(null);
  }, [open]);

  const range = window ? `${window.start_time.slice(0, 5)}–${window.end_time.slice(0, 5)}` : '';

  async function confirm() {
    if (!window) return;
    setError(null);
    try {
      await deleteWindow.mutateAsync();
      onOpenChange(false);
      onSuccess(window.day_of_week);
    } catch (deleteError) {
      setError(apiErrorMessage(deleteError, (key) => t(key)) ?? t('common.error'));
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent aria-labelledby={TITLE_ID} aria-describedby={DESC_ID}>
        <DialogHeader>
          <DialogTitle id={TITLE_ID}>{t('availability.window.delete_title')}</DialogTitle>
        </DialogHeader>
        <p id={DESC_ID} className="text-sm text-gray-600">
          {t('availability.window.delete_description', { day: dayLabel, range })}
        </p>
        {error && <Alert variant="error">{error}</Alert>}
        <DialogFooter>
          <Button variant="outline" type="button" data-autofocus onClick={() => onOpenChange(false)}>
            {t('session_actions.common.dismiss')}
          </Button>
          <Button
            variant="danger"
            type="button"
            loading={deleteWindow.isPending}
            onClick={() => void confirm()}
          >
            {t('availability.window.delete_confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
