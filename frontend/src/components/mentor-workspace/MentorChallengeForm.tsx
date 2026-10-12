'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { FileUploadField } from '@/components/mentor-workspace/FileUploadField';
import { useCreateMentorChallenge } from '@/hooks/use-mentor-workspace';
import { mentorChallengeSchema, type MentorChallengeFormValues } from '@/lib/schemas/mentor-workspace';

export function MentorChallengeForm({ onCreated }: { onCreated?: () => void }) {
  const t = useTranslations();
  const mutation = useCreateMentorChallenge();
  const [selectedFileName, setSelectedFileName] = useState('');
  const form = useForm<MentorChallengeFormValues>({
    resolver: zodResolver(mentorChallengeSchema),
    defaultValues: { title: '', description: '', content_file: undefined, due_at: '' },
  });
  const fileRegistration = form.register('content_file');

  async function submit(values: MentorChallengeFormValues) {
    try {
      await mutation.mutateAsync(values);
      form.reset();
      setSelectedFileName('');
      onCreated?.();
    } catch {
      // The mutation error is rendered below.
    }
  }

  return (
    <form onSubmit={(event) => void form.handleSubmit(submit)(event)} className="space-y-4">
      <div className="grid gap-4 sm:grid-cols-2">
        <Input label={t('mentor_workspace.challenge_title')} error={form.formState.errors.title?.message} {...form.register('title')} />
        <Input label={t('mentor_workspace.challenge_due_date')} type="datetime-local" error={form.formState.errors.due_at?.message} {...form.register('due_at')} />
      </div>
      <Textarea label={t('mentor_workspace.challenge_description')} rows={4} error={form.formState.errors.description?.message} {...form.register('description')} />
      <div>
        <FileUploadField
          label={t('mentor_workspace.challenge_content_file')}
          hint={selectedFileName || t('mentor_workspace.choose_file')}
          accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.mp3,.wav,.m4a,.mp4,.mov,.webm"
          error={form.formState.errors.content_file?.message}
          {...fileRegistration}
          onChange={(event) => {
            fileRegistration.onChange(event);
            const file = event.target.files?.[0];
            setSelectedFileName(file?.name ?? '');
            form.setValue('content_file', file as MentorChallengeFormValues['content_file'], { shouldDirty: true, shouldValidate: true });
          }}
        />
        <p className="mt-2 text-xs text-slate-500">{t('mentor_workspace.file_upload_only_hint')}</p>
      </div>
      {mutation.error && <Alert variant="error">{t('mentor_workspace.save_error')}</Alert>}
      {mutation.isSuccess && <Alert variant="success">{t('mentor_workspace.challenge_saved')}</Alert>}
      <Button type="submit" loading={mutation.isPending}>{t('mentor_workspace.create_challenge')}</Button>
    </form>
  );
}
