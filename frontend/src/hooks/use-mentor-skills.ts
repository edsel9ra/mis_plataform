import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import {
  unwrapCollection,
  type CollectionResponse,
  type MentorSkillInput,
  type SkillTag,
  type SkillTagPayload,
} from '@/lib/mentor-skills';

const skillTagsKey = ['skill-tags'] as const;
const adminSkillTagsKey = ['admin-skill-tags'] as const;

export function useSkillTags(enabled = true) {
  return useQuery({
    queryKey: skillTagsKey,
    queryFn: async ({ signal }) => {
      const response = await api.get<CollectionResponse<SkillTag>>('/skill-tags', undefined, { signal });

      return unwrapCollection(response);
    },
    enabled,
    staleTime: 5 * 60_000,
  });
}

export function useAdminSkillTags(enabled = true) {
  return useQuery({
    queryKey: adminSkillTagsKey,
    queryFn: async ({ signal }) => {
      const response = await api.get<CollectionResponse<SkillTag>>('/admin/skill-tags', undefined, { signal });

      return unwrapCollection(response);
    },
    enabled,
    staleTime: 60_000,
  });
}

export function useUpdateOwnMentorSkills() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (skills: MentorSkillInput[]) => api.put('/mentor/skills', { skills }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['profile'] });
      void queryClient.invalidateQueries({ queryKey: ['mentors'] });
    },
  });
}

export function useUpdateMentorSkills(mentorId: string | null) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (skills: MentorSkillInput[]) => {
      if (!mentorId) throw new Error('A mentor is required');

      return api.put(`/admin/mentors/${mentorId}/skills`, { skills });
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-mentors'] });
      if (mentorId) void queryClient.invalidateQueries({ queryKey: ['mentor', mentorId] });
      void queryClient.invalidateQueries({ queryKey: ['mentors'] });
    },
  });
}

export function useCreateSkillTag() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SkillTagPayload) => api.post<SkillTag>('/admin/skill-tags', payload),
    onSuccess: () => invalidateSkillTagQueries(queryClient),
  });
}

export function useUpdateSkillTag() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Partial<SkillTagPayload> & { is_active?: boolean } }) =>
      api.put<SkillTag>(`/admin/skill-tags/${id}`, payload),
    onSuccess: () => invalidateSkillTagQueries(queryClient),
  });
}

export function useArchiveSkillTag() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => api.delete(`/admin/skill-tags/${id}`),
    onSuccess: () => invalidateSkillTagQueries(queryClient),
  });
}

function invalidateSkillTagQueries(queryClient: ReturnType<typeof useQueryClient>) {
  void queryClient.invalidateQueries({ queryKey: skillTagsKey });
  void queryClient.invalidateQueries({ queryKey: adminSkillTagsKey });
}
