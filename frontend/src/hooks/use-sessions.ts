import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';

interface Session {
  id: string;
  title: string;
  session_type: string;
  status: string;
  scheduled_at: string;
  duration_minutes: number;
  meet_link: string | null;
}

export function useSessions() {
  return useQuery({
    queryKey: ['sessions'],
    queryFn: () => api.get<{ data: Session[] }>('/sessions'),
  });
}

export function useSession(id: string) {
  return useQuery({
    queryKey: ['sessions', id],
    queryFn: () => api.get<Session>(`/sessions/${id}`),
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
