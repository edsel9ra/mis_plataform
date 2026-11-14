'use client';

import { useCallback, useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { useRealtime } from '@/components/realtime/RealtimeProvider';
import { queryKeys } from '@/lib/query-keys';
import { subscribeSessionReminderChannel } from '@/lib/realtime';
import {
  dismissReminderNotice,
  parseSessionReminder,
  upsertReminderNotice,
  type ReminderNotice,
} from '@/lib/session-reminders';

export function useSessionReminders(userId: string | undefined) {
  const { echo } = useRealtime();
  const queryClient = useQueryClient();
  const [notices, setNotices] = useState<ReminderNotice[]>([]);
  const [lastAnnounced, setLastAnnounced] = useState<ReminderNotice | null>(null);

  useEffect(() => {
    if (!echo || !userId) {
      return;
    }

    return subscribeSessionReminderChannel(echo, userId, (raw) => {
      const payload = parseSessionReminder(raw);
      if (!payload) {
        return;
      }

      setNotices((current) => {
        const next = upsertReminderNotice(current, payload);
        setLastAnnounced(next.find((notice) => notice.id === payload.notification_id) ?? null);
        return next;
      });

      queryClient.invalidateQueries({ queryKey: queryKeys.notificationsRoot });
      queryClient.invalidateQueries({ queryKey: queryKeys.unreadNotificationCount });
    });
  }, [echo, queryClient, userId]);

  useEffect(() => {
    if (!userId) {
      setNotices([]);
      setLastAnnounced(null);
    }
  }, [userId]);

  const dismiss = useCallback((id: string) => {
    setNotices((current) => dismissReminderNotice(current, id));
  }, []);

  return { notices, lastAnnounced, dismiss };
}
