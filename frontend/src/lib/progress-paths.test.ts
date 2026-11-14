import { describe, expect, it } from 'vitest';
import { ApiError } from '@/lib/api';
import {
  allowedChallengeTransitions,
  challengeStatusKey,
  linkableModuleOptions,
  linkErrorMessage,
  localizedTitle,
  moduleCompletionLabelKey,
} from '@/lib/progress';
import type { LearningPathProgress, MetricValue } from '@/types/progress';

function metric(state: MetricValue['state'], value: number | null = null): MetricValue {
  return { state, value, numerator: null, denominator: null, source: null, period: null, incomplete: false };
}

function module(id: string, position: number) {
  return {
    id,
    title: `Mod ${id}`,
    position,
    activity_rate: metric('no_data'),
    completion: { state: 'no_data' as const, completed_at: null },
  };
}

function path(id: string, title: LearningPathProgress['learning_path']['title'], modules: LearningPathProgress['modules']): LearningPathProgress {
  return { learning_path: { id, title }, rate: metric('no_data'), modules };
}

describe('moduleCompletionLabelKey', () => {
  it('maps every completion state to a distinct i18n key', () => {
    expect(moduleCompletionLabelKey('completed')).toBe('progress.learning_paths.completion_completed');
    expect(moduleCompletionLabelKey('not_completed')).toBe('progress.learning_paths.completion_not_completed');
    expect(moduleCompletionLabelKey('no_data')).toBe('progress.learning_paths.completion_no_data');
    expect(moduleCompletionLabelKey('incomplete')).toBe('progress.learning_paths.completion_incomplete');
  });

  it('falls back to no_data for unknown states', () => {
    expect(moduleCompletionLabelKey('whatever')).toBe('progress.learning_paths.completion_no_data');
  });
});

describe('localizedTitle', () => {
  it('returns plain strings unchanged', () => {
    expect(localizedTitle('Módulo', 'en')).toBe('Módulo');
  });

  it('resolves JSONB titles by locale with Spanish fallback', () => {
    const title = { es: 'Ruta', en: 'Path' };
    expect(localizedTitle(title, 'en')).toBe('Path');
    expect(localizedTitle(title, 'pt')).toBe('Ruta');
    expect(localizedTitle(title, 'es')).toBe('Ruta');
  });

  it('returns empty string for nullish titles', () => {
    expect(localizedTitle(null, 'es')).toBe('');
    expect(localizedTitle(undefined, 'es')).toBe('');
  });
});

describe('linkableModuleOptions', () => {
  it('groups modules under their path ordered by position', () => {
    const groups = linkableModuleOptions([
      path('p1', { es: 'Ruta A' }, [module('m2', 2), module('m1', 1)]),
      path('p2', 'Ruta B', [module('m3', 1)]),
    ], 'es');

    expect(groups).toHaveLength(2);
    expect(groups[0].pathTitle).toBe('Ruta A');
    expect(groups[0].options.map((o) => o.value)).toEqual(['m1', 'm2']);
    expect(groups[1].options[0]).toEqual({ value: 'm3', label: 'Mod m3' });
  });

  it('skips paths without modules', () => {
    expect(linkableModuleOptions([path('p1', 'Ruta', [])], 'es')).toEqual([]);
  });
});

describe('linkErrorMessage', () => {
  it('maps 422 to a field-level error', () => {
    expect(linkErrorMessage(new ApiError('bad', 422, { path_module_id: ['x'] }))).toEqual({
      kind: 'field',
      key: 'progress.learning_paths.link_error_field',
    });
  });

  it('maps 404 and 403 to a generic alert that reveals nothing', () => {
    expect(linkErrorMessage(new ApiError('nope', 404))).toEqual({
      kind: 'alert',
      key: 'progress.learning_paths.link_error_unavailable',
    });
    expect(linkErrorMessage(new ApiError('nope', 403))).toEqual({
      kind: 'alert',
      key: 'progress.learning_paths.link_error_unavailable',
    });
  });

  it('maps anything else to the generic alert and null to nothing', () => {
    expect(linkErrorMessage(new Error('net'))).toEqual({
      kind: 'alert',
      key: 'progress.learning_paths.link_error_unavailable',
    });
    expect(linkErrorMessage(null)).toBeNull();
  });
});

describe('allowedChallengeTransitions', () => {
  // Mirrors backend/app/Models/Challenge.php VALID_TRANSITIONS (T012.2 grid).
  const EXPECTED: Record<string, string[]> = {
    template: ['template'],
    assigned: ['assigned', 'in_progress', 'canceled', 'completed', 'abandoned'],
    in_progress: ['in_progress', 'completed', 'canceled', 'abandoned'],
    completed: ['completed'],
    canceled: ['canceled'],
    abandoned: ['abandoned', 'assigned', 'in_progress'],
  };

  it('matches the backend transition matrix for all 36 pairs', () => {
    const statuses = Object.keys(EXPECTED);
    for (const from of statuses) {
      expect(allowedChallengeTransitions(from).sort()).toEqual([...EXPECTED[from]].sort());
      const allowed = new Set<string>(allowedChallengeTransitions(from));
      for (const to of statuses) {
        expect(allowed.has(to), `${from} -> ${to}`).toBe(EXPECTED[from].includes(to));
      }
    }
  });

  it('offers abandoned only from assigned/in_progress and reactivation from abandoned', () => {
    expect(allowedChallengeTransitions('assigned')).toContain('abandoned');
    expect(allowedChallengeTransitions('in_progress')).toContain('abandoned');
    expect(allowedChallengeTransitions('completed')).not.toContain('abandoned');
    expect(allowedChallengeTransitions('canceled')).not.toContain('abandoned');
    expect(allowedChallengeTransitions('abandoned')).toEqual(['abandoned', 'assigned', 'in_progress']);
  });

  it('returns an empty list for unknown statuses', () => {
    expect(allowedChallengeTransitions('bogus')).toEqual([]);
  });
});

describe('challengeStatusKey (T012.9)', () => {
  it('maps every backend status to a status label key', () => {
    for (const s of ['template', 'assigned', 'in_progress', 'completed', 'canceled', 'abandoned']) {
      expect(challengeStatusKey(s)).toBe(`mentor_workspace.status_${s}`);
    }
    expect(challengeStatusKey('bogus')).toBe('mentor_workspace.status_unknown');
    expect(challengeStatusKey(null)).toBe('mentor_workspace.status_unknown');
  });
});
