import { useMutation } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { shouldRetryQuery } from '@/lib/query-client';
import type { Subscription, User } from '@/components/auth/AuthProvider';
import type { LoginFormData, RegisterFormData } from '@/lib/schemas/auth';

interface AuthResponse {
  user: User;
  subscription?: Subscription;
}

export function useLoginMutation() {
  return useMutation({
    mutationFn: (data: LoginFormData) =>
      api.post<AuthResponse>('/auth/login', data),
  });
}

export function useRegisterMutation() {
  return useMutation({
    mutationFn: (data: RegisterFormData) =>
      api.post<AuthResponse & { subscription: Subscription }>('/auth/register', data),
  });
}

export function useLogoutMutation() {
  return useMutation({
    mutationFn: () => api.post('/auth/logout'),
  });
}

export function useProfileQuery() {
  return {
    queryKey: ['profile'],
    queryFn: ({ signal }: { signal: AbortSignal }) => api.get<AuthResponse['user']>('/profile', undefined, { signal }),
    staleTime: 60_000,
    retry: shouldRetryQuery,
  };
}
