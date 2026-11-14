'use client';

import { useEffect, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useConfirmAttendance } from '@/hooks/use-sessions';
import { apiErrorMessage } from '@/lib/errors';
import {
  attendanceMarkLabelKey,
  attendanceTargetsPresentation,
  sessionActionErrorMessage,
  sessionActionErrorOutcome,
  type AttendanceEntry,
  type SessionAttendeeLike,
} from '@/lib/session-actions';
import { cn } from '@/lib/utils';

interface AttendancePanelProps {
  sessionId: string;
  entries: AttendanceEntry[];
  attendees: readonly (SessionAttendeeLike | null | undefined)[];
  /** Announces a success message in the page's persistent status region. */
  onSuccess: (message: string) => void;
}

interface RowError {
  userId: string;
  message: string;
  staleDetail: boolean;
}

export function AttendancePanel({
  sessionId,
  entries,
  attendees,
  onSuccess,
}: AttendancePanelProps) {
  const t = useTranslations();
  const confirmAttendance = useConfirmAttendance(sessionId);
  const [pending, setPending] = useState<{ userId: string; status: string } | null>(null);
  const [rowError, setRowError] = useState<RowError | null>(null);
  const [confirmedUserId, setConfirmedUserId] = useState<string | null>(null);
  const panelRef = useRef<HTMLElement>(null);

  const rows = attendanceTargetsPresentation(entries, attendees);

  // After a refetch the pressed button may be gone (the row flipped to a
  // reason text); move focus to the row's name element instead of losing it.
  useEffect(() => {
    if (!confirmedUserId) return;
    const panel = panelRef.current;
    if (!panel) return;
    const active = document.activeElement;
    if (active && panel.contains(active)) {
      setConfirmedUserId(null);
      return;
    }
    const target =
      panel.querySelector<HTMLElement>(`[data-attendance-name="${confirmedUserId}"]`) ??
      panel.querySelector<HTMLElement>(`[data-attendance-button="${confirmedUserId}"]`);
    target?.focus();
    setConfirmedUserId(null);
  }, [entries, confirmedUserId]);

  async function mark(userId: string, self: boolean, status: 'attended' | 'absent', name: string | null) {
    setPending({ userId, status });
    setRowError(null);
    try {
      await confirmAttendance.mutateAsync({
        status,
        ...(self ? {} : { attendee_user_id: userId }),
      });
      const displayName = self ? t('session_actions.attendance.self') : (name ?? t('session_actions.attendance.unknown_person'));
      onSuccess(t('session_actions.attendance.success', {
        name: displayName,
        status: t(`session_actions.attendance.${status}`),
      }));
      setConfirmedUserId(userId);
    } catch (error) {
      const outcome = sessionActionErrorOutcome(error);
      setRowError({
        userId,
        message: sessionActionErrorMessage(outcome, t)
          ?? outcome.fieldErrors.attendance?.[0]
          ?? apiErrorMessage(error, (key) => t(key))
          ?? t('common.error'),
        staleDetail: outcome.staleDetail || outcome.kind === 'forbidden',
      });
    } finally {
      setPending(null);
    }
  }

  return (
    <section ref={panelRef} aria-labelledby="attendance-panel-title" className="">
      <h2 id="attendance-panel-title" className="text-base font-semibold text-gray-950">
        {t('session_actions.attendance.title')}
      </h2>
      <ul className="mt-3 space-y-3">
        {rows.map((row) => {
          const displayName = row.self
            ? t('session_actions.attendance.self')
            : (row.name ?? t('session_actions.attendance.unknown_person'));
          const rowPending = pending?.userId === row.userId;
          return (
            <li
              key={row.userId}
              className="flex flex-col gap-2 rounded-lg border border-gray-100 p-3 sm:flex-row sm:items-center sm:justify-between"
            >
              <div className="min-w-0">
                <p
                  data-attendance-name={row.userId}
                  tabIndex={-1}
                  className="truncate text-sm font-medium text-gray-900 outline-none"
                >
                  {displayName}
                </p>
                {row.currentStatusKey && (
                  <p className="text-xs text-gray-500">
                    {t('session_actions.attendance.current', { status: t(row.currentStatusKey) })}
                  </p>
                )}
              </div>
              {row.allowed ? (
                <div className="flex shrink-0 gap-2">
                  {(['attended', 'absent'] as const).map((status) => (
                    <Button
                      key={status}
                      type="button"
                      variant={row.currentStatus === status ? 'primary' : 'outline'}
                      size="sm"
                      data-attendance-button={row.userId}
                      // Visible text first so the accessible name satisfies
                      // label-content-name-mismatch, then the person context.
                      aria-label={`${t(`session_actions.attendance.${status}`)} — ${t(attendanceMarkLabelKey(row.self, status), row.self ? {} : { name: displayName })}`}
                      aria-pressed={row.currentStatus === status}
                      disabled={pending !== null}
                      loading={rowPending && pending.status === status}
                      onClick={() => void mark(row.userId, row.self, status, row.name)}
                    >
                      {t(`session_actions.attendance.${status}`)}
                    </Button>
                  ))}
                </div>
              ) : (
                row.reasonKey && (
                  <p className="shrink-0 text-sm text-gray-600">{t(row.reasonKey)}</p>
                )
              )}
              {rowError?.userId === row.userId && (
                <div className="sm:basis-full">
                  <Alert variant="error">{rowError.message}</Alert>
                  {rowError.staleDetail && (
                    <Alert variant="info" className={cn('mt-2')}>
                      {t('session_actions.common.stale_notice')}
                    </Alert>
                  )}
                </div>
              )}
            </li>
          );
        })}
      </ul>
    </section>
  );
}
