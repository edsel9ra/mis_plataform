import { QueryClient } from '@tanstack/react-query';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    postWithStatus: vi.fn(),
  },
}));

import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-keys';
import {
  bookableRelationshipsQueryOptions,
  bookSessionMutationOptions,
} from './use-booking';

function invalidatedKeys(queryClient: QueryClient): unknown[] {
  return vi.mocked(queryClient.invalidateQueries).mock.calls.map(
    ([filter]) => filter?.queryKey,
  );
}

const payload = {
  relationship_id: 'rel-1',
  session_type: 'individual',
  title: 'Sesión',
  scheduled_at: '2026-11-16T16:00:00+00:00',
  duration_minutes: 60,
  timezone: 'America/Mexico_City',
};

describe('bookableRelationshipsQueryOptions (T017-03)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('fetches GET /sessions/bookable-relationships under its own root', async () => {
    vi.mocked(api.get).mockResolvedValue({
      data: [{ id: 'r1', session_type: 'individual' }],
    });

    const options = bookableRelationshipsQueryOptions();
    const relationships = await options.queryFn({});

    expect(options.queryKey).toEqual(['bookable-relationships']);
    expect(api.get).toHaveBeenCalledWith('/sessions/bookable-relationships', undefined, { signal: undefined });
    expect(relationships[0]?.id).toBe('r1');
  });
});

describe('bookSessionMutationOptions (T017-03)', () => {
  let queryClient: QueryClient;

  beforeEach(() => {
    queryClient = new QueryClient();
    vi.clearAllMocks();
    vi.spyOn(queryClient, 'invalidateQueries');
  });

  it('reports created=true for 201 and false for a 200 idempotent retry', async () => {
    vi.mocked(api.postWithStatus).mockResolvedValueOnce({ data: { id: 's1' }, status: 201 });

    const options = bookSessionMutationOptions(queryClient);
    const result = await options.mutationFn!(payload, {} as never);

    expect(api.postWithStatus).toHaveBeenCalledWith('/sessions', payload);
    expect(result).toEqual({ session: { id: 's1' }, created: true });

    vi.mocked(api.postWithStatus).mockResolvedValueOnce({ data: { id: 's1' }, status: 200 });
    const retried = await options.mutationFn!(payload, {} as never);

    expect(retried).toEqual({ session: { id: 's1' }, created: false });
  });

  it('invalidates the sessions and availability roots on settled', async () => {
    vi.mocked(api.postWithStatus).mockResolvedValue({ data: { id: 's1' }, status: 201 });

    const options = bookSessionMutationOptions(queryClient);
    await options.mutationFn!(payload, {} as never);
    options.onSettled?.(undefined, null, payload, undefined, undefined as never);

    expect(invalidatedKeys(queryClient)).toEqual([
      [...queryKeys.sessionsRoot],
      [...queryKeys.availabilityRoot],
    ]);
  });
});
