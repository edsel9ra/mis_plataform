'use client';

import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';

export default function DashboardError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <div className="flex items-center justify-center p-8">
      <Card className="max-w-md text-center">
        <CardHeader>
          <CardTitle>Dashboard error</CardTitle>
        </CardHeader>
        <p className="mb-4 text-gray-600">{error.message}</p>
        <Button onClick={reset}>Try again</Button>
      </Card>
    </div>
  );
}
