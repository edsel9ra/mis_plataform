'use client';

import { useTranslations } from 'next-intl';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { datetimeLocalToApiIso } from '@/lib/datetime';
import { useProposeObjective } from '@/hooks/use-progress';
import { objectiveProposalSchema, type ObjectiveProposalFormValues } from '@/lib/schemas/objective';

/**
 * A mentee proposes an objective. No scope/owner/status fields are exposed, so
 * the proposer gains no administration rights (RF-1, RF-12, CA-1.5).
 */
export function ObjectiveProposalForm({
  relationshipId,
  onDone,
  onCancel,
}: {
  relationshipId?: string;
  onDone?: () => void;
  onCancel?: () => void;
}) {
  const t = useTranslations();
  const mutation = useProposeObjective();
  const form = useForm<ObjectiveProposalFormValues>({
    resolver: zodResolver(objectiveProposalSchema),
    defaultValues: { title: '', description: '', success_criteria: '', due_at: '' },
  });
  const errors = form.formState.errors;

  async function submit(values: ObjectiveProposalFormValues) {
    try {
      await mutation.mutateAsync({
        title: values.title,
        description: values.description || null,
        success_criteria: values.success_criteria,
        due_at: datetimeLocalToApiIso(values.due_at),
        relationship_id: relationshipId || undefined,
      });
      form.reset();
      onDone?.();
    } catch {
      // Surfaced below.
    }
  }

  return (
    <form onSubmit={(event) => void form.handleSubmit(submit)(event)} className="space-y-4">
      <Input label={t('progress.form.title_label')} error={errors.title?.message} {...form.register('title')} />
      <Textarea
        label={t('progress.form.success_criteria_label')}
        rows={3}
        error={errors.success_criteria?.message}
        {...form.register('success_criteria')}
      />
      <Textarea
        label={t('progress.form.description_label')}
        rows={3}
        error={errors.description?.message}
        {...form.register('description')}
      />
      <Input
        label={t('progress.form.due_at_label')}
        type="datetime-local"
        error={errors.due_at?.message}
        {...form.register('due_at')}
      />

      {mutation.error ? <Alert variant="error">{t('progress.form.save_error')}</Alert> : null}
      {mutation.isSuccess ? <Alert variant="success">{t('progress.form.proposed')}</Alert> : null}

      <div className="flex gap-2">
        <Button type="submit" loading={mutation.isPending}>
          {t('progress.form.submit_propose')}
        </Button>
        {onCancel ? (
          <Button type="button" variant="ghost" onClick={onCancel}>
            {t('progress.form.cancel')}
          </Button>
        ) : null}
      </div>
    </form>
  );
}
