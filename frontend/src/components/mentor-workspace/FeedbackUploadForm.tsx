'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { FileUploadField } from '@/components/mentor-workspace/FileUploadField';
import { useCreateMentorFeedback } from '@/hooks/use-mentor-workspace';
import { mentorFeedbackSchema, type MentorFeedbackFormValues } from '@/lib/schemas/mentor-workspace';

export function FeedbackUploadForm({
  userId,
  relationshipId,
}: {
  userId: string;
  relationshipId: string;
}) {
  const t = useTranslations();
  const mutation = useCreateMentorFeedback();
  const [selectedFileName, setSelectedFileName] = useState('');
  const form = useForm<MentorFeedbackFormValues>({
    resolver: zodResolver(mentorFeedbackSchema),
    defaultValues: { note: '', feedback_file: undefined },
  });
  const fileRegistration = form.register('feedback_file');

  async function submit(values: MentorFeedbackFormValues) {
    try {
      await mutation.mutateAsync({ user_id: userId, relationship_id: relationshipId, note: values.note, feedback_file: values.feedback_file });
      form.reset();
      setSelectedFileName('');
    } catch {
      // The mutation error is rendered below.
    }
  }

  return (
    <form onSubmit={(event) => void form.handleSubmit(submit)(event)} className="space-y-4">
      <FileUploadField
        label={t('mentor_workspace.feedback_file')}
        hint={selectedFileName || t('mentor_workspace.choose_audio_video')}
        accept="audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/webm,video/mp4,video/quicktime,video/webm"
        error={form.formState.errors.feedback_file?.message}
        {...fileRegistration}
        onChange={(event) => {
          fileRegistration.onChange(event);
          const file = event.target.files?.[0];
          setSelectedFileName(file?.name ?? '');
          form.setValue('feedback_file', file as MentorFeedbackFormValues['feedback_file'], { shouldDirty: true, shouldValidate: true });
        }}
      />
      <p className="text-xs text-slate-500">{t('mentor_workspace.feedback_upload_only_hint')}</p>
      <Textarea label={t('mentor_workspace.feedback_note')} rows={3} error={form.formState.errors.note?.message} {...form.register('note')} />
      {mutation.error && <Alert variant="error">{t('mentor_workspace.save_error')}</Alert>}
      {mutation.isSuccess && <Alert variant="success">{t('mentor_workspace.feedback_saved')}</Alert>}
      <Button type="submit" loading={mutation.isPending}>{t('mentor_workspace.send_feedback')}</Button>
    </form>
  );
}
