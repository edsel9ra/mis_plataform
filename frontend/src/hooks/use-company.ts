import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-keys';
import type { InviteEmployeePayload, UpdateEmployeePayload } from '@/lib/schemas/company';

export interface ManagedEmployee {
  id: string;
  position: string | null;
  department: string | null;
  status: string;
  user?: { name: string; last_name: string | null; full_name?: string; email: string };
}

export async function inviteEmployee(
  companyId: string,
  payload: InviteEmployeePayload,
): Promise<{ employee: ManagedEmployee }> {
  return api.post<{ employee: ManagedEmployee }>(`/companies/${companyId}/employees`, payload);
}

export async function updateEmployee(id: string, payload: UpdateEmployeePayload): Promise<ManagedEmployee> {
  return api.put<ManagedEmployee>(`/employees/${id}`, payload);
}

export async function activateEmployee(id: string): Promise<ManagedEmployee> {
  return api.post<ManagedEmployee>(`/employees/${id}/activate`);
}

export async function deactivateEmployee(id: string): Promise<ManagedEmployee> {
  return api.delete<ManagedEmployee>(`/employees/${id}`);
}

function useInvalidateCompanyEmployees(companyId: string) {
  const queryClient = useQueryClient();

  return () => {
    queryClient.invalidateQueries({ queryKey: queryKeys.companyEmployeesRoot(companyId) });
    queryClient.invalidateQueries({ queryKey: ['company', companyId] });
  };
}

export function useInviteEmployee(companyId: string) {
  const invalidate = useInvalidateCompanyEmployees(companyId);

  return useMutation({
    mutationFn: (payload: InviteEmployeePayload) => inviteEmployee(companyId, payload),
    onSuccess: invalidate,
  });
}

export function useUpdateEmployee(companyId: string) {
  const invalidate = useInvalidateCompanyEmployees(companyId);

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: UpdateEmployeePayload }) => updateEmployee(id, payload),
    onSuccess: invalidate,
  });
}

export function useActivateEmployee(companyId: string) {
  const invalidate = useInvalidateCompanyEmployees(companyId);

  return useMutation({
    mutationFn: (id: string) => activateEmployee(id),
    onSuccess: invalidate,
  });
}

export function useDeactivateEmployee(companyId: string) {
  const invalidate = useInvalidateCompanyEmployees(companyId);

  return useMutation({
    mutationFn: (id: string) => deactivateEmployee(id),
    onSuccess: invalidate,
  });
}
