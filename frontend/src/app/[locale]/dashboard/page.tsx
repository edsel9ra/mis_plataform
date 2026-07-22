'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/components/auth/AuthProvider';
import { useTranslations } from 'next-intl';

export default function DashboardRouter() {
  const { user, isLoading } = useAuth();
  const router = useRouter();
  const t = useTranslations();

  useEffect(() => {
    if (isLoading) return;
    if (!user) { router.replace('/login'); return; }

    const dashboards: Record<string, string> = {
      personal: '/dashboard/personal',
      familiar: '/dashboard/familiar',
      grupal: '/dashboard/grupal',
      empresa: '/dashboard/empresa',
    };

    const target = dashboards[user.client_type] || '/dashboard/personal';
    router.replace(target);
  }, [user, isLoading, router]);

  if (isLoading) return <div className="flex h-screen items-center justify-center">{t('common.loading')}</div>;

  return null;
}
