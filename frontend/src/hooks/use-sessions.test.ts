import { QueryClient } from '@tanstack/react-query';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { api, ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/query-keys';
import {
  cancelSessionMutationOptions,
  completeSessionMutationOptions,
  confirmAttendanceMutationOptions,
  rescheduleSessionMutationOptions,
} from './use-sessions';

vi.mock('@/lib/api', async (importOriginal) => {
  const original = await importOriginal<typeof import('@/lib/api')>();

  return {
    ...original,
    api: {
      get: vi.fn(),
      post: vi.fn(),
      put: vi.fn(),
      delete: vi.fn(),
    },
  };
});

const ID = 'session-1';

function invalidatedKeys(queryClient: QueryClient): unknown[] {
  return vi.mocked(queryClient.invalidateQueries).mock.calls.map(
    ([filter]) => filter?.queryKey,
  );
}

describe('session action mutation options (T016-07)', () => {
  let queryClient: QueryClient;

  beforeEach(() => {
    queryClient = new QueryClient();
    vi.spyOn(queryClient, 'invalidateQueries');
    vi.clearAllMocks();
    vi.spyOn(queryClient, 'invalidateQueries');
  });

  it('reschedules via PUT /sessions/{id}/reschedule with the payload', async () => {
    const payload = { scheduled_at: '2026-11-20T16:30:00Z', duration_minutes: 60, timezone: 'America/Mexico_City' };
    vi.mocked(api.put).mockResolvedValue({ id: ID });

    const options = rescheduleSessionMutationOptions(queryClient, ID);
    await options.mutationFn!(payload, {} as never);
    options.onSettled?.(undefined, null, payload, undefined, undefined as never);

    expect(api.put).toHaveBeenCalledWith(`/sessions/${ID}/reschedule`, payload);
    expect(invalidatedKeys(queryClient)).toEqual([[...queryKeys.session(ID)], [...queryKeys.sessionsRoot]]);
  });

  it('cancels via POST /sessions/{id}/cancel with the reason', async () => {
    vi.mocked(api.post).mockResolvedValue({ id: ID });

    const options = cancelSessionMutationOptions(queryClient, ID);
    await options.mutationFn!({ reason: 'conflicto' }, {} as never);
    options.onSettled?.(undefined, null, { reason: 'conflicto' }, undefined, undefined as never);

    expect(api.post).toHaveBeenCalledWith(`/sessions/${ID}/cancel`, { reason: 'conflicto' });
    expect(invalidatedKeys(queryClient)).toEqual([[...queryKeys.session(ID)], [...queryKeys.sessionsRoot]]);
  });

  it('completes via PUT /sessions/{id}/status with status=completed', async () => {
    vi.mocked(api.put).mockResolvedValue({ id: ID });

    const options = completeSessionMutationOptions(queryClient, ID);
    await options.mutationFn!(undefined, {} as never);
    options.onSettled?.(undefined, null, undefined, undefined, undefined as never);

    expect(api.put).toHaveBeenCalledWith(`/sessions/${ID}/status`, { status: 'completed' });
    expect(invalidatedKeys(queryClient)).toEqual([[...queryKeys.session(ID)], [...queryKeys.sessionsRoot]]);
  });

  it('confirms attendance, omitting attendee_user_id for the self entry', async () => {
    vi.mocked(api.put).mockResolvedValue({ id: ID });

    const options = confirmAttendanceMutationOptions(queryClient, ID);
    await options.mutationFn!({ status: 'attended' }, {} as never);
    await options.mutationFn!({ status: 'absent', attendee_user_id: 'u-9' }, {} as never);

    expect(api.put).toHaveBeenNthCalledWith(1, `/sessions/${ID}/attendance`, { status: 'attended' });
    expect(api.put).toHaveBeenNthCalledWith(2, `/sessions/${ID}/attendance`, { status: 'absent', attendee_user_id: 'u-9' });
  });

  it('invalidates on rejection too (RF-016.10: refresh after a domain 422)', async () => {
    vi.mocked(api.put).mockRejectedValue(new ApiError('unprocessable', 422, { session: ['La sesión ya está cerrada.'] }));

    const options = rescheduleSessionMutationOptions(queryClient, ID);
    const payload = { scheduled_at: '2026-11-20T16:30:00Z', duration_minutes: 60, timezone: 'America/Mexico_City' };

    await expect(options.mutationFn!(payload, {} as never)).rejects.toBeInstanceOf(ApiError);
    options.onSettled?.(undefined, new ApiError('unprocessable', 422), payload, undefined, undefined as never);

    expect(invalidatedKeys(queryClient)).toEqual([[...queryKeys.session(ID)], [...queryKeys.sessionsRoot]]);
  });
});
