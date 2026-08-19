'use client';

import { createContext, useCallback, useContext, useState, useEffect, type ReactNode } from 'react';
import { ApiError, api } from '@/lib/api';
import { queryClient } from '@/lib/query-client';

interface PersonalityAssessment {
  id: string;
  test_version: string;
  completed_at: string | null;
}

export interface User {
  id: string;
  name: string;
  last_name: string | null;
  email: string;
  sex: string | null;
  birth_date: string | null;
  client_type: string | null;
  role: string;
  avatar: string | null;
  locale: string;
  company_id: string | null;
  personality_assessment?: PersonalityAssessment | null;
}

export interface Subscription {
  id: string;
  plan_id: string;
  status: string;
  trial_ends_at: string | null;
  starts_at: string | null;
  ends_at: string | null;
}

interface RegistrationResponse {
  user: User;
  subscription: Subscription;
}

interface AuthContextType {
  user: User | null;
  login: (email: string, password: string) => Promise<User>;
  register: (data: RegisterData) => Promise<RegistrationResponse>;
  logout: () => Promise<void>;
  isLoading: boolean;
  authError: string | null;
  refreshUser: () => Promise<User>;
}

interface RegisterData {
  name: string;
  last_name?: string;
  email: string;
  password: string;
  sex: string;
  birth_date: string;
  client_type: string;
  plan_id: string;
  locale: 'es' | 'en' | 'pt';
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({
  children,
  initialHasAuthCookie = false,
}: {
  children: ReactNode;
  initialHasAuthCookie?: boolean;
}) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(initialHasAuthCookie);
  const [authError, setAuthError] = useState<string | null>(null);

  const clearSession = useCallback(() => {
    api.setToken(null);
    setUser(null);
    setAuthError(null);
    queryClient.clear();
  }, []);

  useEffect(() => {
    const handleUnauthorized = () => clearSession();

    window.addEventListener('auth:unauthorized', handleUnauthorized);

    if (!initialHasAuthCookie) {
      setIsLoading(false);
      return () => window.removeEventListener('auth:unauthorized', handleUnauthorized);
    }

    api.get<User>('/profile')
      .then((profile) => {
        setUser(profile);
        setAuthError(null);
      })
      .catch((error) => {
        if (error instanceof ApiError && error.status === 401) {
          clearSession();
          return;
        }

        setAuthError(error instanceof Error ? error.message : 'Unable to verify session');
      })
      .finally(() => setIsLoading(false));

    return () => window.removeEventListener('auth:unauthorized', handleUnauthorized);
  }, [clearSession, initialHasAuthCookie]);

  const login = useCallback(async (email: string, password: string) => {
    const res = await api.post<{ user: User }>('/auth/login', { email, password });
    queryClient.clear();
    setAuthError(null);
    setUser(res.user);

    return res.user;
  }, []);

  const register = useCallback(async (data: RegisterData) => {
    const res = await api.post<RegistrationResponse>('/auth/register', data);
    queryClient.clear();
    setAuthError(null);
    setUser(res.user);

    return res;
  }, []);

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout');
    } catch (error) {
      clearSession();

      if (error instanceof ApiError && error.status === 401) return;

      setAuthError(error instanceof Error ? error.message : 'Unable to log out');
      throw error;
    }

    clearSession();
  }, [clearSession]);

  const refreshUser = useCallback(async (): Promise<User> => {
    try {
      const profile = await api.get<User>('/profile');
      setUser(profile);
      setAuthError(null);

      return profile;
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        clearSession();
      } else {
        setAuthError(error instanceof Error ? error.message : 'Unable to verify session');
      }

      throw error;
    }
  }, [clearSession]);

  return (
    <AuthContext.Provider value={{ user, login, register, logout, isLoading, authError, refreshUser }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used within AuthProvider');
  return context;
}
