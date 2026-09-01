import { afterEach, describe, expect, it, vi } from 'vitest';

import { api } from './api';

function abortablePendingFetch() {
  return vi.fn((_input: RequestInfo | URL, init?: RequestInit) =>
    new Promise<Response>((_resolve, reject) => {
      const rejectAsAborted = () => reject(new DOMException('Aborted', 'AbortError'));

      if (init?.signal?.aborted) {
        rejectAsAborted();
      } else {
        init?.signal?.addEventListener('abort', rejectAsAborted, { once: true });
      }
    }),
  );
}

afterEach(() => {
  vi.useRealTimers();
});

describe('ApiClient error normalization', () => {
  it('normalizes its timeout and aborts the underlying request', async () => {
    vi.useFakeTimers();
    const fetchMock = abortablePendingFetch();
    vi.stubGlobal('fetch', fetchMock);

    const assertion = expect(api.get('/slow', undefined, { timeoutMs: 50 })).rejects.toMatchObject({
      name: 'ApiError',
      message: 'Request timed out',
      status: 408,
      code: 'timeout',
    });

    await vi.advanceTimersByTimeAsync(50);
    await assertion;
    expect(fetchMock.mock.calls[0]?.[1]?.signal?.aborted).toBe(true);
  });

  it('distinguishes an external abort from a timeout', async () => {
    vi.stubGlobal('fetch', abortablePendingFetch());
    const controller = new AbortController();
    const request = api.get('/canceled', undefined, { signal: controller.signal, timeoutMs: 1000 });

    controller.abort();

    await expect(request).rejects.toMatchObject({
      message: 'Request canceled',
      status: 0,
      code: 'aborted',
    });
  });

  it('preserves structured HTTP errors', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({
      message: 'Validation failed',
      errors: { email: ['The email is invalid.'] },
    }), {
      status: 422,
      headers: { 'Content-Type': 'application/json' },
    })));

    await expect(api.post('/users', {})).rejects.toMatchObject({
      message: 'Validation failed',
      status: 422,
      code: 'http',
      errors: { email: ['The email is invalid.'] },
    });
  });

  it('uses response text for non-JSON HTTP errors', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('Bad gateway', { status: 502 })));

    await expect(api.get('/upstream')).rejects.toMatchObject({
      message: 'Bad gateway',
      status: 502,
      code: 'http',
    });
  });

  it('preserves JSON null responses', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('null', { status: 200 })));

    await expect(api.get<null>('/subscriptions/active')).resolves.toBeNull();
  });

  it('normalizes fetch failures as network errors', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('socket closed')));

    await expect(api.get('/offline')).rejects.toMatchObject({
      message: 'Network request failed',
      status: 0,
      code: 'network',
    });
  });

  it('sends credentials and query parameters without a token in local storage', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: [] }), { status: 200 }));
    vi.stubGlobal('fetch', fetchMock);

    await api.get('/sessions', { page: 2, per_page: 20, search: undefined });

    expect(fetchMock).toHaveBeenCalledWith('/api/v1/sessions?page=2&per_page=20', expect.objectContaining({
      credentials: 'include',
      method: 'GET',
    }));
    expect((fetchMock.mock.calls[0]?.[1]?.headers as Headers).get('Accept-Language')).toBe('es');
    expect((fetchMock.mock.calls[0]?.[1]?.headers as Headers).get('X-MIS-Locale')).toBe('es');
  });

  it('sends the active interface locale to the API', async () => {
    vi.stubGlobal('document', { documentElement: { lang: 'en-US' }, cookie: '' });
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: [] }), { status: 200 }));
    vi.stubGlobal('fetch', fetchMock);

    await api.get('/sessions');

    expect((fetchMock.mock.calls[0]?.[1]?.headers as Headers).get('Accept-Language')).toBe('en');
    expect((fetchMock.mock.calls[0]?.[1]?.headers as Headers).get('X-MIS-Locale')).toBe('en');
  });

  it('sends FormData unchanged without setting a JSON content type', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ id: 'challenge-1' }), { status: 200 }));
    vi.stubGlobal('fetch', fetchMock);
    const formData = new FormData();
    formData.append('title', 'Focus practice');
    formData.append('content_file', new Blob(['content'], { type: 'text/plain' }), 'practice.txt');

    await api.post('/mentor/challenges', formData);

    const init = fetchMock.mock.calls[0]?.[1];
    expect(init?.body).toBe(formData);
    expect((init?.headers as Headers).get('Content-Type')).toBeNull();
  });
});
