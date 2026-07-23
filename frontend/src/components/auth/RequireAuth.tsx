'use client';

import { useEffect, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/components/auth/AuthProvider';
import { PageSpinner } from '@/components/ui/spinner';

export function RequireAuth({ children }: { children: ReactNode }) {
  const router = useRouter();
  const { user, isLoading } = useAuth();

  useEffect(() => {
    if (!isLoading && !user) {
      router.replace('/login');
    }
  }, [isLoading, router, user]);

  if (isLoading || !user) {
    return <PageSpinner />;
  }

  return <>{children}</>;
}
