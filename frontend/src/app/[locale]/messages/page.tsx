'use client';

import { Suspense, useEffect, useMemo, useRef } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useLocale, useTranslations } from 'next-intl';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import type { Resolver } from 'react-hook-form';
import { ArrowLeft, MessageSquare, Send } from 'lucide-react';
import { AppShell } from '@/components/layout/AppShell';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { PageSpinner } from '@/components/ui/spinner';
import { useAuth } from '@/components/auth/AuthProvider';
import { useRelationships, type Relationship } from '@/hooks/use-relationships';
import {
  useMarkMessageRead,
  useRelationshipChannel,
  useRelationshipMessages,
  useSendMessage,
  type ChatMessage,
} from '@/hooks/use-messages';
import { useNotificationChannel } from '@/hooks/use-notifications';
import { messageSchema, type MessageFormInput, type MessageFormValues } from '@/lib/schemas/message';
import { cn } from '@/lib/utils';

function counterpartName(relationship: Relationship, viewerIsMentor: boolean, fallback: string): string {
  if (viewerIsMentor) {
    return relationship.source?.name ?? fallback;
  }
  return relationship.mentor?.full_name ?? fallback;
}

function formatTime(iso: string, locale: string): string {
  return new Date(iso).toLocaleString(locale, { hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short' });
}

export default function MessagesPage() {
  return (
    <Suspense fallback={<AppShell><PageSpinner /></AppShell>}>
      <MessagesContent />
    </Suspense>
  );
}

function MessagesContent() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const searchParams = useSearchParams();
  const { user } = useAuth();

  const selectedId = searchParams.get('relationship') ?? undefined;
  const viewerIsMentor = user?.role === 'mentor';

  const { data: relationshipsPage, isLoading: isLoadingRelationships } = useRelationships(1, 50);
  const relationships = useMemo(() => relationshipsPage?.data ?? [], [relationshipsPage]);

  const messagesQuery = useRelationshipMessages(selectedId);
  const sendMessage = useSendMessage(selectedId);
  const markRead = useMarkMessageRead(selectedId);
  useRelationshipChannel(selectedId);
  useNotificationChannel(user?.id);

  const messages = useMemo<ChatMessage[]>(() => {
    const pages = messagesQuery.data?.pages ?? [];
    return [...pages].reverse().flatMap((page) => [...(page.data ?? [])].reverse());
  }, [messagesQuery.data]);

  const unreadFromOthers = useMemo(
    () => messages.filter((m) => m.sender_id !== user?.id && m.read_at === null),
    [messages, user?.id],
  );
  const unreadKey = unreadFromOthers.map((m) => m.id).join(',');

  useEffect(() => {
    if (!unreadKey) return;
    unreadKey.split(',').forEach((id) => markRead.mutate(id));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [unreadKey]);

  const bottomRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    bottomRef.current?.scrollIntoView({ block: 'end' });
  }, [messages.length, selectedId]);

  const { register, handleSubmit, reset, formState } = useForm<MessageFormInput, unknown, MessageFormValues>({
    resolver: zodResolver(messageSchema) as Resolver<MessageFormInput, unknown, MessageFormValues>,
    mode: 'onChange',
    defaultValues: { content: '' },
  });

  const selectedRelationship = relationships.find((r) => r.id === selectedId);

  const openConversation = (id: string) => {
    const params = new URLSearchParams(searchParams.toString());
    params.set('relationship', id);
    router.push(`?${params.toString()}`, { scroll: false });
  };

  const closeConversation = () => {
    const params = new URLSearchParams(searchParams.toString());
    params.delete('relationship');
    router.push(`?${params.toString()}`, { scroll: false });
  };

  const onSubmit = handleSubmit((values) => {
    if (sendMessage.isPending) return;

    sendMessage.mutate(values.content, {
      onSuccess: () => reset({ content: '' }),
    });
  });

  if (isLoadingRelationships) {
    return (
      <AppShell>
        <PageSpinner />
      </AppShell>
    );
  }

  if (relationships.length === 0) {
    return (
      <AppShell>
        <div className="space-y-6">
          <div>
            <h1 className="text-2xl font-bold text-gray-950">{t('nav.messages')}</h1>
            <p className="mt-1 text-sm text-gray-500">{t('messages.subtitle')}</p>
          </div>
          <Card className="border-dashed border-gray-300 bg-white text-center shadow-sm">
            <MessageSquare className="mx-auto h-10 w-10 text-primary-500" aria-hidden="true" />
            <h2 className="mt-4 text-lg font-semibold text-gray-950">{t('messages.empty_title')}</h2>
            <p className="mt-2 text-sm text-gray-500">{t('messages.empty_desc')}</p>
          </Card>
        </div>
      </AppShell>
    );
  }

  const conversationList = (
    <div className={cn('space-y-2', selectedId && 'hidden lg:block')}>
      {relationships.map((relationship) => {
        const name = counterpartName(relationship, viewerIsMentor, t('messages.unknown_participant'));
        const isActive = relationship.id === selectedId;
        return (
          <button
            key={relationship.id}
            type="button"
            onClick={() => openConversation(relationship.id)}
            aria-current={isActive ? 'page' : undefined}
            className={cn(
              'flex w-full items-center gap-3 rounded-2xl border px-4 py-3 text-left transition',
              isActive
                ? 'border-primary-300 bg-primary-50 shadow-sm'
                : 'border-gray-200 bg-white hover:border-primary-200 hover:bg-slate-50',
            )}
          >
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-700">
              {name.charAt(0).toUpperCase()}
            </span>
            <span className="min-w-0">
              <span className="block truncate text-sm font-semibold text-gray-950">{name}</span>
              <span className="block truncate text-xs text-gray-600">
                {t(`client_types.${relationship.type}`)}
              </span>
            </span>
          </button>
        );
      })}
    </div>
  );

  const counterpartLabel = selectedRelationship
    ? counterpartName(selectedRelationship, viewerIsMentor, t('messages.unknown_participant'))
    : t('messages.unknown_participant');

  const conversationPanel = selectedId ? (
    <Card className="flex min-h-[60vh] flex-col gap-0 overflow-hidden p-0 lg:min-h-[70vh]">
      <div className="flex items-center gap-3 border-b border-gray-100 px-4 py-3">
        <button
          type="button"
          onClick={closeConversation}
          className="rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 lg:hidden"
          aria-label={t('messages.back_to_list')}
        >
          <ArrowLeft className="h-5 w-5" aria-hidden="true" />
        </button>
        <h2 className="min-w-0 flex-1 truncate text-sm font-semibold text-gray-950">
          {selectedRelationship ? counterpartLabel : t('messages.conversations')}
        </h2>
      </div>

      <div className="flex-1 space-y-3 overflow-y-auto px-4 py-4" role="log" aria-live="polite" aria-label={t('nav.messages')}>
        {messagesQuery.hasNextPage && (
          <div className="text-center">
            <Button
              type="button"
              variant="ghost"
              onClick={() => messagesQuery.fetchNextPage()}
              disabled={messagesQuery.isFetchingNextPage}
            >
              {t('messages.load_older')}
            </Button>
          </div>
        )}
        {messages.length === 0 && !messagesQuery.isLoading && (
          <p className="py-8 text-center text-sm text-gray-500">{t('messages.empty_conversation')}</p>
        )}
        {messages.map((message) => {
          const own = message.sender_id === user?.id;
          return (
            <div key={message.id} className={cn('flex', own ? 'justify-end' : 'justify-start')}>
              <div
                className={cn(
                  'max-w-[80%] rounded-2xl px-4 py-2.5 shadow-sm',
                  own
                    ? 'rounded-br-md bg-primary-600 text-white'
                    : 'rounded-bl-md bg-white text-gray-950 ring-1 ring-gray-200',
                )}
              >
                <p className="whitespace-pre-wrap break-words text-sm">
                  <span className="sr-only">
                    {own ? `${t('messages.you')}: ` : `${message.sender?.full_name ?? counterpartLabel}: `}
                  </span>
                  {message.content}
                </p>
                <p className={cn('mt-1 text-[10px]', own ? 'text-primary-100' : 'text-gray-600')}>
                  {formatTime(message.created_at, locale)}
                  {own && message.read_at !== null ? ' · ✓' : ''}
                </p>
              </div>
            </div>
          );
        })}
        <div ref={bottomRef} />
      </div>

      {sendMessage.isError && (
        <div className="px-4 pt-2">
          <Alert variant="error">{t('messages.send_error')}</Alert>
        </div>
      )}

      <form onSubmit={onSubmit} className="flex items-end gap-2 border-t border-gray-100 p-3">
        <label htmlFor="message-composer" className="sr-only">{t('messages.composer_placeholder')}</label>
        <textarea
          id="message-composer"
          rows={1}
          {...register('content')}
          onKeyDown={(event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
              event.preventDefault();
              void onSubmit();
            }
          }}
          placeholder={t('messages.composer_placeholder')}
          className="min-h-11 flex-1 resize-none rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-950 placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-200 disabled:opacity-50"
        />
        <Button type="submit" disabled={!formState.isValid} loading={sendMessage.isPending} aria-label={t('messages.composer_send')}>
          <Send className="h-4 w-4" aria-hidden="true" />
          <span className="hidden sm:inline">{sendMessage.isPending ? t('messages.composer_sending') : t('messages.composer_send')}</span>
        </Button>
      </form>
    </Card>
  ) : (
    <div className="hidden items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-white/60 p-12 text-center lg:flex">
      <div>
        <MessageSquare className="mx-auto h-10 w-10 text-primary-400" aria-hidden="true" />
        <p className="mt-3 text-sm text-gray-500">{t('messages.select_conversation')}</p>
      </div>
    </div>
  );

  return (
    <AppShell>
      <div className="space-y-6">
        <div className={cn(selectedId && 'hidden lg:block')}>
          <h1 className="text-2xl font-bold text-gray-950">{t('nav.messages')}</h1>
          <p className="mt-1 text-sm text-gray-500">{t('messages.subtitle')}</p>
        </div>
        <div className="grid gap-4 lg:grid-cols-[320px_1fr]">
          {conversationList}
          {conversationPanel}
        </div>
      </div>
    </AppShell>
  );
}


