import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import {
  fetchAllCollection,
  MENTOR_WORKSPACE_ENDPOINTS,
  unwrapData,
} from '@/lib/mentor-workspace';
import { queryKeys } from '@/lib/query-keys';
import type {
  ActiveSubscription,
  EmotionalCheckIn,
  MentorAlert,
  MentorChallenge,
  MentorFeedback,
  MentorObservationPayload,
  MentorUserProfileResponse,
  NeurocognitiveProfile,
  SystemicMap,
  WorkspacePlan,
} from '@/types/mentor-workspace';

const MEDIA_UPLOAD_TIMEOUT_MS = Number(process.env.NEXT_PUBLIC_MEDIA_UPLOAD_TIMEOUT_MS ?? 120_000);

export interface MentorChallengePayload {
  title: string;
  description?: string;
  content_file: File;
  due_at?: string;
}

export interface MentorFeedbackPayload {
  user_id: string;
  relationship_id: string;
  note?: string;
  feedback_file?: File;
}

export function usePersonalPlans(enabled = true) {
  return useQuery({
    queryKey: queryKeys.plans('personal'),
    queryFn: async ({ signal }) => {
      const response = await api.get<WorkspacePlan[] | { data?: WorkspacePlan[] }>(
        '/plans',
        { client_type: 'personal' },
        { signal },
      );

      return Array.isArray(response) ? response : response.data ?? [];
    },
    enabled,
    staleTime: 5 * 60_000,
  });
}

export function useActiveSubscription(enabled = true) {
  return useQuery({
    queryKey: queryKeys.activeSubscription,
    queryFn: async ({ signal }) => {
      const response = await api.get<ActiveSubscription | null | { data: ActiveSubscription | null }>(
        '/subscriptions/active',
        undefined,
        { signal },
      );

      return unwrapData<ActiveSubscription | null>(response);
    },
    enabled,
    staleTime: 60_000,
  });
}

export function useStartSubscription() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (planId: string) => api.post<ActiveSubscription>('/subscriptions', { plan_id: planId }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.activeSubscription });
    },
  });
}

export function useMentorUserProfile(userId: string, relationshipId: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorUserProfile(userId, relationshipId),
    queryFn: async ({ signal }) => {
      const response = await api.get<MentorUserProfileResponse | { data: MentorUserProfileResponse }>(
        MENTOR_WORKSPACE_ENDPOINTS.profile(userId),
        { relationship_id: relationshipId },
        { signal },
      );

      return unwrapData<MentorUserProfileResponse>(response);
    },
    enabled: enabled && Boolean(userId && relationshipId),
    retry: false,
  });
}

export function useMentorUserNeurocognitive(userId: string, relationshipId: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorUserNeurocognitive(userId, relationshipId),
    queryFn: async ({ signal }) => {
      const response = await api.get<NeurocognitiveProfile | { data: NeurocognitiveProfile }>(
        MENTOR_WORKSPACE_ENDPOINTS.neurocognitive(userId),
        { relationship_id: relationshipId },
        { signal },
      );

      return unwrapData<NeurocognitiveProfile>(response);
    },
    enabled: enabled && Boolean(userId && relationshipId),
    retry: false,
  });
}

export function useMentorSystemicMap(userId: string, relationshipId: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorSystemicMap(userId, relationshipId),
    queryFn: async ({ signal }) => {
      const response = await api.get<SystemicMap | { data: SystemicMap }>(
        MENTOR_WORKSPACE_ENDPOINTS.systemicMap(userId),
        { relationship_id: relationshipId },
        { signal },
      );

      return unwrapData<SystemicMap>(response);
    },
    enabled: enabled && Boolean(userId && relationshipId),
    retry: false,
  });
}

export function useMentorUserChallenges(userId: string, relationshipId: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorUserChallenges(userId, relationshipId),
    queryFn: async ({ signal }) => {
      return fetchAllCollection((page) => api.get<MentorChallenge[] | { data?: MentorChallenge[]; meta?: { last_page?: number } }>(
        MENTOR_WORKSPACE_ENDPOINTS.userChallenges(userId),
        { relationship_id: relationshipId, page, per_page: 100 },
        { signal },
      ));
    },
    enabled: enabled && Boolean(userId && relationshipId),
    retry: false,
  });
}

export function useMentorChallengeDetails(challengeId: string, relationshipId: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorChallenge(challengeId, relationshipId),
    queryFn: async ({ signal }) => {
      const response = await api.get<MentorChallenge | { data: MentorChallenge }>(
        MENTOR_WORKSPACE_ENDPOINTS.challenge(challengeId),
        { relationship_id: relationshipId },
        { signal },
      );

      return unwrapData<MentorChallenge>(response);
    },
    enabled: enabled && Boolean(challengeId && relationshipId),
    retry: false,
  });
}

export function useMentorChallenges(enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorChallenges,
    queryFn: async ({ signal }) => {
      return fetchAllCollection((page) => api.get<MentorChallenge[] | { data?: MentorChallenge[]; meta?: { last_page?: number } }>(
        MENTOR_WORKSPACE_ENDPOINTS.challenges,
        { page, per_page: 100 },
        { signal },
      ));
    },
    enabled,
    retry: false,
  });
}

export function useMentorFeedback(userId?: string, relationshipId?: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorFeedback(userId, relationshipId),
    queryFn: async ({ signal }) => {
      return fetchAllCollection((page) => api.get<MentorFeedback[] | { data?: MentorFeedback[]; meta?: { last_page?: number } }>(
        MENTOR_WORKSPACE_ENDPOINTS.feedback,
        { user_id: userId, relationship_id: relationshipId, page, per_page: 100 },
        { signal },
      ));
    },
    enabled: enabled && Boolean(userId && relationshipId),
    retry: false,
  });
}

export function useMentorEmotionalCheckIns(userId: string, relationshipId: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorEmotionalCheckIns(userId, relationshipId),
    queryFn: async ({ signal }) => {
      return fetchAllCollection((page) => api.get<EmotionalCheckIn[] | { data?: EmotionalCheckIn[]; meta?: { last_page?: number } }>(
        MENTOR_WORKSPACE_ENDPOINTS.emotionalCheckIns(userId),
        { relationship_id: relationshipId, page, per_page: 100 },
        { signal },
      ));
    },
    enabled: enabled && Boolean(userId && relationshipId),
    retry: false,
  });
}

export function useMentorAlerts(userId: string, relationshipId: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorAlerts(userId, relationshipId),
    queryFn: async ({ signal }) => fetchAllCollection((page) => api.get<MentorAlert[] | { data?: MentorAlert[]; meta?: { last_page?: number } }>(
      MENTOR_WORKSPACE_ENDPOINTS.alerts(userId),
      { relationship_id: relationshipId, page, per_page: 100 },
      { signal },
    )),
    enabled: enabled && Boolean(userId && relationshipId),
    retry: false,
  });
}

export function useSaveMentorNeurocognitive(userId: string, relationshipId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (observation: MentorObservationPayload) => api.put(
      MENTOR_WORKSPACE_ENDPOINTS.neurocognitive(userId),
      { relationship_id: relationshipId, mentor_observation: observation },
    ),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.mentorUserNeurocognitive(userId, relationshipId) });
      void queryClient.invalidateQueries({ queryKey: queryKeys.mentorUserProfile(userId, relationshipId) });
    },
  });
}

export function useSaveMentorSystemicMap(userId: string, relationshipId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (map: SystemicMap) => api.put(
      MENTOR_WORKSPACE_ENDPOINTS.systemicMap(userId),
      { relationship_id: relationshipId, nodes: map.nodes, edges: map.edges },
    ),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.mentorSystemicMap(userId, relationshipId) });
      void queryClient.invalidateQueries({ queryKey: queryKeys.mentorUserProfile(userId, relationshipId) });
    },
  });
}

export function useCreateMentorChallenge() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: MentorChallengePayload) => api.post<MentorChallenge>(
      MENTOR_WORKSPACE_ENDPOINTS.challenges,
      challengeFormData(payload),
      { timeoutMs: MEDIA_UPLOAD_TIMEOUT_MS },
    ),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.mentorChallenges });
    },
  });
}

export function useAssignMentorChallenge(userId: string, relationshipId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: { challenge_id: string; due_at?: string }) => api.post(
      MENTOR_WORKSPACE_ENDPOINTS.userChallenges(userId),
      { ...payload, relationship_id: relationshipId },
    ),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.mentorUserChallenges(userId, relationshipId) });
    },
  });
}

export function useCreateMentorFeedback() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: MentorFeedbackPayload) => api.post<MentorFeedback>(
      MENTOR_WORKSPACE_ENDPOINTS.feedback,
      feedbackFormData(payload),
      { timeoutMs: MEDIA_UPLOAD_TIMEOUT_MS },
    ),
    onSuccess: (_data, variables) => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.mentorFeedback(variables.user_id, variables.relationship_id) });
      void queryClient.invalidateQueries({ queryKey: queryKeys.mentorFeedback() });
    },
  });
}

function challengeFormData(payload: MentorChallengePayload): FormData {
  const formData = new FormData();
  formData.append('title', payload.title);
  if (payload.description) formData.append('description', payload.description);
  if (payload.due_at) formData.append('due_at', payload.due_at);
  formData.append('content_file', payload.content_file);

  return formData;
}

function feedbackFormData(payload: MentorFeedbackPayload): FormData {
  const formData = new FormData();
  formData.append('user_id', payload.user_id);
  formData.append('relationship_id', payload.relationship_id);
  if (payload.note) formData.append('note', payload.note);
  if (payload.feedback_file) formData.append('feedback_file', payload.feedback_file);

  return formData;
}
