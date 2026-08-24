const API_URL = '/api/v1';
const API_TIMEOUT_MS = Number(process.env.NEXT_PUBLIC_API_TIMEOUT_MS ?? 5000);
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const XSRF_COOKIE_NAME = 'XSRF-TOKEN';
const UNSAFE_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);
const DEFAULT_LOCALE = 'es';
const SUPPORTED_LOCALES = new Set(['es', 'en', 'pt']);

let csrfRequest: Promise<void> | null = null;

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public errors?: Record<string, string[]>,
    public code: 'http' | 'aborted' | 'timeout' | 'network' = 'http',
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export interface ApiRequestOptions extends RequestInit {
  params?: Record<string, string | number | undefined>;
  timeoutMs?: number;
}

class ApiClient {
  setToken(_token: string | null) {
    void _token;
    if (typeof window === 'undefined') return;

    // Legacy cleanup: auth now uses an HttpOnly cookie set by Laravel.
    localStorage.removeItem('auth_token');
  }

  getToken(): string | null {
    if (typeof window !== 'undefined') {
      localStorage.removeItem('auth_token');
    }

    return null;
  }

  private async request<T>(endpoint: string, options: ApiRequestOptions = {}): Promise<T> {
    const { params, timeoutMs = API_TIMEOUT_MS, ...fetchOptions } = options;
    const controller = new AbortController();
    const externalSignal = fetchOptions.signal;
    const method = (fetchOptions.method ?? 'GET').toUpperCase();
    let timedOut = false;
    let externallyAborted = externalSignal?.aborted ?? false;
    const timeout = setTimeout(() => {
      timedOut = true;
      controller.abort();
    }, timeoutMs);
    const abortFromExternalSignal = () => {
      externallyAborted = true;
      controller.abort();
    };

    let url = `${API_URL}${endpoint}`;

    if (params) {
      const searchParams = new URLSearchParams();
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined) {
          searchParams.append(key, String(value));
        }
      });
      const qs = searchParams.toString();
      if (qs) url += `?${qs}`;
    }

    const headers = new Headers(fetchOptions.headers);
    if (!headers.has('Content-Type')) headers.set('Content-Type', 'application/json');
    if (!headers.has('Accept')) headers.set('Accept', 'application/json');
    if (!headers.has('Accept-Language')) headers.set('Accept-Language', currentLocale());
    if (!headers.has('X-MIS-Locale')) headers.set('X-MIS-Locale', currentLocale());

    if (externalSignal?.aborted) {
      controller.abort();
    } else {
      externalSignal?.addEventListener('abort', abortFromExternalSignal, { once: true });
    }

    try {
      if (UNSAFE_METHODS.has(method)) {
        await ensureCsrfCookie(controller.signal);
      }

      const xsrfToken = readCookie(XSRF_COOKIE_NAME);
      if (xsrfToken && UNSAFE_METHODS.has(method)) {
        headers.set('X-XSRF-TOKEN', xsrfToken);
      }

      const response = await fetch(url, {
        ...fetchOptions,
        method,
        headers,
        credentials: 'include',
        signal: controller.signal,
      });
      const text = await response.text();
      const payload = text ? safeJsonParse(text) : null;

      if (!response.ok) {
        if (response.status === 401 && typeof window !== 'undefined') {
          window.dispatchEvent(new Event('auth:unauthorized'));
        }

        throw new ApiError(
          payload?.message || payload?.error || `HTTP ${response.status}`,
          response.status,
          payload?.errors,
        );
      }

      return payload as T;
    } catch (error) {
      if (timedOut) throw new ApiError('Request timed out', 408, undefined, 'timeout');
      if (externallyAborted || externalSignal?.aborted) {
        throw new ApiError('Request canceled', 0, undefined, 'aborted');
      }
      if (error instanceof ApiError) throw error;
      if (controller.signal.aborted) {
        throw new ApiError('Request canceled', 0, undefined, 'aborted');
      }

      throw new ApiError('Network request failed', 0, undefined, 'network');
    } finally {
      clearTimeout(timeout);
      externalSignal?.removeEventListener('abort', abortFromExternalSignal);
    }
  }

  get<T>(
    endpoint: string,
    params?: Record<string, string | number | undefined>,
    options: ApiRequestOptions = {},
  ): Promise<T> {
    return this.request<T>(endpoint, { ...options, method: 'GET', params });
  }

  post<T>(endpoint: string, data?: unknown, options: ApiRequestOptions = {}): Promise<T> {
    return this.request<T>(endpoint, { ...options, method: 'POST', body: JSON.stringify(data) });
  }

  put<T>(endpoint: string, data?: unknown, options: ApiRequestOptions = {}): Promise<T> {
    return this.request<T>(endpoint, { ...options, method: 'PUT', body: JSON.stringify(data) });
  }

  delete<T>(endpoint: string, options: ApiRequestOptions = {}): Promise<T> {
    return this.request<T>(endpoint, { ...options, method: 'DELETE' });
  }
}

interface ParsedApiPayload {
  message?: string;
  error?: string;
  errors?: Record<string, string[]>;
}

function safeJsonParse(text: string): ParsedApiPayload {
  try {
    const parsed: unknown = JSON.parse(text);

    if (parsed && typeof parsed === 'object') {
      return parsed as ParsedApiPayload;
    }

    return { message: typeof parsed === 'string' ? parsed : text };
  } catch {
    return { message: text };
  }
}

function readCookie(name: string): string | null {
  if (typeof document === 'undefined') return null;

  const cookie = document.cookie
    .split(';')
    .map((item) => item.trim())
    .find((item) => item.startsWith(`${name}=`));

  if (!cookie) return null;

  const value = cookie.slice(name.length + 1);

  try {
    return decodeURIComponent(value);
  } catch {
    return value;
  }
}

function currentLocale(): string {
  if (typeof document === 'undefined') return DEFAULT_LOCALE;

  const locale = document.documentElement.lang.split('-')[0].toLowerCase();

  return SUPPORTED_LOCALES.has(locale) ? locale : DEFAULT_LOCALE;
}

async function ensureCsrfCookie(signal: AbortSignal): Promise<void> {
  if (typeof window === 'undefined' || readCookie(XSRF_COOKIE_NAME)) return;

  if (!csrfRequest) {
    const request = fetch(CSRF_COOKIE_URL, {
      method: 'GET',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
      .then((response) => {
        if (!response.ok) {
          throw new ApiError(`CSRF cookie request failed (${response.status})`, response.status);
        }
      })
      .catch((error) => {
        if (error instanceof ApiError) throw error;
        throw new ApiError('CSRF cookie request failed', 0, undefined, 'network');
      })
    csrfRequest = request.finally(() => {
      csrfRequest = null;
    });
    void csrfRequest.catch(() => undefined);
  }

  await waitForAbort(csrfRequest, signal);
}

function waitForAbort<T>(promise: Promise<T>, signal: AbortSignal): Promise<T> {
  if (signal.aborted) {
    return Promise.reject(new DOMException('Aborted', 'AbortError'));
  }

  return new Promise<T>((resolve, reject) => {
    let settled = false;
    const cleanup = () => signal.removeEventListener('abort', onAbort);
    const onAbort = () => {
      settled = true;
      cleanup();
      reject(new DOMException('Aborted', 'AbortError'));
    };

    signal.addEventListener('abort', onAbort, { once: true });
    promise.then(
      (value) => {
        cleanup();
        if (!settled) resolve(value);
      },
      (error) => {
        cleanup();
        if (!settled) reject(error);
      },
    );
  });
}

export const api = new ApiClient();
