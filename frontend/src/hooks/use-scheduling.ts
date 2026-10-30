import { useQuery } from '@tanstack/react-query';

import { api } from '@/lib/api';
import {
  normalizePaginatedResponse,
  type PaginatedResponse,
  type RawPaginatedResponse,
} from '@/lib/pagination';
import { queryKeys } from '@/lib/query-keys';
import type { Session } from './use-sessions';

export type SessionsRangeResponse = PaginatedResponse<Session>;

/**
 * Fetches a calendar range (RF-009.1). The backend already applies the
 * participant/responsable/support authorization (T009-01); this transport
 * only carries `from`/`to` over the single MIS `GET /sessions` source.
 */
export function fetchSessionsRange(
  from: string,
  to: string,
  includeCollectives = false,
  signal?: AbortSignal,
  perPage?: number,
): Promise<SessionsRangeResponse> {
  const params: Record<string, string | number | undefined> = { from, to };

  if (includeCollectives) params.include_collectives = 1;
  if (perPage !== undefined) params.per_page = perPage;

  return api
    .get<RawPaginatedResponse<Session>>('/sessions', params, { signal })
    .then((response) => normalizePaginatedResponse(response, { page: 1, perPage: perPage ?? 20 }));
}

export interface UseSessionsRangeOptions {
  includeCollectives?: boolean;
  enabled?: boolean;
  /** Optional backend `per_page` override (max 100) for wide calendar months. */
  perPage?: number;
}

/**
 * Reads sessions for a previously derived calendar range
 * (`CalendarRange.forPeriod`, spec 009 RF-009.2). `from`/`to` come from the
 * pure range function, never from a global clock.
 */
export function useSessionsRange(
  from: string,
  to: string,
  options: UseSessionsRangeOptions = {},
) {
  const includeCollectives = options.includeCollectives ?? false;
  const enabled = (options.enabled ?? true) && Boolean(from && to);

  return useQuery({
    queryKey: queryKeys.sessionsRange(from, to, includeCollectives),
    queryFn: ({ signal }) => fetchSessionsRange(from, to, includeCollectives, signal, options.perPage),
    enabled,
  });
}
