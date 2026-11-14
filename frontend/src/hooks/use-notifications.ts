import { useEffect } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { queryKeys } from '@/lib/query-keys';
import { subscribeNotificationChannel } from '@/lib/realtime';
import { useRealtime } from '@/components/realtime/RealtimeProvider';

export interface AppNotification {
  id: string;
  user_id: string;
  type: string;
  data: Record<string, unknown> | null;
  status: string;
  dedupe_key: string | null;
  sent_at: string | null;
  read_at: string | null;
  created_at: string;
  updated_at: string;
}

const FALLBACK_UNREAD_REFETCH_MS = 60_000;

export function useNotifications(page = 1, perPage = 10) {
  return useQuery({
    queryKey: queryKeys.notifications(page, perPage),
    queryFn: async ({ signal }): Promise<PaginatedResponse<AppNotification>> => {
      const response = await api.get<RawPaginatedResponse<AppNotification>>('/notifications', {
        page,
        per_page: perPage,
      }, { signal });

      return normalizePaginatedResponse(response, { page, perPage });
    },
  });
}

export function useUnreadNotificationCount() {
  const { isConnected } = useRealtime();

  return useQuery({
    queryKey: queryKeys.unreadNotificationCount,
    queryFn: ({ signal }) => api.get<{ unread_count: number }>('/notifications/unread-count', undefined, { signal }),
    refetchInterval: isConnected ? false : FALLBACK_UNREAD_REFETCH_MS,
  });
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => api.put<AppNotification>(`/notifications/${id}/read`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.notificationsRoot });
      queryClient.invalidateQueries({ queryKey: queryKeys.unreadNotificationCount });
    },
  });
}

export function useMarkAllNotificationsRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => api.put<{ message: string }>('/notifications/read-all'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.notificationsRoot });
      queryClient.invalidateQueries({ queryKey: queryKeys.unreadNotificationCount });
    },
  });
}

export function useNotificationChannel(userId: string | undefined) {
  const { echo } = useRealtime();
  const queryClient = useQueryClient();

  useEffect(() => {
    if (!echo || !userId) {
      return;
    }

    return subscribeNotificationChannel(echo, userId, () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.notificationsRoot });
      queryClient.invalidateQueries({ queryKey: queryKeys.unreadNotificationCount });
    });
  }, [echo, queryClient, userId]);
}
