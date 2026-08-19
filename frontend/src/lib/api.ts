const API_URL = '/api/v1';
const API_TIMEOUT_MS = Number(process.env.NEXT_PUBLIC_API_TIMEOUT_MS ?? 5000);

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

    if (externalSignal?.aborted) {
      controller.abort();
    } else {
      externalSignal?.addEventListener('abort', abortFromExternalSignal, { once: true });
    }

    try {
      const response = await fetch(url, {
        ...fetchOptions,
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
      if (error instanceof ApiError) throw error;

      if (timedOut) throw new ApiError('Request timed out', 408, undefined, 'timeout');
      if (externallyAborted || controller.signal.aborted) {
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
    return JSON.parse(text) as ParsedApiPayload;
  } catch {
    return { message: text };
  }
}

export const api = new ApiClient();
