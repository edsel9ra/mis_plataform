'use client';

import { useTranslations } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';

export default function EmpresaDashboard() {
  const t = useTranslations();
  const { user } = useAuth();

  if (user?.role !== 'company_admin') {
    return (
      <div>
        <h1 className="text-2xl font-bold text-gray-900">{t('dashboard.welcome')}, {user?.name}</h1>
        <p className="mt-4 text-gray-600">Panel de empleado — tus sesiones y progreso aparecerán aquí.</p>
      </div>
    );
  }

  return (
    <div>
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Dashboard Corporativo</h1>
        <a
          href="/dashboard/empresa/employees"
          className="rounded-lg bg-primary-600 px-4 py-2 text-sm text-white hover:bg-primary-700"
        >
          Gestionar Empleados
        </a>
      </div>

      <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <div className="rounded-xl border bg-white p-6 shadow-sm">
          <h3 className="text-sm font-medium text-gray-500">Empleados Activos</h3>
          <p className="mt-2 text-2xl font-bold text-gray-900">0</p>
        </div>
        <div className="rounded-xl border bg-white p-6 shadow-sm">
          <h3 className="text-sm font-medium text-gray-500">Sesiones Este Mes</h3>
          <p className="mt-2 text-2xl font-bold text-gray-900">0</p>
        </div>
        <div className="rounded-xl border bg-white p-6 shadow-sm">
          <h3 className="text-sm font-medium text-gray-500">Horas de Mentoría</h3>
          <p className="mt-2 text-2xl font-bold text-gray-900">0h</p>
        </div>
        <div className="rounded-xl border bg-white p-6 shadow-sm">
          <h3 className="text-sm font-medium text-gray-500">Progreso Promedio</h3>
          <p className="mt-2 text-2xl font-bold text-gray-900">0%</p>
        </div>
      </div>
    </div>
  );
}
