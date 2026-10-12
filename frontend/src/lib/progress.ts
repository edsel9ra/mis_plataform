/**
 * Pure helpers for the progress panel (spec 001, T-091, RF-13, RF-14).
 *
 * No IO, no React, no i18n runtime: components translate the returned keys.
 * The goal is that "no data" never renders as a misleading `0`.
 */
import type {
  CollectiveAggregateState,
  CollectiveProgress,
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
