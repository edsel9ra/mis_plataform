'use client';

import { useMemo, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { MessageSquare, Send } from 'lucide-react';
import { Alert } from '@/components/ui/alert';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { FeedbackUploadForm } from '@/components/mentor-workspace/FeedbackUploadForm';
import { useMentorFeedback } from '@/hooks/use-mentor-workspace';
import { useAllMentorUsers as useAssignedUsers, type MentorAssignment } from '@/hooks/use-mentor-dashboard';
import { canAccessWorkspaceFeature } from '@/lib/mentor-workspace';
import type { MentorFeedback } from '@/types/mentor-workspace';

export default function MentorFeedbackPage() {
  const t = useTranslations();
  const [selectedUserId, setSelectedUserId] = useState('');
  const [selectedRelationshipId, setSelectedRelationshipId] = useState('');
  const usersQuery = useAssignedUsers(true);
  const feedbackQuery = useMentorFeedback(selectedUserId || undefined, selectedRelationshipId || undefined, true);
  const selectedUser = usersQuery.data?.find((user) => user.id === selectedUserId);
  const eligibleUsers = useMemo(
    () => usersQuery.data?.filter((user) => user.assignments.some(isFeedbackAssignment)) ?? [],
    [usersQuery.data],
  );
  const userOptions = useMemo(() => eligibleUsers.map((user) => ({ value: user.id, label: user.full_name })), [eligibleUsers]);
  const relationshipOptions = selectedUser?.assignments.filter(isFeedbackAssignment).map((assignment) => ({
    value: assignment.relationship_id,
    label: assignment.context.name,
  })) ?? [];

  function selectUser(value: string) {
    setSelectedUserId(value);
    const nextUser = eligibleUsers.find((user) => user.id === value);
    setSelectedRelationshipId(nextUser?.assignments.find(isFeedbackAssignment)?.relationship_id ?? '');
  }

  return (
    <div className="space-y-6">
      <Card className="border-primary-100 bg-primary-50/50">
        <div className="flex items-start gap-3">
          <MessageSquare className="mt-0.5 h-5 w-5 shrink-0 text-primary-700" aria-hidden="true" />
          <div><h2 className="font-semibold text-primary-950">{t('mentor_workspace.feedback_page_title')}</h2><p className="mt-1 text-sm leading-6 text-primary-800">{t('mentor_workspace.feedback_page_description')}</p></div>
        </div>
      </Card>
      {usersQuery.error && <Alert variant="error">{t('mentor_dashboard.load_error')}</Alert>}
      {!usersQuery.isLoading && !usersQuery.error && eligibleUsers.length === 0 && <Alert>{t('mentor_workspace.no_feedback_recipients')}</Alert>}
      <div className="grid gap-6 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
        <Card>
          <CardHeader><CardTitle>{t('mentor_workspace.select_feedback_recipient')}</CardTitle></CardHeader>
          <div className="space-y-4">
            <Select label={t('mentor_workspace.mentee')} value={selectedUserId} onChange={(event) => selectUser(event.target.value)} options={userOptions} placeholder={t('mentor_workspace.select_mentee')} />
            {selectedUserId && <Select label={t('mentor_workspace.assignment')} value={selectedRelationshipId} onChange={(event) => setSelectedRelationshipId(event.target.value)} options={relationshipOptions} />}
            {selectedUserId && selectedRelationshipId ? <div className="border-t border-slate-100 pt-4"><div className="mb-4 flex items-center gap-2"><Send className="h-4 w-4 text-primary-600" aria-hidden="true" /><h3 className="font-semibold text-slate-900">{t('mentor_workspace.new_feedback')}</h3></div><FeedbackUploadForm userId={selectedUserId} relationshipId={selectedRelationshipId} /></div> : <p className="text-sm text-slate-500">{t('mentor_workspace.select_mentee_hint')}</p>}
          </div>
        </Card>
        <Card>
          <CardHeader><CardTitle>{t('mentor_workspace.feedback_history')}</CardTitle></CardHeader>
          {!selectedUserId || !selectedRelationshipId ? <p className="text-sm text-slate-500">{t('mentor_workspace.select_mentee_hint')}</p> : feedbackQuery.isLoading ? <p className="text-sm text-slate-500">{t('common.loading')}</p> : feedbackQuery.error ? <Alert variant="error">{t('mentor_workspace.feature_load_error')}</Alert> : feedbackQuery.data?.length ? <div className="space-y-3">{feedbackQuery.data.map((feedback) => <FeedbackRow key={feedback.id} feedback={feedback} />)}</div> : <p className="text-sm text-slate-500">{t('mentor_workspace.no_feedback')}</p>}
        </Card>
      </div>
    </div>
  );
}

function isFeedbackAssignment(assignment: MentorAssignment) {
  return assignment.type === 'personal'
    && !assignment.locked
    && canAccessWorkspaceFeature('feedback', assignment.plan, assignment.entitlements);
}

function FeedbackRow({ feedback }: { feedback: MentorFeedback }) {
  const t = useTranslations();
  const locale = useLocale();
  const url = feedback.media_url || feedback.file_url;
  return <div className="rounded-xl border border-slate-100 p-4"><p className="text-sm text-slate-700">{feedback.note || t('mentor_workspace.media_feedback')}</p><p className="mt-1 text-xs text-slate-500">{feedback.created_at ? new Date(feedback.created_at).toLocaleString(locale) : '—'}</p>{url && <a href={url} target="_blank" rel="noreferrer" className="mt-2 inline-flex text-sm font-semibold text-primary-700 hover:underline">{feedback.file_name || t('mentor_workspace.open_feedback')}</a>}</div>;
}
