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
  inviteEmployeeSchema,
  toInviteEmployeePayload,
  type InviteEmployeeFormValues,
} from '@/lib/schemas/company';
import { useInviteEmployee, type ManagedEmployee } from '@/hooks/use-company';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

const TITLE_ID = 'employee-invite-title';
const EMPLOYEE_FIELDS = new Set(['email', 'name', 'last_name', 'position', 'department']);
const FIELD_ORDER = ['email', 'name', 'last_name', 'position', 'department'] as const;

interface EmployeeInviteDialogProps {
  companyId: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onInvited: (employee: ManagedEmployee) => void;
}

export function EmployeeInviteDialog({ companyId, open, onOpenChange, onInvited }: EmployeeInviteDialogProps) {
  const t = useTranslations();
  const invite = useInviteEmployee(companyId);
  const [formError, setFormError] = useState<unknown>(null);
  const alertRef = useRef<HTMLDivElement>(null);

  const form = useForm<InviteEmployeeFormValues>({
    resolver: zodResolver(inviteEmployeeSchema),
    defaultValues: { email: '', name: '', last_name: '', position: '', department: '' },
  });
  const errors = form.formState.errors;

  useEffect(() => {
    if (!open) {
      form.reset();
      setFormError(null);
    }
  }, [open, form]);

  function focusAlert() {
    requestAnimationFrame(() => alertRef.current?.focus());
  }

  async function submit(values: InviteEmployeeFormValues) {
    setFormError(null);

    try {
      const { employee } = await invite.mutateAsync(toInviteEmployeePayload(values));
      form.reset();
      onInvited(employee);
      onOpenChange(false);
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        const mapped = mapServerFieldErrors(error.errors ?? {}, EMPLOYEE_FIELDS);
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
          focusAlert();
        } else if (!focused) {
          setFormError(error);
          focusAlert();
        }
      } else {
        setFormError(error);
        focusAlert();
      }
    }
  }

  function clearServerField(field: keyof InviteEmployeeFormValues) {
    if (form.formState.errors[field]?.type === 'server') {
      form.clearErrors(field);
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent aria-labelledby={TITLE_ID}>
        <DialogHeader>
          <DialogTitle id={TITLE_ID}>{t('company.invite.title')}</DialogTitle>
        </DialogHeader>
        <p className="mt-1 text-sm text-gray-500">{t('company.invite.description')}</p>
        <form
          onSubmit={(event) => void form.handleSubmit(submit)(event)}
          className="mt-4 space-y-4"
          noValidate
        >
          <Input
            type="email"
            autoComplete="off"
            label={t('company.invite.email')}
            required
            data-autofocus
            error={validationMessage(errors.email?.message, t)}
            {...form.register('email', { onChange: () => clearServerField('email') })}
          />
          <Input
            label={t('company.invite.name')}
            required
            error={validationMessage(errors.name?.message, t)}
            {...form.register('name', { onChange: () => clearServerField('name') })}
          />
          <Input
            label={t('company.invite.last_name')}
            error={validationMessage(errors.last_name?.message, t)}
            {...form.register('last_name', { onChange: () => clearServerField('last_name') })}
          />
          <Input
            label={t('company.invite.position')}
            error={validationMessage(errors.position?.message, t)}
            {...form.register('position', { onChange: () => clearServerField('position') })}
          />
          <Input
            label={t('company.invite.department')}
            error={validationMessage(errors.department?.message, t)}
            {...form.register('department', { onChange: () => clearServerField('department') })}
          />
          {Boolean(formError) && (
            <div ref={alertRef} tabIndex={-1} className="focus-visible:outline-none">
              <Alert variant="error">
                {typeof formError === 'string' ? formError : apiErrorMessage(formError, (key) => t(key)) || t('company.invite.error')}
              </Alert>
            </div>
          )}
          <DialogFooter>
            <Button variant="outline" type="button" onClick={() => onOpenChange(false)}>
              {t('company.invite.cancel')}
            </Button>
            <Button variant="primary" type="submit" loading={invite.isPending}>
              {t('company.invite.submit')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
