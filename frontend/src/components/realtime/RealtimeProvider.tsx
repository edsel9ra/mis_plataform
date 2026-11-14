'use client';

import Echo from 'laravel-echo';
import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { useAuth } from '@/components/auth/AuthProvider';
import { getSocketId, setSocketId } from '@/lib/api';
import { createEcho, type RealtimeConfig } from '@/lib/realtime';

interface RealtimeContextValue {
  echo: Echo<'reverb'> | null;
  isConnected: boolean;
}

const RealtimeContext = createContext<RealtimeContextValue>({
  echo: null,
  isConnected: false,
});

export function useRealtime(): RealtimeContextValue {
  return useContext(RealtimeContext);
}

export function RealtimeProvider({
  config,
  children,
}: {
  config: RealtimeConfig | null;
  children: ReactNode;
}) {
  const { user } = useAuth();
  const userId = user?.id;
  const [echo, setEcho] = useState<Echo<'reverb'> | null>(null);
  const [isConnected, setIsConnected] = useState(false);

  useEffect(() => {
    if (!config?.key || !userId) {
      return;
    }

    const instance = createEcho(config);
    const connection = instance.connector.pusher.connection;

    const handleConnected = () => {
      setSocketId(instance.socketId() ?? null);
      setIsConnected(true);
    };
    const handleDisconnected = () => {
      setIsConnected(false);
      setSocketId(null);
    };
    const handleError = () => {
      setIsConnected(false);
    };

    connection.bind('connected', handleConnected);
    connection.bind('disconnected', handleDisconnected);
    connection.bind('error', handleError);

    if (instance.socketId() && instance.socketId() !== getSocketId()) {
      setSocketId(instance.socketId() ?? null);
    }

    setEcho(instance);

    return () => {
      connection.unbind('connected', handleConnected);
      connection.unbind('disconnected', handleDisconnected);
      connection.unbind('error', handleError);
      instance.disconnect();
      setEcho(null);
      setIsConnected(false);
      setSocketId(null);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- primitive config fields are the real deps; object identity changes each render
  }, [config?.key, config?.host, config?.port, config?.scheme, userId]);

  return (
    <RealtimeContext.Provider value={{ echo, isConnected }}>
      {children}
    </RealtimeContext.Provider>
  );
}
