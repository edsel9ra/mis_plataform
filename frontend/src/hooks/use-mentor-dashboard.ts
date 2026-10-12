import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-keys';
import { fetchAllCollection } from '@/lib/mentor-workspace';
import type { WorkspaceEntitlements, WorkspacePlan } from '@/types/mentor-workspace';

export interface MentorAssignment {
  relationship_id: string;
  type: 'personal' | 'familiar' | 'grupal' | 'empresa';
  context: {
    type: 'user' | 'family_group' | 'cohort' | 'company';
    id: string;
    name: string;
  };
  plan: WorkspacePlan | null;
  plan_status?: string;
  locked?: boolean;
  lock_reason?: string | null;
  entitlements?: WorkspaceEntitlements;
}

export interface MentorAssignedUser {
  id: string;
  name: string;
  last_name: string | null;
  full_name: string;
  avatar: string | null;
  personality_completed: boolean;
  assignments: MentorAssignment[];
}

export interface MentorUsersResponse {
  data: MentorAssignedUser[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  plan_counts?: {
    all: number;
    basic: number;
    premium: number;
    locked: number;
  };
}

export interface MentorPersonalityResponse {
  user: MentorAssignedUser;
  assessment: {
    test_version: string;
    completed_at: string;
    factors: Record<string, number>;
    facets: Record<string, number>;
    interpretation: Record<string, { trait: string; score: number; level: string; needs_improvement?: boolean }>;
  } | null;
}

export interface MentorRecommendationsResponse {
  user_ocean: Record<string, number> | null;
  recommended_mentors: Array<{
    id: string;
    full_name: string;
    avatar: string | null;
    client_type: string;
    compatibility_score: number;
    active_mentees_count: number;
  }>;
  recommended_sessions: Array<{
    id: string;
    session_type: string;
    title: string;
    description: string | null;
    status: string;
    scheduled_at: string;
    duration_minutes: number;
  }>;
  recommended_evaluations: Array<{
    id: string;
    title: string;
    description: string;
    type: string;
  }>;
}

export type MentorPlanFilter = 'all' | 'basic' | 'premium' | 'locked';

export function useMentorUsers(
  search: string,
  page: number,
  enabled = true,
  planFilter: MentorPlanFilter = 'all',
  perPage = 12,
) {
  return useQuery({
    queryKey: queryKeys.mentorUsers(search, page, planFilter, perPage),
    queryFn: ({ signal }) => api.get<MentorUsersResponse>('/mentor/users', {
      search: search || undefined,
      page,
      per_page: perPage,
      plan_filter: planFilter === 'all' ? undefined : planFilter,
    }, { signal }),
    enabled,
  });
}

export function useAllMentorUsers(enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorUsersAll,
    queryFn: ({ signal }) => fetchAllCollection((page) => api.get<MentorUsersResponse>('/mentor/users', {
      page,
      per_page: 100,
    }, { signal })),
    enabled,
  });
}

export function useMentorUserPersonality(userId: string, relationshipId: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorUserPersonality(userId, relationshipId),
    queryFn: ({ signal }) => api.get<MentorPersonalityResponse>(`/mentor/users/${userId}/personality`, {
      relationship_id: relationshipId,
    }, { signal }),
    enabled: enabled && Boolean(userId && relationshipId),
  });
}

export function useMentorUserRecommendations(userId: string, relationshipId: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorRecommendations(userId, relationshipId),
    queryFn: ({ signal }) => api.get<MentorRecommendationsResponse>(`/mentor/users/${userId}/recommendations`, {
      relationship_id: relationshipId,
    }, { signal }),
    enabled: enabled && Boolean(userId && relationshipId),
  });
}
