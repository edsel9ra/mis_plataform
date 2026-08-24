import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { queryKeys } from '@/lib/query-keys';

export interface Session {
  id: string;
  title: string;
  description?: string | null;
  session_type: string;
  status: string;
  scheduled_at: string;
  duration_minutes: number;
  meet_link: string | null;
  relationship?: {
    mentor?: { id: string; full_name: string; avatar: string | null } | null;
  } | null;
}

export function useSessions(page = 1, perPage = 20, enabled = true) {
  return useQuery({
    queryKey: queryKeys.sessions(page, perPage),
    queryFn: async ({ signal }): Promise<PaginatedResponse<Session>> => {
      const response = await api.get<RawPaginatedResponse<Session>>('/sessions', {
        page,
        per_page: perPage,
      }, { signal });

      return normalizePaginatedResponse(response, { page, perPage });
    },
    enabled,
  });
}

export function useSession(id: string) {
  return useQuery({
    queryKey: ['sessions', id],
    queryFn: ({ signal }) => api.get<Session>(`/sessions/${id}`, undefined, { signal }),
    enabled: !!id,
  });
}

export function useCreateSession() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: {
      relationship_id: string;
      session_type: string;
      title: string;
      description?: string;
      scheduled_at: string;
      duration_minutes: number;
    }) => api.post<Session>('/sessions', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.sessionsRoot });
      queryClient.invalidateQueries({ queryKey: queryKeys.recommendations });
    },
  });
}
