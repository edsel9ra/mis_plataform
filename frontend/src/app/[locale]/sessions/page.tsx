'use client';

import { useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { useRouter } from 'next/navigation';
import { AppShell } from '@/components/layout/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';
import { useSessions, type Session } from '@/hooks/use-sessions';
import { apiErrorMessage } from '@/lib/errors';
import { localePath } from '@/i18n/paths';
import { Badge } from '@/components/ui/badge';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { PageSpinner } from '@/components/ui/spinner';
import { PaginationControls } from '@/components/ui/pagination';
import { Calendar, Clock, ExternalLink, Users } from 'lucide-react';

export default function SessionsPage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const { user } = useAuth();
  const [page, setPage] = useState(1);
  const perPage = 20;
  const { data, isLoading, isFetching, error } = useSessions(page, perPage, Boolean(user));
  const sessions = data?.data ?? [];
  const upcoming = sessions.filter((session) => ['scheduled', 'in_progress'].includes(session.status));
  const completed = sessions.filter((session) => session.status === 'completed');
  const past = sessions.filter((session) => !['scheduled', 'in_progress'].includes(session.status));

  return (
    <AppShell>
      <div className="space-y-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-950">{t('sessions.title')}</h1>
            <p className="mt-1 text-sm text-gray-500">{t('sessions.subtitle')}</p>
          </div>
          <Button type="button" onClick={() => router.push(localePath('/mentors', locale))}>
            {t('sessions.schedule')}
          </Button>
        </div>

        <section className="grid gap-4 sm:grid-cols-3">
          {[
            { label: t('sessions.upcoming'), value: upcoming.length, icon: Calendar },
            { label: t('common.completed'), value: completed.length, icon: Clock },
            { label: t('common.total'), value: data?.meta.total ?? sessions.length, icon: Users },
          ].map(({ label, value, icon: Icon }) => (
            <Card key={label} className="border-0 shadow-sm">
              <div className="flex items-center justify-between gap-4">
                <div>
                  <p className="text-sm text-gray-500">{label}</p>
                  <p className="mt-1 text-2xl font-bold text-gray-950">{value}</p>
                </div>
                <span className="rounded-2xl bg-primary-50 p-3 text-primary-700">
                  <Icon className="h-5 w-5" aria-hidden="true" />
                </span>
              </div>
            </Card>
          ))}
        </section>

        {isLoading ? (
          <PageSpinner />
        ) : error ? (
          <Alert variant="error">{apiErrorMessage(error, (key) => t(key)) || t('common.load_error')}</Alert>
        ) : sessions.length === 0 ? (
          <EmptyState title={t('sessions.empty_title')} description={t('sessions.empty_desc')} />
        ) : (
          <div className="space-y-6">
            <div className="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
              <SessionList title={t('sessions.upcoming')} sessions={upcoming} locale={locale} t={t} empty={t('sessions.no_upcoming')} />
              <SessionList title={t('sessions.past')} sessions={past} locale={locale} t={t} empty={t('sessions.no_past')} />
            </div>
            {data && (
              <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <PaginationControls
                  meta={data.meta}
                  page={page}
                  onPageChange={setPage}
                  isLoading={isFetching}
                  ariaLabel={t('common.pagination')}
                  previousLabel={t('common.previous')}
                  nextLabel={t('common.next')}
                  pageLabel={t('common.page')}
                  ofLabel={t('common.of')}
                />
              </div>
            )}
          </div>
        )}
      </div>
    </AppShell>
  );
}

function SessionList({
  title,
  sessions,
  locale,
  t,
  empty,
}: {
  title: string;
  sessions: Session[];
  locale: string;
  t: ReturnType<typeof useTranslations>;
  empty: string;
}) {
  return (
    <section className="space-y-3">
      <h2 className="text-lg font-semibold text-gray-950">{title}</h2>
      {sessions.length === 0 ? (
        <p className="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500">{empty}</p>
      ) : (
        sessions.map((session) => (
          <Card key={session.id} className="border-0 shadow-sm">
            <CardHeader>
              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <CardTitle className="text-base">{session.title}</CardTitle>
                  <p className="mt-1 text-sm text-gray-500">{formatDate(session.scheduled_at, locale)}</p>
                </div>
                <Badge variant={statusVariant(session.status)}>{sessionStatusLabel(session.status, t)}</Badge>
              </div>
            </CardHeader>
            <div className="space-y-3 text-sm text-gray-600">
              <p>{session.duration_minutes} {t('sessions.minutes')} · {sessionTypeLabel(session.session_type, t)}</p>
              {session.relationship?.mentor?.full_name && (
                <p>{t('admin.mentor')}: <span className="font-medium text-gray-900">{session.relationship.mentor.full_name}</span></p>
              )}
              {session.description && <p className="leading-6">{session.description}</p>}
              {session.meet_link && (
                <a href={session.meet_link} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 font-semibold text-primary-600 hover:text-primary-700">
                  {t('sessions.join_meet')}
                  <ExternalLink className="h-4 w-4" aria-hidden="true" />
                </a>
              )}
            </div>
          </Card>
        ))
      )}
    </section>
  );
}

function EmptyState({ title, description }: { title: string; description: string }) {
  return (
    <Card className="border-dashed border-gray-300 bg-white text-center shadow-sm">
      <Calendar className="mx-auto h-10 w-10 text-primary-500" aria-hidden="true" />
      <h2 className="mt-4 text-lg font-semibold text-gray-950">{title}</h2>
      <p className="mt-2 text-sm text-gray-500">{description}</p>
    </Card>
  );
}

function statusVariant(status: string): 'default' | 'primary' | 'success' | 'warning' | 'danger' {
  if (status === 'scheduled') return 'primary';
  if (status === 'in_progress') return 'warning';
  if (status === 'completed') return 'success';
  if (status === 'cancelled' || status === 'canceled') return 'danger';

  return 'default';
}

function sessionStatusLabel(status: string, t: ReturnType<typeof useTranslations>): string {
  const labels: Record<string, string> = {
    scheduled: t('session_statuses.scheduled'),
    in_progress: t('session_statuses.in_progress'),
    completed: t('session_statuses.completed'),
    canceled: t('session_statuses.canceled'),
    cancelled: t('session_statuses.cancelled'),
  };

  return labels[status] ?? t('common.not_applicable');
}

function sessionTypeLabel(type: string, t: ReturnType<typeof useTranslations>): string {
  const labels: Record<string, string> = {
    individual: t('session_types.individual'),
    family: t('session_types.family'),
    group: t('session_types.group'),
    corporate: t('session_types.corporate'),
  };

  return labels[type] ?? t('common.not_applicable');
}

function formatDate(value: string, locale: string): string {
  return new Date(value).toLocaleString(locale, {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
}
