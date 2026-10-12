'use client';

import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useLocale, useTranslations } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';
import { PageSpinner } from '@/components/ui/spinner';
import { Alert } from '@/components/ui/alert';
import { localePath } from '@/i18n/paths';

const platformAdminRoles = new Set(['super_admin', 'admin']);
const oauthErrorMessages: Record<string, string> = {
  invalid_provider: 'auth.oauth_invalid_provider',
  invalid_oauth_state: 'auth.oauth_invalid_state',
  provider_error: 'auth.oauth_provider_error',
  oauth_email_unverified: 'auth.oauth_email_unverified',
  oauth_account_conflict: 'auth.oauth_account_conflict',
  inactive: 'auth.oauth_inactive',
  access_denied: 'auth.oauth_access_denied',
};

export default function OAuthCallbackPage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const { refreshUser } = useAuth();
  const [error, setError] = useState('');
  const handled = useRef(false);

  useEffect(() => {
    if (handled.current) return;
    handled.current = true;

    const params = new URLSearchParams(window.location.hash.replace(/^#/, ''));
    window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}`);
    const oauthError = params.get('error');

    if (oauthError) {
      setError(t(oauthErrorMessages[oauthError] ?? 'auth.oauth_error'));
      return;
    }

    if (!params.get('success')) {
      setError(t('common.error'));
      return;
    }

    refreshUser()
      .then((user) => {
        if (!user.id || !user.role) {
          setError(t('common.error'));
          return;
        }

        const destination = platformAdminRoles.has(user.role) ? '/admin' : '/dashboard';
        router.replace(localePath(destination, locale));
      })
      .catch(() => setError(t('common.error')));
  }, [locale, refreshUser, router, t]);

  if (error) {
    return (
      <div className="flex min-h-screen items-center justify-center px-4">
        <Alert variant="error">{error}</Alert>
      </div>
    );
  }

  return <PageSpinner />;
}
