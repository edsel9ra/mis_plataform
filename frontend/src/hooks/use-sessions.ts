import {
  useQuery,
  useMutation,
  useQueryClient,
  type MutationOptions,
  type QueryClient,
} from '@tanstack/react-query';
import { api } from '@/lib/api';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { queryKeys } from '@/lib/query-keys';
import {
  parseAvailableActions,
  sessionActionInvalidationKeys,
  type SessionAvailableActions,
} from '@/lib/session-actions';
import type { ReschedulePayload } from '@/lib/schemas/session-actions';

export interface Session {
  id: string;
  title: string;
  description?: string | null;
  session_type: string;
  status: string;
  scheduled_at: string;
  duration_minutes: number;
  timezone?: string | null;
  meet_link: string | null;
  relationship?: {
    mentor?: { id: string; full_name: string; avatar: string | null } | null;
    source_type?: string | null;
    source?: { id: string; type: string; name: string | null } | null;
  } | null;
  attendees?: Array<{
    id: string;
    user?: { id: string; name: string | null; full_name: string | null; avatar: string | null; role: string } | null;
  } | null> | null;
  available_actions?: SessionAvailableActions | null;
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
    queryKey: queryKeys.session(id),
    queryFn: async ({ signal }): Promise<Session> => {
      const session = await api.get<Session>(`/sessions/${id}`, undefined, { signal });

      return {
        ...session,
        available_actions: parseAvailableActions(session.available_actions),
      };
    },
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

/**
 * Session action mutations (spec 016, T016-07). Each factory returns plain
 * `MutationOptions` so the hooks stay thin and the contract is testable
 * without React. `onSettled` always invalidates the session detail and every
 * query under the `sessions` root (lists + ranged calendar keys): on success
 * the calendar refreshes (RF-016.9) and on rejection the detail refetches the
 * freshest `available_actions` after a possible state change (RF-016.10).
 */
function sessionActionOptions<TVariables>(
  queryClient: QueryClient,
  sessionId: string,
  mutationFn: (variables: TVariables) => Promise<Session>,
): MutationOptions<Session, Error, TVariables> {
  return {
    mutationFn,
    onSettled: () => {
      for (const queryKey of sessionActionInvalidationKeys(sessionId)) {
        void queryClient.invalidateQueries({ queryKey });
      }
    },
  };
}

export function rescheduleSessionMutationOptions(
  queryClient: QueryClient,
  sessionId: string,
): MutationOptions<Session, Error, ReschedulePayload> {
  return sessionActionOptions(queryClient, sessionId, (payload) =>
    api.put<Session>(`/sessions/${sessionId}/reschedule`, payload));
}

export function useRescheduleSession(sessionId: string) {
  const queryClient = useQueryClient();

  return useMutation(rescheduleSessionMutationOptions(queryClient, sessionId));
}

export function cancelSessionMutationOptions(
  queryClient: QueryClient,
  sessionId: string,
): MutationOptions<Session, Error, { reason: string }> {
  return sessionActionOptions(queryClient, sessionId, ({ reason }) =>
    api.post<Session>(`/sessions/${sessionId}/cancel`, { reason }));
}

export function useCancelSession(sessionId: string) {
  const queryClient = useQueryClient();

  return useMutation(cancelSessionMutationOptions(queryClient, sessionId));
}

export function completeSessionMutationOptions(
  queryClient: QueryClient,
  sessionId: string,
): MutationOptions<Session, Error, void> {
  return sessionActionOptions(queryClient, sessionId, () =>
    api.put<Session>(`/sessions/${sessionId}/status`, { status: 'completed' }));
}

export function useCompleteSession(sessionId: string) {
  const queryClient = useQueryClient();

  return useMutation(completeSessionMutationOptions(queryClient, sessionId));
}

export interface ConfirmAttendanceVariables {
  status: 'attended' | 'absent';
  /** Omitted for the actor's own (self) confirmation. */
  attendee_user_id?: string;
}

export function confirmAttendanceMutationOptions(
  queryClient: QueryClient,
  sessionId: string,
): MutationOptions<Session, Error, ConfirmAttendanceVariables> {
  return sessionActionOptions(queryClient, sessionId, ({ status, attendee_user_id }) =>
    api.put<Session>(`/sessions/${sessionId}/attendance`, {
      status,
      ...(attendee_user_id ? { attendee_user_id } : {}),
    }));
}

export function useConfirmAttendance(sessionId: string) {
  const queryClient = useQueryClient();

  return useMutation(confirmAttendanceMutationOptions(queryClient, sessionId));
}
