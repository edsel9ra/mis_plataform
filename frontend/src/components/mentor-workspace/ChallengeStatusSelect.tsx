'use client';

import { useId, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { ApiError } from '@/lib/api';
import { allowedChallengeTransitions, challengeStatusKey } from '@/lib/progress';
import { useUpdateChallengeStatus } from '@/hooks/use-challenges';

/**
 * Mentor-side challenge status selector (spec 012, T012.10). Offers only the
 * transitions allowed from the current status (`allowedChallengeTransitions`,
 * excluding the self no-op); the server remains authoritative — a rejected
 * transition surfaces as a 422 next to the field. Terminals and templates
 * show an explanatory text instead of a dead control.
 */
export function ChallengeStatusSelect({
  challengeId,
  relationshipId,
  currentStatus,
}: {
  challengeId: string;
  relationshipId: string;
  currentStatus: string | null | undefined;
}) {
  const t = useTranslations();
  const selectId = useId();
  const mutation = useUpdateChallengeStatus(challengeId, relationshipId);
  const [selected, setSelected] = useState('');

  const options = allowedChallengeTransitions(currentStatus ?? '')
    .filter((status) => status !== currentStatus);

  if (options.length === 0) {
    return (
      <p className="text-xs text-slate-500">
        {t('challenges.status_label')}: {t(challengeStatusKey(currentStatus))} — {t('challenges.status_none')}
      </p>
    );
  }

  const apiError = mutation.error instanceof ApiError ? mutation.error : null;
  const fieldError = apiError?.status === 422
    ? apiError.errors?.status?.[0] ?? t('challenges.status_error')
    : null;

  async function save() {
    try {
      await mutation.mutateAsync(selected);
      setSelected('');
    } catch {
      // The error message is rendered below.
    }
  }

  return (
    <div className="space-y-2">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
        <div className="min-w-52 flex-1 space-y-1">
          <label htmlFor={selectId} className="block text-sm font-medium text-gray-700">
            {t('challenges.status_label')}
          </label>
          <select
            id={selectId}
            value={selected}
            disabled={mutation.isPending}
            onChange={(event) => setSelected(event.target.value)}
            aria-invalid={fieldError ? true : undefined}
            className="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500"
          >
            <option value="">{t('challenges.status_placeholder')}</option>
            {options.map((status) => (
              <option key={status} value={status}>
                {t(challengeStatusKey(status))}
              </option>
            ))}
          </select>
          {fieldError ? (
            <p className="text-sm text-red-600" role="alert">
              {fieldError}
            </p>
          ) : null}
        </div>
        <Button
          type="button"
          size="sm"
          variant="outline"
          loading={mutation.isPending}
          disabled={!selected}
          onClick={() => void save()}
        >
          {t('challenges.status_save')}
        </Button>
      </div>
      {apiError && apiError.status !== 422 ? (
        <Alert variant="error">{t('challenges.status_error')}</Alert>
      ) : null}
      <p role="status" aria-live="polite" className="text-sm text-slate-600">
        {mutation.isSuccess && !mutation.isPending ? t('challenges.status_saved') : ''}
      </p>
    </div>
  );
}
