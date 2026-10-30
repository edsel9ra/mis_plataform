import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-keys';
import { fetchSessionsRange } from './use-scheduling';

const getMock = vi.mocked(api.get);

const laravelPage = {
  data: [
    {
      id: '8f1c9f2a-0000-4000-8000-000000000001',
      title: 'Sesión de mentoría',
      session_type: 'individual',
      status: 'scheduled',
      scheduled_at: '2026-03-05T12:00:00Z',
      duration_minutes: 60,
      meet_link: null,
    },
  ],
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: null, to: null },
  links: { first: null, last: null, prev: null, next: null },
};

describe('fetchSessionsRange (contract behind useSessionsRange)', () => {
  beforeEach(() => {
    getMock.mockResolvedValue(laravelPage);
  });

  it('registers the query key `sessions` twice shared with the root invalidation', () => {
    const key = queryKeys.sessionsRange('2026-03-02T00:00:00-05:00', '2026-03-09T00:00:00-04:00', true);

    expect(key[0]).toBe('sessions');
    expect(key[1]).toBe('range');
    expect(key[2]).toEqual({
      from: '2026-03-02T00:00:00-05:00',
      to: '2026-03-09T00:00:00-04:00',
      includeCollectives: true,
    });

    // Distinct `from`/`to` produce distinct cache entries.
    expect(queryKeys.sessionsRange('a', 'b')).toEqual(queryKeys.sessionsRange('a', 'b'));
    expect(queryKeys.sessionsRange('a', 'b', false)).not.toEqual(
      queryKeys.sessionsRange('a', 'b', true),
    );
  });

  it('requests GET /sessions with from/to and normalizes the paginated response', async () => {
    const result = await fetchSessionsRange(
      '2026-03-02T00:00:00-05:00',
      '2026-03-09T00:00:00-04:00',
    );

    const [endpoint, params, options] = getMock.mock.calls[0];

    expect(endpoint).toBe('/sessions');
    expect(params).toEqual({
      from: '2026-03-02T00:00:00-05:00',
      to: '2026-03-09T00:00:00-04:00',
    });
    expect(options).toMatchObject({ signal: undefined });

    expect(result.data).toHaveLength(1);
    expect(result.data[0]?.id).toBe('8f1c9f2a-0000-4000-8000-000000000001');
    expect(result.meta).toMatchObject({
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 1,
    });
  });

  it('passes through the abort signal to the API client', async () => {
    const controller = new AbortController();

    await fetchSessionsRange('a', 'b', false, controller.signal);

    expect(getMock.mock.calls[0]?.[2]).toMatchObject({ signal: controller.signal });
  });

  it('maps includeCollectives to include_collectives=1 only when enabled', async () => {
    await fetchSessionsRange('from-a', 'to-a', true);
    await fetchSessionsRange('from-b', 'to-b', false);

    expect(getMock.mock.calls[0]?.[1]).toEqual({
      from: 'from-a',
      to: 'to-a',
      include_collectives: 1,
    });
    expect(getMock.mock.calls[1]?.[1]).toEqual({ from: 'from-b', to: 'to-b' });
  });
});
