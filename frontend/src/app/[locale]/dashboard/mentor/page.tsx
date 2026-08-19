'use client';

import { useDeferredValue, useEffect, useState } from 'react';
import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { Brain, ChevronLeft, ChevronRight, Search, Users } from 'lucide-react';
import { useAuth } from '@/components/auth/AuthProvider';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PageSpinner } from '@/components/ui/spinner';
import { localePath } from '@/i18n/paths';
import { useMentorUsers } from '@/hooks/use-mentor-dashboard';

export default function MentorDashboardPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const deferredSearch = useDeferredValue(search.trim());
  const { data, isLoading, isFetching, error } = useMentorUsers(deferredSearch, page, user?.role === 'mentor');

  useEffect(() => setPage(1), [deferredSearch]);

  if (isLoading) return <PageSpinner />;

  return (
    <div className="mx-auto max-w-7xl space-y-6">
      <header className="overflow-hidden rounded-3xl bg-slate-950 px-6 py-8 text-white shadow-xl sm:px-8">
        <div className="flex flex-col justify-between gap-6 md:flex-row md:items-end">
          <div>
            <p className="text-sm font-semibold uppercase tracking-[0.2em] text-primary-300">{t('mentor_dashboard.eyebrow')}</p>
            <h1 className="mt-2 text-3xl font-bold sm:text-4xl">{t('mentor_dashboard.title')}</h1>
            <p className="mt-3 max-w-2xl text-slate-300">{t('mentor_dashboard.subtitle')}</p>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/5 px-5 py-4">
            <span className="text-3xl font-bold">{data?.meta.total ?? 0}</span>
            <span className="ml-2 text-sm text-slate-300">{t('mentor_dashboard.assigned_total')}</span>
          </div>
        </div>
      </header>

      <div className="relative max-w-xl">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder={t('mentor_dashboard.search_placeholder')}
          aria-label={t('mentor_dashboard.search_label')}
          className="pl-10"
        />
      </div>

      {error ? (
        <Card className="border-red-200 bg-red-50 text-red-800" role="alert">
          {t('mentor_dashboard.load_error')}
        </Card>
      ) : data?.data.length === 0 ? (
        <Card className="py-14 text-center">
          <Users className="mx-auto h-11 w-11 text-slate-300" aria-hidden="true" />
          <h2 className="mt-4 text-lg font-semibold text-slate-900">{t('mentor_dashboard.empty_title')}</h2>
          <p className="mt-1 text-sm text-slate-500">{t('mentor_dashboard.empty_description')}</p>
        </Card>
      ) : (
        <div className={`grid gap-5 md:grid-cols-2 xl:grid-cols-3 ${isFetching ? 'opacity-60' : ''}`} aria-busy={isFetching}>
          {data?.data.map((assignedUser) => (
            <Card key={assignedUser.id} className="flex flex-col border-slate-200 transition hover:-translate-y-0.5 hover:shadow-md">
              <div className="flex items-start gap-4">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary-100 text-lg font-bold text-primary-700">
                  {assignedUser.full_name.charAt(0)}
                </div>
                <div className="min-w-0 flex-1">
                  <h2 className="truncate text-lg font-semibold text-slate-950">{assignedUser.full_name}</h2>
                  <Badge variant={assignedUser.personality_completed ? 'success' : 'warning'} className="mt-2">
                    <Brain className="mr-1 h-3 w-3" aria-hidden="true" />
                    {t(assignedUser.personality_completed ? 'mentor_dashboard.assessment_complete' : 'mentor_dashboard.assessment_pending')}
                  </Badge>
                </div>
              </div>

              <div className="mt-5 space-y-3">
                {assignedUser.assignments.map((assignment) => (
                  <Link
                    key={assignment.relationship_id}
                    href={localePath(`/dashboard/mentor/users/${assignedUser.id}?relationship_id=${assignment.relationship_id}`, locale)}
                    className="block rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-primary-300 hover:bg-primary-50 focus-visible:ring-2 focus-visible:ring-primary-500"
                  >
                    <div className="flex items-center justify-between gap-3">
                      <span className="font-medium text-slate-900">{assignment.context.name}</span>
                      <ChevronRight className="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                    </div>
                    <p className="mt-1 text-xs text-slate-500">
                      {t(`mentor_dashboard.context_${assignment.context.type}`)}
                      {' · '}
                      {assignment.plan?.name ?? t('mentor_dashboard.no_plan')}
                    </p>
                  </Link>
                ))}
              </div>
            </Card>
          ))}
        </div>
      )}

      {data && data.meta.last_page > 1 && (
        <nav className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-3" aria-label={t('mentor_dashboard.pagination')}>
          <button
            type="button"
            onClick={() => setPage((current) => Math.max(1, current - 1))}
            disabled={page <= 1 || isFetching}
            className="inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
          >
            <ChevronLeft className="h-4 w-4" aria-hidden="true" /> {t('common.previous')}
          </button>
          <span className="text-sm text-slate-500">{t('common.page')} {data.meta.current_page} {t('common.of')} {data.meta.last_page}</span>
          <button
            type="button"
            onClick={() => setPage((current) => Math.min(data.meta.last_page, current + 1))}
            disabled={page >= data.meta.last_page || isFetching}
            className="inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
          >
            {t('common.next')} <ChevronRight className="h-4 w-4" aria-hidden="true" />
          </button>
        </nav>
      )}
    </div>
  );
}
