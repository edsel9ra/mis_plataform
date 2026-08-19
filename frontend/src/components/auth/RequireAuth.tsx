'use client';

import { useEffect, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { useLocale } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';
import { SessionVerificationFallback } from '@/components/auth/SessionVerificationFallback';
import { PageSpinner } from '@/components/ui/spinner';
import { localePath } from '@/i18n/paths';

export function RequireAuth({ children }: { children: ReactNode }) {
  const router = useRouter();
  const locale = useLocale();
  const { user, isLoading, authError } = useAuth();

  useEffect(() => {
    if (!isLoading && !user && !authError) {
      router.replace(localePath('/login', locale));
    }
  }, [authError, isLoading, locale, router, user]);

  if (isLoading) {
    return <PageSpinner />;
  }

  if (authError) {
    return <SessionVerificationFallback />;
  }

  if (!user) {
    return <PageSpinner />;
  }

  return <>{children}</>;
}
