import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { fetchAllCollection, type CollectionResponse } from '@/lib/mentor-workspace';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { queryKeys } from '@/lib/query-keys';

export interface Relationship {
  id: string;
  type: string;
  status: string;
  source_type?: string;
  source_id?: string;
  mentor: { id: string; full_name: string; avatar: string | null };
  source?: { id: string; type: string; name: string | null } | null;
  created_at: string;
  features?: { emotional_check_ins: boolean };
}

export function useRelationships(page = 1, perPage = 20, enabled = true) {
  return useQuery({
    queryKey: queryKeys.relationships(page, perPage),
    queryFn: async ({ signal }): Promise<PaginatedResponse<Relationship>> => {
      const response = await api.get<RawPaginatedResponse<Relationship>>('/relationships', {
        page,
        per_page: perPage,
      }, { signal });

      return normalizePaginatedResponse(response, { page, perPage });
    },
    enabled,
  });
}

/**
 * Every relationship of the viewer, not just the first page: the personal
 * dashboard needs all of them so an old active mentorship still offers its
 * emotional check-in card.
 */
export function allRelationshipsQueryOptions(enabled = true) {
  return {
    queryKey: queryKeys.allRelationships,
    queryFn: ({ signal }: { signal?: AbortSignal }) =>
      fetchAllCollection<Relationship>((page) =>
        api.get<CollectionResponse<Relationship>>('/relationships', { page, per_page: 100 }, { signal }),
      ),
    enabled,
    staleTime: 30_000,
  };
}

export function useAllRelationships(enabled = true) {
  return useQuery(allRelationshipsQueryOptions(enabled));
}

export function useRelationship(id: string) {
  return useQuery({
    queryKey: ['relationships', id],
    queryFn: ({ signal }) => api.get<Relationship>(`/relationships/${id}`, undefined, { signal }),
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
      queryClient.invalidateQueries({ queryKey: queryKeys.relationshipsRoot });
      queryClient.invalidateQueries({ queryKey: ['relationship-contexts'] });
      queryClient.invalidateQueries({ queryKey: queryKeys.recommendations });
    },
  });
}

export interface RelationshipContext {
  source_type: 'user' | 'family_group' | 'cohort' | 'company';
  source_id: string;
  type: 'personal' | 'familiar' | 'grupal' | 'empresa';
  name: string;
  plan: {
    id: string;
    name: string;
    max_sessions_per_month: number;
    max_members: number;
    max_mentors: number;
  } | null;
  relationship: { id: string; status: 'pending' | 'active' } | null;
}

export function useRelationshipContexts(mentorId: string | undefined, enabled = true) {
  return useQuery({
    queryKey: ['relationship-contexts', mentorId],
    queryFn: ({ signal }) => api.get<{ data: RelationshipContext[] }>(
      '/relationship-contexts',
      { mentor_id: mentorId },
      { signal },
    ),
    enabled: enabled && Boolean(mentorId),
  });
}
