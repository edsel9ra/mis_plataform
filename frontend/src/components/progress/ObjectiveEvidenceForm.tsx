'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { FileUploadField } from '@/components/ui/file-upload-field';
import { Textarea } from '@/components/ui/textarea';
import { useSubmitObjectiveEvidence } from '@/hooks/use-progress';
import { apiErrorMessage } from '@/lib/errors';
import { SUPPORTED_MEDIA_TYPES } from '@/lib/media-types';
import { validationMessage } from '@/lib/validation-message';
import { objectiveEvidenceSchema, type ObjectiveEvidenceFormValues } from '@/lib/schemas/objective';

const ACCEPT = Array.from(SUPPORTED_MEDIA_TYPES).join(',');

/**
 * Attach evidence (note and/or file) to an objective (RF-1, RF-020.8). The
 * file is uploaded first; if the upload fails the evidence is not created and
 * the note and chosen file stay in the form.
 */
export function ObjectiveEvidenceForm({
  objectiveId,
  onDone,
}: {
  objectiveId: string;
  onDone?: () => void;
}) {
  const t = useTranslations();
  const mutation = useSubmitObjectiveEvidence(objectiveId);
  const [selectedFileName, setSelectedFileName] = useState('');
  const form = useForm<ObjectiveEvidenceFormValues>({
    resolver: zodResolver(objectiveEvidenceSchema),
    defaultValues: { note: '' },
  });
  const fileRegistration = form.register('file');

  async function submit(values: ObjectiveEvidenceFormValues) {
    try {
      await mutation.mutateAsync({ note: values.note, file: values.file });
      form.reset();
      setSelectedFileName('');
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
        error={validationMessage(form.formState.errors.note?.message, t)}
        {...form.register('note')}
      />

      <FileUploadField
        label={t('progress.evidence.file_label')}
        hint={selectedFileName || t('progress.evidence.file_hint')}
        accept={ACCEPT}
        error={validationMessage(form.formState.errors.file?.message, t)}
        {...fileRegistration}
        onChange={(event) => {
          fileRegistration.onChange(event);
          const file = event.target.files?.[0];
          setSelectedFileName(file?.name ?? '');
          form.setValue('file', file as ObjectiveEvidenceFormValues['file'], { shouldDirty: true, shouldValidate: true });
        }}
      />

      {mutation.error ? <Alert variant="error">{apiErrorMessage(mutation.error, t) || t('progress.evidence.error')}</Alert> : null}
      {mutation.isSuccess ? <Alert variant="success">{t('progress.evidence.saved')}</Alert> : null}

      <Button type="submit" size="sm" loading={mutation.isPending}>
        {t('progress.evidence.submit')}
      </Button>
    </form>
  );
}
