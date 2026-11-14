import { QueryClient } from '@tanstack/react-query';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

import { api } from '@/lib/api';
import {
  menteeChallengesQueryOptions,
  updateChallengeStatusMutationOptions,
} from './use-challenges';

function qc() {
  return new QueryClient({ defaultOptions: { queries: { retry: false } } });
}

describe('menteeChallengesQueryOptions (T012.9)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('fetches every page of the relationship challenges endpoint', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ data: [{ id: 'c1' }], meta: { last_page: 2 } })
      .mockResolvedValueOnce({ data: [{ id: 'c2' }], meta: { last_page: 2 } });

    const result = await qc().fetchQuery(menteeChallengesQueryOptions('rel-1'));

    expect(api.get).toHaveBeenCalledTimes(2);
    expect(vi.mocked(api.get).mock.calls[0][0]).toBe('/relationships/rel-1/challenges');
    expect(result.map((c) => c.id)).toEqual(['c1', 'c2']);
  });

  it('is disabled without a relationship id or when told so', () => {
    expect(menteeChallengesQueryOptions('').enabled).toBe(false);
    expect(menteeChallengesQueryOptions('rel-1', false).enabled).toBe(false);
    expect(menteeChallengesQueryOptions('rel-1').enabled).toBe(true);
  });
});

describe('updateChallengeStatusMutationOptions (T012.10)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('PUTs the new status to the mentor endpoint with the relationship scope', async () => {
    vi.mocked(api.put).mockResolvedValue({ id: 'c1', status: 'abandoned' });
    const client = qc();

    await updateChallengeStatusMutationOptions('c1', 'rel-1', client).mutationFn!('abandoned');

    expect(api.put).toHaveBeenCalledWith(
      '/mentor/challenges/c1',
      { status: 'abandoned' },
      { params: { relationship_id: 'rel-1' } },
    );
  });
});
