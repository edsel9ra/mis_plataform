import { QueryClient } from '@tanstack/react-query';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-keys';
import { startSubscriptionMutationOptions } from './use-mentor-workspace';

function invalidatedKeys(queryClient: QueryClient): unknown[] {
  return vi.mocked(queryClient.invalidateQueries).mock.calls.map(
    ([filter]) => filter?.queryKey,
  );
}

describe('start subscription mutation options (PR #9 review)', () => {
  let queryClient: QueryClient;

  beforeEach(() => {
    queryClient = new QueryClient();
    vi.clearAllMocks();
    vi.spyOn(queryClient, 'invalidateQueries');
  });

  it('posts the plan and invalidates the subscription and the relationships', async () => {
    vi.mocked(api.post).mockResolvedValue({ id: 'sub-1' });

    const options = startSubscriptionMutationOptions(queryClient);
    await options.mutationFn!('plan-1', {} as never);
    options.onSuccess?.({ id: 'sub-1' } as never, 'plan-1', undefined, undefined as never);

    expect(api.post).toHaveBeenCalledWith('/subscriptions', { plan_id: 'plan-1' });
    // relationships cover list + detail: a fresh plan can unlock features such
    // as the mentee check-in card (features.emotional_check_ins).
    expect(invalidatedKeys(queryClient)).toEqual([
      [...queryKeys.activeSubscription],
      [...queryKeys.relationshipsRoot],
    ]);
  });
});
