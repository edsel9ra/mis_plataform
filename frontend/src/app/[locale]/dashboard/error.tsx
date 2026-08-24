'use client';

import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from 'next-intl';

export default function DashboardError({
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  const t = useTranslations();

  return (
    <div className="flex items-center justify-center p-8">
      <Card className="max-w-md text-center">
        <CardHeader>
          <CardTitle>{t('common.dashboard_error')}</CardTitle>
        </CardHeader>
        <p className="mb-4 text-gray-600">{t('common.unexpected_error_description')}</p>
        <Button onClick={reset}>{t('common.retry')}</Button>
      </Card>
    </div>
  );
}
