'use client';

import { useEffect, useMemo, useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useTranslations } from 'next-intl';
import { UserPlus } from 'lucide-react';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { queryKeys } from '@/lib/query-keys';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { useAuth } from '@/components/auth/AuthProvider';
import { EmployeeDeactivateDialog } from '@/components/company/EmployeeDeactivateDialog';
import { EmployeeEditDialog } from '@/components/company/EmployeeEditDialog';
import { EmployeeInviteDialog } from '@/components/company/EmployeeInviteDialog';
import { useActivateEmployee, type ManagedEmployee } from '@/hooks/use-company';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert } from '@/components/ui/alert';
import { PageSpinner } from '@/components/ui/spinner';
import { PaginationControls } from '@/components/ui/pagination';

type Employee = ManagedEmployee;

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
  const [inviteOpen, setInviteOpen] = useState(false);
  const [feedback, setFeedback] = useState('');
  const [actionError, setActionError] = useState<unknown>(null);
  const [editEmployee, setEditEmployee] = useState<ManagedEmployee | null>(null);
  const [deactivateEmployee, setDeactivateEmployee] = useState<ManagedEmployee | null>(null);
  const [pendingFocusId, setPendingFocusId] = useState<string | null>(null);
  const perPage = 12;
  const companyId = user?.company_id ?? '';

  const { data, isLoading, isFetching, error } = useQuery<EmployeesResponse>({
    queryKey: queryKeys.companyEmployees(companyId, page, perPage),
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
    placeholderData: keepPreviousData,
  });

  const activate = useActivateEmployee(companyId);
  const employees = useMemo(() => data?.data ?? [], [data]);

  useEffect(() => {
    if (!pendingFocusId) return;
    if (!employees.some((employee) => employee.id === pendingFocusId)) return;
    document.getElementById(`employee-${pendingFocusId}-name`)?.focus();
    setPendingFocusId(null);
  }, [employees, pendingFocusId]);

  async function handleActivate(employee: Employee) {
    setActionError(null);

    try {
      await activate.mutateAsync(employee.id);
      setFeedback(t('company.manage.activated', { name: employeeName(employee) }));
      setPendingFocusId(employee.id);
    } catch (error) {
      setActionError(error);
    }
  }

  if (isLoading) return <PageSpinner />;
  if (!user?.company_id)
    return (
      <div>
        <h1 className="text-2xl font-bold text-gray-900">{t('company.manage_employees')}</h1>
        <Alert variant="warning" className="mt-4">{t('company.company_required')}</Alert>
      </div>
    );
  if (error)
    return (
      <div>
        <h1 className="text-2xl font-bold text-gray-900">{t('company.manage_employees')}</h1>
        <Alert variant="error" className="mt-4">{apiErrorMessage(error, (key) => t(key)) || t('company.employees_error')}</Alert>
      </div>
    );

  return (
    <div>
      <div className="flex items-end justify-between gap-4">
        <h1 className="text-2xl font-bold text-gray-900">{t('company.manage_employees')}</h1>
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-500">{t('company.employee_count', { count: data?.meta.total ?? 0 })}</span>
          {user.company_id && (
            <Button type="button" onClick={() => setInviteOpen(true)}>
              <UserPlus className="h-4 w-4" aria-hidden="true" />
              {t('company.invite.open')}
            </Button>
          )}
        </div>
      </div>
      <div role="status" className="sr-only">{feedback}</div>
      {Boolean(actionError) && (
        <Alert variant="error" className="mt-4">
          {apiErrorMessage(actionError, (key) => t(key)) || t('company.manage.error')}
        </Alert>
      )}
      {user.company_id && (
        <>
          <EmployeeInviteDialog
            companyId={user.company_id}
            open={inviteOpen}
            onOpenChange={setInviteOpen}
            onInvited={(employee) => {
              setPage(1);
              setFeedback(t('company.invite.sent', { email: employee.user?.email ?? '' }));
            }}
          />
          <EmployeeEditDialog
            companyId={user.company_id}
            employee={editEmployee}
            open={editEmployee !== null}
            onOpenChange={(next) => { if (!next) setEditEmployee(null); }}
            onUpdated={(employee) => {
              setFeedback(t('company.manage.updated', { name: employeeName(employee) }));
            }}
          />
          <EmployeeDeactivateDialog
            companyId={user.company_id}
            employee={deactivateEmployee}
            open={deactivateEmployee !== null}
            onOpenChange={(next) => { if (!next) setDeactivateEmployee(null); }}
            onDeactivated={(employee) => {
              setFeedback(t('company.manage.deactivated', { name: employeeName(employee) }));
              setPendingFocusId(employee.id);
            }}
          />
        </>
      )}
      <div className="mt-4 grid gap-3 sm:grid-cols-3">
        <StatusCard label={t('company.active_employees')} value={data?.status_counts.active ?? 0} />
        <StatusCard label={t('company.pending_employees')} value={data?.status_counts.invited ?? 0} />
        <StatusCard label={t('company.employee_status_inactive')} value={data?.status_counts.inactive ?? 0} />
      </div>
      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {employees.map((employee) => {
          const name = employeeName(employee);
          return (
            <Card key={employee.id}>
              <CardHeader>
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle as="h2" className="text-base" id={`employee-${employee.id}-name`} tabIndex={-1}>
                      {name}
                    </CardTitle>
                    <p className="mt-1 text-sm text-gray-500">{employee.user?.email}</p>
                  </div>
                  <Badge>{employeeStatusLabel(employee.status, t)}</Badge>
                </div>
              </CardHeader>
              <p className="text-sm text-gray-600">{employee.position ?? t('common.pending')}</p>
              {employee.department && <p className="mt-1 text-xs text-gray-600">{employee.department}</p>}
              <div className="mt-4 flex flex-wrap gap-2">
                <Button size="sm" variant="outline" type="button" onClick={() => setEditEmployee(employee)}>
                  {t('company.manage.edit')}
                  <span className="sr-only"> {name}</span>
                </Button>
                {(employee.status === 'inactive' || employee.status === 'invited') && (
                  <Button
                    size="sm"
                    variant="outline"
                    type="button"
                    loading={activate.isPending && activate.variables === employee.id}
                    onClick={() => void handleActivate(employee)}
                  >
                    {t('company.manage.activate')}
                    <span className="sr-only"> {name}</span>
                  </Button>
                )}
                {(employee.status === 'active' || employee.status === 'invited') && (
                  <Button
                    size="sm"
                    variant="outline"
                    type="button"
                    onClick={() => setDeactivateEmployee(employee)}
                  >
                    {t('company.manage.deactivate')}
                    <span className="sr-only"> {name}</span>
                  </Button>
                )}
              </div>
            </Card>
          );
        })}
      </div>
      {(!data?.data || data.data.length === 0) && (
        <p className="mt-8 text-center text-gray-600">{t('common.no_results')}</p>
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

function employeeName(employee: Employee): string {
  return employee.user?.full_name || `${employee.user?.name ?? ''} ${employee.user?.last_name ?? ''}`.trim();
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
