'use client';

import { useEffect, useRef, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslations } from 'next-intl';
import { ApiError } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { mapServerFieldErrors } from '@/lib/availability';
import { validationMessage } from '@/lib/validation-message';
import {
  updateEmployeeSchema,
  toUpdateEmployeePayload,
  type UpdateEmployeeFormValues,
} from '@/lib/schemas/company';
import { useUpdateEmployee, type ManagedEmployee } from '@/hooks/use-company';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

const TITLE_ID = 'employee-edit-title';
const EDIT_FIELDS = new Set(['position', 'department']);
const FIELD_ORDER = ['position', 'department'] as const;

interface EmployeeEditDialogProps {
  companyId: string;
  employee: ManagedEmployee | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onUpdated: (employee: ManagedEmployee) => void;
}

export function EmployeeEditDialog({ companyId, employee, open, onOpenChange, onUpdated }: EmployeeEditDialogProps) {
  const t = useTranslations();
  const update = useUpdateEmployee(companyId);
  const [formError, setFormError] = useState<unknown>(null);
  const alertRef = useRef<HTMLDivElement>(null);

  const form = useForm<UpdateEmployeeFormValues>({
    resolver: zodResolver(updateEmployeeSchema),
    defaultValues: { position: '', department: '' },
  });
  const errors = form.formState.errors;

  useEffect(() => {
    if (open && employee) {
      form.reset({
        position: employee.position ?? '',
        department: employee.department ?? '',
      });
      setFormError(null);
    }
    if (!open) {
      form.reset();
      setFormError(null);
    }
  }, [open, employee, form]);

  async function submit(values: UpdateEmployeeFormValues) {
    if (!employee) return;
    setFormError(null);

    try {
      const updated = await update.mutateAsync({ id: employee.id, payload: toUpdateEmployeePayload(values) });
      onUpdated(updated);
      onOpenChange(false);
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        const mapped = mapServerFieldErrors(error.errors ?? {}, EDIT_FIELDS);
        let focused = false;
        for (const field of FIELD_ORDER) {
          const message = mapped.fields[field];
          if (message) {
            form.setError(field, { type: 'server', message }, { shouldFocus: !focused });
            focused = true;
          }
        }
        if (mapped.formError) {
          setFormError(mapped.formError);
          requestAnimationFrame(() => alertRef.current?.focus());
        } else if (!focused) {
          setFormError(error);
          requestAnimationFrame(() => alertRef.current?.focus());
        }
      } else {
        setFormError(error);
        requestAnimationFrame(() => alertRef.current?.focus());
      }
    }
  }

  function clearServerField(field: keyof UpdateEmployeeFormValues) {
    if (form.formState.errors[field]?.type === 'server') {
      form.clearErrors(field);
    }
  }

  const fullName = employee?.user?.full_name || `${employee?.user?.name ?? ''} ${employee?.user?.last_name ?? ''}`.trim();

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent aria-labelledby={TITLE_ID}>
        <DialogHeader>
          <DialogTitle id={TITLE_ID}>{t('company.manage.edit_title', { name: fullName })}</DialogTitle>
        </DialogHeader>
        <form
          onSubmit={(event) => void form.handleSubmit(submit)(event)}
          className="mt-4 space-y-4"
          noValidate
        >
          <Input
            label={t('company.manage.position')}
            error={validationMessage(errors.position?.message, t)}
            {...form.register('position', { onChange: () => clearServerField('position') })}
          />
          <Input
            label={t('company.manage.department')}
            error={validationMessage(errors.department?.message, t)}
            {...form.register('department', { onChange: () => clearServerField('department') })}
          />
          {Boolean(formError) && (
            <div ref={alertRef} tabIndex={-1} className="focus-visible:outline-none">
              <Alert variant="error">
                {typeof formError === 'string' ? formError : apiErrorMessage(formError, (key) => t(key)) || t('company.manage.error')}
              </Alert>
            </div>
          )}
          <DialogFooter>
            <Button variant="outline" type="button" onClick={() => onOpenChange(false)}>
              {t('company.manage.cancel')}
            </Button>
            <Button variant="primary" type="submit" loading={update.isPending}>
              {t('company.manage.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
