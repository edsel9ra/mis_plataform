import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { api } from './api';

export interface RealtimeConfig {
  key: string;
  host: string;
  port: number;
  scheme: string;
}

export interface BroadcastAuthResponse {
  auth: string;
  channel_data?: string;
}

type AuthorizeCallback = (
  error: Error | null,
  data: BroadcastAuthResponse | null,
) => void;

declare global {
  interface Window {
    Pusher?: typeof Pusher;
  }
}

export function createChannelAuthorizer() {
  return (channel: { name: string }) => ({
    authorize: (socketId: string, callback: AuthorizeCallback) => {
      api.post<BroadcastAuthResponse>('/broadcasting/auth', {
        socket_id: socketId,
        channel_name: channel.name,
      })
        .then((response) => callback(null, response))
        .catch((error: unknown) => callback(error instanceof Error ? error : new Error('Broadcast authorization failed'), null));
    },
  });
}

export function createEcho(config: RealtimeConfig): Echo<'reverb'> {
  window.Pusher = Pusher;

  const forceTLS = config.scheme === 'https';

  return new Echo<'reverb'>({
    broadcaster: 'reverb',
    key: config.key,
    wsHost: config.host,
    wsPort: config.port,
    wssPort: config.port,
    forceTLS,
    enabledTransports: ['ws', 'wss'],
    authorizer: createChannelAuthorizer(),
  });
}

export interface ChannelLike {
  listen: (event: string, callback: (payload: unknown) => void) => unknown;
  stopListening: (event: string, callback: (payload: unknown) => void) => unknown;
}

export interface EchoLike {
  private: (channel: string) => ChannelLike;
  leave: (channel: string) => void;
}

function subscribeUserChannel(
  echo: EchoLike,
  userId: string,
  event: string,
  onEvent: (payload: unknown) => void,
): () => void {
  const channel = echo.private(`user.${userId}`);
  channel.listen(event, onEvent);
  return () => channel.stopListening(event, onEvent);
}

export function subscribeNotificationChannel(
  echo: EchoLike,
  userId: string,
  onEvent: () => void,
): () => void {
  return subscribeUserChannel(echo, userId, '.notification.pushed', onEvent);
}

export function subscribeSessionReminderChannel(
  echo: EchoLike,
  userId: string,
  onEvent: (payload: unknown) => void,
): () => void {
  return subscribeUserChannel(echo, userId, '.session.reminder', onEvent);
}

export function subscribeRelationshipChannel(
  echo: EchoLike,
  relationshipId: string,
  onEvent: () => void,
): () => void {
  const channelName = `relationship.${relationshipId}`;
  echo.private(channelName).listen('.message.sent', onEvent);
  return () => echo.leave(channelName);
}
