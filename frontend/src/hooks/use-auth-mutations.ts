import { useMutation } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { LoginFormData, RegisterFormData } from '@/lib/schemas/auth';

interface AuthResponse {
  user: {
    id: string;
    name: string;
    email: string;
    client_type: string;
    role: string;
    avatar: string | null;
    locale: string;
  };
  token: string;
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
      api.post<AuthResponse>('/auth/register', data),
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
    queryFn: () => api.get<AuthResponse['user']>('/profile'),
    staleTime: 60_000,
    retry: 1,
  };
}
