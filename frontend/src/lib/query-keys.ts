export const queryKeys = {
  profile: ['profile'] as const,
  recommendations: ['recommendations'] as const,
  sessionsRoot: ['sessions'] as const,
  sessions: (page: number, perPage: number) => ['sessions', { page, per_page: perPage }] as const,
  relationshipsRoot: ['relationships'] as const,
  relationships: (page: number, perPage: number) => ['relationships', { page, per_page: perPage }] as const,
  mentorRecommendationsRoot: ['mentor', 'users'] as const,
  mentorRecommendations: (userId: string, relationshipId: string) => [
    'mentor',
    'users',
    userId,
    'recommendations',
    relationshipId,
  ] as const,
} as const;
