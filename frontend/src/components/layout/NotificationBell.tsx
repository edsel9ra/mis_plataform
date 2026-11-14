'use client';

import { useEffect, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Bell, CheckCheck } from 'lucide-react';
import { useAuth } from '@/components/auth/AuthProvider';
import {
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
  useNotificationChannel,
  useNotifications,
  useUnreadNotificationCount,
} from '@/hooks/use-notifications';
import { cn } from '@/lib/utils';
import { formatUnreadBadge, KNOWN_NOTIFICATION_TYPES } from '@/lib/notifications';

function relativeTime(iso: string, t: (key: string, values?: Record<string, number>) => string): string {
  const seconds = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
  if (seconds < 90) return t('just_now');
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return t('minutes_ago', { count: minutes });
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return t('hours_ago', { count: hours });
  return t('days_ago', { count: Math.floor(hours / 24) });
}

export function NotificationBell() {
  const t = useTranslations();
  const { user } = useAuth();
  const [isOpen, setIsOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);

  const { data: countData } = useUnreadNotificationCount();
  const { data: notifications } = useNotifications(1, 10);
  const markRead = useMarkNotificationRead();
  const markAllRead = useMarkAllNotificationsRead();
  useNotificationChannel(user?.id);

  const unreadCount = countData?.unread_count ?? 0;

  useEffect(() => {
    if (!isOpen) return;

    const handlePointerDown = (event: PointerEvent) => {
      if (rootRef.current && !rootRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setIsOpen(false);
        buttonRef.current?.focus();
      }
    };

    document.addEventListener('pointerdown', handlePointerDown);
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('pointerdown', handlePointerDown);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen]);

  const items = notifications?.data ?? [];

  return (
    <div ref={rootRef} className="relative">
      <button
        ref={buttonRef}
        type="button"
        onClick={() => setIsOpen((open) => !open)}
        className="relative rounded-lg p-2 text-slate-200 transition hover:bg-slate-800 focus-visible:ring-white"
        aria-label={unreadCount > 0
          ? t('notifications.aria_button', { badge: formatUnreadBadge(unreadCount), count: unreadCount })
          : t('notifications.aria_button_none')}
        aria-expanded={isOpen}
        aria-haspopup="true"
      >
        <Bell className="h-5 w-5" aria-hidden="true" />
        {unreadCount > 0 && (
          <span
            aria-hidden="true"
            className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-700 px-1 text-[10px] font-bold text-white"
          >
            {formatUnreadBadge(unreadCount)}
          </span>
        )}
      </button>

      {isOpen && (
        <div
          role="menu"
          aria-label={t('notifications.title')}
          className="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
        >
          <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h2 className="text-sm font-semibold text-slate-950">{t('notifications.title')}</h2>
            {unreadCount > 0 && (
              <button
                type="button"
                onClick={() => markAllRead.mutate()}
                disabled={markAllRead.isPending}
                className="flex items-center gap-1 text-xs font-medium text-primary-600 transition hover:text-primary-700 disabled:opacity-50"
              >
                <CheckCheck className="h-3.5 w-3.5" aria-hidden="true" />
                {t('notifications.mark_all_read')}
              </button>
            )}
          </div>

          <ul className="max-h-80 overflow-y-auto" aria-live="polite">
            {items.length === 0 && (
              <li className="px-4 py-6 text-center text-sm text-slate-500">
                {t('notifications.empty')}
              </li>
            )}
            {items.map((notification) => {
              const typeKey = KNOWN_NOTIFICATION_TYPES.has(notification.type) ? notification.type : 'generic';
              const isUnread = notification.read_at === null;
              const messageText = typeof notification.data?.message === 'string'
                ? notification.data.message
                : typeof notification.data?.title === 'string'
                  ? notification.data.title
                  : null;

              return (
                <li key={notification.id}>
                  <button
                    type="button"
                    onClick={() => {
                      if (isUnread) markRead.mutate(notification.id);
                    }}
                    className={cn(
                      'flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-slate-50',
                      isUnread && 'bg-primary-50/60',
                    )}
                  >
                    <span
                      aria-hidden="true"
                      className={cn(
                        'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                        isUnread ? 'bg-primary-600' : 'bg-slate-200',
                      )}
                    />
                    <span className="min-w-0 flex-1">
                      <span className="block text-sm font-medium text-slate-950">
                        {t(`notifications.types.${typeKey}`)}
                      </span>
                      {messageText && (
                        <span className="mt-0.5 block truncate text-xs text-slate-500">
                          {messageText}
                        </span>
                      )}
                      <span className="mt-0.5 block text-xs text-slate-400">
                        {relativeTime(notification.created_at, (key, values) => t(`notifications.${key}`, values))}
                      </span>
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>
        </div>
      )}
    </div>
  );
}
