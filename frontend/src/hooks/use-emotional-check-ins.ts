import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-keys';
import type { EmotionalCheckIn } from '@/types/mentor-workspace';
import type { EmotionalCheckInPayload } from '@/lib/schemas/mentee';

export async function fetchEmotionalCheckIns(
  relationshipId: string,
  signal?: AbortSignal,
): Promise<EmotionalCheckIn[]> {
  const response = await api.get<{ data: EmotionalCheckIn[] }>(
    `/relationships/${relationshipId}/emotional-check-ins`,
    { per_page: 5 },
    { signal },
  );

  return Array.isArray(response?.data) ? response.data : [];
}

export async function createEmotionalCheckIn(
  relationshipId: string,
  payload: EmotionalCheckInPayload,
): Promise<EmotionalCheckIn> {
  return api.post<EmotionalCheckIn>(`/relationships/${relationshipId}/emotional-check-ins`, payload);
}

export function useEmotionalCheckIns(relationshipId: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.menteeEmotionalCheckIns(relationshipId),
    queryFn: ({ signal }) => fetchEmotionalCheckIns(relationshipId, signal),
    enabled: enabled && Boolean(relationshipId),
    placeholderData: keepPreviousData,
  });
}

export function useCreateEmotionalCheckIn(relationshipId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: EmotionalCheckInPayload) => createEmotionalCheckIn(relationshipId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.menteeEmotionalCheckIns(relationshipId) });
    },
  });
}
