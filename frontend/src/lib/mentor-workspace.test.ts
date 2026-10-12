import { describe, expect, it } from 'vitest';
import {
  canAccessWorkspaceFeature,
  getPlanTier,
  hasEntitledPlan,
  isEntitlementEnabled,
  isActiveSubscription,
  isOceanScoreBelowThreshold,
} from './mentor-workspace';
import { mentorFeedbackSchema } from './schemas/mentor-workspace';
import type { ActiveSubscription, WorkspacePlan } from '../types/mentor-workspace';

const basicPlan: WorkspacePlan = {
  id: 'personal-basic',
  client_type: 'personal',
  name: 'Personal Basic',
};

const premiumPlan: WorkspacePlan = {
  id: 'personal-premium',
  client_type: 'personal',
  name: 'Personal Premium',
};

const activeSubscription: ActiveSubscription = {
  id: 'subscription-1',
  plan_id: basicPlan.id,
  status: 'active',
};

describe('mentor workspace plan access', () => {
  it('recognizes the two personal plan tiers', () => {
    expect(getPlanTier(basicPlan)).toBe('basic');
    expect(getPlanTier(premiumPlan)).toBe('premium');
  });

  it('requires a personal plan and an active subscription when provided', () => {
    expect(hasEntitledPlan(basicPlan)).toBe(true);
    expect(hasEntitledPlan(basicPlan, activeSubscription)).toBe(true);
    expect(hasEntitledPlan(basicPlan, { ...activeSubscription, status: 'canceled' })).toBe(false);
    expect(hasEntitledPlan({ ...basicPlan, client_type: 'empresa' })).toBe(false);
  });

  it('allows explicit backend entitlements to override the default plan gate', () => {
    expect(canAccessWorkspaceFeature('systemic_map', null, { systemic_map: true })).toBe(false);
    expect(canAccessWorkspaceFeature('systemic_map', basicPlan, { systemic_map: true })).toBe(true);
    expect(canAccessWorkspaceFeature('feedback', premiumPlan, { feedback: false })).toBe(false);
    expect(canAccessWorkspaceFeature('neurocognitive', premiumPlan, { attention_tracking: true })).toBe(true);
    expect(canAccessWorkspaceFeature('feedback', premiumPlan, { challenge_feedback: false })).toBe(false);
    expect(canAccessWorkspaceFeature('ocean', basicPlan, {})).toBe(false);
    expect(isActiveSubscription({ ...activeSubscription, status: 'trial', trial_ends_at: '2000-01-01' })).toBe(false);
  });

  it('normalizes boolean-like entitlement values from the API', () => {
    expect(isEntitlementEnabled(1)).toBe(true);
    expect(isEntitlementEnabled('true')).toBe(true);
    expect(isEntitlementEnabled('0')).toBe(false);
    expect(canAccessWorkspaceFeature('feedback', premiumPlan, { challenge_feedback: 'true' })).toBe(true);
    expect(canAccessWorkspaceFeature('feedback', premiumPlan, { feedback: 'false', challenge_feedback: true })).toBe(false);
  });

  it('uses a strict 51-point threshold for low OCEAN scores', () => {
    expect(isOceanScoreBelowThreshold(50.99)).toBe(true);
    expect(isOceanScoreBelowThreshold(51)).toBe(false);
    expect(isOceanScoreBelowThreshold(80)).toBe(false);
  });

  it('allows feedback notes without requiring a media file', () => {
    expect(mentorFeedbackSchema.safeParse({ note: 'Written feedback.' }).success).toBe(true);
    expect(mentorFeedbackSchema.safeParse({}).success).toBe(false);
  });
});
