'use client';

import { useRouter } from 'next/navigation';
import { useLocale, useTranslations } from 'next-intl';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { localePath } from '@/i18n/paths';

export function SessionVerificationFallback() {
  const router = useRouter();
  const locale = useLocale();
  const t = useTranslations();

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <Card className="w-full max-w-md text-center">
        <Alert variant="error">
          {t('auth.session_verification_error')}
        </Alert>
        <div className="mt-6 flex justify-center gap-3">
          <Button type="button" onClick={() => window.location.reload()}>
            {t('common.retry')}
          </Button>
          <Button type="button" variant="outline" onClick={() => router.replace(localePath('/login', locale))}>
            {t('auth.go_to_login')}
          </Button>
        </div>
      </Card>
    </div>
  );
}
