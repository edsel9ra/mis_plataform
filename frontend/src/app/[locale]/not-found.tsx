'use client';

import Link from 'next/link';
import { useLocale } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { localePath } from '@/i18n/paths';

export default function NotFound() {
  const locale = useLocale();

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <Card className="max-w-md text-center">
        <CardHeader>
          <CardTitle>Page not found</CardTitle>
        </CardHeader>
        <p className="mb-4 text-gray-600">The page you are looking for does not exist.</p>
        <Link href={localePath('/', locale)}>
          <Button variant="primary">Go home</Button>
        </Link>
      </Card>
    </div>
  );
}
