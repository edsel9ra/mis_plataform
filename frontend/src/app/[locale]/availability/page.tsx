'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { AppShell } from '@/components/layout/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';
import { AvailabilityWindowDialog } from '@/components/availability/AvailabilityWindowDialog';
import { DeleteWindowDialog } from '@/components/availability/DeleteWindowDialog';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { PageSpinner } from '@/components/ui/spinner';
import { AvailabilityExceptionDialog } from '@/components/availability/AvailabilityExceptionDialog';
import { DeleteExceptionDialog } from '@/components/availability/DeleteExceptionDialog';
import { useAvailabilityExceptions, useAvailabilityWindows } from '@/hooks/use-availability';
import { apiErrorMessage } from '@/lib/errors';
import {
  conflictSessionsPresentation,
  exceptionItemPresentation,
  groupWindowsByDay,
  sortExceptions,
  type AvailabilityException,
  type AvailabilityWindow,
  type ConflictingSession,
} from '@/lib/availability';
import { calendarLocalDate } from '@/lib/scheduling';
import { localePath } from '@/i18n/paths';

export default function AvailabilityPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user, isLoading: authLoading } = useAuth();
  const isMentor = user?.role === 'mentor';

  const { data: windows, isLoading, error, refetch } = useAvailabilityWindows(isMentor);
  const { data: exceptions } = useAvailabilityExceptions(isMentor);

  const [announcement, setAnnouncement] = useState<string | null>(null);
  const [focusDay, setFocusDay] = useState<number | null>(null);
  const [focusExceptions, setFocusExceptions] = useState(false);
  const [conflicts, setConflicts] = useState<ConflictingSession[] | null>(null);
  const [exceptionDialog, setExceptionDialog] = useState<{ open: boolean; exception: AvailabilityException | null }>({
    open: false,
    exception: null,
  });
  const [deleteExceptionTarget, setDeleteExceptionTarget] = useState<AvailabilityException | null>(null);
  const [windowDialog, setWindowDialog] = useState<{ open: boolean; window: AvailabilityWindow | null }>({
    open: false,
    window: null,
  });
  const [deleteTarget, setDeleteTarget] = useState<AvailabilityWindow | null>(null);

  // Focus returns to the day heading once the dialog unmounted (post-commit).
  useEffect(() => {
    if (focusDay === null) return;
    document.getElementById(`availability-day-${focusDay}`)?.focus();
    setFocusDay(null);
  }, [focusDay, windows]);

  // A conflicts notice takes the focus instead of the section heading.
  useEffect(() => {
    if (conflicts !== null && conflicts.length > 0) {
      document.getElementById('availability-conflicts')?.focus();
    } else if (focusExceptions) {
      document.getElementById('availability-exceptions')?.focus();
      setFocusExceptions(false);
    }
  }, [conflicts, focusExceptions, exceptions]);

  function windowRange(window: AvailabilityWindow): string {
    return `${window.start_time.slice(0, 5)}–${window.end_time.slice(0, 5)}`;
  }

  const days = groupWindowsByDay(windows ?? [], locale);
  const hasWindows = (windows ?? []).length > 0;
  const viewerTimezone = user?.timezone ?? 'UTC';
  const today = calendarLocalDate(new Date().toISOString(), viewerTimezone);
  const sortedExceptions = sortExceptions(exceptions ?? [], today);

  function dayLabel(dayOfWeek: number): string {
    return days.find((day) => day.dayOfWeek === dayOfWeek)?.label ?? '';
  }

  return (
    <AppShell>
      <div className="space-y-6">
        <div className="flex flex-col gap-2">
          <h1 className="text-2xl font-bold text-gray-950">{t('availability.title')}</h1>
        </div>

        <div id="availability-status" role="status" tabIndex={-1} className="sr-only">
          {announcement}
        </div>

        {authLoading ? (
          <PageSpinner />
        ) : !isMentor ? (
          <Alert variant="info">
            {t('availability.mentor_only')}
            <Link
              href={localePath('/sessions', locale)}
              className="ml-2 rounded font-semibold text-primary-600 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
              {t('availability.go_to_agenda')}
            </Link>
          </Alert>
        ) : (
          <section aria-labelledby="availability-weekly">
            <h2 id="availability-weekly" className="text-lg font-semibold text-gray-900">
              {t('availability.weekly.title')}
            </h2>
            <p className="mt-1 text-sm text-gray-500">{t('availability.weekly.zone_note')}</p>

            <div className="mt-4">
              <Button
                variant="primary"
                type="button"
                onClick={() => setWindowDialog({ open: true, window: null })}
              >
                {t('availability.weekly.add')}
              </Button>
            </div>

            <div className="mt-6">
              {isLoading ? (
                <PageSpinner />
              ) : error ? (
                <Alert variant="error">
                  {apiErrorMessage(error, (key) => t(key)) || t('common.load_error')}
                  <button
                    type="button"
                    onClick={() => refetch()}
                    className="ml-2 rounded font-semibold text-primary-600 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                  >
                    {t('common.retry')}
                  </button>
                </Alert>
              ) : !hasWindows ? (
                <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                  <p className="mx-auto max-w-md text-sm text-slate-500">
                    {t('availability.weekly.empty')}
                  </p>
                </div>
              ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                  {days.map((day) => (
                    <section
                      key={day.dayOfWeek}
                      className="rounded-2xl border border-slate-200 bg-white p-4"
                    >
                      <h3
                        id={`availability-day-${day.dayOfWeek}`}
                        tabIndex={-1}
                        className="text-sm font-semibold text-gray-900 outline-none"
                      >
                        {day.label}
                      </h3>
                      {day.windows.length === 0 ? (
                        <p className="mt-2 text-sm text-slate-500">{t('availability.weekly.empty_day')}</p>
                      ) : (
                        <ul className="mt-2 space-y-2">
                          {day.windows.map((window) => (
                            <li
                              key={window.id}
                              className="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-700"
                            >
                              <span>
                                {windowRange(window)} · {window.timezone}
                              </span>
                              <span className="flex gap-1">
                                <Button
                                  variant="outline"
                                  type="button"
                                  className="min-h-6 min-w-6 px-2 py-1 text-xs"
                                  aria-label={t('availability.weekly.edit_label', {
                                    day: day.label,
                                    range: windowRange(window),
                                  })}
                                  onClick={() => setWindowDialog({ open: true, window })}
                                >
                                  {t('availability.weekly.edit')}
                                </Button>
                                <Button
                                  variant="outline"
                                  type="button"
                                  className="min-h-6 min-w-6 px-2 py-1 text-xs"
                                  aria-label={t('availability.weekly.delete_label', {
                                    day: day.label,
                                    range: windowRange(window),
                                  })}
                                  onClick={() => setDeleteTarget(window)}
                                >
                                  {t('availability.weekly.delete')}
                                </Button>
                              </span>
                            </li>
                          ))}
                        </ul>
                      )}
                    </section>
                  ))}
                </div>
              )}
            </div>
          </section>
        )}

        {isMentor && !authLoading && (
          <section aria-labelledby="availability-exceptions">
            <h2
              id="availability-exceptions"
              tabIndex={-1}
              className="text-lg font-semibold text-gray-900 outline-none"
            >
              {t('availability.exceptions.title')}
            </h2>
            <p className="mt-1 text-sm text-gray-500">{t('availability.exceptions.note')}</p>

            <div className="mt-4">
              <Button
                variant="primary"
                type="button"
                onClick={() => setExceptionDialog({ open: true, exception: null })}
              >
                {t('availability.exceptions.add')}
              </Button>
            </div>

            {conflicts !== null && conflicts.length > 0 && (
              <Alert variant="warning" className="mt-4">
                <h3
                  id="availability-conflicts"
                  tabIndex={-1}
                  className="font-semibold outline-none"
                >
                  {t('availability.exceptions.conflicts.title', { count: conflicts.length })}
                </h3>
                <p className="mt-1 text-sm">{t('availability.exceptions.conflicts.body')}</p>
                <ul className="mt-2 list-disc space-y-1 pl-5 text-sm">
                  {conflictSessionsPresentation(
                    conflicts,
                    user?.timezone ?? 'UTC',
                    locale,
                  ).map((conflict) => (
                    <li key={conflict.id}>
                      <Link
                        href={localePath(`/sessions/${conflict.id}`, locale)}
                        className="rounded font-medium text-primary-700 underline hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                      >
                        {conflict.label}
                      </Link>
                    </li>
                  ))}
                </ul>
                <Button
                  variant="outline"
                  type="button"
                  className="mt-3 min-h-6 px-2 py-1 text-xs"
                  onClick={() => setConflicts(null)}
                >
                  {t('availability.exceptions.conflicts.dismiss')}
                </Button>
              </Alert>
            )}

            <div className="mt-4">
              {sortedExceptions.length === 0 ? (
                <p className="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">
                  {t('availability.exceptions.empty')}
                </p>
              ) : (
                <ul className="space-y-2">
                  {sortedExceptions.map((exception) => {
                    const presentation = exceptionItemPresentation(exception, locale);
                    const period = presentation.bandText === null
                      ? `${presentation.periodText} · ${t('availability.exceptions.all_day')}`
                      : `${presentation.periodText} · ${presentation.bandText}`;

                    return (
                      <li
                        key={exception.id}
                        className="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-gray-700"
                      >
                        <div className="min-w-0">
                          <span className="mr-2 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                            {t(`availability.exceptions.${presentation.modeKey}`)}
                          </span>
                          <span className="font-medium text-gray-900">{period}</span>
                          <span className="text-gray-500"> · {exception.timezone}</span>
                          {exception.reason && (
                            <span className="block text-gray-500">
                              {t('availability.exceptions.reason_private', { reason: exception.reason })}
                            </span>
                          )}
                        </div>
                        <span className="flex gap-1">
                          <Button
                            variant="outline"
                            type="button"
                            className="min-h-6 min-w-6 px-2 py-1 text-xs"
                            aria-label={t('availability.exceptions.edit_label', { period })}
                            onClick={() => setExceptionDialog({ open: true, exception })}
                          >
                            {t('availability.weekly.edit')}
                          </Button>
                          <Button
                            variant="outline"
                            type="button"
                            className="min-h-6 min-w-6 px-2 py-1 text-xs"
                            aria-label={t('availability.exceptions.delete_label', { period })}
                            onClick={() => setDeleteExceptionTarget(exception)}
                          >
                            {t('availability.weekly.delete')}
                          </Button>
                        </span>
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>
          </section>
        )}
      </div>

      {isMentor && (
        <>
          <AvailabilityWindowDialog
            window={windowDialog.window}
            viewerTimezone={user?.timezone ?? 'UTC'}
            open={windowDialog.open}
            onOpenChange={(open) => setWindowDialog((current) => ({ ...current, open }))}
            onSuccess={(dayOfWeek, message) => {
              setAnnouncement(message);
              setFocusDay(dayOfWeek);
            }}
          />
          <DeleteWindowDialog
            window={deleteTarget}
            dayLabel={deleteTarget ? dayLabel(deleteTarget.day_of_week) : ''}
            open={deleteTarget !== null}
            onOpenChange={(open) => {
              if (!open) setDeleteTarget(null);
            }}
            onSuccess={(dayOfWeek) => {
              setAnnouncement(t('availability.window.deleted'));
              setFocusDay(dayOfWeek);
            }}
          />
          <AvailabilityExceptionDialog
            exception={exceptionDialog.exception}
            viewerTimezone={viewerTimezone}
            open={exceptionDialog.open}
            onOpenChange={(open) => setExceptionDialog((current) => ({ ...current, open }))}
            onSuccess={(saved) => {
              setAnnouncement(t('availability.exceptions.saved'));
              const affected = saved.conflicting_sessions ?? [];
              setConflicts(affected.length > 0 ? affected : null);
              setFocusExceptions(true);
            }}
          />
          <DeleteExceptionDialog
            exception={deleteExceptionTarget}
            periodText={
              deleteExceptionTarget
                ? (() => {
                    const presentation = exceptionItemPresentation(deleteExceptionTarget, locale);
                    return presentation.bandText === null
                      ? `${presentation.periodText} · ${t('availability.exceptions.all_day')}`
                      : `${presentation.periodText} · ${presentation.bandText}`;
                  })()
                : ''
            }
            open={deleteExceptionTarget !== null}
            onOpenChange={(open) => {
              if (!open) setDeleteExceptionTarget(null);
            }}
            onSuccess={() => {
              setAnnouncement(t('availability.exceptions.deleted'));
              setFocusExceptions(true);
            }}
          />
        </>
      )}
    </AppShell>
  );
}
