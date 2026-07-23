'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { useAuth } from '@/components/auth/AuthProvider';
import { PageSpinner } from '@/components/ui/spinner';
import { Alert } from '@/components/ui/alert';

export default function OAuthCallbackPage() {
  const t = useTranslations();
  const router = useRouter();
  const { refreshUser } = useAuth();
  const [error, setError] = useState('');

  useEffect(() => {
    const params = new URLSearchParams(window.location.hash.replace(/^#/, ''));
    const token = params.get('token');
    const oauthError = params.get('error');

    if (oauthError) {
      setError(oauthError);
      return;
    }

    if (!token) {
      setError(t('common.error'));
      return;
    }

    api.setToken(token);
    refreshUser()
      .then(() => router.replace('/dashboard'))
      .catch(() => setError(t('common.error')));
  }, [refreshUser, router, t]);

  if (error) {
    return (
      <div className="flex min-h-screen items-center justify-center px-4">
        <Alert variant="error">{error}</Alert>
      </div>
    );
  }

  return <PageSpinner />;
}
