import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';

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

export function useSessions(enabled = true) {
  return useQuery({
    queryKey: ['sessions'],
    queryFn: ({ signal }) => api.get<{ data: Session[] }>('/sessions', undefined, { signal }),
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
      queryClient.invalidateQueries({ queryKey: ['sessions'] });
    },
  });
}
