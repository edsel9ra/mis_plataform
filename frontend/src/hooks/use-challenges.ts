import {
  useMutation,
  useQuery,
  useQueryClient,
  type QueryClient,
} from '@tanstack/react-query';
import { api } from '@/lib/api';
import { fetchAllCollection, MENTOR_WORKSPACE_ENDPOINTS } from '@/lib/mentor-workspace';
import { queryKeys } from '@/lib/query-keys';
import type { MentorChallenge } from '@/types/mentor-workspace';

/**
 * Mentee "Mis retos" data and mentor status updates (spec 012, T012.9/T012.10).
 */

export function menteeChallengesQueryOptions(relationshipId: string, enabled = true) {
  return {
    queryKey: queryKeys.relationshipChallenges(relationshipId),
    queryFn: ({ signal }: { signal?: AbortSignal }) =>
      fetchAllCollection<MentorChallenge>((page) =>
        api.get<MentorChallenge[] | { data?: MentorChallenge[]; meta?: { last_page?: number } }>(
          `/relationships/${relationshipId}/challenges`,
          { page, per_page: 50 },
          { signal },
        )),
    enabled: enabled && Boolean(relationshipId),
    staleTime: 30_000,
    retry: false,
  };
}

export function useMenteeChallenges(relationshipId: string, enabled = true) {
  return useQuery(menteeChallengesQueryOptions(relationshipId, enabled));
}

function invalidateChallengeQueries(queryClient: QueryClient): void {
  void queryClient.invalidateQueries({ queryKey: queryKeys.progressRoot });
  void queryClient.invalidateQueries({ queryKey: queryKeys.mentorChallenges });
  void queryClient.invalidateQueries({ queryKey: ['mentor-workspace', 'challenges'] });
  void queryClient.invalidateQueries({ queryKey: ['mentor-workspace', 'users'] });
  void queryClient.invalidateQueries({ queryKey: ['mentor', 'progress'] });
  void queryClient.invalidateQueries({ queryKey: ['challenges'] });
}

/** `PUT /mentor/challenges/{id}?relationship_id=…` — server validates transitions. */
export function updateChallengeStatusMutationOptions(
  challengeId: string,
  relationshipId: string,
  queryClient: QueryClient,
) {
  return {
    mutationFn: (status: string) => api.put<MentorChallenge>(
      `${MENTOR_WORKSPACE_ENDPOINTS.challenge(challengeId)}`,
      { status },
      { params: { relationship_id: relationshipId } },
    ),
    onSettled: () => invalidateChallengeQueries(queryClient),
  };
}

export function useUpdateChallengeStatus(challengeId: string, relationshipId: string) {
  const queryClient = useQueryClient();

  return useMutation(updateChallengeStatusMutationOptions(challengeId, relationshipId, queryClient));
}
