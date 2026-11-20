import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-keys';
import type { InviteEmployeePayload } from '@/lib/schemas/company';

export interface InvitedEmployee {
  id: string;
  position: string | null;
  department: string | null;
  status: string;
  user?: { name: string; last_name: string | null; full_name?: string; email: string };
}

export async function inviteEmployee(
  companyId: string,
  payload: InviteEmployeePayload,
): Promise<{ employee: InvitedEmployee }> {
  return api.post<{ employee: InvitedEmployee }>(`/companies/${companyId}/employees`, payload);
}

export function useInviteEmployee(companyId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: InviteEmployeePayload) => inviteEmployee(companyId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.companyEmployeesRoot(companyId) });
      queryClient.invalidateQueries({ queryKey: ['company', companyId] });
    },
  });
}
