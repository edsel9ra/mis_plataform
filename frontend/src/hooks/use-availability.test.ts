import { QueryClient } from '@tanstack/react-query';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-keys';
import {
  availabilityExceptionsQueryOptions,
  availabilitySlotsQueryOptions,
  availabilityWindowsQueryOptions,
  createExceptionMutationOptions,
  createWindowMutationOptions,
  deleteExceptionMutationOptions,
  deleteWindowMutationOptions,
  updateExceptionMutationOptions,
  updateWindowMutationOptions,
} from './use-availability';

const windowPayload = {
  day_of_week: 2,
  start_time: '09:00',
  end_time: '13:00',
  timezone: 'America/Mexico_City',
};

const exceptionPayload = {
  mode: 'block' as const,
  start_date: '2026-12-24',
  end_date: null,
  start_time: null,
  end_time: null,
  timezone: 'UTC',
};

function invalidatedKeys(queryClient: QueryClient): unknown[] {
  return vi.mocked(queryClient.invalidateQueries).mock.calls.map(
    ([filter]) => filter?.queryKey,
  );
}

describe('availability query options (T017-03)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('fetches windows and exceptions through the collection envelope', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: [{ id: 'w1' }, { id: 'w2' }] });

    const windows = await availabilityWindowsQueryOptions().queryFn({});
    const exceptions = await availabilityExceptionsQueryOptions().queryFn({});

    expect(api.get).toHaveBeenCalledWith('/availability/windows', undefined, { signal: undefined });
    expect(api.get).toHaveBeenCalledWith('/availability/exceptions', undefined, { signal: undefined });
    expect(windows).toHaveLength(2);
    expect(exceptions[0]?.id).toBe('w1');
  });

  it('fetches slots with from/to/duration and the slots query key', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: [{ start: 's', end: 'e', timezone: 'UTC' }] });

    const options = availabilitySlotsQueryOptions('m-1', 'f', 't', 60, false);
    const slots = await options.queryFn({});

    expect(options.queryKey).toEqual(['availability', 'slots', { mentorId: 'm-1', from: 'f', to: 't', duration_minutes: 60 }]);
    expect(options.enabled).toBe(false);
    expect(api.get).toHaveBeenCalledWith(
      '/mentors/m-1/availability/slots',
      { from: 'f', to: 't', duration_minutes: 60 },
      { signal: undefined },
    );
    expect(slots).toHaveLength(1);
    expect(availabilitySlotsQueryOptions('m-1', 'f', 't', 60).enabled).toBe(true);
    expect(availabilitySlotsQueryOptions('', 'f', 't', 60).enabled).toBe(false);
  });
});

describe('availability mutation options (T017-03)', () => {
  let queryClient: QueryClient;

  beforeEach(() => {
    queryClient = new QueryClient();
    vi.clearAllMocks();
    vi.spyOn(queryClient, 'invalidateQueries');
  });

  it('creates, updates and deletes windows, invalidating the availability root', async () => {
    vi.mocked(api.post).mockResolvedValue({ id: 'w1' });
    vi.mocked(api.put).mockResolvedValue({ id: 'w1' });
    vi.mocked(api.delete).mockResolvedValue(null);

    const create = createWindowMutationOptions(queryClient);
    await create.mutationFn!(windowPayload, {} as never);
    create.onSettled?.(undefined, null, windowPayload, undefined, undefined as never);

    expect(api.post).toHaveBeenCalledWith('/availability/windows', windowPayload);
    expect(invalidatedKeys(queryClient)).toEqual([[...queryKeys.availabilityRoot]]);

    const update = updateWindowMutationOptions(queryClient, 'w1');
    await update.mutationFn!(windowPayload, {} as never);
    expect(api.put).toHaveBeenCalledWith('/availability/windows/w1', windowPayload);

    const remove = deleteWindowMutationOptions(queryClient, 'w1');
    await remove.mutationFn!(undefined, {} as never);
    expect(api.delete).toHaveBeenCalledWith('/availability/windows/w1');
  });

  it('creates and updates exceptions returning the full resource (incl. conflicting_sessions)', async () => {
    const created = { id: 'e1', conflicting_sessions: [{ id: 's1' }] };
    vi.mocked(api.post).mockResolvedValue(created);
    vi.mocked(api.put).mockResolvedValue(created);
    vi.mocked(api.delete).mockResolvedValue(null);

    const create = createExceptionMutationOptions(queryClient);
    const result = await create.mutationFn!(exceptionPayload, {} as never);
    create.onSettled?.(undefined, null, exceptionPayload, undefined, undefined as never);

    expect(api.post).toHaveBeenCalledWith('/availability/exceptions', exceptionPayload);
    expect(result).toEqual(created);
    expect(invalidatedKeys(queryClient)).toEqual([[...queryKeys.availabilityRoot]]);

    const update = updateExceptionMutationOptions(queryClient, 'e1');
    const updated = await update.mutationFn!(exceptionPayload, {} as never);
    expect(api.put).toHaveBeenCalledWith('/availability/exceptions/e1', exceptionPayload);
    expect(updated).toEqual(created);

    const remove = deleteExceptionMutationOptions(queryClient, 'e1');
    await remove.mutationFn!(undefined, {} as never);
    expect(api.delete).toHaveBeenCalledWith('/availability/exceptions/e1');
  });
});
