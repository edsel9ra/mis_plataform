import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';

interface Relationship {
  id: string;
  type: string;
  status: string;
  mentor: { id: string; full_name: string; avatar: string | null };
  created_at: string;
}

export function useRelationships() {
  return useQuery({
    queryKey: ['relationships'],
    queryFn: () => api.get<{ data: Relationship[] }>('/relationships'),
  });
}

export function useRelationship(id: string) {
  return useQuery({
    queryKey: ['relationships', id],
    queryFn: () => api.get<Relationship>(`/relationships/${id}`),
    enabled: !!id,
  });
}

export function useCreateRelationship() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: {
      type: string;
      source_type: string;
      source_id: string;
      mentor_id: string;
      objectives?: string;
    }) => api.post<Relationship>('/relationships', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['relationships'] });
    },
  });
}
