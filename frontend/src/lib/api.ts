const API_URL = '/api/v1';

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

interface RequestOptions extends RequestInit {
  params?: Record<string, string | number | undefined>;
}

class ApiClient {
  private token: string | null = null;

  setToken(token: string | null) {
    this.token = token;
    if (typeof window === 'undefined') return;

    if (token) {
      localStorage.setItem('auth_token', token);
    } else {
      localStorage.removeItem('auth_token');
    }
  }

  getToken(): string | null {
    if (typeof window === 'undefined') return this.token;

    if (!this.token) {
      this.token = localStorage.getItem('auth_token');
    }
    return this.token;
  }

  private async request<T>(endpoint: string, options: RequestOptions = {}): Promise<T> {
    const { params, ...fetchOptions } = options;

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

    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(fetchOptions.headers as Record<string, string>),
    };

    const token = this.getToken();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const response = await fetch(url, { ...fetchOptions, headers, credentials: 'include' });
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
  }

  get<T>(endpoint: string, params?: Record<string, string | number | undefined>): Promise<T> {
    return this.request<T>(endpoint, { method: 'GET', params });
  }

  post<T>(endpoint: string, data?: unknown): Promise<T> {
    return this.request<T>(endpoint, { method: 'POST', body: JSON.stringify(data) });
  }

  put<T>(endpoint: string, data?: unknown): Promise<T> {
    return this.request<T>(endpoint, { method: 'PUT', body: JSON.stringify(data) });
  }

  delete<T>(endpoint: string): Promise<T> {
    return this.request<T>(endpoint, { method: 'DELETE' });
  }
}

function safeJsonParse(text: string): any {
  try {
    return JSON.parse(text);
  } catch {
    return { message: text };
  }
}

export const api = new ApiClient();
