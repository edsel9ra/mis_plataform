import { describe, expect, it } from 'vitest';

import esMessages from '../i18n/messages/es/common.json';
import enMessages from '../i18n/messages/en/common.json';
import ptMessages from '../i18n/messages/pt/common.json';
import {
  HISTORY_PAGE_SIZE,
  flattenHistoryPages,
  formatHistoryTime,
  groupHistoryByDay,
  historyActorView,
  historyEventView,
  historyIncompleteLabelKeys,
  historyViewState,
} from './progress-history';
import type { HistoryActor, HistoryEvent, ProgressHistoryPage } from '@/types/progress';

function ev(overrides: Partial<HistoryEvent> = {}): HistoryEvent {
  return {
    id: `e-${Math.random().toString(36).slice(2, 8)}`,
    type: 'objective.created',
    domain: 'objectives',
    occurred_at: '2026-11-15T16:00:00+00:00',
    actor: { id: 'u1', name: 'Ana' },
    subject_ref: { title: 'Mi objetivo' },
    before: null,
    after: null,
    incomplete: [],
    ...overrides,
  };
}

function page(events: HistoryEvent[], undated: HistoryEvent[] = [], next: string | null = null): ProgressHistoryPage {
  return { data: events, undated, next_cursor: next };
}

function flattenKeys(value: unknown, prefix = ''): string[] {
  if (value === null || typeof value !== 'object' || Array.isArray(value)) {
    return prefix ? [prefix] : [];
  }
  const entries = Object.entries(value as Record<string, unknown>);
  if (entries.length === 0) return prefix ? [prefix] : [];
  return entries.flatMap(([key, child]) => flattenKeys(child, prefix ? `${prefix}.${key}` : key));
}

describe('flattenHistoryPages', () => {
  it('concatenates data in page order, de-duplicating by id (first wins)', () => {
    const e1 = ev({ id: 'e1' });
    const e2 = ev({ id: 'e2' });
    const e3 = ev({ id: 'e3' });
    const e2dup = ev({ id: 'e2', type: 'changed-copy' });

    const flat = flattenHistoryPages([page([e1, e2]), page([e2dup, e3])]);

    expect(flat.events.map((e) => e.id)).toEqual(['e1', 'e2', 'e3']);
    expect(flat.events[1].type).toBe('objective.created');
  });

  it('takes undated only from the first page and de-duplicates it', () => {
    const u1 = ev({ id: 'u1', occurred_at: null });
    const u2 = ev({ id: 'u2', occurred_at: null });
    const u1dup = ev({ id: 'u1', occurred_at: null });
    const uOther = ev({ id: 'u3', occurred_at: null });

    const flat = flattenHistoryPages([page([], [u1, u2]), page([], [uOther, u1dup])]);

    expect(flat.undated.map((e) => e.id)).toEqual(['u1', 'u2']);
  });
});

describe('groupHistoryByDay / formatHistoryTime', () => {
  const instant = '2026-11-15T02:30:00+00:00';

  it('groups the same UTC instant into different days per viewer zone', () => {
    const event = ev({ occurred_at: instant });

    expect(groupHistoryByDay([event], 'UTC', 'en')[0].dayKey).toBe('2026-11-15');
    expect(groupHistoryByDay([event], 'America/Mexico_City', 'es')[0].dayKey).toBe('2026-11-14');
  });

  it('preserves the incoming (server) order within and across days', () => {
    const newer = ev({ id: 'n', occurred_at: '2026-11-15T20:00:00+00:00' });
    const sameDay = ev({ id: 's', occurred_at: '2026-11-15T08:00:00+00:00' });
    const older = ev({ id: 'o', occurred_at: '2026-11-14T20:00:00+00:00' });

    const groups = groupHistoryByDay([newer, sameDay, older], 'UTC', 'en');

    expect(groups.map((g) => g.dayKey)).toEqual(['2026-11-15', '2026-11-14']);
    expect(groups[0].events.map((e) => e.id)).toEqual(['n', 's']);
    expect(groups[0].label).toBe(
      new Intl.DateTimeFormat('en', { dateStyle: 'full', timeZone: 'UTC' }).format(new Date('2026-11-15T20:00:00+00:00')),
    );
  });

  it('never groups an event without occurred_at', () => {
    const groups = groupHistoryByDay([ev({ occurred_at: null })], 'UTC', 'en');
    expect(groups).toEqual([]);
  });

  it('formats the time in the viewer zone and locale', () => {
    expect(formatHistoryTime('2026-11-15T16:30:00+00:00', 'UTC', 'en')).toBe(
      new Intl.DateTimeFormat('en', { timeStyle: 'short', timeZone: 'UTC' }).format(new Date('2026-11-15T16:30:00+00:00')),
    );
  });
});

describe('historyViewState', () => {
  it('maps the query into the four states', () => {
    expect(historyViewState({ isLoading: true, isError: false, events: [], undated: [] })).toBe('loading');
    expect(historyViewState({ isLoading: false, isError: true, events: [], undated: [] })).toBe('error');
    expect(historyViewState({ isLoading: false, isError: false, events: [], undated: [] })).toBe('empty');
    expect(historyViewState({ isLoading: false, isError: false, events: [ev()], undated: [] })).toBe('ready');
    expect(historyViewState({ isLoading: false, isError: false, events: [], undated: [ev({ occurred_at: null })] })).toBe('ready');
  });
});

describe('historyActorView', () => {
  it('classifies the four actor shapes', () => {
    expect(historyActorView({ id: 'u1', name: 'Ana' })).toEqual({ kind: 'user', name: 'Ana' });
    expect(historyActorView({ kind: 'system' })).toEqual({ kind: 'system' });
    expect(historyActorView({ kind: 'hidden' })).toEqual({ kind: 'hidden' });
    expect(historyActorView(null)).toEqual({ kind: 'unknown' });
    expect(historyActorView({ kind: 'weird' } as unknown as HistoryActor)).toEqual({ kind: 'unknown' });
  });
});

describe('historyEventView', () => {
  it('titles objective events and localizes status labels', () => {
    const created = historyEventView(
      ev({ subject_ref: { title: 'Mi meta' }, after: { status: 'not_started' } }),
      'es',
    );
    expect(created.titleKey).toBe('progress.history.events.objective_created');
    expect(created.titleValues.title).toBe('Mi meta');
    expect(created.detail).toEqual({
      key: 'progress.history.detail.status_initial',
      values: {},
      valueKeys: {
        to: { key: 'progress.objectives.status.not_started', fallback: 'not_started' },
      },
    });

    const changed = historyEventView(
      ev({
        type: 'objective.status_changed',
        before: { status: 'in_progress' },
        after: { status: 'achieved' },
      }),
      'en',
    );
    expect(changed.detail?.key).toBe('progress.history.detail.status_change');
    expect(changed.detail?.values).toEqual({});
    expect(changed.detail?.valueKeys).toEqual({
      from: { key: 'progress.objectives.status.in_progress', fallback: 'in_progress' },
      to: { key: 'progress.objectives.status.achieved', fallback: 'achieved' },
    });
  });

  it('details evidence attachment flags', () => {
    const withAttachment = historyEventView(
      ev({ type: 'objective.evidence_added', after: { has_attachment: true } }),
      'es',
    );
    const without = historyEventView(
      ev({ type: 'objective.evidence_added', after: { has_attachment: false } }),
      'es',
    );
    expect(withAttachment.detail?.key).toBe('progress.history.detail.with_attachment');
    expect(without.detail?.key).toBe('progress.history.detail.without_attachment');
  });

  it('uses the challenge status labels for transitions', () => {
    const view = historyEventView(
      ev({
        type: 'challenge.status_transition',
        domain: 'activities',
        subject_ref: { title: 'Reto' },
        before: { status: 'assigned' },
        after: { status: 'completed' },
      }),
      'es',
    );
    expect(view.titleKey).toBe('progress.history.events.challenge_status_transition');
    expect(view.detail?.valueKeys).toEqual({
      from: { key: 'mentor_workspace.status_assigned', fallback: 'assigned' },
      to: { key: 'mentor_workspace.status_completed', fallback: 'completed' },
    });
  });

  it('describes module link changes incl. the deleted-module reason', () => {
    const linked = historyEventView(
      ev({ type: 'challenge.module_link_changed', domain: 'activities', before: { path_module_id: null }, after: { path_module_id: 'm1' } }),
      'es',
    );
    const unlinked = historyEventView(
      ev({ type: 'challenge.module_link_changed', domain: 'activities', before: { path_module_id: 'm1' }, after: { path_module_id: null } }),
      'es',
    );
    const moduleDeleted = historyEventView(
      ev({ type: 'challenge.module_link_changed', domain: 'activities', before: { path_module_id: 'm1' }, after: { path_module_id: null, reason: 'path_module_deleted' } }),
      'es',
    );
    const relinked = historyEventView(
      ev({ type: 'challenge.module_link_changed', domain: 'activities', before: { path_module_id: 'm1' }, after: { path_module_id: 'm2' } }),
      'es',
    );

    expect(linked.detail?.key).toBe('progress.history.detail.linked');
    expect(unlinked.detail?.key).toBe('progress.history.detail.unlinked');
    expect(moduleDeleted.detail?.key).toBe('progress.history.detail.reason_module_deleted');
    expect(relinked.detail?.key).toBe('progress.history.detail.relinked');
  });

  it('details module completions with the translated trigger event', () => {
    const view = historyEventView(
      ev({
        type: 'module_completion.granted',
        domain: 'activities',
        subject_ref: { title: 'Módulo 1' },
        after: { result: 'completed', triggered_by: { event: 'challenge.status_transition' } },
      }),
      'es',
    );

    expect(view.titleKey).toBe('progress.history.events.module_completion_granted');
    expect(view.detail).toEqual({
      key: 'progress.history.detail.completion_trigger',
      values: {},
      valueKeys: {
        event: {
          key: 'progress.history.trigger.challenge_status_transition',
          fallback: 'challenge.status_transition',
        },
      },
    });

    const unknownTrigger = historyEventView(
      ev({
        type: 'module_completion.revoked',
        domain: 'activities',
        after: { result: 'revoked', triggered_by: { event: 'foo.bar' } },
      }),
      'es',
    );
    expect(unknownTrigger.detail?.valueKeys).toEqual({
      event: { key: 'progress.history.trigger.foo_bar', fallback: 'foo.bar' },
    });
  });

  it('emits no detail for submissions and learning-path events, with i18n titles', () => {
    const submitted = historyEventView(ev({ type: 'challenge_submission.submitted', domain: 'activities' }), 'es');
    expect(submitted.titleKey).toBe('progress.history.events.challenge_submission_submitted');
    expect(submitted.detail).toBeNull();

    const started = historyEventView(
      ev({ type: 'learning_path.started', domain: 'activities', subject_ref: { title: { es: 'Ruta A', en: 'Path A' } } }),
      'en',
    );
    expect(started.titleKey).toBe('progress.history.events.learning_path_started');
    expect(started.titleValues.title).toBe('Path A');
    expect(started.detail).toBeNull();
  });

  it('describes competency link changes with the resolved competency', () => {
    const linked = historyEventView(
      ev({ type: 'competency.evidence_link_changed', domain: 'competencies', before: null, after: { competency_key: 't1', competency_name: 'Trabajo' } }),
      'es',
    );
    const ocean = historyEventView(
      ev({ type: 'competency.evidence_link_changed', domain: 'competencies', before: null, after: { competency_type: 'ocean_factor', competency_key: 'O', competency_name: null } }),
      'es',
    );
    const skillTag = historyEventView(
      ev({ type: 'competency.evidence_link_changed', domain: 'competencies', before: null, after: { competency_type: 'skill_tag', competency_key: 't1', competency_name: null } }),
      'es',
    );
    const unknownOcean = historyEventView(
      ev({ type: 'competency.evidence_link_changed', domain: 'competencies', before: null, after: { competency_type: 'ocean_factor', competency_key: 'X', competency_name: null } }),
      'es',
    );
    const unlinked = historyEventView(
      ev({ type: 'competency.evidence_link_changed', domain: 'competencies', before: { competency_key: 't1', competency_name: 'Trabajo' }, after: null }),
      'es',
    );
    const relinked = historyEventView(
      ev({ type: 'competency.evidence_link_changed', domain: 'competencies', before: { competency_key: 't1', competency_name: 'A' }, after: { competency_key: 't2', competency_name: 'B' } }),
      'es',
    );

    expect(linked.detail).toEqual({ key: 'progress.history.detail.competency_linked', values: { competency: 'Trabajo' } });
    expect(ocean.detail?.values).toEqual({});
    expect(ocean.detail?.valueKeys).toEqual({
      competency: { key: 'personality.openness', fallback: 'O' },
    });
    expect(skillTag.detail?.values).toEqual({ competency: 't1' });
    expect(unknownOcean.detail?.values).toEqual({ competency: 'X' });
    expect(unlinked.detail).toEqual({ key: 'progress.history.detail.competency_unlinked', values: { competency: 'Trabajo' } });
    expect(relinked.detail).toEqual({ key: 'progress.history.detail.competency_relinked', values: { competency: 'B' } });
  });

  it.each([
    ['O', 'openness'],
    ['C', 'conscientiousness'],
    ['E', 'extraversion'],
    ['A', 'agreeableness'],
    ['N', 'neuroticism'],
  ])('maps the OCEAN letter %s to personality.%s', (letter, factor) => {
    const view = historyEventView(
      ev({ type: 'competency.evidence_link_changed', domain: 'competencies', before: null, after: { competency_type: 'ocean_factor', competency_key: letter, competency_name: null } }),
      'es',
    );
    expect(view.detail?.valueKeys?.competency).toEqual({
      key: `personality.${factor}`,
      fallback: letter,
    });
  });

  it('keeps the OCEAN factor name when the payload already carries it', () => {
    const named = historyEventView(
      ev({ type: 'competency.evidence_link_changed', domain: 'competencies', before: null, after: { competency_type: 'ocean_factor', competency_key: 'A', competency_name: 'Amabilidad' } }),
      'es',
    );
    expect(named.detail?.values).toEqual({ competency: 'Amabilidad' });
  });

  it('describes source state changes and deletions', () => {
    const state = historyEventView(
      ev({ type: 'competency.evidence_source_state_changed', domain: 'competencies', before: { state: 'current' }, after: { state: 'incomplete' } }),
      'es',
    );
    expect(state.detail).toEqual({
      key: 'progress.history.detail.source_state',
      values: {},
      valueKeys: {
        from: { key: 'progress.history.source_state.current', fallback: 'current' },
        to: { key: 'progress.history.source_state.incomplete', fallback: 'incomplete' },
      },
    });

    const deleted = historyEventView(
      ev({ type: 'competency.evidence_source_deleted', domain: 'competencies', actor: { kind: 'hidden' }, before: { competency_key: 't9', competency_name: 'Resiliencia' }, after: null }),
      'es',
    );
    expect(deleted.titleKey).toBe('progress.history.events.competency_evidence_source_deleted');
    expect(deleted.detail).toEqual({
      key: 'progress.history.detail.source_deleted',
      values: { competency: 'Resiliencia' },
    });

    const deletedOcean = historyEventView(
      ev({ type: 'competency.evidence_source_deleted', domain: 'competencies', actor: { kind: 'hidden' }, before: { competency_type: 'ocean_factor', competency_key: 'N', competency_name: null }, after: null }),
      'es',
    );
    expect(deletedOcean.detail?.valueKeys).toEqual({
      competency: { key: 'personality.neuroticism', fallback: 'N' },
    });
  });

  it('falls back to the unknown title for unlisted event types', () => {
    const view = historyEventView(ev({ type: 'totally.new' }), 'es');
    expect(view.titleKey).toBe('progress.history.events.unknown');
    expect(view.detail).toBeNull();
  });
});

describe('historyIncompleteLabelKeys', () => {
  it('maps known fields and falls back to other, de-duplicated', () => {
    expect(historyIncompleteLabelKeys(['actor', 'occurred_at', 'zzz', 'actor', 'before'])).toEqual([
      'progress.history.incomplete_fields.actor',
      'progress.history.incomplete_fields.occurred_at',
      'progress.history.incomplete_fields.other',
      'progress.history.incomplete_fields.before',
    ]);
    expect(historyIncompleteLabelKeys([])).toEqual([]);
  });
});

describe('progress.history catalogs', () => {
  it('exposes the identical key set in es, en and pt', () => {
    const subtree = (messages: unknown) =>
      flattenKeys((messages as { progress: { history: Record<string, unknown> } }).progress.history, 'progress.history');

    const esKeys = new Set(subtree(esMessages));
    const enKeys = new Set(subtree(enMessages));
    const ptKeys = new Set(subtree(ptMessages));

    expect(esKeys.size).toBeGreaterThan(0);
    expect(enKeys).toEqual(esKeys);
    expect(ptKeys).toEqual(esKeys);
  });

  it('keeps the page size constant exported', () => {
    expect(HISTORY_PAGE_SIZE).toBe(25);
  });
});
