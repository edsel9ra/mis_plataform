'use client';

import { useTranslations } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';
import { AppShell } from '@/components/layout/AppShell';
import { Card } from '@/components/ui/card';

export default function ProfilePage() {
  const t = useTranslations();
  const { user } = useAuth();

  return (
    <AppShell>
      <div className="mx-auto max-w-2xl space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">{t('nav.profile')}</h1>
          <p className="mt-1 text-sm text-gray-500">{t('profile.subtitle')}</p>
        </div>
        <Card className="border-0 shadow-sm">
          <div className="flex items-center gap-4">
            <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-100 text-2xl font-bold text-primary-700">
              {user?.name?.charAt(0)}
            </div>
            <div>
              <h2 className="text-xl font-semibold text-gray-950">{user?.name} {user?.last_name}</h2>
              <p className="text-gray-500">{user?.email}</p>
              <p className="text-sm text-gray-400">{user?.client_type ?? t('common.not_applicable')} · {user?.role}</p>
            </div>
          </div>
        </Card>
      </div>
    </AppShell>
  );
}
