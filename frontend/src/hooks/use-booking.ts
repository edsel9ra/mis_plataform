import {
  useQuery,
  useMutation,
  useQueryClient,
  type MutationOptions,
  type QueryClient,
} from '@tanstack/react-query';
import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-keys';
import type { BookableRelationship, BookingPayload } from '@/lib/booking';
import type { Session } from './use-sessions';

/**
 * Hooks de reserva (spec 017, T017-03). La reserva invalida las sesiones
 * (agenda) y la raíz `availability` porque los huecos del mentor cambian.
 */

export function bookableRelationshipsQueryOptions() {
  return {
    queryKey: queryKeys.bookableRelationships,
    queryFn: async ({ signal }: { signal?: AbortSignal }): Promise<BookableRelationship[]> => {
      const response = await api.get<{ data: BookableRelationship[] }>(
        '/sessions/bookable-relationships', undefined, { signal },
      );

      return response.data;
    },
  };
}

export function useBookableRelationships() {
  return useQuery(bookableRelationshipsQueryOptions());
}

export interface BookSessionResult {
  session: Session;
  /** `true` con 201 (nueva), `false` con 200 (ya existía — reintento idempotente). */
  created: boolean;
}

export function bookSessionMutationOptions(
  queryClient: QueryClient,
): MutationOptions<BookSessionResult, Error, BookingPayload> {
  return {
    mutationFn: async (payload) => {
      const response = await api.postWithStatus<Session>('/sessions', payload);

      return { session: response.data as Session, created: response.status === 201 };
    },
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.sessionsRoot });
      void queryClient.invalidateQueries({ queryKey: queryKeys.availabilityRoot });
    },
  };
}

export function useBookSession() {
  const queryClient = useQueryClient();

  return useMutation(bookSessionMutationOptions(queryClient));
}
