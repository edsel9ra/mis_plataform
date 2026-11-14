'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { AppShell } from '@/components/layout/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';
import { useSessionsRange } from '@/hooks/use-scheduling';
import { useBookableRelationships } from '@/hooks/use-booking';
import {
  CalendarNavigator,
  CalendarRange,
  CalendarSessionMapper,
  calendarViewState,
  type CalendarPeriod,
} from '@/lib/scheduling';
import { buttonVariants } from '@/components/ui/button';
import { localePath } from '@/i18n/paths';
import { CalendarDayView } from '@/components/scheduling/CalendarDayView';
import { CalendarWeekView } from '@/components/scheduling/CalendarWeekView';
import { CalendarMonthView } from '@/components/scheduling/CalendarMonthView';
import { CalendarPeriodSelector } from '@/components/scheduling/CalendarPeriodSelector';
import { apiErrorMessage } from '@/lib/errors';
import { Alert as UiAlert } from '@/components/ui/alert';
import { PageSpinner } from '@/components/ui/spinner';

export default function SessionsPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();
  const timezone = user?.timezone ?? 'UTC';

  const [period, setPeriod] = useState<CalendarPeriod>('week');
  const [anchor, setAnchor] = useState<string>(() => new Date().toISOString());

  const now = new Date().toISOString();
  const range = CalendarRange.forPeriod(period, { anchor, now, timezone });

  const { data, isLoading, error, refetch } = useSessionsRange(range.from, range.to, {
    includeCollectives: true,
  });
  const { data: bookableRelationships } = useBookableRelationships();

  const items = (data?.data ?? []).map((session) =>
    CalendarSessionMapper.toCalendarItem(session, timezone, now),
  );

  const heading = new Date(range.from).toLocaleDateString(locale, {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });

  return (
    <AppShell>
      <div className="space-y-6">
        <div className="flex flex-col gap-2">
          <h1 className="text-2xl font-bold text-gray-950">{t('sessions.title')}</h1>
          <p className="text-sm text-gray-500">{t('sessions.subtitle')}</p>
        </div>

        <CalendarPeriodSelector
          period={period}
          heading={heading}
          onPeriodChange={(value) => setPeriod(value as CalendarPeriod)}
          onPrevious={() => setAnchor(CalendarNavigator.previous(period, range.from, timezone))}
          onNext={() => setAnchor(CalendarNavigator.next(period, range.to, timezone))}
          onToday={() => setAnchor(new Date().toISOString())}
        />

        {(user?.role === 'mentor' || (bookableRelationships?.length ?? 0) > 0) && (
          <div className="flex flex-wrap items-center gap-2">
            {(bookableRelationships?.length ?? 0) > 0 && (
              <Link
                href={localePath('/sessions/new', locale)}
                className={buttonVariants({ variant: 'primary' })}
              >
                {t('booking.book')}
              </Link>
            )}
            {user?.role === 'mentor' && (
              <Link
                href={localePath('/availability', locale)}
                className={buttonVariants({ variant: 'outline' })}
              >
                {t('sessions.manage_availability')}
              </Link>
            )}
          </div>
        )}

        <div role="region" aria-label={t('calendar.region_label')}>
          {calendarViewState({ isLoading, isError: Boolean(error), itemCount: items.length }) === 'loading' ? (
            <PageSpinner />
          ) : error ? (
            <UiAlert variant="error">
              {apiErrorMessage(error, (key) => t(key)) || t('common.load_error')}
              <button
                type="button"
                onClick={() => refetch()}
                className="ml-2 rounded font-semibold text-primary-600 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
              >
                {t('common.retry')}
              </button>
            </UiAlert>
          ) : items.length === 0 ? (
            <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
              <h2 className="text-lg font-semibold text-slate-900">{t('sessions.empty_title')}</h2>
              <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">{t('sessions.empty_desc')}</p>
              <div className="mt-5 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <Link
                  href={localePath('/mentors', locale)}
                  className={buttonVariants({ variant: 'primary' })}
                >
                  {t('progress.empty_state_cta')}
                </Link>
                <button
                  type="button"
                  onClick={() => setAnchor(new Date().toISOString())}
                  className={buttonVariants({ variant: 'outline' })}
                >
                  {t('calendar.today')}
                </button>
              </div>
            </div>
          ) : period === 'day' ? (
            <CalendarDayView rangeFrom={range.from} items={items} />
          ) : period === 'week' ? (
            <CalendarWeekView rangeFrom={range.from} timezone={timezone} items={items} />
          ) : (
            <CalendarMonthView reference={range.from} timezone={timezone} items={items} now={now} />
          )}
        </div>
      </div>
    </AppShell>
  );
}
