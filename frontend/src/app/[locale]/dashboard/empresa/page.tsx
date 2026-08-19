'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useLocale, useTranslations } from 'next-intl';
import { ApiError, api } from '@/lib/api';
import { companySchema, type CompanyFormData } from '@/lib/schemas/company';
import { useAuth } from '@/components/auth/AuthProvider';
import { localePath } from '@/i18n/paths';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { PageSpinner } from '@/components/ui/spinner';

interface Company {
  id: string;
  legal_name: string;
  subscription_status: string;
  employees_count: number;
}

interface EmployeeSummary {
  data: unknown[];
  meta: { total: number };
  status_counts: { active: number; invited: number; inactive: number };
}

export default function EmpresaDashboard() {
  const t = useTranslations();
  const locale = useLocale();
  const { user, refreshUser } = useAuth();
  const companyId = user?.company_id ?? undefined;

  const companyQuery = useQuery({
    queryKey: ['company', companyId],
    queryFn: ({ signal }) => api.get<Company>(`/companies/${companyId}`, undefined, { signal }),
    enabled: user?.role === 'company_admin' && Boolean(companyId),
  });
  const employeesQuery = useQuery({
    queryKey: ['company-employees', companyId],
    queryFn: ({ signal }) => api.get<EmployeeSummary>(`/companies/${companyId}/employees`, undefined, { signal }),
    enabled: user?.role === 'company_admin' && Boolean(companyId),
  });

  if (user?.client_type === 'empresa' && !user.company_id) {
    return <CompanySetupForm refreshUser={refreshUser} />;
  }

  if (user?.role !== 'company_admin') {
    return (
      <div>
        <h1 className="text-2xl font-bold text-gray-900">{t('dashboard.welcome')}, {user?.name}</h1>
        <p className="mt-4 text-gray-600">{t('company.employee_dashboard')}</p>
      </div>
    );
  }

  if (companyQuery.isLoading || employeesQuery.isLoading) return <PageSpinner />;

  if (companyQuery.error || employeesQuery.error || !companyQuery.data || !employeesQuery.data) {
    return <Alert variant="error">{t('company.dashboard_error')}</Alert>;
  }

  const company = companyQuery.data;
  const employees = employeesQuery.data;
  const metrics = [
    { label: t('company.total_employees'), value: employees.meta.total },
    { label: t('company.active_employees'), value: employees.status_counts.active },
    { label: t('company.pending_employees'), value: employees.status_counts.invited },
    { label: t('company.subscription_status'), value: t(`company.status_${company.subscription_status}`) },
  ];

  return (
    <div>
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{company.legal_name}</h1>
          <p className="mt-1 text-sm text-gray-500">{t('company.dashboard_title')}</p>
        </div>
        <Link
          href={localePath('/dashboard/empresa/employees', locale)}
          className="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
        >
          {t('company.manage_employees')}
        </Link>
      </div>

      <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        {metrics.map((metric) => (
          <Card key={metric.label}>
            <p className="text-sm font-medium text-gray-500">{metric.label}</p>
            <p className="mt-2 text-2xl font-bold text-gray-900">{metric.value}</p>
          </Card>
        ))}
      </div>
    </div>
  );
}

function CompanySetupForm({ refreshUser }: { refreshUser: () => Promise<unknown> }) {
  const t = useTranslations();
  const [submitError, setSubmitError] = useState<string | null>(null);
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<CompanyFormData>({
    resolver: zodResolver(companySchema),
    defaultValues: {
      legal_name: '',
      tax_id: '',
      industry: '',
      website: '',
      phone: '',
    },
  });

  const submit = handleSubmit(async (values) => {
    setSubmitError(null);
    try {
      await api.post('/companies', values);
      await refreshUser();
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        Object.entries(error.errors ?? {}).forEach(([field, messages]) => {
          if (field in values) setError(field as keyof CompanyFormData, { message: messages[0] });
        });
      }
      setSubmitError(error instanceof ApiError ? error.message : t('company.setup_error'));
    }
  });

  return (
    <Card className="mx-auto max-w-3xl border-0 shadow-sm">
      <CardHeader>
        <CardTitle>{t('company.setup_title')}</CardTitle>
        <p className="mt-1 text-sm text-gray-500">{t('company.setup_description')}</p>
      </CardHeader>
      <form onSubmit={submit} className="space-y-5" noValidate>
        <Input
          label={t('company.legal_name')}
          error={errors.legal_name ? t('company.legal_name_error') : undefined}
          autoComplete="organization"
          required
          {...register('legal_name')}
        />
        <div className="grid gap-4 sm:grid-cols-2">
          <Input label={t('company.tax_id')} error={errors.tax_id?.message} {...register('tax_id')} />
          <Input label={t('company.industry')} error={errors.industry?.message} {...register('industry')} />
          <Select
            label={t('company.size')}
            placeholder={t('company.select_size')}
            options={[
              { value: 'startup', label: t('company.size_startup') },
              { value: 'sme', label: t('company.size_sme') },
              { value: 'enterprise', label: t('company.size_enterprise') },
            ]}
            error={errors.size?.message}
            {...register('size')}
          />
          <Input label={t('company.phone')} type="tel" autoComplete="tel" error={errors.phone?.message} {...register('phone')} />
        </div>
        <Input
          label={t('company.website')}
          type="url"
          placeholder="https://example.com"
          error={errors.website ? t('company.website_error') : undefined}
          {...register('website')}
        />
        {submitError && <Alert variant="error">{submitError}</Alert>}
        <Button type="submit" loading={isSubmitting}>{t('company.create_company')}</Button>
      </form>
    </Card>
  );
}
