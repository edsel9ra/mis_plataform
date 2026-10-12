export type PersonalPlanTier = 'basic' | 'premium' | 'unknown';

export type MentorWorkspaceFeature =
  | 'ocean'
  | 'neurocognitive'
  | 'systemic_map'
  | 'challenges'
  | 'feedback';

export interface WorkspacePlan {
  id: string;
  code?: string;
  client_type: string;
  name: string;
  description?: string | null;
  price_monthly?: number | null;
  price_yearly?: number | null;
  max_sessions_per_month?: number | null;
  max_members?: number | null;
  max_mentors?: number | null;
  features?: string[];
  entitlements?: WorkspaceEntitlements;
  is_active?: boolean;
}

export type EntitlementValue = boolean | number | string;

export interface ActiveSubscription {
  id: string;
  plan_id: string;
  status: string;
  trial_ends_at?: string | null;
  starts_at?: string | null;
  ends_at?: string | null;
  plan?: WorkspacePlan | null;
}

export interface WorkspaceEntitlements {
  ocean?: EntitlementValue;
  neurocognitive?: EntitlementValue;
  systemic_map?: EntitlementValue;
  challenges?: EntitlementValue;
  feedback?: EntitlementValue;
  [feature: string]: EntitlementValue | undefined;
}

export interface MentorProfileUser {
  id: string;
  name?: string;
  last_name?: string | null;
  full_name?: string;
  avatar?: string | null;
  client_type?: string | null;
}

export interface MentorUserProfileResponse {
  user: MentorProfileUser;
  plan?: WorkspacePlan | null;
  subscription?: ActiveSubscription | null;
  entitlements?: WorkspaceEntitlements;
  assignment?: {
    relationship_id?: string;
    type?: string;
    locked?: boolean;
    lock_reason?: string | null;
    plan_status?: string;
    plan?: WorkspacePlan | null;
  } | null;
}

export interface AttentionObservation {
  focus?: number | null;
  attention?: number | null;
  regulation?: number | null;
  emotional_regulation?: number | null;
  stress?: number | null;
  energy?: number | null;
  motivation?: number | null;
  note?: string | null;
  notes?: string | null;
  dimensions?: Record<string, number | string | null>;
  scores?: Record<string, number | string | null>;
  [key: string]: unknown;
}

export interface NeurocognitiveProfile {
  self_report?: AttentionObservation | null;
  mentee_self_report?: AttentionObservation | null;
  mentor_observation?: AttentionObservation | null;
  attention?: {
    self_report?: AttentionObservation | null;
    mentee_self_report?: AttentionObservation | null;
    mentor_observation?: AttentionObservation | null;
  } | null;
  updated_at?: string | null;
  [key: string]: unknown;
}

export interface SystemicMapNode {
  id: string;
  label: string;
  x: number;
  y: number;
  color?: string;
  kind?: string;
}

export interface SystemicMapEdge {
  id: string;
  source: string;
  target: string;
  label?: string;
}

export interface SystemicMap {
  nodes: SystemicMapNode[];
  edges: SystemicMapEdge[];
  updated_at?: string | null;
}

export interface MentorChallenge {
  id: string;
  title: string;
  description?: string | null;
  instructions?: string | null;
  content_url?: string | null;
  content_file_url?: string | null;
  content_name?: string | null;
  content_type?: string | null;
  status?: string | null;
  due_at?: string | null;
  created_at?: string | null;
  assigned_at?: string | null;
  completed_at?: string | null;
  check_ins_count?: number;
  submissions_count?: number;
  check_ins?: ChallengeCheckIn[];
  submissions?: ChallengeSubmission[];
}

export interface ChallengeCheckIn {
  id: string;
  challenge_id: string;
  relationship_id?: string;
  source?: string | null;
  status?: string | null;
  attention_score?: number | null;
  focus?: number | null;
  attention?: number | null;
  note?: string | null;
  checked_in_at?: string | null;
  created_at?: string | null;
  author?: { id: string; name?: string; full_name?: string } | null;
}

export interface ChallengeSubmission {
  id: string;
  challenge_id: string;
  relationship_id?: string;
  mentee_id?: string;
  file_url?: string | null;
  file_name?: string | null;
  mime_type?: string | null;
  note?: string | null;
  status?: string | null;
  submitted_at?: string | null;
  reviewed_at?: string | null;
  created_at?: string | null;
  author?: { id: string; name?: string; full_name?: string } | null;
}

export interface EmotionalCheckIn {
  id: string;
  relationship_id?: string;
  mentee_id?: string;
  mood_score?: number | null;
  stress_score?: number | null;
  status?: string | null;
  note?: string | null;
  checked_in_at?: string | null;
  created_at?: string | null;
}

export interface MentorAlert {
  id: string;
  relationship_id: string;
  mentee_id: string;
  type: string;
  severity?: string | null;
  data?: Record<string, unknown> | null;
  detected_at?: string | null;
  resolved_at?: string | null;
  created_at?: string | null;
}

export interface MentorFeedback {
  id: string;
  user_id?: string;
  user?: MentorProfileUser | null;
  relationship_id?: string | null;
  note?: string | null;
  media_url?: string | null;
  file_url?: string | null;
  file_name?: string | null;
  mime_type?: string | null;
  created_at?: string | null;
}

export interface MentorObservationPayload {
  focus?: number;
  regulation?: number;
  stress?: number;
  energy?: number;
  note?: string;
}
