'use client';

import { useTranslations } from 'next-intl';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useAddObjectiveEvidence } from '@/hooks/use-progress';
import { objectiveEvidenceSchema, type ObjectiveEvidenceFormValues } from '@/lib/schemas/objective';

/**
 * Attach evidence (a note) to an objective with loading / success / error
 * states (RF-1). File attachments require a pre-uploaded media asset, which has
 * no objective-scoped upload endpoint yet, so only the note path is exposed.
 */
export function ObjectiveEvidenceForm({
  objectiveId,
  onDone,
}: {
  objectiveId: string;
  onDone?: () => void;
}) {
  const t = useTranslations();
  const mutation = useAddObjectiveEvidence();
  const form = useForm<ObjectiveEvidenceFormValues>({
    resolver: zodResolver(objectiveEvidenceSchema),
    defaultValues: { note: '' },
  });

  async function submit(values: ObjectiveEvidenceFormValues) {
    try {
      await mutation.mutateAsync({ id: objectiveId, payload: { note: values.note?.trim() || null } });
      form.reset();
      onDone?.();
    } catch {
      // Surfaced below.
    }
  }

  return (
    <form onSubmit={(event) => void form.handleSubmit(submit)(event)} className="space-y-3">
      <Textarea
        label={t('progress.evidence.note_label')}
        rows={3}
        placeholder={t('progress.evidence.note_placeholder')}
        error={form.formState.errors.note?.message}
        {...form.register('note')}
      />

      {mutation.error ? <Alert variant="error">{t('progress.evidence.error')}</Alert> : null}
      {mutation.isSuccess ? <Alert variant="success">{t('progress.evidence.saved')}</Alert> : null}

      <Button type="submit" size="sm" loading={mutation.isPending}>
        {t('progress.evidence.submit')}
      </Button>
    </form>
  );
}
