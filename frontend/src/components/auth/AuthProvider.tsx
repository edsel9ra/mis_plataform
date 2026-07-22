'use client';

import { createContext, useContext, useState, useEffect, type ReactNode } from 'react';
import { api } from '@/lib/api';

interface PersonalityAssessment {
  id: string;
  test_version: string;
  completed_at: string | null;
}

interface User {
  id: string;
  name: string;
  last_name: string | null;
  email: string;
  sex: string | null;
  birth_date: string | null;
  client_type: string;
  role: string;
  avatar: string | null;
  locale: string;
  company_id: string | null;
  personality_assessment?: PersonalityAssessment | null;
}

interface AuthContextType {
  user: User | null;
  login: (email: string, password: string) => Promise<void>;
  register: (data: RegisterData) => Promise<void>;
  logout: () => void;
  isLoading: boolean;
  refreshUser: () => Promise<void>;
}

interface RegisterData {
  name: string;
  last_name?: string;
  email: string;
  password: string;
  sex: string;
  birth_date: string;
  client_type: string;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const token = api.getToken();
    if (token) {
      api.get<User>('/profile')
        .then(setUser)
        .catch(() => api.setToken(null))
        .finally(() => setIsLoading(false));
    } else {
      setIsLoading(false);
    }
  }, []);

  const login = async (email: string, password: string) => {
    const res = await api.post<{ user: User; token: string }>('/auth/login', { email, password });
    api.setToken(res.token);
    setUser(res.user);
  };

  const register = async (data: RegisterData) => {
    const res = await api.post<{ user: User; token: string }>('/auth/register', data);
    api.setToken(res.token);
    setUser(res.user);
  };

  const logout = () => {
    api.post('/auth/logout').catch(() => {});
    api.setToken(null);
    setUser(null);
  };

  const refreshUser = async () => {
    try {
      const profile = await api.get<User>('/profile');
      setUser(profile);
    } catch {
      logout();
    }
  };

  return (
    <AuthContext.Provider value={{ user, login, register, logout, isLoading, refreshUser }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used within AuthProvider');
  return context;
}
