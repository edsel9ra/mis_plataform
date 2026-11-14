import {
  useQuery,
  useMutation,
  useQueryClient,
  type MutationOptions,
  type QueryClient,
} from '@tanstack/react-query';
import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-keys';
import type {
  AvailabilityException,
  AvailabilityExceptionPayload,
  AvailabilitySlot,
  AvailabilityWindow,
  AvailabilityWindowPayload,
} from '@/lib/availability';

/**
 * Hooks de disponibilidad (spec 017, T017-03). Cada factoría devuelve
 * `MutationOptions` planos para que el contrato sea testable sin React.
 * `onSettled` invalida la raíz `availability` — cubre ventanas, excepciones
 * y los huecos calculados, que dependen de ambas.
 */

export function availabilityWindowsQueryOptions(enabled = true) {
  return {
    enabled,
    queryKey: queryKeys.availabilityWindows,
    queryFn: async ({ signal }: { signal?: AbortSignal }): Promise<AvailabilityWindow[]> => {
      const response = await api.get<{ data: AvailabilityWindow[] }>(
        '/availability/windows', undefined, { signal },
      );

      return response.data;
    },
  };
}

export function useAvailabilityWindows(enabled = true) {
  return useQuery(availabilityWindowsQueryOptions(enabled));
}

export function availabilityExceptionsQueryOptions(enabled = true) {
  return {
    enabled,
    queryKey: queryKeys.availabilityExceptions,
    queryFn: async ({ signal }: { signal?: AbortSignal }): Promise<AvailabilityException[]> => {
      const response = await api.get<{ data: AvailabilityException[] }>(
        '/availability/exceptions', undefined, { signal },
      );

      return response.data;
    },
  };
}

export function useAvailabilityExceptions(enabled = true) {
  return useQuery(availabilityExceptionsQueryOptions(enabled));
}

function availabilityMutationOptions<TData, TVariables>(
  queryClient: QueryClient,
  mutationFn: (variables: TVariables) => Promise<TData>,
): MutationOptions<TData, Error, TVariables> {
  return {
    mutationFn,
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.availabilityRoot });
    },
  };
}

export function createWindowMutationOptions(
  queryClient: QueryClient,
): MutationOptions<AvailabilityWindow, Error, AvailabilityWindowPayload> {
  return availabilityMutationOptions(queryClient, (payload) =>
    api.post<AvailabilityWindow>('/availability/windows', payload));
}

export function useCreateAvailabilityWindow() {
  const queryClient = useQueryClient();

  return useMutation(createWindowMutationOptions(queryClient));
}

export function updateWindowMutationOptions(
  queryClient: QueryClient,
  windowId: string,
): MutationOptions<AvailabilityWindow, Error, AvailabilityWindowPayload> {
  return availabilityMutationOptions(queryClient, (payload) =>
    api.put<AvailabilityWindow>(`/availability/windows/${windowId}`, payload));
}

export function useUpdateAvailabilityWindow(windowId: string) {
  const queryClient = useQueryClient();

  return useMutation(updateWindowMutationOptions(queryClient, windowId));
}

export function deleteWindowMutationOptions(
  queryClient: QueryClient,
  windowId: string,
): MutationOptions<null, Error, void> {
  return availabilityMutationOptions(queryClient, () =>
    api.delete<null>(`/availability/windows/${windowId}`));
}

export function useDeleteAvailabilityWindow(windowId: string) {
  const queryClient = useQueryClient();

  return useMutation(deleteWindowMutationOptions(queryClient, windowId));
}

// POST/PUT de excepciones devuelven el recurso completo, incluido
// `conflicting_sessions` cuando `mode=block` (T017-02).
export function createExceptionMutationOptions(
  queryClient: QueryClient,
): MutationOptions<AvailabilityException, Error, AvailabilityExceptionPayload> {
  return availabilityMutationOptions(queryClient, (payload) =>
    api.post<AvailabilityException>('/availability/exceptions', payload));
}

export function useCreateAvailabilityException() {
  const queryClient = useQueryClient();

  return useMutation(createExceptionMutationOptions(queryClient));
}

export function updateExceptionMutationOptions(
  queryClient: QueryClient,
  exceptionId: string,
): MutationOptions<AvailabilityException, Error, AvailabilityExceptionPayload> {
  return availabilityMutationOptions(queryClient, (payload) =>
    api.put<AvailabilityException>(`/availability/exceptions/${exceptionId}`, payload));
}

export function useUpdateAvailabilityException(exceptionId: string) {
  const queryClient = useQueryClient();

  return useMutation(updateExceptionMutationOptions(queryClient, exceptionId));
}

export function deleteExceptionMutationOptions(
  queryClient: QueryClient,
  exceptionId: string,
): MutationOptions<null, Error, void> {
  return availabilityMutationOptions(queryClient, () =>
    api.delete<null>(`/availability/exceptions/${exceptionId}`));
}

export function useDeleteAvailabilityException(exceptionId: string) {
  const queryClient = useQueryClient();

  return useMutation(deleteExceptionMutationOptions(queryClient, exceptionId));
}

export function availabilitySlotsQueryOptions(
  mentorId: string,
  from: string,
  to: string,
  durationMinutes: number,
  enabled = true,
) {
  return {
    queryKey: queryKeys.availabilitySlots(mentorId, from, to, durationMinutes),
    queryFn: async ({ signal }: { signal?: AbortSignal }): Promise<AvailabilitySlot[]> => {
      const response = await api.get<{ data: AvailabilitySlot[] }>(
        `/mentors/${mentorId}/availability/slots`,
        { from, to, duration_minutes: durationMinutes },
        { signal },
      );

      return response.data;
    },
    enabled: enabled && Boolean(mentorId),
  };
}

export function useAvailabilitySlots(
  mentorId: string,
  from: string,
  to: string,
  durationMinutes: number,
  enabled = true,
) {
  return useQuery(availabilitySlotsQueryOptions(mentorId, from, to, durationMinutes, enabled));
}
