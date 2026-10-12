import type {
  ActiveSubscription,
  MentorWorkspaceFeature,
  PersonalPlanTier,
  WorkspaceEntitlements,
  WorkspacePlan,
} from '@/types/mentor-workspace';

/** The API paths used by the confirmed mentor workspace. */
export const MENTOR_WORKSPACE_ENDPOINTS = {
  users: '/mentor/users',
  profile: (userId: string) => `/mentor/users/${userId}/profile`,
  neurocognitive: (userId: string) => `/mentor/users/${userId}/neurocognitive`,
  systemicMap: (userId: string) => `/mentor/users/${userId}/systemic-map`,
  userChallenges: (userId: string) => `/mentor/users/${userId}/challenges`,
  challenges: '/mentor/challenges',
  challenge: (challengeId: string) => `/mentor/challenges/${challengeId}`,
  feedback: '/mentor/feedback',
  emotionalCheckIns: (userId: string) => `/mentor/users/${userId}/emotional-check-ins`,
  alerts: (userId: string) => `/mentor/users/${userId}/alerts`,
} as const;

export const OCEAN_IMPROVEMENT_THRESHOLD = 51;

export function isOceanScoreBelowThreshold(score: number): boolean {
  return score < OCEAN_IMPROVEMENT_THRESHOLD;
}

export function unwrapData<T>(response: T | { data: T }): T {
  if (response && typeof response === 'object' && 'data' in response) {
    return (response as { data: T }).data;
  }

  return response as T;
}

export function unwrapCollection<T>(response: T[] | { data?: T[] }): T[] {
  if (Array.isArray(response)) return response;

  return Array.isArray(response.data) ? response.data : [];
}

export interface CollectionMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
}

export type CollectionResponse<T> = T[] | { data?: T[]; meta?: CollectionMeta };

export async function fetchAllCollection<T>(
  fetchPage: (page: number) => Promise<CollectionResponse<T>>,
): Promise<T[]> {
  const firstPage = await fetchPage(1);
  if (Array.isArray(firstPage)) return firstPage;

  const items = firstPage.data ?? [];
  const lastPage = Math.max(1, firstPage.meta?.last_page ?? 1);
  if (lastPage === 1) return items;

  const remainingPages = await Promise.all(
    Array.from({ length: lastPage - 1 }, (_, index) => fetchPage(index + 2)),
  );

  return items.concat(
    ...remainingPages.map((page) => (Array.isArray(page) ? page : page.data ?? [])),
  );
}

export function getPlanTier(plan: WorkspacePlan | null | undefined): PersonalPlanTier {
  if (!plan) return 'unknown';

  const value = `${plan.id} ${plan.code ?? ''} ${plan.name}`.toLocaleLowerCase();
  if (value.includes('premium')) return 'premium';
  if (value.includes('basic') || value.includes('básic') || value.includes('basico')) return 'basic';

  return 'unknown';
}

export function isActiveSubscription(subscription: ActiveSubscription | null | undefined): boolean {
  if (!subscription || !['active', 'trial'].includes(subscription.status)) return false;

  const now = Date.now();
  if (subscription.ends_at && new Date(subscription.ends_at).getTime() <= now) return false;
  if (subscription.status === 'trial' && subscription.trial_ends_at && new Date(subscription.trial_ends_at).getTime() <= now) {
    return false;
  }

  return true;
}

export function hasEntitledPlan(
  plan: WorkspacePlan | null | undefined,
  subscription?: ActiveSubscription | null,
): boolean {
  if (!plan || plan.client_type !== 'personal' || plan.is_active === false) return false;

  // Assignment payloads already represent an applicable active plan. The
  // subscription check is used for the signed-in user's own dashboard.
  return subscription === undefined || isActiveSubscription(subscription);
}

export function isEntitlementEnabled(value: unknown): boolean {
  if (typeof value === 'boolean') return value;
  if (typeof value === 'number') return Number.isFinite(value) && value > 0;

  if (typeof value !== 'string') return false;

  const normalized = value.trim().toLowerCase();
  if (['true', '1', 'yes', 'on'].includes(normalized)) return true;
  if (['false', '0', 'no', 'off', ''].includes(normalized)) return false;

  return Number.isFinite(Number(normalized)) && Number(normalized) > 0;
}

export function hasEntitlement(
  entitlements: WorkspaceEntitlements | null | undefined,
  ...keys: string[]
): boolean {
  if (!entitlements) return false;

  const key = keys.find((candidate) => Object.prototype.hasOwnProperty.call(entitlements, candidate));
  return key ? isEntitlementEnabled(entitlements[key]) : false;
}

export function canAccessWorkspaceFeature(
  feature: MentorWorkspaceFeature,
  plan: WorkspacePlan | null | undefined,
  entitlements?: WorkspaceEntitlements | null,
  subscription?: ActiveSubscription | null,
): boolean {
  if (!hasEntitledPlan(plan, subscription ?? undefined)) return false;

  const entitlementKeys: Record<MentorWorkspaceFeature, string[]> = {
    ocean: ['ocean'],
    neurocognitive: ['neurocognitive', 'attention_tracking'],
    systemic_map: ['systemic_map', 'systemic_maps'],
    challenges: ['challenges'],
    feedback: ['feedback', 'challenge_feedback'],
  };

  if (entitlements !== undefined && entitlements !== null) {
    const key = entitlementKeys[feature].find((candidate) => Object.prototype.hasOwnProperty.call(entitlements, candidate));
    return key ? isEntitlementEnabled(entitlements[key]) : false;
  }

  return true;
}

export function formatPlanName(plan: WorkspacePlan | null | undefined): string {
  return plan?.name || 'Personal';
}

export function asNumber(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  if (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value))) return Number(value);

  return null;
}
