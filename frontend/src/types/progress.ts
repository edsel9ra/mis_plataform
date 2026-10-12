/**
 * Contract types for the progress panel (spec 001, T-090).
 *
 * Mirrors the JSON produced by `ProgressPanelResource`,
 * `CollectiveProgressResource`, `MenteeProgressFlagResource` and
 * `ObjectiveResource` in the Laravel API.
 */

// ---------------------------------------------------------------------------
// Normalized metric value (plan §9.0)
// ---------------------------------------------------------------------------

/** `no_data` is deliberately distinct from a real `zero`. */
export type MetricState =
  | 'value'
  | 'zero'
  | 'no_data'
  | 'incomplete'
  | 'suppressed'
  | 'blocked';

export type ProgressPeriodType = 'current_month' | 'current_state' | 'next_7_days';

export interface ProgressPeriod {
  type: ProgressPeriodType;
  from: string | null;
  to: string | null;
  label: string;
}

/**
 * Normalized indicator. Extra calculator-provided keys (for example
 * `numerator` / `denominator` for the objective achievement metric) are merged
 * at the top level by the backend.
 */
export interface MetricValue {
  state: MetricState;
  value: number | null;
  numerator: number | null;
  denominator: number | null;
  source: string | null;
  period: ProgressPeriod | null;
  incomplete: boolean;
  [key: string]: unknown;
}

// ---------------------------------------------------------------------------
// Panel blocks
// ---------------------------------------------------------------------------

export type ObjectiveStatus = 'not_started' | 'in_progress' | 'achieved' | 'abandoned';
export type ObjectiveScope = 'individual' | 'collective';

export interface ObjectivesBlock {
  state: MetricState;
  metric: MetricValue;
  by_status: Record<ObjectiveStatus, number>;
  overdue: number;
  allow_proposal: boolean;
}

export interface ScheduledSessionItem {
  id: string | null;
  title: string | null;
  at: string;
}

export interface SessionsBlock {
  state: MetricState;
  metric: MetricValue;
  scheduled: ScheduledSessionItem[];
}

export type PendingActivityType = 'challenge' | 'learning_module';

export interface PendingActivityItem {
  type: PendingActivityType;
  id: string | null;
  title: string | null;
  due_at: string | null;
  overdue: boolean;
}

export interface ActivitiesBlock {
  state: MetricState;
  metric: MetricValue;
  pending: PendingActivityItem[];
  overdue: number;
  completed: number;
}

export interface CompetencySkillItem {
  id: string | null;
  tag: string | null;
  category: string | null;
  level: number | null;
  source: 'skills';
}

export type OceanState = 'value' | 'no_data' | 'blocked';

export interface OceanBlock {
  state: OceanState;
  source: 'personality';
  reason?: 'entitlement_missing';
  factors?: Record<string, number>;
  assessed_at?: string;
  test_version?: string | null;
}

export interface CompetenciesBlock {
  state: MetricState;
  skills: CompetencySkillItem[];
  ocean: OceanBlock;
}

export type CommitmentKind = 'session' | 'activity' | 'objective';

export interface CommitmentItem {
  kind: CommitmentKind;
  id: string | null;
  title: string | null;
  at: string;
}

export interface CommitmentsBlock {
  state: MetricState;
  metric: MetricValue;
  items: CommitmentItem[];
}

export type ProgressBlockName =
  | 'objectives'
  | 'sessions'
  | 'activities'
  | 'competencies'
  | 'commitments';

export interface ProgressSubject {
  id: string;
  name: string;
}

export type ProgressViewerRole = 'self' | 'mentor' | 'member' | 'support' | 'admin';

/** `GET /progress/panel` (and the mentor/admin variants) response body. */
export interface ProgressPanel {
  subject: ProgressSubject | null;
  viewer_role: ProgressViewerRole;
  period: ProgressPeriod | null;
  incomplete_blocks: ProgressBlockName[];
  objectives: ObjectivesBlock;
  sessions: SessionsBlock;
  activities: ActivitiesBlock;
  competencies: CompetenciesBlock;
  commitments: CommitmentsBlock;
  collectives: CollectiveProgress[];
}

// ---------------------------------------------------------------------------
// Collective aggregate (RF-10, RF-11)
// ---------------------------------------------------------------------------

export type CollectiveSubjectType = 'family_group' | 'cohort' | 'company';
export type CollectiveAggregateState = 'value' | 'no_data' | 'suppressed';

export interface CollectiveSubject {
  type: CollectiveSubjectType;
  id: string;
  name: string;
}

export interface CollectiveAggregateMetrics {
  mean: number | null;
  sum: { achieved: number; total: number };
}

export interface CollectiveProgressBase {
  eligible_members: number | null;
  contributing_members: number | null;
}

/**
 * A suppressed aggregate never discloses metrics nor the exact base size.
 */
export interface CollectiveProgress {
  subject: CollectiveSubject | null;
  viewer_role: 'member';
  state: CollectiveAggregateState;
  suppressed: boolean;
  suppression_reason: 'insufficient_base' | string | null;
  metrics: CollectiveAggregateMetrics | null;
  base: CollectiveProgressBase | null;
}

// ---------------------------------------------------------------------------
// Mentor portfolio flags (RF-9 CA-9.4)
// ---------------------------------------------------------------------------

export interface MenteeSummary {
  id: string;
  name: string;
}

export interface MenteeProgressFlag {
  mentee: MenteeSummary | null;
  relationship_id: string | null;
  objectives_overdue: number;
  objectives_pending: number;
  activities_pending: number;
  no_activity: boolean;
  last_activity_at: string | null;
  workspace_locked: boolean;
}

// ---------------------------------------------------------------------------
// Objectives (RF-1, RF-2)
// ---------------------------------------------------------------------------

export interface ObjectiveEvidence {
  id: string;
  objective_id: string;
  user_id: string | null;
  note: string | null;
  media_asset_id: string | null;
  created_at: string | null;
  user?: { id: string; name: string } | null;
  media_asset?: Record<string, unknown> | null;
}

export interface ObjectiveHistoryEntry {
  id: string;
  description: string | null;
  old_status: ObjectiveStatus | null;
  new_status: ObjectiveStatus | null;
  causer_id: string | null;
  causer_name: string | null;
  created_at: string | null;
}

export interface ObjectivePermissions {
  update: boolean;
  transition: boolean;
  add_evidence: boolean;
}

export interface Objective {
  id: string;
  scope: ObjectiveScope;
  scope_label: string;
  owner_type: string;
  owner_id: string;
  relationship_id: string | null;
  title: string;
  description: string | null;
  success_criteria: string;
  status: ObjectiveStatus;
  status_label: string;
  due_at: string | null;
  achieved_at: string | null;
  proposed_by: string | null;
  created_by: string | null;
  created_at: string | null;
  updated_at: string | null;
  permissions: ObjectivePermissions;
  evidences?: ObjectiveEvidence[];
  history?: ObjectiveHistoryEntry[];
}

export interface ObjectivePayload {
  scope: ObjectiveScope;
  owner_id: string;
  owner_type?: CollectiveSubjectType | null;
  relationship_id?: string | null;
  title: string;
  description?: string | null;
  success_criteria: string;
  due_at?: string | null;
}

export interface ObjectiveProposalPayload {
  title: string;
  description?: string | null;
  success_criteria: string;
  due_at?: string | null;
  relationship_id?: string | null;
}

export interface ObjectiveUpdatePayload {
  title?: string;
  description?: string | null;
  success_criteria?: string;
  due_at?: string | null;
}

export interface ObjectiveEvidencePayload {
  note?: string | null;
  media_asset_id?: string | null;
}
