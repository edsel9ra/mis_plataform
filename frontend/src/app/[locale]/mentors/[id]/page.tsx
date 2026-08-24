'use client';

import { type ReactNode, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { localePath } from '@/i18n/paths';
import { useCreateRelationship, useRelationshipContexts } from '@/hooks/use-relationships';
import { AppShell } from '@/components/layout/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { PageSpinner } from '@/components/ui/spinner';
import { Avatar } from '@/components/ui/avatar';
import { Brain, Users } from 'lucide-react';
import type { UserSkill } from '@/lib/mentor-skills';

interface Mentor {
  id: string;
  full_name: string;
  avatar: string | null;
  bio: string | null;
  client_type: string;
  active_mentees_count?: number;
  skills?: UserSkill[];
  personality_assessment?: { completed_at: string | null } | null;
}

type MentorResponse = Mentor | { data: Mentor };

function unwrapMentor(response: MentorResponse | undefined): Mentor | undefined {
  if (!response) return undefined;

  return 'data' in response ? response.data : response;
}

export default function MentorDetailPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();
  const [selectedContextId, setSelectedContextId] = useState('');
  const [objectives, setObjectives] = useState('');
  const params = useParams<{ id?: string | string[] }>();
  const mentorId = Array.isArray(params.id) ? params.id[0] : params.id;

  const { data, isLoading, error } = useQuery<MentorResponse>({
    queryKey: ['mentor', mentorId],
    queryFn: ({ signal }) => api.get<MentorResponse>(`/mentors/${mentorId}`, undefined, { signal }),
    enabled: !!mentorId,
  });
  const canRequest = Boolean(user && user.role !== 'mentor' && user.id !== mentorId);
  const contextsQuery = useRelationshipContexts(mentorId, canRequest);
  const createRelationship = useCreateRelationship();

  const mentor = unwrapMentor(data);
  const contexts = contextsQuery.data?.data ?? [];
  const selectedContext = contexts.find((context) => context.source_id === selectedContextId) ?? contexts[0];
  const createdForSelectedContext = createRelationship.data?.source_id === selectedContext?.source_id
    ? createRelationship.data
    : null;
  const relationship = selectedContext?.relationship ?? createdForSelectedContext;

  const withShell = (content: ReactNode) => (user ? <AppShell>{content}</AppShell> : content);

  if (isLoading) return withShell(<PageSpinner />);

  if (error || !mentor) {
    return withShell(
      <Alert variant="error" className="mx-auto max-w-3xl">
        {error ? apiErrorMessage(error, (key) => t(key)) || t('common.load_error') : t('common.no_results')}
      </Alert>,
    );
  }

  return withShell(
    <main className="mx-auto max-w-3xl px-4 py-10">
      <Card className="border-0 shadow-xl shadow-gray-200">
        <CardHeader>
          <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div className="flex items-start gap-4">
              <Avatar
                src={mentor.avatar}
                alt={mentor.full_name}
                size="lg"
                fallback={mentor.full_name.charAt(0)}
                className="rounded-2xl bg-primary-100 text-xl font-bold text-primary-700"
              />
              <div>
                <CardTitle>{mentor.full_name}</CardTitle>
                <div className="mt-2 flex flex-wrap gap-2">
                  <Badge variant="success">{t(`client_types.${mentor.client_type}`)}</Badge>
                  {'personality_assessment' in mentor && (
                    <Badge variant={mentor.personality_assessment?.completed_at ? 'primary' : 'warning'}>
                      {mentor.personality_assessment?.completed_at ? t('common.completed') : t('common.pending')}
                    </Badge>
                  )}
                </div>
              </div>
            </div>
            <div className="rounded-2xl bg-primary-50 px-4 py-3 text-primary-800">
              <div className="flex items-center gap-2 text-sm font-semibold">
                <Users className="h-4 w-4" aria-hidden="true" />
                {mentor.active_mentees_count ?? 0}
              </div>
              <p className="mt-1 text-xs text-primary-700">{t('mentors.active_mentees')}</p>
            </div>
          </div>
        </CardHeader>
        {mentor.bio && <p className="text-sm leading-6 text-gray-600">{mentor.bio}</p>}
        <div className="mt-6 rounded-2xl border bg-gray-50 p-4">
          <div className="flex items-center gap-2 text-sm font-semibold text-gray-900">
            <Brain className="h-4 w-4 text-primary-600" aria-hidden="true" />
            {t('mentors.skills')}
          </div>
          <div className="mt-3 flex flex-wrap gap-2">
            {(mentor.skills ?? []).map((skill, index) => (
              <span key={skill.id ?? index} className="rounded-full bg-white px-3 py-1 text-xs font-medium text-gray-700 shadow-sm">
                {skill.skill?.name}
                <span className="ml-1 text-gray-400">· {t(`learning_levels.${skill.level}`)}</span>
              </span>
            ))}
            {(mentor.skills ?? []).length === 0 && (
              <p className="text-sm text-gray-500">{t('common.no_results')}</p>
            )}
          </div>
        </div>
        {canRequest && (
          <section className="mt-6 border-t border-gray-100 pt-6" aria-labelledby="mentorship-request-title">
            <h2 id="mentorship-request-title" className="text-lg font-semibold text-gray-950">
              {t('mentors.request_title')}
            </h2>
            <p className="mt-1 text-sm text-gray-500">{t('mentors.request_description')}</p>

            {contextsQuery.isLoading ? (
              <div className="mt-5"><PageSpinner /></div>
            ) : contextsQuery.error ? (
              <Alert variant="error" className="mt-5">
                {apiErrorMessage(contextsQuery.error, (key) => t(key)) || t('mentors.contexts_error')}
              </Alert>
            ) : contexts.length === 0 ? (
              <Alert variant="info" className="mt-5">{t('mentors.no_contexts')}</Alert>
            ) : (
              <div className="mt-5 space-y-4">
                {contexts.length > 1 ? (
                  <Select
                    label={t('mentors.context_label')}
                    value={selectedContext?.source_id ?? ''}
                    options={contexts.map((context) => ({ value: context.source_id, label: context.name }))}
                    onChange={(event) => {
                      setSelectedContextId(event.target.value);
                      createRelationship.reset();
                    }}
                  />
                ) : (
                  <div className="rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{t('mentors.context_label')}</p>
                    <p className="mt-1 font-semibold text-gray-900">{selectedContext?.name}</p>
                  </div>
                )}

                {selectedContext?.plan && (
                  <p className="text-sm text-gray-500">
                    {t('mentors.plan_label')}: <span className="font-medium text-gray-800">{selectedContext.plan.name}</span>
                    {' · '}{t('mentors.plan_sessions', { count: selectedContext.plan.max_sessions_per_month })}
                  </p>
                )}

                {relationship?.status === 'active' ? (
                  <Alert variant="success">
                    <div>
                      <p className="font-semibold">{t('mentors.relationship_active')}</p>
                      <Link href={localePath('/sessions', locale)} className="mt-1 inline-block underline">
                        {t('mentors.view_sessions')}
                      </Link>
                    </div>
                  </Alert>
                ) : relationship?.status === 'pending' ? (
                  <Alert variant="info">{t('mentors.relationship_pending')}</Alert>
                ) : (
                  <>
                    <Textarea
                      label={t('mentors.objectives_label')}
                      value={objectives}
                      maxLength={2000}
                      rows={4}
                      onChange={(event) => setObjectives(event.target.value)}
                    />
                    {createRelationship.error && (
                      <Alert variant="error">{relationshipError(createRelationship.error, t)}</Alert>
                    )}
                    <Button
                      type="button"
                      loading={createRelationship.isPending}
                      onClick={() => selectedContext && createRelationship.mutate({
                        type: selectedContext.type,
                        source_type: selectedContext.source_type,
                        source_id: selectedContext.source_id,
                        mentor_id: mentor.id,
                        objectives: objectives.trim() || undefined,
                      })}
                    >
                      {t('mentors.request_cta')}
                    </Button>
                    <p className="text-xs text-gray-500">{t('mentors.scheduling_note')}</p>
                  </>
                )}
              </div>
            )}
          </section>
        )}
      </Card>
    </main>
  );
}

function relationshipError(error: Error, t: ReturnType<typeof useTranslations>): string {
  const message = apiErrorMessage(error, (key) => t(key));

  return message || t('mentors.request_error');
}
