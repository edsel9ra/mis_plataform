'use client';

import { useMemo } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { RotateCcw } from 'lucide-react';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import {
  flattenHistoryPages,
  formatHistoryTime,
  groupHistoryByDay,
  historyActorView,
  historyEventView,
  historyIncompleteLabelKeys,
  historyViewState,
} from '@/lib/progress-history';
import type { HistoryEvent, ProgressHistoryPage } from '@/types/progress';

interface ProgressHistoryQuery {
  data: { pages: ProgressHistoryPage[] } | undefined;
  isLoading: boolean;
  isError: boolean;
  hasNextPage: boolean;
  isFetchingNextPage: boolean;
  isFetchNextPageError: boolean;
  fetchNextPage: () => void;
  refetch: () => void;
}

/**
 * Unified progress timeline (spec 015, RF-015.1/.7/.8): dated events grouped
 * into viewer-local days keeping server order, plus the undated annex — dates
 * are never invented for it. Mobile-first, wraps at 320 px.
 */
export function ProgressHistory({ query }: { query: ProgressHistoryQuery }) {
  const t = useTranslations();
  const locale = useLocale();
  const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

  const { events, undated } = useMemo(
    () => flattenHistoryPages(query.data?.pages ?? []),
    [query.data],
  );
  const days = useMemo(
    () => groupHistoryByDay(events, timeZone, locale),
    [events, timeZone, locale],
  );
  const state = historyViewState({
    isLoading: query.isLoading,
    isError: query.isError,
    hasData: Boolean(query.data),
    events,
    undated,
  });

  if (state === 'loading') {
    return (
      <div role="status" aria-live="polite" className="flex flex-col items-center justify-center gap-3 py-16">
        <Spinner size="lg" />
        <p className="text-sm text-slate-500">{t('progress.loading')}</p>
      </div>
    );
  }

  if (state === 'error') {
    return (
      <Alert variant="error">
        <div className="space-y-3">
          <p>{t('progress.error')}</p>
          <Button type="button" size="sm" variant="outline" onClick={() => query.refetch()}>
            <RotateCcw className="h-4 w-4" aria-hidden="true" />
            {t('progress.retry')}
          </Button>
        </div>
      </Alert>
    );
  }

  if (state === 'empty') {
    return (
      <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
        <p className="text-sm text-slate-500">{t('progress.history.empty')}</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {days.map((day) => (
        <section key={day.dayKey} aria-labelledby={`history-day-${day.dayKey}`}>
          <h2 id={`history-day-${day.dayKey}`} className="text-sm font-semibold text-slate-700">
            {day.label}
          </h2>
          <Card className="mt-2">
            <ol className="space-y-4">
              {day.events.map((event) => (
                <HistoryEventRow
                  key={event.id}
                  event={event}
                  locale={locale}
                  timeZone={timeZone}
                />
              ))}
            </ol>
          </Card>
        </section>
      ))}

      {undated.length > 0 && (
        <section aria-labelledby="history-undated">
          <h2 id="history-undated" className="text-sm font-semibold text-slate-700">
            {t('progress.history.undated_title')}
          </h2>
          <Card className="mt-2">
            <p className="mb-3 text-xs text-slate-500">{t('progress.history.undated_hint')}</p>
            <ol className="space-y-4">
              {undated.map((event) => (
                <HistoryEventRow key={event.id} event={event} locale={locale} timeZone={timeZone} />
              ))}
            </ol>
          </Card>
        </section>
      )}

      <div aria-live="polite" className="space-y-3">
        {query.isFetchNextPageError && (
          <Alert variant="error">
            <div className="space-y-3">
              <p>{t('progress.error')}</p>
              <Button type="button" size="sm" variant="outline" onClick={() => query.fetchNextPage()}>
                <RotateCcw className="h-4 w-4" aria-hidden="true" />
                {t('progress.retry')}
              </Button>
            </div>
          </Alert>
        )}
        {query.hasNextPage && (
          <Button
            type="button"
            variant="outline"
            className="w-full"
            // aria-disabled keeps the button focusable mid-fetch; `disabled`
            // would drop keyboard focus to <body> when the fetch starts.
            aria-disabled={query.isFetchingNextPage}
            aria-busy={query.isFetchingNextPage}
            onClick={() => {
              if (!query.isFetchingNextPage) query.fetchNextPage();
            }}
          >
            {query.isFetchingNextPage ? (
              <>
                <Spinner size="sm" />
                {t('progress.history.loading_more')}
              </>
            ) : (
              t('progress.history.load_more')
            )}
          </Button>
        )}
      </div>
    </div>
  );
}

function HistoryEventRow({
  event,
  locale,
  timeZone,
}: {
  event: HistoryEvent;
  locale: string;
  timeZone: string;
}) {
  const t = useTranslations();
  const view = historyEventView(event, locale);
  const actor = historyActorView(event.actor);
  const incompleteKeys = historyIncompleteLabelKeys(event.incomplete);

  const detailValues: Record<string, string> = { ...view.detail?.values };
  for (const [name, entry] of Object.entries(view.detail?.valueKeys ?? {})) {
    detailValues[name] = t.has(entry.key) ? t(entry.key) : entry.fallback;
  }

  return (
    <li className="rounded-xl border border-slate-100 p-4 text-sm">
      <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <span className="flex min-w-0 items-center gap-2">
          <Badge variant="primary">{t(`progress.history.domains.${event.domain}`)}</Badge>
          <span className="font-medium text-slate-900">{t(view.titleKey, view.titleValues)}</span>
        </span>
        {event.occurred_at !== null && (
          <time dateTime={event.occurred_at} className="text-xs text-slate-500">
            {formatHistoryTime(event.occurred_at, timeZone, locale)}
          </time>
        )}
      </div>

      {view.detail !== null && (
        <p className="mt-1 text-xs text-slate-600">{t(view.detail.key, detailValues)}</p>
      )}

      <p className="mt-1 text-xs text-slate-500">
        {actor.kind === 'user'
          ? t('progress.history.actor.by', { name: actor.name })
          : actor.kind === 'system'
            ? t('progress.history.actor.system')
            : actor.kind === 'hidden'
              ? `${t('progress.history.actor.hidden')} — ${t('progress.history.actor.hidden_hint')}`
              : t('progress.history.actor.unknown')}
      </p>

      {incompleteKeys.length > 0 && (
        <p className="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
          <Badge variant="warning">{t('progress.history.incomplete')}</Badge>
          <span className="text-slate-500">{incompleteKeys.map((key) => t(key)).join(', ')}</span>
        </p>
      )}
    </li>
  );
}
