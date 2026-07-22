'use client';

import { useTranslations } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';

export default function ProfilePage() {
  const t = useTranslations();
  const { user } = useAuth();

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <h1 className="text-2xl font-bold text-gray-900">{t('nav.profile')}</h1>
      <div className="mt-8 rounded-xl border bg-white p-6 shadow-sm">
        <div className="flex items-center gap-4">
          <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-2xl font-bold text-primary-700">
            {user?.name?.charAt(0)}
          </div>
          <div>
            <h2 className="text-xl font-semibold">{user?.name} {user?.last_name}</h2>
            <p className="text-gray-500">{user?.email}</p>
            <p className="text-sm text-gray-400">{user?.client_type} · {user?.role}</p>
          </div>
        </div>
      </div>
    </div>
  );
}
