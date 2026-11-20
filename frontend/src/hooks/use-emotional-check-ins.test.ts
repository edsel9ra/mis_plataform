import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-keys';
import { createEmotionalCheckIn, fetchEmotionalCheckIns } from './use-emotional-check-ins';

const REL = 'rel-123';

describe('emotional check-ins api (T020.5)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('lists the last five check-ins normalized to an array', async () => {
    const items = [{ id: 'c1' }, { id: 'c2' }];
    vi.mocked(api.get).mockResolvedValue({ data: items, links: {}, meta: {} });

    const result = await fetchEmotionalCheckIns(REL, new AbortController().signal);

    expect(api.get).toHaveBeenCalledWith(
      `/relationships/${REL}/emotional-check-ins`,
      { per_page: 5 },
      { signal: expect.any(AbortSignal) },
    );
    expect(result).toEqual(items);
  });

  it('posts the check-in payload to the relationship endpoint', async () => {
    vi.mocked(api.post).mockResolvedValue({ id: 'c9', mood_score: 4 });

    const result = await createEmotionalCheckIn(REL, { mood_score: 4, stress_score: 2, note: 'bien' });

    expect(api.post).toHaveBeenCalledWith(
      `/relationships/${REL}/emotional-check-ins`,
      { mood_score: 4, stress_score: 2, note: 'bien' },
    );
    expect(result).toEqual({ id: 'c9', mood_score: 4 });
  });

  it('scopes its query key to the relationship', () => {
    expect(queryKeys.menteeEmotionalCheckIns(REL)).toEqual(['mentee', 'emotional-check-ins', REL]);
  });
});
