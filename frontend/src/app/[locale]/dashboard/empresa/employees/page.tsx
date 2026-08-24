'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { useAuth } from '@/components/auth/AuthProvider';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert } from '@/components/ui/alert';
import { PageSpinner } from '@/components/ui/spinner';
import { PaginationControls } from '@/components/ui/pagination';

interface Employee {
  id: string;
  position: string | null;
  department: string | null;
  status: string;
  user?: { name: string; last_name: string | null; full_name?: string; email: string };
}

interface EmployeesResponse extends PaginatedResponse<Employee> {
  status_counts: { active: number; invited: number; inactive: number };
}

type RawEmployeesResponse = RawPaginatedResponse<Employee> & {
  status_counts?: EmployeesResponse['status_counts'];
};

export default function CompanyEmployeesPage() {
  const t = useTranslations();
  const { user } = useAuth();
  const [page, setPage] = useState(1);
  const perPage = 12;

  const { data, isLoading, isFetching, error } = useQuery<EmployeesResponse>({
    queryKey: ['company-employees', user?.company_id, { page, per_page: perPage }],
    enabled: Boolean(user?.company_id),
    queryFn: async ({ signal }) => {
      const response = await api.get<RawEmployeesResponse>(`/companies/${user?.company_id}/employees`, {
        page,
        per_page: perPage,
      }, { signal });
      const paginated = normalizePaginatedResponse(response, { page, perPage });

      return {
        ...paginated,
        status_counts: response.status_counts ?? { active: 0, invited: 0, inactive: 0 },
      };
    },
  });

  if (isLoading) return <PageSpinner />;
  if (!user?.company_id) return <Alert variant="warning">{t('company.company_required')}</Alert>;
  if (error) return <Alert variant="error">{apiErrorMessage(error, (key) => t(key)) || t('company.employees_error')}</Alert>;

  return (
    <div>
      <div className="flex items-end justify-between gap-4">
        <h1 className="text-2xl font-bold text-gray-900">{t('company.manage_employees')}</h1>
        <span className="text-sm text-gray-500">{t('company.employee_count', { count: data?.meta.total ?? 0 })}</span>
      </div>
      <div className="mt-4 grid gap-3 sm:grid-cols-3">
        <StatusCard label={t('company.active_employees')} value={data?.status_counts.active ?? 0} />
        <StatusCard label={t('company.pending_employees')} value={data?.status_counts.invited ?? 0} />
        <StatusCard label={t('company.employee_status_inactive')} value={data?.status_counts.inactive ?? 0} />
      </div>
      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {(data?.data ?? []).map((employee) => (
          <Card key={employee.id}>
            <CardHeader>
              <div className="flex items-start justify-between gap-4">
                <div>
                  <CardTitle className="text-base">
                    {employee.user?.full_name || `${employee.user?.name ?? ''} ${employee.user?.last_name ?? ''}`.trim()}
                  </CardTitle>
                  <p className="mt-1 text-sm text-gray-500">{employee.user?.email}</p>
                </div>
                 <Badge>{employeeStatusLabel(employee.status, t)}</Badge>
              </div>
            </CardHeader>
            <p className="text-sm text-gray-600">{employee.position ?? t('common.pending')}</p>
            {employee.department && <p className="mt-1 text-xs text-gray-400">{employee.department}</p>}
          </Card>
        ))}
      </div>
      {(!data?.data || data.data.length === 0) && (
        <p className="mt-8 text-center text-gray-400">{t('common.no_results')}</p>
      )}
      {data && (
        <div className="mt-8 rounded-2xl border border-gray-200 bg-white shadow-sm">
          <PaginationControls
            meta={data.meta}
            page={page}
            onPageChange={setPage}
            isLoading={isFetching}
            ariaLabel={t('common.pagination')}
            previousLabel={t('common.previous')}
            nextLabel={t('common.next')}
            pageLabel={t('common.page')}
            ofLabel={t('common.of')}
          />
        </div>
      )}
    </div>
  );
}

function employeeStatusLabel(status: string, t: (key: string) => string): string {
  const labels: Record<string, string> = {
    active: 'company.employee_status_active',
    invited: 'company.employee_status_invited',
    inactive: 'company.employee_status_inactive',
  };

  return labels[status] ? t(labels[status]) : t('common.not_applicable');
}

function StatusCard({ label, value }: { label: string; value: number }) {
  return (
    <Card className="border-0 shadow-sm">
      <p className="text-sm text-gray-500">{label}</p>
      <p className="mt-1 text-2xl font-bold text-gray-950">{value}</p>
    </Card>
  );
}
