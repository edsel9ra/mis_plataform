'use client';

import { useQuery } from '@tanstack/react-query';
import { useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import {
  Users,
  GraduationCap,
  Calendar,
  ClipboardCheck,
} from 'lucide-react';

interface ReportData {
  total_users: number;
  users_by_type: { client_type: string; total: number }[];
  users_by_role: { role: string; total: number }[];
  active_relationships: number;
  total_sessions: number;
  completed_sessions: number;
  revenue_by_plan: { plan_id: string; total: number; plan: { name: Record<string, string> } }[];
}

export default function AdminDashboard() {
  const t = useTranslations();

  const { data: reports, isLoading } = useQuery<ReportData>({
    queryKey: ['admin-reports'],
    queryFn: () => api.get<ReportData>('/admin/reports'),
  });

  if (isLoading) return <Spinner />;

  const stats = [
    { label: t('admin.total_users'), value: reports?.total_users ?? 0, icon: Users, color: 'bg-blue-500' },
    { label: t('admin.total_mentors'), value: reports?.users_by_role?.find(r => r.role === 'mentor')?.total ?? 0, icon: GraduationCap, color: 'bg-green-500' },
    { label: t('admin.total_sessions'), value: reports?.total_sessions ?? 0, icon: Calendar, color: 'bg-purple-500' },
    { label: t('admin.completed_sessions'), value: reports?.completed_sessions ?? 0, icon: ClipboardCheck, color: 'bg-amber-500' },
  ];

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">{t('admin.overview')}</h1>

      <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        {stats.map(({ label, value, icon: Icon, color }) => (
          <Card key={label}>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle className="text-sm font-medium text-gray-500">{label}</CardTitle>
                <div className={`rounded-lg ${color} p-2`}>
                  <Icon className="h-5 w-5 text-white" aria-hidden="true" />
                </div>
              </div>
            </CardHeader>
            <p className="text-3xl font-bold text-gray-900">{value}</p>
          </Card>
        ))}
      </div>

      <div className="mt-8 grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{t('admin.users_by_type')}</CardTitle>
          </CardHeader>
          <div className="space-y-3">
            {reports?.users_by_type?.map((item) => (
              <div key={item.client_type} className="flex items-center justify-between">
                <span className="text-sm text-gray-600">{t(`client_types.${item.client_type}`)}</span>
                <span className="text-sm font-semibold text-gray-900">{item.total}</span>
              </div>
            ))}
            {(!reports?.users_by_type || reports.users_by_type.length === 0) && (
              <p className="text-sm text-gray-400">{t('common.no_results')}</p>
            )}
          </div>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('admin.active_relationships')}</CardTitle>
          </CardHeader>
          <p className="text-3xl font-bold text-gray-900">{reports?.active_relationships ?? 0}</p>
        </Card>
      </div>
    </div>
  );
}
