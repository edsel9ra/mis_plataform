'use client';

import { useTranslations } from 'next-intl';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm, type Resolver } from 'react-hook-form';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { apiIsoToDatetimeLocal, datetimeLocalToApiIso } from '@/lib/datetime';
import { useCreateObjective, useUpdateObjective } from '@/hooks/use-progress';
import { objectiveSchema, objectiveUpdateSchema } from '@/lib/schemas/objective';
import type { CollectiveSubjectType, Objective, ObjectiveScope } from '@/types/progress';

interface ObjectiveFormShape {
  scope: ObjectiveScope;
  owner_id: string;
  owner_type?: CollectiveSubjectType;
  title: string;
  description?: string;
  success_criteria: string;
  due_at: string;
}

interface ObjectiveFormProps {
  ownerId: string;
  relationshipId?: string;
  objective?: Objective;
  allowCollective?: boolean;
  onDone?: () => void;
  onCancel?: () => void;
}

/** Create / edit an objective with react-hook-form + Zod (RF-1). */
export function ObjectiveForm({
  ownerId,
  relationshipId,
  objective,
  allowCollective = false,
  onDone,
  onCancel,
}: ObjectiveFormProps) {
  const t = useTranslations();
  const isEdit = Boolean(objective);
  const create = useCreateObjective();
  const update = useUpdateObjective();
  const mutation = isEdit ? update : create;

  const form = useForm<ObjectiveFormShape>({
    resolver: zodResolver(isEdit ? objectiveUpdateSchema : objectiveSchema) as Resolver<ObjectiveFormShape>,
    defaultValues: {
      scope: (objective?.scope as ObjectiveScope) ?? 'individual',
      owner_id: objective?.owner_id ?? ownerId,
      owner_type: (objective?.owner_type as CollectiveSubjectType) || undefined,
      title: objective?.title ?? '',
      description: objective?.description ?? '',
      success_criteria: objective?.success_criteria ?? '',
      due_at: apiIsoToDatetimeLocal(objective?.due_at),
    },
  });

  const scope = form.watch('scope');
  const errors = form.formState.errors;

  async function submit(values: ObjectiveFormShape) {
    try {
      if (isEdit && objective) {
        await update.mutateAsync({
          id: objective.id,
          payload: {
            title: values.title,
            description: values.description || null,
            success_criteria: values.success_criteria,
            due_at: datetimeLocalToApiIso(values.due_at),
          },
        });
      } else {
        await create.mutateAsync({
          scope: values.scope,
          owner_id: values.owner_id,
          owner_type: values.scope === 'collective' ? values.owner_type : undefined,
          relationship_id: relationshipId || undefined,
          title: values.title,
          description: values.description || null,
          success_criteria: values.success_criteria,
          due_at: datetimeLocalToApiIso(values.due_at),
        });
      }
      form.reset();
      onDone?.();
    } catch {
      // The mutation error is surfaced below.
    }
  }

  return (
    <form onSubmit={(event) => void form.handleSubmit(submit)(event)} className="space-y-4">
      {allowCollective && !isEdit ? (
        <div className="grid gap-4 sm:grid-cols-2">
          <Select
            label={t('progress.form.scope_label')}
            options={[
              { value: 'individual', label: t('progress.form.scope_individual') },
              { value: 'collective', label: t('progress.form.scope_collective') },
            ]}
            error={errors.scope?.message}
            {...form.register('scope', {
              onChange: (event) => {
                if (event.target.value !== 'collective') form.setValue('owner_type', undefined);
              },
            })}
          />
          {scope === 'collective' ? (
            <Select
              label={t('progress.form.owner_type_label')}
              placeholder="—"
              options={[
                { value: 'family_group', label: t('progress.form.owner_type_family_group') },
                { value: 'cohort', label: t('progress.form.owner_type_cohort') },
                { value: 'company', label: t('progress.form.owner_type_company') },
              ]}
              error={errors.owner_type?.message}
              {...form.register('owner_type')}
            />
          ) : null}
        </div>
      ) : null}

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

      <div className="flex gap-2">
        <Button type="submit" loading={mutation.isPending}>
          {isEdit ? t('progress.form.submit_update') : t('progress.form.submit_create')}
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
