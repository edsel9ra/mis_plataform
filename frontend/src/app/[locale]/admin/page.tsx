'use client';

import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { localePath } from '@/i18n/paths';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert } from '@/components/ui/alert';
import { Spinner } from '@/components/ui/spinner';
import {
  ArrowRight,
  Users,
  GraduationCap,
  Calendar,
  ClipboardCheck,
  Activity,
  Sparkles,
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
  const locale = useLocale();

  const { data: reports, isLoading, error } = useQuery<ReportData>({
    queryKey: ['admin-reports'],
    queryFn: ({ signal }) => api.get<ReportData>('/admin/reports', undefined, { signal }),
  });

  if (isLoading) return <Spinner />;
  if (error || !reports) return <Alert variant="error">{apiErrorMessage(error, (key) => t(key)) || t('common.load_error')}</Alert>;

  const completionRate = reports?.total_sessions
    ? Math.round(((reports.completed_sessions ?? 0) / reports.total_sessions) * 100)
    : 0;
  const stats = [
    { label: t('admin.total_users'), value: reports?.total_users ?? 0, icon: Users, color: 'from-blue-500 to-cyan-500', href: '/admin/users' },
    { label: t('admin.total_mentors'), value: reports?.users_by_role?.find(r => r.role === 'mentor')?.total ?? 0, icon: GraduationCap, color: 'from-emerald-500 to-teal-500', href: '/admin/mentors' },
    { label: t('admin.total_sessions'), value: reports?.total_sessions ?? 0, icon: Calendar, color: 'from-violet-500 to-purple-500', href: '/admin/sessions' },
    { label: t('admin.completed_sessions'), value: `${completionRate}%`, icon: ClipboardCheck, color: 'from-amber-500 to-orange-500', href: '/admin/sessions' },
  ];

  return (
    <div className="space-y-8 animate-fade-in">
      <section className="relative overflow-hidden rounded-3xl bg-[radial-gradient(circle_at_top_left,#1d4ed8_0,#111827_44%,#020617_100%)] p-6 text-white shadow-xl shadow-slate-200 sm:p-8">
        <div className="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-primary-500/30 blur-3xl" aria-hidden="true" />
        <div className="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-primary-100">
              <Sparkles className="h-3.5 w-3.5" aria-hidden="true" />
               {t('admin.operational_badge')}
            </div>
            <h1 className="mt-5 text-3xl font-bold tracking-tight sm:text-4xl">{t('admin.overview')}</h1>
            <p className="mt-3 max-w-2xl text-slate-300">
              {t('admin.operational_description')}
            </p>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/10 p-5 backdrop-blur">
            <p className="text-sm text-slate-300">{t('admin.active_relationships')}</p>
            <p className="mt-2 text-4xl font-bold">{reports?.active_relationships ?? 0}</p>
          </div>
        </div>
      </section>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {stats.map(({ label, value, icon: Icon, color, href }) => (
          <Link key={label} href={localePath(href, locale)} className="group rounded-xl focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
            <Card className="h-full border-0 bg-white/95 shadow-sm ring-1 ring-slate-100 transition-all duration-300 group-hover:-translate-y-1 group-hover:shadow-xl group-hover:shadow-slate-200/80">
              <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle className="text-sm font-medium text-gray-500">{label}</CardTitle>
                <div className={`rounded-2xl bg-gradient-to-br ${color} p-3 shadow-lg shadow-slate-200`}>
                  <Icon className="h-5 w-5 text-white" aria-hidden="true" />
                </div>
              </div>
              </CardHeader>
              <p className="text-3xl font-bold text-gray-950">{value}</p>
              <span className="mt-4 inline-flex items-center text-sm font-semibold text-primary-600">
                 {t('admin.view_detail')} <ArrowRight className="ml-1 h-4 w-4 transition-transform group-hover:translate-x-1" aria-hidden="true" />
              </span>
            </Card>
          </Link>
        ))}
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_0.85fr]">
        <Card className="border-0 shadow-sm ring-1 ring-slate-100">
          <CardHeader>
            <CardTitle>{t('admin.users_by_type')}</CardTitle>
          </CardHeader>
          <div className="space-y-3">
            {reports?.users_by_type?.map((item) => (
              <div key={item.client_type}>
                <div className="flex items-center justify-between text-sm">
                  <span className="font-medium text-gray-700">{t(`client_types.${item.client_type}`)}</span>
                  <span className="font-semibold text-gray-950">{item.total}</span>
                </div>
                <div className="mt-2 h-2 rounded-full bg-slate-100">
                  <div
                    className="h-2 rounded-full bg-primary-500 transition-[width] duration-700"
                    style={{ width: `${Math.min(100, ((item.total || 0) / Math.max(1, reports?.total_users ?? 1)) * 100)}%` }}
                  />
                </div>
              </div>
            ))}
            {(!reports?.users_by_type || reports.users_by_type.length === 0) && (
              <p className="text-sm text-gray-400">{t('common.no_results')}</p>
            )}
          </div>
        </Card>

        <Card className="border-0 shadow-sm ring-1 ring-slate-100">
          <CardHeader>
             <CardTitle>{t('admin.role_distribution')}</CardTitle>
          </CardHeader>
          <div className="space-y-3">
            {reports?.users_by_role?.map((item) => (
              <div key={item.role} className="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                 <span className="text-sm font-medium text-slate-700">{roleLabel(item.role, t)}</span>
                <span className="text-lg font-bold text-slate-950">{item.total}</span>
              </div>
            ))}
          </div>
        </Card>
      </div>

      <Card className="border-0 bg-slate-950 text-white shadow-xl shadow-slate-200">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-start gap-3">
            <Activity className="mt-1 h-6 w-6 text-primary-300" aria-hidden="true" />
            <div>
               <h2 className="text-lg font-bold">{t('admin.next_operation_title')}</h2>
               <p className="mt-1 text-sm text-slate-300">{t('admin.next_operation_description')}</p>
            </div>
          </div>
          <Link href={localePath('/admin/evaluations', locale)} className="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-primary-50">
             {t('admin.manage_evaluations')}
          </Link>
        </div>
        </Card>
    </div>
  );
}

function roleLabel(role: string, t: ReturnType<typeof useTranslations>): string {
  const labels: Record<string, string> = {
    super_admin: t('roles.super_admin'),
    admin: t('roles.admin'),
    company_admin: t('roles.company_admin'),
    employee: t('roles.employee'),
    mentor: t('roles.mentor'),
    mentee: t('roles.mentee'),
  };

  return labels[role] ?? t('common.not_applicable');
}
