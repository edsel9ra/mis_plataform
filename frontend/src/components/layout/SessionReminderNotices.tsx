'use client';

import { useRef } from 'react';
import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { BellRing, X } from 'lucide-react';
import { useAuth } from '@/components/auth/AuthProvider';
import { useSessionReminders } from '@/hooks/use-session-reminders';
import { localePath } from '@/i18n/paths';

export function SessionReminderNotices() {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();
  const { notices, lastAnnounced, dismiss } = useSessionReminders(user?.id);
  const sectionRef = useRef<HTMLElement>(null);

  if (!user || notices.length === 0) {
    return (
      <div aria-live="polite" aria-atomic="true" role="status" className="sr-only">
        {lastAnnounced ? `${lastAnnounced.payload.title ?? ''} ${lastAnnounced.payload.message ?? ''}`.trim() : ''}
      </div>
    );
  }

  const handleDismiss = (id: string, index: number) => {
    dismiss(id);
    const remaining = notices.filter((notice) => notice.id !== id);
    const next = remaining[Math.min(index, remaining.length - 1)];
    const nextButton = next
      ? sectionRef.current?.querySelector<HTMLButtonElement>(`[data-dismiss-id="${next.id}"]`)
      : null;
    if (nextButton) {
      nextButton.focus();
    } else {
      document.getElementById('app-content')?.focus();
    }
  };

  return (
    <>
      <div aria-live="polite" aria-atomic="true" role="status" className="sr-only">
        {`${lastAnnounced?.payload.title ?? ''} ${lastAnnounced?.payload.message ?? ''}`.trim()}
      </div>
      <section
        ref={sectionRef}
        aria-label={t('reminders.region_label')}
        className="fixed inset-x-0 bottom-0 z-40 flex max-h-[40vh] flex-col gap-2 overflow-y-auto p-3 sm:inset-x-auto sm:right-4 sm:bottom-4 sm:w-96 sm:max-w-[calc(100vw-2rem)]"
      >
        {notices.map((notice, index) => {
          const { payload } = notice;
          const title = payload.title ?? t('reminders.default_title');
          return (
            <article
              key={notice.id}
              data-reminder={notice.id}
              className="rounded-xl border border-slate-200 bg-white p-4 shadow-lg"
            >
              <div className="flex items-start gap-3">
                <BellRing className="mt-0.5 h-5 w-5 shrink-0 text-primary-600" aria-hidden="true" />
                <div className="min-w-0 flex-1">
                  <h2 className="text-sm font-semibold text-slate-900">{title}</h2>
                  {payload.message ? (
                    <p className="mt-1 text-sm break-words text-slate-600">{payload.message}</p>
                  ) : null}
                  {payload.session_id ? (
                    <Link
                      href={localePath(`/sessions/${payload.session_id}`, locale)}
                      className="mt-2 inline-flex min-h-6 items-center rounded text-sm font-medium text-primary-700 underline underline-offset-2 hover:text-primary-800 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:outline-none"
                    >
                      {t('reminders.view_session')}
                    </Link>
                  ) : null}
                </div>
                <button
                  type="button"
                  data-dismiss-id={notice.id}
                  onClick={() => handleDismiss(notice.id, index)}
                  aria-label={`${t('common.dismiss')} ${title}`}
                  className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:outline-none"
                >
                  <X className="h-4 w-4" aria-hidden="true" />
                </button>
              </div>
            </article>
          );
        })}
      </section>
    </>
  );
}
