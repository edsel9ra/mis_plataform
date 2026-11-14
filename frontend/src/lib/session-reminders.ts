export interface SessionReminderPayload {
  notification_id: string;
  event_type?: string;
  session_id?: string;
  title?: string;
  message?: string;
  scheduled_at?: string;
  timezone?: string;
  hours_until?: number | string;
  meet_link?: string | null;
  [key: string]: unknown;
}

export interface ReminderNotice {
  id: string;
  payload: SessionReminderPayload;
  receivedCount: number;
}

function isNonEmptyString(value: unknown): value is string {
  return typeof value === 'string' && value.trim() !== '';
}

export function parseSessionReminder(raw: unknown): SessionReminderPayload | null {
  if (typeof raw !== 'object' || raw === null) {
    return null;
  }

  const payload = raw as Record<string, unknown>;

  if (!isNonEmptyString(payload.notification_id)) {
    return null;
  }

  if (!isNonEmptyString(payload.message) && !isNonEmptyString(payload.title)) {
    return null;
  }

  return payload as SessionReminderPayload;
}

export function upsertReminderNotice(
  current: ReminderNotice[],
  incoming: SessionReminderPayload,
): ReminderNotice[] {
  const index = current.findIndex((notice) => notice.id === incoming.notification_id);

  if (index === -1) {
    return [
      ...current,
      { id: incoming.notification_id, payload: incoming, receivedCount: 1 },
    ];
  }

  const next = [...current];
  const existing = next[index];
  next[index] = {
    ...existing,
    payload: incoming,
    receivedCount: existing.receivedCount + 1,
  };
  return next;
}

export function dismissReminderNotice(
  current: ReminderNotice[],
  id: string,
): ReminderNotice[] {
  return current.filter((notice) => notice.id !== id);
}
