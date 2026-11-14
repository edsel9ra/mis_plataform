import { afterEach, describe, expect, it, vi } from 'vitest';

import { api, setSocketId } from './api';
import {
  createChannelAuthorizer,
  subscribeNotificationChannel,
  subscribeRelationshipChannel,
  subscribeSessionReminderChannel,
  type BroadcastAuthResponse,
  type ChannelLike,
  type EchoLike,
} from './realtime';
import { formatUnreadBadge } from './notifications';
import { messageSchema } from './schemas/message';

afterEach(() => {
  setSocketId(null);
  vi.restoreAllMocks();
});

describe('broadcasting channel authorizer', () => {
  it('posts to /broadcasting/auth and resolves the callback', async () => {
    const post = vi.spyOn(api, 'post').mockResolvedValue({ auth: 'signed-token' });
    const authorizer = createChannelAuthorizer()({ name: 'private-user.abc' });
    const callback = vi.fn();

    authorizer.authorize('socket-1', callback);
    await vi.waitFor(() => expect(callback).toHaveBeenCalled());

    expect(post).toHaveBeenCalledWith('/broadcasting/auth', {
      socket_id: 'socket-1',
      channel_name: 'private-user.abc',
    });
    expect(callback).toHaveBeenCalledWith(null, { auth: 'signed-token' });
  });

  it('rejects the callback when authorization fails', async () => {
    vi.spyOn(api, 'post').mockRejectedValue(new Error('HTTP 403'));
    const authorizer = createChannelAuthorizer()({ name: 'private-user.abc' });
    const callback = vi.fn();

    authorizer.authorize('socket-1', callback);
    await vi.waitFor(() => expect(callback).toHaveBeenCalled());

    expect(callback.mock.calls[0]?.[0]).toBeInstanceOf(Error);
    expect(callback.mock.calls[0]?.[1]).toBeNull();
  });
});

function fakeEcho() {
  const listeners = new Map<string, Set<(payload: unknown) => void>>();
  const channel: ChannelLike = {
    listen: (event, callback) => {
      const set = listeners.get(event) ?? new Set();
      set.add(callback);
      listeners.set(event, set);
      return channel;
    },
    stopListening: (event, callback) => {
      listeners.get(event)?.delete(callback);
      return channel;
    },
  };
  const echo = {
    private: vi.fn(() => channel),
    leave: vi.fn(),
  } satisfies EchoLike;
  const emit = (event: string, payload: unknown) => {
    listeners.get(event)?.forEach((callback) => callback(payload));
  };
  const count = (event: string) => listeners.get(event)?.size ?? 0;
  return { echo, listeners, emit, count };
}

describe('channel subscriptions', () => {
  it('subscribes to the user channel and fires on .notification.pushed', () => {
    const { echo, emit } = fakeEcho();
    const onEvent = vi.fn();

    const unsubscribe = subscribeNotificationChannel(echo, 'user-1', onEvent);

    expect(echo.private).toHaveBeenCalledWith('user.user-1');
    emit('.notification.pushed', { id: 'n1' });
    expect(onEvent).toHaveBeenCalledTimes(1);

    unsubscribe();
    emit('.notification.pushed', { id: 'n2' });
    expect(onEvent).toHaveBeenCalledTimes(1);
    expect(echo.leave).not.toHaveBeenCalled();
  });

  it('keeps other subscribers on the user channel when one unsubscribes', () => {
    const { echo, emit, count } = fakeEcho();
    const onNotification = vi.fn();
    const onReminder = vi.fn();

    const unsubscribeNotifications = subscribeNotificationChannel(echo, 'user-1', onNotification);
    const unsubscribeReminders = subscribeSessionReminderChannel(echo, 'user-1', onReminder);

    unsubscribeNotifications();

    emit('.session.reminder', { notification_id: 'r1' });
    expect(onReminder).toHaveBeenCalledTimes(1);
    expect(count('.session.reminder')).toBe(1);

    unsubscribeReminders();
    expect(count('.session.reminder')).toBe(0);
    expect(echo.leave).not.toHaveBeenCalled();
  });

  it('subscribes to the relationship channel, fires on .message.sent, and leaves', () => {
    const { echo, emit } = fakeEcho();
    const onEvent = vi.fn();

    const unsubscribe = subscribeRelationshipChannel(echo, 'rel-1', onEvent);

    expect(echo.private).toHaveBeenCalledWith('relationship.rel-1');
    emit('.message.sent', { id: 'm1' });
    expect(onEvent).toHaveBeenCalledTimes(1);

    unsubscribe();
    expect(echo.leave).toHaveBeenCalledWith('relationship.rel-1');
  });
});

describe('X-Socket-ID header', () => {
  it('sends X-Socket-ID only while a socket id is set', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      text: () => Promise.resolve('{}'),
    } as unknown as Response);
    vi.stubGlobal('fetch', fetchMock);

    setSocketId('socket-xyz');
    await api.get('/notifications/unread-count');
    expect(new Headers(fetchMock.mock.calls[0]?.[1]?.headers).get('X-Socket-ID')).toBe('socket-xyz');

    setSocketId(null);
    await api.get('/notifications/unread-count');
    expect(new Headers(fetchMock.mock.calls[1]?.[1]?.headers).get('X-Socket-ID')).toBeNull();
  });
});

describe('messageSchema', () => {
  it('accepts normal content and trims it', () => {
    expect(messageSchema.parse({ content: '  hola  ' })).toEqual({ content: 'hola' });
  });

  it('rejects empty and whitespace-only content', () => {
    expect(messageSchema.safeParse({ content: '' }).success).toBe(false);
    expect(messageSchema.safeParse({ content: '   ' }).success).toBe(false);
  });

  it('rejects content over 5000 chars', () => {
    expect(messageSchema.safeParse({ content: 'x'.repeat(5001) }).success).toBe(false);
  });
});

describe('formatUnreadBadge', () => {
  it('caps at 9+', () => {
    expect(formatUnreadBadge(0)).toBe('0');
    expect(formatUnreadBadge(9)).toBe('9');
    expect(formatUnreadBadge(10)).toBe('9+');
    expect(formatUnreadBadge(142)).toBe('9+');
  });
});

describe('BroadcastAuthResponse type', () => {
  it('documents the auth response contract', () => {
    const sample: BroadcastAuthResponse = { auth: 'a:b' };
    expect(sample.auth).toBe('a:b');
  });
});
