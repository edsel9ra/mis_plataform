import { useEffect } from 'react';
import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { RawPaginatedResponse } from '@/lib/pagination';
import { queryKeys } from '@/lib/query-keys';
import { subscribeRelationshipChannel } from '@/lib/realtime';
import { useRealtime } from '@/components/realtime/RealtimeProvider';

const MESSAGES_PAGE_SIZE = 50;
const FALLBACK_MESSAGES_REFETCH_MS = 15_000;

export interface ChatMessage {
  id: string;
  relationship_id: string;
  sender_id: string;
  content: string;
  type: string;
  attachment_url: string | null;
  read_at: string | null;
  created_at: string;
  sender?: { id: string; name?: string; full_name?: string; avatar?: string | null } | null;
}

export function useRelationshipMessages(relationshipId: string | undefined) {
  const { isConnected } = useRealtime();

  return useInfiniteQuery({
    queryKey: queryKeys.messages(relationshipId ?? 'none'),
    enabled: !!relationshipId,
    initialPageParam: 1,
    queryFn: ({ pageParam, signal }) => api.get<RawPaginatedResponse<ChatMessage>>('/messages', {
      relationship_id: relationshipId!,
      per_page: MESSAGES_PAGE_SIZE,
      page: pageParam,
    }, { signal }),
    getNextPageParam: (lastPage) => {
      const current = lastPage.current_page ?? 1;
      const last = lastPage.last_page ?? current;
      return current < last ? current + 1 : undefined;
    },
    refetchInterval: isConnected ? false : FALLBACK_MESSAGES_REFETCH_MS,
  });
}

export function useSendMessage(relationshipId: string | undefined) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (content: string) => api.post<ChatMessage>('/messages', {
      relationship_id: relationshipId,
      content,
      type: 'text',
    }),
    onSuccess: () => {
      if (relationshipId) {
        queryClient.invalidateQueries({ queryKey: queryKeys.messages(relationshipId) });
      }
    },
  });
}

export function useMarkMessageRead(relationshipId: string | undefined) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (messageId: string) => api.put<ChatMessage>(`/messages/${messageId}/read`),
    onSuccess: () => {
      if (relationshipId) {
        queryClient.invalidateQueries({ queryKey: queryKeys.messages(relationshipId) });
      }
    },
  });
}

export function useRelationshipChannel(relationshipId: string | undefined) {
  const { echo } = useRealtime();
  const queryClient = useQueryClient();

  useEffect(() => {
    if (!echo || !relationshipId) {
      return;
    }

    return subscribeRelationshipChannel(echo, relationshipId, () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.messages(relationshipId) });
    });
  }, [echo, queryClient, relationshipId]);
}
