import { useMutation, useQuery, useQueryClient, type QueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { unwrapCollection, unwrapData } from '@/lib/mentor-workspace';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { PROGRESS_ENDPOINTS } from '@/lib/progress';
import { queryKeys } from '@/lib/query-keys';
import type {
  ChallengePathModuleLink,
  CollectiveProgress,
  CollectiveSubjectType,
  LearningMapPath,
  LearningPathProgress,
  MenteeProgressFlag,
  Objective,
  ObjectiveEvidencePayload,
  ObjectivePayload,
  ObjectiveProposalPayload,
  ObjectiveStatus,
  ObjectiveUpdatePayload,
  ProgressPanel,
} from '@/types/progress';

/**
 * React Query hooks for the progress panel and objectives (spec 001, T-095).
 *
 * All calls go through `api`, which already sends `credentials: 'include'`
 * (HttpOnly Sanctum cookie). Mutations invalidate the related progress and
 * objective keys so every block refreshes consistently.
 */

export interface UseObjectivesOptions {
  subjectType?: CollectiveSubjectType | 'user';
  status?: ObjectiveStatus | 'all';
  page?: number;
  perPage?: number;
  enabled?: boolean;
}

function invalidateProgress(queryClient: QueryClient, objectiveId?: string): void {
  void queryClient.invalidateQueries({ queryKey: queryKeys.progressRoot });
  void queryClient.invalidateQueries({ queryKey: queryKeys.objectivesRoot });

  if (objectiveId) {
    void queryClient.invalidateQueries({ queryKey: queryKeys.objective(objectiveId) });
  }
}

export function useProgressPanel(subjectId?: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.progressPanel(subjectId),
    queryFn: async ({ signal }) => {
      const response = await api.get<ProgressPanel | { data: ProgressPanel }>(
        PROGRESS_ENDPOINTS.panel,
        subjectId ? { subject_id: subjectId } : undefined,
        { signal },
      );

      return unwrapData<ProgressPanel>(response);
    },
    enabled,
    staleTime: 30_000,
  });
}

export function useCollectiveProgress(type: CollectiveSubjectType, id: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.collectiveProgress(type, id),
    queryFn: async ({ signal }) => {
      const response = await api.get<CollectiveProgress | { data: CollectiveProgress }>(
        PROGRESS_ENDPOINTS.collective(type, id),
        undefined,
        { signal },
      );

      return unwrapData<CollectiveProgress>(response);
    },
    enabled: enabled && Boolean(type && id),
    staleTime: 30_000,
  });
}

export function useObjectives(subjectId: string, options: UseObjectivesOptions = {}) {
  const { subjectType = 'user', status = 'all', page = 1, perPage = 25, enabled = true } = options;

  return useQuery({
    queryKey: queryKeys.objectives(subjectType, subjectId, status),
    queryFn: async ({ signal }): Promise<PaginatedResponse<Objective>> => {
      const response = await api.get<RawPaginatedResponse<Objective>>(
        PROGRESS_ENDPOINTS.objectives,
        {
          subject_type: subjectType,
          subject_id: subjectId,
          status: status === 'all' ? undefined : status,
          page,
          per_page: perPage,
        },
        { signal },
      );

      return normalizePaginatedResponse(response, { page, perPage });
    },
    enabled: enabled && Boolean(subjectId),
  });
}

export function useMentorProgressOverview(enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorProgressOverview,
    queryFn: async ({ signal }) => {
      return unwrapCollection<MenteeProgressFlag>(
        await api.get<MenteeProgressFlag[] | { data?: MenteeProgressFlag[] }>(
          PROGRESS_ENDPOINTS.mentorPortfolio,
          undefined,
          { signal },
        ),
      );
    },
    enabled,
    retry: false,
  });
}

export function useMentorProgressPanel(userId: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorProgressPanel(userId),
    queryFn: async ({ signal }) => {
      const response = await api.get<ProgressPanel | { data: ProgressPanel }>(
        PROGRESS_ENDPOINTS.mentorPanel(userId),
        undefined,
        { signal },
      );

      return unwrapData<ProgressPanel>(response);
    },
    enabled: enabled && Boolean(userId),
    retry: false,
  });
}

// ---------------------------------------------------------------------------
// Learning-path progress & challenge-module link (spec 012)
// ---------------------------------------------------------------------------

export function useLearningPathProgress(enabled = true) {
  return useQuery({
    queryKey: queryKeys.progressLearningPaths(),
    queryFn: async ({ signal }) => {
      const response = await api.get<{ data: LearningPathProgress[] }>(
        PROGRESS_ENDPOINTS.learningPaths,
        undefined,
        { signal },
      );

      return unwrapCollection<LearningPathProgress>(response);
    },
    enabled,
    staleTime: 30_000,
    retry: false,
  });
}

export function useMentorLearningPathProgress(userId: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorLearningPaths(userId),
    queryFn: async ({ signal }) => {
      const response = await api.get<{ data: LearningPathProgress[] }>(
        PROGRESS_ENDPOINTS.mentorLearningPaths(userId),
        undefined,
        { signal },
      );

      return unwrapCollection<LearningPathProgress>(response);
    },
    enabled: enabled && Boolean(userId),
    staleTime: 30_000,
    retry: false,
  });
}

// ---------------------------------------------------------------------------
// Learning map (spec 013)
// ---------------------------------------------------------------------------

export function useLearningMap(enabled = true) {
  return useQuery({
    queryKey: queryKeys.progressMap(),
    queryFn: async ({ signal }) => {
      const response = await api.get<{ data: LearningMapPath[] }>(
        PROGRESS_ENDPOINTS.map,
        undefined,
        { signal },
      );

      return unwrapCollection<LearningMapPath>(response);
    },
    enabled,
    staleTime: 30_000,
    retry: false,
  });
}

export function useMentorLearningMap(userId: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.mentorMap(userId),
    queryFn: async ({ signal }) => {
      const response = await api.get<{ data: LearningMapPath[] }>(
        PROGRESS_ENDPOINTS.mentorMap(userId),
        undefined,
        { signal },
      );

      return unwrapCollection<LearningMapPath>(response);
    },
    enabled: enabled && Boolean(userId),
    staleTime: 30_000,
    retry: false,
  });
}

export function useSetChallengePathModule(challengeId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (pathModuleId: string | null) => api.put<ChallengePathModuleLink>(
      PROGRESS_ENDPOINTS.challengePathModule(challengeId),
      { path_module_id: pathModuleId },
    ),
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.progressRoot });
      void queryClient.invalidateQueries({ queryKey: queryKeys.mentorChallenges });
      void queryClient.invalidateQueries({ queryKey: ['mentor-workspace', 'challenges'] });
      void queryClient.invalidateQueries({ queryKey: ['mentor', 'progress'] });
    },
  });
}

export function useAdminProgressPanel(userId: string, enabled = true) {
  return useQuery({
    queryKey: queryKeys.adminProgressPanel(userId),
    queryFn: async ({ signal }) => {
      const response = await api.get<ProgressPanel | { data: ProgressPanel }>(
        PROGRESS_ENDPOINTS.adminPanel,
        { user_id: userId },
        { signal },
      );

      return unwrapData<ProgressPanel>(response);
    },
    enabled: enabled && Boolean(userId),
    retry: false,
  });
}

export function useCreateObjective() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: ObjectivePayload) =>
      unwrapData<Objective>(await api.post<Objective | { data: Objective }>(PROGRESS_ENDPOINTS.objectives, payload)),
    onSuccess: () => invalidateProgress(queryClient),
  });
}

export function useProposeObjective() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: ObjectiveProposalPayload) =>
      unwrapData<Objective>(
        await api.post<Objective | { data: Objective }>(PROGRESS_ENDPOINTS.objectiveProposals, payload),
      ),
    onSuccess: () => invalidateProgress(queryClient),
  });
}

export function useUpdateObjective() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, payload }: { id: string; payload: ObjectiveUpdatePayload }) =>
      unwrapData<Objective>(
        await api.put<Objective | { data: Objective }>(PROGRESS_ENDPOINTS.objective(id), payload),
      ),
    onSuccess: (_objective, variables) => invalidateProgress(queryClient, variables.id),
  });
}

export function useTransitionObjectiveStatus() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, status }: { id: string; status: ObjectiveStatus }) =>
      unwrapData<Objective>(
        await api.post<Objective | { data: Objective }>(PROGRESS_ENDPOINTS.objectiveStatus(id), { status }),
      ),
    onSuccess: (_objective, variables) => invalidateProgress(queryClient, variables.id),
  });
}

export function useAddObjectiveEvidence() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, payload }: { id: string; payload: ObjectiveEvidencePayload }) =>
      api.post(PROGRESS_ENDPOINTS.objectiveEvidences(id), payload),
    onSuccess: (_evidence, variables) => invalidateProgress(queryClient, variables.id),
  });
}
