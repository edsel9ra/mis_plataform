'use client';

import { useEffect } from 'react';
import { useTranslations } from 'next-intl';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useSaveMentorNeurocognitive } from '@/hooks/use-mentor-workspace';
import { mentorObservationSchema, type MentorObservationFormValues } from '@/lib/schemas/mentor-workspace';
import type { AttentionObservation } from '@/types/mentor-workspace';

export function MentorObservationForm({
  userId,
  relationshipId,
  observation,
}: {
  userId: string;
  relationshipId: string;
  observation?: AttentionObservation | null;
}) {
  const t = useTranslations();
  const mutation = useSaveMentorNeurocognitive(userId, relationshipId);
  const form = useForm<MentorObservationFormValues>({
    resolver: zodResolver(mentorObservationSchema),
    defaultValues: observationValues(observation),
  });

  useEffect(() => {
    form.reset(observationValues(observation));
  }, [form, observation]);

  async function submit(values: MentorObservationFormValues) {
    try {
      await mutation.mutateAsync(values);
    } catch {
      // The mutation error is rendered below.
    }
  }

  return (
    <form onSubmit={(event) => void form.handleSubmit(submit)(event)} className="space-y-4 rounded-2xl border border-slate-100 bg-slate-50 p-5">
      <div>
        <h3 className="font-semibold text-slate-950">{t('mentor_workspace.add_observation')}</h3>
        <p className="mt-1 text-sm text-slate-500">{t('mentor_workspace.add_observation_description')}</p>
      </div>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Input label={t('mentor_workspace.attention_focus')} type="number" min="0" max="100" {...form.register('focus')} error={form.formState.errors.focus?.message} />
        <Input label={t('mentor_workspace.emotional_regulation')} type="number" min="0" max="100" {...form.register('regulation')} error={form.formState.errors.regulation?.message} />
        <Input label={t('mentor_workspace.stress_load')} type="number" min="0" max="100" {...form.register('stress')} error={form.formState.errors.stress?.message} />
        <Input label={t('mentor_workspace.energy_level')} type="number" min="0" max="100" {...form.register('energy')} error={form.formState.errors.energy?.message} />
      </div>
      <Textarea label={t('mentor_workspace.observation_note')} rows={3} {...form.register('note')} error={form.formState.errors.note?.message} />
      {mutation.error && <Alert variant="error">{t('mentor_workspace.save_error')}</Alert>}
      {mutation.isSuccess && <Alert variant="success">{t('mentor_workspace.observation_saved')}</Alert>}
      <Button type="submit" loading={mutation.isPending}>{t('mentor_workspace.save_observation')}</Button>
    </form>
  );
}

function observationValues(observation?: AttentionObservation | null): MentorObservationFormValues {
  return {
    focus: percentageValue(observation?.focus ?? observation?.attention),
    regulation: percentageValue(observation?.regulation ?? observation?.emotional_regulation),
    stress: percentageValue(observation?.stress),
    energy: percentageValue(observation?.energy),
    note: typeof observation?.note === 'string' ? observation.note : typeof observation?.notes === 'string' ? observation.notes : '',
  };
}

function percentageValue(value: unknown): number | undefined {
  const number = typeof value === 'number'
    ? value
    : typeof value === 'string' && value.trim() !== ''
      ? Number(value)
      : undefined;

  if (number === undefined || !Number.isFinite(number)) return undefined;
  if (number > 0 && number <= 5) return number * 20;

  return number;
}
