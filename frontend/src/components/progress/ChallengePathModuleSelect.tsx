'use client';

import { useEffect, useId, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useSetChallengePathModule } from '@/hooks/use-progress';
import { linkableModuleOptions, linkErrorMessage } from '@/lib/progress';
import type { LearningPathProgress } from '@/types/progress';

/**
 * "Módulo vinculado" selector for a challenge (spec 012, RF-012.8-.10).
 * Options come from the subject's consultable learning paths, grouped per
 * path; errors are announced via `role="status"` and never reveal whether a
 * link exists when the server denies the change (404 → generic message).
 */
export function ChallengePathModuleSelect({
  challengeId,
  currentModuleId,
  paths,
}: {
  challengeId: string;
  currentModuleId?: string | null;
  paths: LearningPathProgress[] | undefined;
}) {
  const t = useTranslations();
  const locale = useLocale();
  const selectId = useId();
  const mutation = useSetChallengePathModule(challengeId);
  const [selected, setSelected] = useState(currentModuleId ?? '');
  const [savedModuleId, setSavedModuleId] = useState(currentModuleId ?? '');

  useEffect(() => {
    setSelected(currentModuleId ?? '');
    setSavedModuleId(currentModuleId ?? '');
  }, [currentModuleId]);

  const groups = linkableModuleOptions(paths ?? [], locale);
  const error = linkErrorMessage(mutation.error);
  const dirty = selected !== savedModuleId;

  async function save() {
    try {
      const response = await mutation.mutateAsync(selected === '' ? null : selected);
      const linked = response.path_module?.id ?? '';
      setSavedModuleId(linked);
      setSelected(linked);
    } catch {
      // The error message is rendered below; keep the attempted selection.
    }
  }

  return (
    <div className="space-y-2">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
        <div className="min-w-52 flex-1 space-y-1">
          <label htmlFor={selectId} className="block text-sm font-medium text-gray-700">
            {t('progress.learning_paths.linked_module')}
          </label>
          <select
            id={selectId}
            value={selected}
            disabled={mutation.isPending}
            onChange={(event) => setSelected(event.target.value)}
            aria-invalid={error?.kind === 'field' ? true : undefined}
            className="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500"
          >
            <option value="">{t('progress.learning_paths.no_link')}</option>
            {groups.map((group) => (
              <optgroup key={group.pathId} label={group.pathTitle}>
                {group.options.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </optgroup>
            ))}
          </select>
          {error?.kind === 'field' ? (
            <p className="text-sm text-red-600" role="alert">
              {t(error.key)}
            </p>
          ) : null}
        </div>
        <Button
          type="button"
          size="sm"
          variant="outline"
          loading={mutation.isPending}
          disabled={!dirty || groups.length === 0}
          onClick={() => void save()}
        >
          {t('progress.learning_paths.save_link')}
        </Button>
      </div>
      {error?.kind === 'alert' ? (
        <Alert variant="error">{t(error.key)}</Alert>
      ) : null}
      <p role="status" aria-live="polite" className="text-sm text-slate-600">
        {mutation.isSuccess && !dirty ? t('progress.learning_paths.link_saved') : ''}
      </p>
    </div>
  );
}
