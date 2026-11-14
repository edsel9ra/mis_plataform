/**
 * Pure helpers for the progress panel (spec 001, T-091, RF-13, RF-14).
 *
 * No IO, no React, no i18n runtime: components translate the returned keys.
 * The goal is that "no data" never renders as a misleading `0`.
 */
import { ApiError } from '@/lib/api';
import type {
  CollectiveAggregateState,
  CollectiveProgress,
  LearningPathProgress,
  CommitmentItem,
  MetricState,
  MetricValue,
  Objective,
  ObjectiveStatus,
  ProgressPanel,
  ProgressPeriod,
  ProgressPeriodType,
} from '@/types/progress';

/** API paths consumed by `hooks/use-progress.ts`. */
export const PROGRESS_ENDPOINTS = {
  panel: '/progress/panel',
  collective: (type: string, id: string) => `/progress/collectives/${type}/${id}`,
  objectives: '/objectives',
  objective: (id: string) => `/objectives/${id}`,
  objectiveStatus: (id: string) => `/objectives/${id}/status`,
  objectiveEvidences: (id: string) => `/objectives/${id}/evidences`,
  objectiveProposals: '/objectives/proposals',
  mentorPortfolio: '/mentor/progress/overview',
  mentorPanel: (userId: string) => `/mentor/users/${userId}/progress`,
  adminPanel: '/admin/progress/panel',
  learningPaths: '/progress/learning-paths',
  mentorLearningPaths: (userId: string) => `/mentor/users/${userId}/progress/learning-paths`,
  map: '/progress/map',
  mentorMap: (userId: string) => `/mentor/users/${userId}/progress/map`,
  challengePathModule: (challengeId: string) => `/challenges/${challengeId}/path-module`,
} as const;

/** Minimum base size below which a collective aggregate is suppressed (RNF-2). */
export const COLLECTIVE_PRIVACY_THRESHOLD = 3;

export type MetricTone = 'value' | 'zero' | 'empty' | 'incomplete' | 'suppressed' | 'blocked';

export interface MetricUi {
  tone: MetricTone;
  /** Whether a numeric value should be rendered at all. */
  showsNumber: boolean;
  /** i18n key to show when no number should be rendered (null when a value is). */
  messageKey: string | null;
}

/**
 * Single source of truth for `state` -> UI. `no_data` is intentionally
 * distinguishable from `zero`, and `incomplete` surfaces a warning label.
 */
export const METRIC_STATE_UI: Record<MetricState, MetricUi> = {
  value: { tone: 'value', showsNumber: true, messageKey: null },
  zero: { tone: 'zero', showsNumber: true, messageKey: null },
  no_data: { tone: 'empty', showsNumber: false, messageKey: 'progress.metric.no_data' },
  incomplete: { tone: 'incomplete', showsNumber: true, messageKey: 'progress.metric.incomplete' },
  suppressed: { tone: 'suppressed', showsNumber: false, messageKey: 'progress.metric.suppressed' },
  blocked: { tone: 'blocked', showsNumber: false, messageKey: 'progress.metric.blocked' },
};

export function metricUi(state: MetricState): MetricUi {
  return METRIC_STATE_UI[state] ?? METRIC_STATE_UI.no_data;
}

/** `true` only for real values, so callers can branch without checking state. */
export function hasMetricData(state: MetricState): boolean {
  return state === 'value' || state === 'zero' || state === 'incomplete';
}

/** A metric is a percentage when the calculator provided a numerator/denominator. */
export function isPercentageMetric(metric: MetricValue | null | undefined): boolean {
  return Boolean(metric && metric.numerator !== null && metric.denominator !== null);
}

export function formatPercent(value: number | null | undefined): string | null {
  if (value === null || value === undefined || !Number.isFinite(value)) return null;

  return `${Math.round(value)}%`;
}

export function formatCount(value: number | null | undefined): string | null {
  if (value === null || value === undefined || !Number.isFinite(value)) return null;

  return String(Math.round(value));
}

/** "3/5" for the achievement ratio, or null when unavailable. */
export function formatRatio(numerator: number | null | undefined, denominator: number | null | undefined): string | null {
  if (numerator === null || numerator === undefined || denominator === null || denominator === undefined) {
    return null;
  }

  return `${Math.round(numerator)}/${Math.round(denominator)}`;
}

/**
 * Render a metric as text. Returns null when the state is "no data"
 * (`no_data`/`suppressed`/`blocked`) or the value is missing, so the caller can
 * show the empty-state label instead of `0`.
 */
export function formatMetricValue(metric: MetricValue | null | undefined): string | null {
  if (!metric || !hasMetricData(metric.state)) return null;

  const value = metric.value;
  if (value === null || value === undefined) return null;

  return isPercentageMetric(metric) ? formatPercent(value) : formatCount(value);
}

export const PERIOD_LABEL_KEYS: Record<ProgressPeriodType, string> = {
  current_month: 'progress.periods.current_month',
  current_state: 'progress.periods.current_state',
  next_7_days: 'progress.periods.next_7_days',
};

export function periodLabelKey(type: ProgressPeriodType): string {
  return PERIOD_LABEL_KEYS[type] ?? PERIOD_LABEL_KEYS.current_state;
}

/**
 * Period label: prefer the localized label the backend already resolved, and
 * fall back to the i18n key when it is absent.
 */
export function resolvePeriodLabel(
  period: ProgressPeriod | null | undefined,
  translate: (key: string) => string,
): string | null {
  if (!period) return null;

  return period.label || translate(periodLabelKey(period.type));
}

export function isBlockIncomplete(panel: ProgressPanel | null | undefined, block: string): boolean {
  return Boolean(panel?.incomplete_blocks?.includes(block as ProgressPanel['incomplete_blocks'][number]));
}

/** Future commitments are always presented in ascending chronological order. */
export function sortCommitments(items: CommitmentItem[]): CommitmentItem[] {
  return [...items].sort((a, b) => a.at.localeCompare(b.at));
}

export function collectiveMessageKey(state: CollectiveAggregateState): string | null {
  if (state === 'no_data') return 'progress.collective.no_data';
  if (state === 'suppressed') return 'progress.collective.suppressed';

  return null;
}

/**
 * Suppression message key for a collective aggregate, or null when the
 * aggregate is disclosable.
 */
export function collectiveSuppressionMessageKey(collective: CollectiveProgress | null | undefined): string | null {
  if (!collective || !collective.suppressed) return null;

  return 'progress.collective.suppressed';
}

export function formatAggregateMean(collective: CollectiveProgress | null | undefined): string | null {
  if (!collective || collective.suppressed || collective.state !== 'value') return null;

  return formatPercent(collective.metrics?.mean ?? null);
}

export function formatAggregateSum(collective: CollectiveProgress | null | undefined): string | null {
  if (!collective || collective.suppressed || collective.state !== 'value') return null;

  const sum = collective.metrics?.sum;
  if (!sum) return null;

  return formatRatio(sum.achieved, sum.total);
}

/** Whether the current viewer may administer (create/edit/transition) an objective. */
export function canAdministerObjective(objective: Objective): boolean {
  return Boolean(objective.permissions?.update || objective.permissions?.transition);
}

/** Valid status transitions, mirroring the backend `ObjectiveStatus` enum. */
export const OBJECTIVE_STATUS_TRANSITIONS: Record<ObjectiveStatus, ObjectiveStatus[]> = {
  not_started: ['in_progress', 'achieved', 'abandoned'],
  in_progress: ['achieved', 'abandoned', 'not_started'],
  achieved: ['in_progress', 'not_started', 'abandoned'],
  abandoned: ['not_started', 'in_progress'],
};

export function allowedObjectiveTransitions(status: ObjectiveStatus): ObjectiveStatus[] {
  return OBJECTIVE_STATUS_TRANSITIONS[status] ?? [];
}

export type BadgeTone = 'default' | 'primary' | 'success' | 'warning' | 'danger';

export function objectiveStatusBadgeVariant(status: ObjectiveStatus): BadgeTone {
  switch (status) {
    case 'achieved':
      return 'success';
    case 'in_progress':
      return 'primary';
    case 'abandoned':
      return 'warning';
    default:
      return 'default';
  }
}

/** An objective is overdue when it has a past due date and is not achieved. */
export function isObjectiveOverdue(objective: Objective, now: Date = new Date()): boolean {
  if (objective.status === 'achieved' || !objective.due_at) return false;

  const due = new Date(objective.due_at);
  if (Number.isNaN(due.getTime())) return false;

  return due.getTime() < now.getTime();
}

// ---------------------------------------------------------------------------
// Learning-path progress (spec 012)
// ---------------------------------------------------------------------------

export type ChallengeStatus =
  | 'template'
  | 'assigned'
  | 'in_progress'
  | 'completed'
  | 'canceled'
  | 'abandoned';

/**
 * Mirrors `Challenge::VALID_TRANSITIONS` on the backend (T012.2). The server
 * stays authoritative; this only drives which status options a UI offers.
 */
export const CHALLENGE_TRANSITIONS: Record<ChallengeStatus, ChallengeStatus[]> = {
  template: ['template'],
  assigned: ['assigned', 'in_progress', 'canceled', 'completed', 'abandoned'],
  in_progress: ['in_progress', 'completed', 'canceled', 'abandoned'],
  completed: ['completed'],
  canceled: ['canceled'],
  abandoned: ['abandoned', 'assigned', 'in_progress'],
};

export function allowedChallengeTransitions(from: string): ChallengeStatus[] {
  return CHALLENGE_TRANSITIONS[from as ChallengeStatus] ?? [];
}

/** i18n key for a module completion state (distinct text per state, RNF-012.3). */
export function moduleCompletionLabelKey(state: string): string {
  const keys: Record<string, string> = {
    completed: 'progress.learning_paths.completion_completed',
    not_completed: 'progress.learning_paths.completion_not_completed',
    no_data: 'progress.learning_paths.completion_no_data',
    incomplete: 'progress.learning_paths.completion_incomplete',
  };

  return keys[state] ?? keys.no_data;
}

/** Resolves JSONB `{es,en,pt}` titles (learning paths) or plain strings (modules). */
export function localizedTitle(
  title: string | Record<string, string> | null | undefined,
  locale: string,
): string {
  if (title === null || title === undefined) return '';
  if (typeof title === 'string') return title;

  return title[locale] ?? title.es ?? Object.values(title)[0] ?? '';
}

export interface LinkableModuleGroup {
  pathId: string;
  pathTitle: string;
  options: { value: string; label: string }[];
}

/** Modules the subject could link to, grouped per consultable path (optgroups). */
export function linkableModuleOptions(
  paths: LearningPathProgress[],
  locale: string,
): LinkableModuleGroup[] {
  return paths
    .filter((entry) => entry.modules.length > 0)
    .map((entry) => ({
      pathId: entry.learning_path.id,
      pathTitle: localizedTitle(entry.learning_path.title, locale),
      options: [...entry.modules]
        .sort((a, b) => a.position - b.position)
        .map((module) => ({ value: module.id, label: module.title })),
    }));
}

export type LinkErrorMessage = { kind: 'field' | 'alert'; key: string } | null;

/**
 * 422 → field-level error; 404/403/network → generic "not available" alert that
 * reveals nothing about the link (RF-012.10, mirrors the backend's 404 denials).
 */
export function linkErrorMessage(error: unknown): LinkErrorMessage {
  if (error === null || error === undefined) return null;
  if (error instanceof ApiError && error.status === 422) {
    return { kind: 'field', key: 'progress.learning_paths.link_error_field' };
  }

  return { kind: 'alert', key: 'progress.learning_paths.link_error_unavailable' };
}

/** i18n key for a challenge status label (shared mentor/mentee surfaces). */
export function challengeStatusKey(status: string | null | undefined): string {
  const keys: Record<string, string> = {
    template: 'mentor_workspace.status_template',
    assigned: 'mentor_workspace.status_assigned',
    in_progress: 'mentor_workspace.status_in_progress',
    completed: 'mentor_workspace.status_completed',
    canceled: 'mentor_workspace.status_canceled',
    abandoned: 'mentor_workspace.status_abandoned',
    not_started: 'mentor_workspace.status_not_started',
    blocked: 'mentor_workspace.status_blocked',
  };

  return (status && keys[status]) || 'mentor_workspace.status_unknown';
}

// ---------------------------------------------------------------------------
// Learning map (spec 013)
// ---------------------------------------------------------------------------

/**
 * i18n key for a map element type badge, or null when the type is not known —
 * the UI then renders the raw source type verbatim (RF-013.3).
 */
export function mapElementTypeKey(type: string): string | null {
  return type === 'challenge' ? 'progress.map.element_challenge' : null;
}

/**
 * i18n key for a map element status, or null when there is no known mapping —
 * the UI then renders `source_status` verbatim, never a derived state.
 */
export function mapElementStatusKey(
  element: { type: string; source_status: string },
): string | null {
  if (element.type === 'challenge') return challengeStatusKey(element.source_status);

  return null;
}

// ---------------------------------------------------------------------------
// Collective learning aggregate (spec 013)
// ---------------------------------------------------------------------------

/**
 * i18n key for a learning-aggregate suppression reason (RF-013.7). Each reason
 * maps to a distinct, count-free message; unknown reasons fall back to the
 * generic suppression text rather than leaking which rule applied.
 */
export function learningSuppressionMessageKey(reason: string | null | undefined): string {
  const keys: Record<string, string> = {
    insufficient_base: 'progress.collective.learning.suppressed_base',
    insufficient_metric_contributions: 'progress.collective.learning.suppressed_metric',
  };

  return (reason && keys[reason]) || 'progress.collective.learning.suppressed';
}
