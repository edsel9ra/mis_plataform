'use client';

import { useTranslations } from 'next-intl';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

export default function ProgressError({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  const t = useTranslations();

  return (
    <div className="mx-auto max-w-xl py-12">
      <Alert variant="error">
        <div className="space-y-3">
          <p>{t('progress.error')}</p>
          <Button type="button" size="sm" variant="outline" onClick={reset}>
            {t('progress.retry')}
          </Button>
        </div>
      </Alert>
    </div>
  );
}
