'use client';

import { useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { useParams, useRouter } from 'next/navigation';
import { AppShell } from '@/components/layout/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';
import { useSession } from '@/hooks/use-sessions';
import { apiErrorMessage } from '@/lib/errors';
import { localePath } from '@/i18n/paths';
import { CalendarSessionMapper, type CalendarSessionInput } from '@/lib/scheduling';
import { AttendancePanel } from '@/components/scheduling/session-actions/AttendancePanel';
import { SessionActionsPanel } from '@/components/scheduling/session-actions/SessionActionsPanel';
import { sessionStatusLabel, sessionStatusVariant } from '@/lib/session-statuses';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader } from '@/components/ui/card';
import { PageSpinner } from '@/components/ui/spinner';
import { ArrowLeft, ExternalLink, Users, Video } from 'lucide-react';

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export default function SessionDetailPage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const id = params?.id ?? '';
  const { user } = useAuth();
  const timezone = user?.timezone ?? 'UTC';

  const invalidId = !UUID_RE.test(id);
  const { data: session, isLoading, error, refetch } = useSession(invalidId ? '' : id);
  const [statusAnnouncement, setStatusAnnouncement] = useState<string | null>(null);

  // Runs after the commit in which the dialog unmounts; the dialog's passive
  // cleanup (focus restoration) always executes before this effect.
  useEffect(() => {
    if (statusAnnouncement) {
      document.getElementById('session-status')?.focus();
    }
  }, [statusAnnouncement]);

  const announceSessionChange = (message: string) => {
    setStatusAnnouncement(message);
  };

  if (invalidId) {
    return (
      <AppShell>
        <div className="space-y-4">
          <Button type="button" variant="ghost" onClick={() => router.push(localePath('/sessions', locale))}>
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            {t('common.back')}
          </Button>
          <Alert variant="error">{t('common.not_applicable')}</Alert>
        </div>
      </AppShell>
    );
  }

  if (isLoading) {
    return (
      <AppShell>
        <PageSpinner />
      </AppShell>
    );
  }

  if (error || !session) {
    return (
      <AppShell>
        <div className="space-y-4">
          <Button type="button" variant="ghost" onClick={() => router.push(localePath('/sessions', locale))}>
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            {t('common.back')}
          </Button>
          <Alert variant="error">
            {apiErrorMessage(error, (key) => t(key)) || t('common.load_error')}
          </Alert>
          <div className="flex gap-2">
            <Button type="button" onClick={() => refetch()}>{t('common.retry')}</Button>
            <Button type="button" variant="secondary" onClick={() => router.push(localePath('/sessions', locale))}>
              {t('common.back')}
            </Button>
          </div>
        </div>
      </AppShell>
    );
  }

  const now = new Date().toISOString();
  const item = CalendarSessionMapper.toCalendarItem(session as unknown as CalendarSessionInput, timezone, now);

  return (
    <AppShell>
      <div className="space-y-6">
        <Button type="button" variant="ghost" onClick={() => router.push(localePath('/sessions', locale))}>
          <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          {t('common.back')}
        </Button>

        <Card>
          <CardHeader>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h1 className="text-xl font-semibold text-gray-950">{item.title}</h1>
                <p className="mt-1 text-sm text-gray-500">{item.startTimeLabel}</p>
              </div>
              <div id="session-status" tabIndex={-1} className="outline-none">
                <Badge variant={sessionStatusVariant(item.status)}>
                  {sessionStatusLabel(item.status, t)}
                </Badge>
              </div>
            </div>
          </CardHeader>
          <div className="space-y-3 p-4 text-sm text-gray-600">
            <p>
              {item.durationMinutes} {t('sessions.minutes')}
              {item.sessionType ? ` · ${t(`session_types.${item.sessionType}`)}` : ''}
            </p>
            {item.attendeeNames.length > 0 && (
              <p className="flex items-center gap-2">
                <Users className="h-4 w-4" aria-hidden="true" />
                {item.attendeeNames.join(', ')}
              </p>
            )}
            {item.isCollective && (
              <Badge variant="default">
                {item.collectiveName ?? t('calendar.collective_session')}
              </Badge>
            )}
            {item.meet.state === 'available' && item.meet.link && (
              <a
                href={item.meet.link}
                target="_blank"
                rel="noreferrer"
                className="inline-flex min-h-6 items-center gap-2 rounded font-semibold text-primary-600 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
              >
                <Video className="h-4 w-4" aria-hidden="true" />
                {t('sessions.join_meet')}
                <ExternalLink className="h-4 w-4" aria-hidden="true" />
              </a>
            )}
            {item.meet.state === 'pending' && (
              <span className="inline-flex items-center gap-2 text-gray-500">
                <Video className="h-4 w-4" aria-hidden="true" />
                {t('calendar.meet_pending')}
              </span>
            )}
          </div>
          {session.available_actions && (
            <SessionActionsPanel
              sessionId={session.id}
              sessionTitle={item.title}
              scheduledAt={session.scheduled_at}
              durationMinutes={session.duration_minutes}
              sessionTimezone={session.timezone}
              viewerTimezone={timezone}
              availableActions={session.available_actions}
              onSuccess={announceSessionChange}
            />
          )}
        </Card>

        {session.available_actions && session.available_actions.attendance.length > 0 && (
          <Card padding="sm">
            <AttendancePanel
              sessionId={session.id}
              entries={session.available_actions.attendance}
              attendees={session.attendees ?? []}
              onSuccess={setStatusAnnouncement}
            />
          </Card>
        )}


        <div role="status" aria-live="polite" aria-atomic="true" className="sr-only">
          {statusAnnouncement}
        </div>
      </div>
    </AppShell>
  );
}