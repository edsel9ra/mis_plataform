import { QueryClient } from '@tanstack/react-query';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

import { api } from '@/lib/api';
import {
  mentorProgressHistoryQueryOptions,
  progressHistoryQueryOptions,
} from './use-progress';

function qc() {
  return new QueryClient({ defaultOptions: { queries: { retry: false } } });
}

const EMPTY_PAGE = { data: [], undated: [], next_cursor: null };

describe('progressHistoryQueryOptions (T015.8)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('requests the first page of the own history with the page size', async () => {
    vi.mocked(api.get).mockResolvedValue(EMPTY_PAGE);

    await qc().fetchInfiniteQuery(progressHistoryQueryOptions());

    expect(api.get).toHaveBeenCalledTimes(1);
    const [endpoint, params] = vi.mocked(api.get).mock.calls[0];
    expect(endpoint).toBe('/progress/history');
    expect(params).toEqual({ limit: 25 });
  });

  it('advances through the server cursor verbatim', async () => {
    vi.mocked(api.get).mockResolvedValue(EMPTY_PAGE);
    const options = progressHistoryQueryOptions();

    await qc().fetchInfiniteQuery({ ...options, initialPageParam: 'CURSOR-ABC' });

    const [, params] = vi.mocked(api.get).mock.calls[0];
    expect(params).toEqual({ cursor: 'CURSOR-ABC', limit: 25 });
  });

  it('exposes the server cursor as next page param and stops on null', () => {
    const options = progressHistoryQueryOptions();

    expect(
      options.getNextPageParam?.(
        { data: [], undated: [], next_cursor: 'NEXT' } as never,
        [] as never,
        null,
        [] as never,
      ),
    ).toBe('NEXT');
    expect(
      options.getNextPageParam?.(
        { data: [], undated: [], next_cursor: null } as never,
        [] as never,
        null,
        [] as never,
      ),
    ).toBeUndefined();
  });

  it('keys the query under the progress history key', () => {
    expect(progressHistoryQueryOptions().queryKey).toEqual(['progress', 'history', 'self']);
  });
});

describe('mentorProgressHistoryQueryOptions (T015.8)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('requests the mentee history through the mentor endpoint', async () => {
    vi.mocked(api.get).mockResolvedValue(EMPTY_PAGE);

    await qc().fetchInfiniteQuery(mentorProgressHistoryQueryOptions('u-9'));

    const [endpoint, params] = vi.mocked(api.get).mock.calls[0];
    expect(endpoint).toBe('/mentor/users/u-9/progress/history');
    expect(params).toEqual({ limit: 25 });
  });

  it('is disabled without a user id or when told so', () => {
    expect(mentorProgressHistoryQueryOptions('').enabled).toBe(false);
    expect(mentorProgressHistoryQueryOptions('u-9', false).enabled).toBe(false);
    expect(mentorProgressHistoryQueryOptions('u-9').enabled).toBe(true);
    expect(progressHistoryQueryOptions(false).enabled).toBe(false);
  });

  it('keys the query under the mentor history key', () => {
    expect(mentorProgressHistoryQueryOptions('u-9').queryKey).toEqual([
      'mentor', 'progress', 'users', 'u-9', 'history',
    ]);
  });
});
