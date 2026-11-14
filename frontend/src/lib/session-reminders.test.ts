import { describe, expect, it } from 'vitest';
import {
  dismissReminderNotice,
  parseSessionReminder,
  upsertReminderNotice,
  type ReminderNotice,
  type SessionReminderPayload,
} from './session-reminders';

function payload(overrides: Partial<SessionReminderPayload> = {}): SessionReminderPayload {
  return {
    notification_id: 'notif-1',
    event_type: 'session.reminder.immediate',
    session_id: 'session-1',
    title: 'Mentoría semanal',
    message: 'Tu sesión empieza pronto',
    ...overrides,
  };
}

describe('parseSessionReminder', () => {
  it('returns the payload when notification_id and message are present', () => {
    expect(parseSessionReminder(payload())).toEqual(payload());
  });

  it('accepts a payload with a title but no message', () => {
    const result = parseSessionReminder(payload({ message: undefined }));
    expect(result?.notification_id).toBe('notif-1');
  });

  it.each([
    ['non-object', 'text'],
    ['null', null],
    ['missing notification_id', payload({ notification_id: undefined })],
    ['empty notification_id', payload({ notification_id: '  ' })],
    ['missing message and title', payload({ message: undefined, title: undefined })],
    ['empty message and title', payload({ message: ' ', title: '' })],
  ])('returns null for %s', (_label, raw) => {
    expect(parseSessionReminder(raw)).toBeNull();
  });
});

describe('upsertReminderNotice', () => {
  it('appends a new notice for a new notification_id', () => {
    const next = upsertReminderNotice([], payload());

    expect(next).toHaveLength(1);
    expect(next[0]).toMatchObject({ id: 'notif-1', receivedCount: 1 });
  });

  it('replaces an existing notice in place for a repeated notification_id', () => {
    const first = upsertReminderNotice([], payload({ message: 'A' }));
    const second = upsertReminderNotice(first, payload({ message: 'B' }));

    expect(second).toHaveLength(1);
    expect(second[0].payload.message).toBe('B');
    expect(second[0].receivedCount).toBe(2);
    expect(first[0].payload.message).toBe('A');
  });

  it('keeps two distinct notification_ids as two notices in arrival order', () => {
    let notices = upsertReminderNotice([], payload({ notification_id: 'a' }));
    notices = upsertReminderNotice(notices, payload({ notification_id: 'b' }));

    expect(notices.map((notice) => notice.id)).toEqual(['a', 'b']);
  });

  it('keeps the position when updating an older notice', () => {
    let notices = upsertReminderNotice([], payload({ notification_id: 'a' }));
    notices = upsertReminderNotice(notices, payload({ notification_id: 'b' }));
    notices = upsertReminderNotice(notices, payload({ notification_id: 'a', message: 'A2' }));

    expect(notices.map((notice) => notice.id)).toEqual(['a', 'b']);
    expect(notices[0].payload.message).toBe('A2');
    expect(notices[0].receivedCount).toBe(2);
    expect(notices[1].receivedCount).toBe(1);
  });

  it('does not mutate the current array', () => {
    const current: ReminderNotice[] = [
      { id: 'a', payload: payload({ notification_id: 'a' }), receivedCount: 1 },
    ];

    const next = upsertReminderNotice(current, payload({ notification_id: 'a', message: 'nuevo' }));

    expect(current).toHaveLength(1);
    expect(current[0].receivedCount).toBe(1);
    expect(next).not.toBe(current);
  });
});

describe('dismissReminderNotice', () => {
  it('removes the notice with the given id', () => {
    let notices = upsertReminderNotice([], payload({ notification_id: 'a' }));
    notices = upsertReminderNotice(notices, payload({ notification_id: 'b' }));

    const next = dismissReminderNotice(notices, 'a');

    expect(next.map((notice) => notice.id)).toEqual(['b']);
    expect(notices).toHaveLength(2);
  });
});
