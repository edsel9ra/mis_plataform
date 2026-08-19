'use client';

import { useQuery } from '@tanstack/react-query';
import { useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { useAuth } from '@/components/auth/AuthProvider';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert } from '@/components/ui/alert';
import { PageSpinner } from '@/components/ui/spinner';

interface Employee {
  id: string;
  position: string | null;
  department: string | null;
  status: string;
  user?: { name: string; last_name: string | null; full_name?: string; email: string };
}

interface EmployeesResponse {
  data: Employee[];
  meta: { total: number; current_page: number; last_page: number };
  status_counts: { active: number; invited: number; inactive: number };
}

export default function CompanyEmployeesPage() {
  const t = useTranslations();
  const { user } = useAuth();

  const { data, isLoading, error } = useQuery<EmployeesResponse>({
    queryKey: ['company-employees', user?.company_id],
    enabled: Boolean(user?.company_id),
    queryFn: ({ signal }) => api.get(`/companies/${user?.company_id}/employees`, undefined, { signal }),
  });

  if (isLoading) return <PageSpinner />;
  if (!user?.company_id) return <Alert variant="warning">{t('company.company_required')}</Alert>;
  if (error) return <Alert variant="error">{t('company.employees_error')}</Alert>;

  return (
    <div>
      <div className="flex items-end justify-between gap-4">
        <h1 className="text-2xl font-bold text-gray-900">{t('company.manage_employees')}</h1>
        <span className="text-sm text-gray-500">{t('company.employee_count', { count: data?.meta.total ?? 0 })}</span>
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
                <Badge>{t(`company.employee_status_${employee.status}`)}</Badge>
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
    </div>
  );
}
