import type { HistoryActor, HistoryEvent, ProgressHistoryPage } from '@/types/progress';
import { challengeStatusKey, localizedTitle } from './progress';

/**
 * Pure view-model helpers for the unified progress history (spec 015,
 * RF-015.1/.7/.8). No React, no fetch, no clock, no catalogs: components
 * feed the query results in and translate the returned keys via next-intl
 * (values that need translation come back in `valueKeys` with a raw fallback).
 */

export const HISTORY_PAGE_SIZE = 25;

/** Flattens every fetched page keeping server order; first occurrence wins. */
export function flattenHistoryPages(pages: ProgressHistoryPage[]): {
  events: HistoryEvent[];
  undated: HistoryEvent[];
} {
  const seen = new Set<string>();
  const events: HistoryEvent[] = [];

  for (const page of pages) {
    for (const event of page.data) {
      if (seen.has(event.id)) continue;
      seen.add(event.id);
      events.push(event);
    }
  }

  const undatedSeen = new Set<string>();
  const undated = (pages[0]?.undated ?? []).filter((event) => {
    if (undatedSeen.has(event.id)) return false;
    undatedSeen.add(event.id);

    return true;
  });

  return { events, undated };
}

export interface HistoryDayGroup {
  /** `YYYY-MM-DD` in the viewer's zone. */
  dayKey: string;
  /** `Intl` `dateStyle: 'full'` in locale + zone. */
  label: string;
  events: HistoryEvent[];
}

function dayKeyOf(instant: Date, timeZone: string): string {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(instant);
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '';

  return `${get('year')}-${get('month')}-${get('day')}`;
}

/**
 * Groups dated events into viewer-local days preserving the incoming order —
 * the server's newest-first order is authoritative, the group order is the
 * first-seen day order. Undated events never join a day group.
 */
export function groupHistoryByDay(
  events: HistoryEvent[],
  timeZone: string,
  locale: string,
): HistoryDayGroup[] {
  const labelFormat = new Intl.DateTimeFormat(locale, { dateStyle: 'full', timeZone });
  const groups = new Map<string, HistoryDayGroup>();

  for (const event of events) {
    if (event.occurred_at === null) continue;
    const instant = new Date(event.occurred_at);
    if (Number.isNaN(instant.getTime())) continue;
    const dayKey = dayKeyOf(instant, timeZone);

    let group = groups.get(dayKey);
    if (!group) {
      group = { dayKey, label: labelFormat.format(instant), events: [] };
      groups.set(dayKey, group);
    }
    group.events.push(event);
  }

  return [...groups.values()];
}

export function formatHistoryTime(iso: string, timeZone: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, { timeStyle: 'short', timeZone }).format(new Date(iso));
}

export type HistoryViewState = 'loading' | 'error' | 'empty' | 'ready';

export function historyViewState(input: {
  isLoading: boolean;
  isError: boolean;
  events: HistoryEvent[];
  undated: HistoryEvent[];
}): HistoryViewState {
  if (input.isLoading) return 'loading';
  if (input.isError) return 'error';
  if (input.events.length === 0 && input.undated.length === 0) return 'empty';

  return 'ready';
}

export type HistoryActorView =
  | { kind: 'user'; name: string }
  | { kind: 'system' }
  | { kind: 'hidden' }
  | { kind: 'unknown' };

export function historyActorView(actor: HistoryActor): HistoryActorView {
  if (actor !== null && typeof actor === 'object') {
    if ('id' in actor && 'name' in actor) return { kind: 'user', name: actor.name };
    if ('kind' in actor && actor.kind === 'system') return { kind: 'system' };
    if ('kind' in actor && actor.kind === 'hidden') return { kind: 'hidden' };
  }

  return { kind: 'unknown' };
}

/** A detail value the component must translate (`t.has(key) ? t(key) : fallback`). */
export interface HistoryDetailValueKey {
  key: string;
  fallback: string;
}

export interface HistoryEventDetail {
  key: string;
  values: Record<string, string>;
  valueKeys?: Record<string, HistoryDetailValueKey>;
}

export interface HistoryEventView {
  /** `progress.history.events.*` key for the translated title. */
  titleKey: string;
  titleValues: Record<string, string>;
  detail: HistoryEventDetail | null;
}

const EVENT_TITLE_KEYS: Record<string, string> = {
  'objective.created': 'progress.history.events.objective_created',
  'objective.status_changed': 'progress.history.events.objective_status_changed',
  'objective.evidence_added': 'progress.history.events.objective_evidence_added',
  'challenge.status_transition': 'progress.history.events.challenge_status_transition',
  'challenge.module_link_changed': 'progress.history.events.challenge_module_link_changed',
  'module_completion.granted': 'progress.history.events.module_completion_granted',
  'module_completion.revoked': 'progress.history.events.module_completion_revoked',
  'challenge_submission.submitted': 'progress.history.events.challenge_submission_submitted',
  'challenge_submission.reviewed': 'progress.history.events.challenge_submission_reviewed',
  'learning_path.started': 'progress.history.events.learning_path_started',
  'learning_path.completed': 'progress.history.events.learning_path_completed',
  'competency.evidence_link_changed': 'progress.history.events.competency_evidence_link_changed',
  'competency.evidence_source_state_changed': 'progress.history.events.competency_evidence_source_state_changed',
  'competency.evidence_source_deleted': 'progress.history.events.competency_evidence_source_deleted',
};

/** OCEAN factor letter → `personality.*` suffix for anonymous competencies. */
const OCEAN_FACTOR_KEYS: Record<string, string> = {
  O: 'openness',
  C: 'conscientiousness',
  E: 'extraversion',
  A: 'agreeableness',
  N: 'neuroticism',
};

type DetailValue = string | HistoryDetailValueKey;

/** Status message key via the existing objective/challenge helpers (raw status as fallback). */
function statusValue(domain: 'objective' | 'challenge', status: unknown): DetailValue {
  if (typeof status !== 'string' || status === '') return '';
  const key = domain === 'challenge'
    ? challengeStatusKey(status)
    : `progress.objectives.status.${status}`;

  return { key, fallback: status };
}

function sourceStateValue(state: unknown): DetailValue {
  if (typeof state !== 'string' || state === '') return '';

  return { key: `progress.history.source_state.${state}`, fallback: state };
}

function triggerValue(event: unknown): DetailValue {
  if (typeof event !== 'string' || event === '') return '';

  return { key: `progress.history.trigger.${event.replaceAll('.', '_')}`, fallback: event };
}

function competencyValue(side: Record<string, unknown> | null): DetailValue {
  const name = side?.competency_name;
  if (typeof name === 'string' && name !== '') return name;
  const key = side?.competency_key;

  if (side?.competency_type === 'ocean_factor' && typeof key === 'string' && key in OCEAN_FACTOR_KEYS) {
    return { key: `personality.${OCEAN_FACTOR_KEYS[key]}`, fallback: key };
  }

  return typeof key === 'string' ? key : '';
}

function moduleLinkDetail(event: HistoryEvent): HistoryEventView['detail'] {
  const beforeId = event.before?.path_module_id ?? null;
  const afterId = event.after?.path_module_id ?? null;

  if (beforeId === null && afterId !== null) {
    return { key: 'progress.history.detail.linked', values: {} };
  }
  if (afterId === null) {
    if (event.after?.reason === 'path_module_deleted') {
      return { key: 'progress.history.detail.reason_module_deleted', values: {} };
    }

    return { key: 'progress.history.detail.unlinked', values: {} };
  }

  return { key: 'progress.history.detail.relinked', values: {} };
}

/**
 * View model for one event: which title key to translate, the values to
 * interpolate (the subject title, already localized), and the optional
 * detail line. Translatable values (statuses, source states, triggers, OCEAN
 * factors) are returned in `valueKeys` — the component resolves each with
 * `t.has(key) ? t(key) : fallback` so nothing but the active locale ships
 * to the browser (RF-015.7).
 */
export function historyEventView(event: HistoryEvent, locale: string): HistoryEventView {
  const titleKey = EVENT_TITLE_KEYS[event.type] ?? 'progress.history.events.unknown';
  const titleValues = {
    title: localizedTitle(
      event.subject_ref.title as string | Record<string, string> | null | undefined,
      locale,
    ),
  };

  const d = (key: string, raw: Record<string, DetailValue> = {}): HistoryEventDetail => {
    const values: Record<string, string> = {};
    const valueKeys: Record<string, HistoryDetailValueKey> = {};
    for (const [name, entry] of Object.entries(raw)) {
      if (typeof entry === 'string') values[name] = entry;
      else valueKeys[name] = entry;
    }
    const detail: HistoryEventDetail = { key: `progress.history.detail.${key}`, values };
    if (Object.keys(valueKeys).length > 0) detail.valueKeys = valueKeys;

    return detail;
  };

  let detail: HistoryEventView['detail'] = null;

  switch (event.type) {
    case 'objective.created':
      detail = d('status_initial', { to: statusValue('objective', event.after?.status) });
      break;
    case 'objective.status_changed':
      detail = d('status_change', {
        from: statusValue('objective', event.before?.status),
        to: statusValue('objective', event.after?.status),
      });
      break;
    case 'objective.evidence_added':
      detail = d(event.after?.has_attachment ? 'with_attachment' : 'without_attachment');
      break;
    case 'challenge.status_transition':
      detail = d('status_change', {
        from: statusValue('challenge', event.before?.status),
        to: statusValue('challenge', event.after?.status),
      });
      break;
    case 'challenge.module_link_changed':
      detail = moduleLinkDetail(event);
      break;
    case 'module_completion.granted':
    case 'module_completion.revoked': {
      const triggeredBy = event.after?.triggered_by as Record<string, unknown> | undefined;
      detail = d('completion_trigger', { event: triggerValue(triggeredBy?.event) });
      break;
    }
    case 'competency.evidence_link_changed': {
      const side = event.after ?? event.before;
      const competency = competencyValue(side);
      if (event.before === null && event.after !== null) {
        detail = d('competency_linked', { competency });
      } else if (event.after === null) {
        detail = d('competency_unlinked', { competency });
      } else {
        detail = d('competency_relinked', { competency });
      }
      break;
    }
    case 'competency.evidence_source_state_changed':
      detail = d('source_state', {
        from: sourceStateValue(event.before?.state),
        to: sourceStateValue(event.after?.state),
      });
      break;
    case 'competency.evidence_source_deleted':
      detail = d('source_deleted', { competency: competencyValue(event.before) });
      break;
    default:
      break;
  }

  return { titleKey, titleValues, detail };
}

const INCOMPLETE_FIELD_KEYS = new Set([
  'actor',
  'occurred_at',
  'status',
  'path_module_id',
  'triggered_by',
  'before',
  'after',
  'state',
  'source_type',
  'competency_type',
  'competency_key',
]);

/** `progress.history.incomplete_fields.*` keys per incomplete field (deduped). */
export function historyIncompleteLabelKeys(incomplete: string[]): string[] {
  const keys = new Set<string>();

  for (const field of incomplete) {
    keys.add(
      INCOMPLETE_FIELD_KEYS.has(field)
        ? `progress.history.incomplete_fields.${field}`
        : 'progress.history.incomplete_fields.other',
    );
  }

  return [...keys];
}
