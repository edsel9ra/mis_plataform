'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/components/auth/AuthProvider';
import { useLocale, useTranslations } from 'next-intl';
import { localePath } from '@/i18n/paths';

export default function DashboardRouter() {
  const { user, isLoading } = useAuth();
  const router = useRouter();
  const t = useTranslations();
  const locale = useLocale();

  useEffect(() => {
    if (isLoading) return;
    if (!user) { router.replace(localePath('/login', locale)); return; }
    if (user.role === 'super_admin' || user.role === 'admin') {
      router.replace(localePath('/admin', locale));
      return;
    }
    if (user.role === 'mentor') {
      router.replace(localePath('/dashboard/mentor', locale));
      return;
    }

    const dashboards: Record<string, string> = {
      personal: '/dashboard/personal',
      familiar: '/dashboard/familiar',
      grupal: '/dashboard/grupal',
      empresa: '/dashboard/empresa',
    };

    const target = user.client_type ? dashboards[user.client_type] : '/dashboard/personal';
    router.replace(localePath(target, locale));
  }, [user, isLoading, router, locale]);

  if (isLoading) return <div className="flex h-screen items-center justify-center">{t('common.loading')}</div>;

  return null;
}
