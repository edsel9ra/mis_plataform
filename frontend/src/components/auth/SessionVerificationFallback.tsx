'use client';

import { useRouter } from 'next/navigation';
import { useLocale } from 'next-intl';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { localePath } from '@/i18n/paths';

export function SessionVerificationFallback() {
  const router = useRouter();
  const locale = useLocale();

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <Card className="w-full max-w-md text-center">
        <Alert variant="error">
          No pudimos verificar tu sesión. El backend puede estar tardando en responder.
        </Alert>
        <div className="mt-6 flex justify-center gap-3">
          <Button type="button" onClick={() => window.location.reload()}>
            Reintentar
          </Button>
          <Button type="button" variant="outline" onClick={() => router.replace(localePath('/login', locale))}>
            Ir al login
          </Button>
        </div>
      </Card>
    </div>
  );
}
