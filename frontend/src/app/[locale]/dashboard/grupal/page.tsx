'use client';

import { useTranslations } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';

export default function GrupalDashboard() {
  const t = useTranslations();
  const { user } = useAuth();

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">{t('dashboard.welcome')}, {user?.name}</h1>
      <p className="mt-2 text-gray-500">Dashboard Grupal — gestiona tus cohortes y sesiones grupales.</p>

      <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div className="rounded-xl border bg-white p-6 shadow-sm">
          <h3 className="text-sm font-medium text-gray-500">Cohortes Activos</h3>
          <p className="mt-2 text-2xl font-bold text-gray-900">0</p>
        </div>
        <div className="rounded-xl border bg-white p-6 shadow-sm">
          <h3 className="text-sm font-medium text-gray-500">Miembros Totales</h3>
          <p className="mt-2 text-2xl font-bold text-gray-900">0</p>
        </div>
      </div>
    </div>
  );
}
