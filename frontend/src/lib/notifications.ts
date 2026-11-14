export const KNOWN_NOTIFICATION_TYPES = new Set(['mentor_alert', 'session_reminder', 'session_change']);

export function formatUnreadBadge(count: number): string {
  return count > 9 ? '9+' : String(count);
}
