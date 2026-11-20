'use client';

import { useEffect, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';
import { apiErrorMessage } from '@/lib/errors';
import { useDeactivateEmployee, type ManagedEmployee } from '@/hooks/use-company';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

const TITLE_ID = 'employee-deactivate-title';
const DESC_ID = 'employee-deactivate-desc';

interface EmployeeDeactivateDialogProps {
  companyId: string;
  employee: ManagedEmployee | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onDeactivated: (employee: ManagedEmployee) => void;
}

export function EmployeeDeactivateDialog({ companyId, employee, open, onOpenChange, onDeactivated }: EmployeeDeactivateDialogProps) {
  const t = useTranslations();
  const deactivate = useDeactivateEmployee(companyId);
  const [formError, setFormError] = useState<unknown>(null);
  const alertRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) setFormError(null);
  }, [open]);

  async function confirm() {
    if (!employee) return;
    setFormError(null);

    try {
      const updated = await deactivate.mutateAsync(employee.id);
      onDeactivated(updated);
      onOpenChange(false);
    } catch (error) {
      setFormError(error);
      requestAnimationFrame(() => alertRef.current?.focus());
    }
  }

  const fullName = employee?.user?.full_name || `${employee?.user?.name ?? ''} ${employee?.user?.last_name ?? ''}`.trim();

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent aria-labelledby={TITLE_ID} aria-describedby={DESC_ID}>
        <DialogHeader>
          <DialogTitle id={TITLE_ID}>{t('company.manage.deactivate_title', { name: fullName })}</DialogTitle>
        </DialogHeader>
        <p id={DESC_ID} className="mt-2 text-sm text-gray-600">
          {t('company.manage.deactivate_description')}
        </p>
        {Boolean(formError) && (
          <div ref={alertRef} tabIndex={-1} className="mt-3 focus-visible:outline-none">
            <Alert variant="error">
              {typeof formError === 'string' ? formError : apiErrorMessage(formError, (key) => t(key)) || t('company.manage.error')}
            </Alert>
          </div>
        )}
        <DialogFooter>
          <Button variant="outline" type="button" data-autofocus onClick={() => onOpenChange(false)}>
            {t('company.manage.cancel')}
          </Button>
          <Button variant="danger" type="button" loading={deactivate.isPending} onClick={() => void confirm()}>
            {t('company.manage.deactivate_confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
