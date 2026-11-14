'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useDeleteAvailabilityException } from '@/hooks/use-availability';
import { apiErrorMessage } from '@/lib/errors';
import type { AvailabilityException } from '@/lib/availability';

const TITLE_ID = 'availability-exception-delete-title';
const DESC_ID = 'availability-exception-delete-desc';

interface DeleteExceptionDialogProps {
  exception: AvailabilityException | null;
  /** Localized period text for the description. */
  periodText: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess: () => void;
}

export function DeleteExceptionDialog({
  exception,
  periodText,
  open,
  onOpenChange,
  onSuccess,
}: DeleteExceptionDialogProps) {
  const t = useTranslations();
  const deleteException = useDeleteAvailabilityException(exception?.id ?? '');
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (open) setError(null);
  }, [open]);

  async function confirm() {
    if (!exception) return;
    setError(null);
    try {
      await deleteException.mutateAsync();
      onOpenChange(false);
      onSuccess();
    } catch (deleteError) {
      setError(apiErrorMessage(deleteError, (key) => t(key)) ?? t('common.error'));
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent aria-labelledby={TITLE_ID} aria-describedby={DESC_ID}>
        <DialogHeader>
          <DialogTitle id={TITLE_ID}>{t('availability.exceptions.delete_title')}</DialogTitle>
        </DialogHeader>
        <p id={DESC_ID} className="text-sm text-gray-600">
          {t('availability.exceptions.delete_description', { period: periodText })}
        </p>
        {error && <Alert variant="error">{error}</Alert>}
        <DialogFooter>
          <Button variant="outline" type="button" data-autofocus onClick={() => onOpenChange(false)}>
            {t('session_actions.common.dismiss')}
          </Button>
          <Button
            variant="danger"
            type="button"
            loading={deleteException.isPending}
            onClick={() => void confirm()}
          >
            {t('availability.weekly.delete')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
