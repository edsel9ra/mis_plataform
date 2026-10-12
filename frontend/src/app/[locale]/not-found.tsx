'use client';

import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { localePath } from '@/i18n/paths';

export default function NotFound() {
  const locale = useLocale();
  const t = useTranslations();

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <Card className="max-w-md text-center">
        <CardHeader>
          <CardTitle>{t('common.page_not_found')}</CardTitle>
        </CardHeader>
        <p className="mb-4 text-gray-600">{t('common.page_not_found_description')}</p>
        <Link href={localePath('/', locale)}>
          <Button variant="primary">{t('common.go_home')}</Button>
        </Link>
      </Card>
    </div>
  );
}
