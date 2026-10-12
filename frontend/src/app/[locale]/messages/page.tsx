'use client';

import { useTranslations } from 'next-intl';
import { AppShell } from '@/components/layout/AppShell';
import { Card } from '@/components/ui/card';
import { MessageSquare } from 'lucide-react';

export default function MessagesPage() {
  const t = useTranslations();

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">{t('nav.messages')}</h1>
          <p className="mt-1 text-sm text-gray-500">{t('messages.subtitle')}</p>
        </div>
        <Card className="border-dashed border-gray-300 bg-white text-center shadow-sm">
          <MessageSquare className="mx-auto h-10 w-10 text-primary-500" aria-hidden="true" />
          <h2 className="mt-4 text-lg font-semibold text-gray-950">{t('messages.empty_title')}</h2>
          <p className="mt-2 text-sm text-gray-500">{t('messages.empty_desc')}</p>
        </Card>
      </div>
    </AppShell>
  );
}
