import { QueryClient } from '@tanstack/react-query';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

import { api } from '@/lib/api';
import { allRelationshipsQueryOptions } from './use-relationships';

function qc() {
  return new QueryClient({ defaultOptions: { queries: { retry: false } } });
}

describe('allRelationshipsQueryOptions', () => {
  beforeEach(() => vi.clearAllMocks());

  it('fetches every page with per_page=100 so older active relationships are included', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({
        data: [{ id: 'r1', status: 'active' }],
        meta: { last_page: 2 },
      })
      .mockResolvedValueOnce({
        data: [{ id: 'r2', status: 'active', features: { emotional_check_ins: true } }],
        meta: { last_page: 2 },
      });

    const result = await qc().fetchQuery(allRelationshipsQueryOptions());

    expect(api.get).toHaveBeenCalledTimes(2);
    expect(vi.mocked(api.get).mock.calls[0][0]).toBe('/relationships');
    expect(vi.mocked(api.get).mock.calls[0][1]).toMatchObject({ page: 1, per_page: 100 });
    expect(vi.mocked(api.get).mock.calls[1][1]).toMatchObject({ page: 2, per_page: 100 });
    expect(result.map((r) => r.id)).toEqual(['r1', 'r2']);

    const withCheckIn = result.filter(
      (r) => r.status === 'active' && r.features?.emotional_check_ins,
    );
    expect(withCheckIn.map((r) => r.id)).toEqual(['r2']);
  });

  it('is disabled when told so', () => {
    expect(allRelationshipsQueryOptions(false).enabled).toBe(false);
    expect(allRelationshipsQueryOptions().enabled).toBe(true);
  });
});
