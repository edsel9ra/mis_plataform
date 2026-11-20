import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

import { api } from '@/lib/api';
import { updateProfileDetails, uploadAvatar } from './use-profile';

describe('updateProfileDetails (T020.4)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('sends PUT /profile with the payload', async () => {
    vi.mocked(api.put).mockResolvedValue({ data: { id: 'u1' } });

    await updateProfileDetails({ name: 'Ana', last_name: null, timezone: 'Asia/Kolkata' });

    expect(api.put).toHaveBeenCalledWith('/profile', { name: 'Ana', last_name: null, timezone: 'Asia/Kolkata' });
  });
});

describe('uploadAvatar (T020.4)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('posts multipart FormData with avatar and _method=PUT to /profile/avatar', async () => {
    vi.mocked(api.post).mockResolvedValue({ avatar: 'http://x/storage/avatars/h.png' });

    const file = new File(['x'], 'a.png', { type: 'image/png' });
    const result = await uploadAvatar(file);

    expect(result).toEqual({ avatar: 'http://x/storage/avatars/h.png' });
    const [endpoint, body, options] = vi.mocked(api.post).mock.calls[0];
    expect(endpoint).toBe('/profile/avatar');
    expect(body).toBeInstanceOf(FormData);
    expect((body as FormData).get('avatar')).toBe(file);
    expect((body as FormData).get('_method')).toBe('PUT');
    expect(options).toMatchObject({ timeoutMs: expect.any(Number) });
  });
});
