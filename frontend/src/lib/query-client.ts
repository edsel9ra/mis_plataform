import { isCancelledError, QueryClient } from '@tanstack/react-query';
import { ApiError } from './api';

export function shouldRetryQuery(failureCount: number, error: unknown): boolean {
  if (failureCount >= 1 || isCancelledError(error)) return false;

  if (!(error instanceof ApiError)) return false;
  if (error.code === 'aborted') return false;
  if (error.status === 401 || error.status === 403 || error.status === 422) return false;

  return error.code === 'network' || error.code === 'timeout' || error.status >= 500;
}

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 60 * 1000,
      retry: shouldRetryQuery,
    },
  },
});
