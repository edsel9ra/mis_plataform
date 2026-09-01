<?php

namespace App\Services;

use App\Models\MentorshipRelationship;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Model;

class PlanEntitlementService
{
    public const MENTOR_WORKSPACE = 'mentor_workspace';
    public const OCEAN = 'ocean';
    public const ATTENTION_TRACKING = 'attention_tracking';
    public const EMOTIONAL_CHECK_INS = 'emotional_check_ins';
    public const CHALLENGES = 'challenges';
    public const CHALLENGE_MEDIA = 'challenge_media';
    public const CHALLENGE_FEEDBACK = 'challenge_feedback';
    public const MENTOR_OBSERVATIONS = 'mentor_observations';
    public const SYSTEMIC_MAPS = 'systemic_maps';

    /**
     * Resolve the plan used by a relationship without relying on display names.
     * Direct context plans win over a subscriber's current plan, matching the
     * existing content access rules.
     *
     * @return array{plan: ?Plan, status: string, locked: bool, lock_reason: ?string, entitlements: array<string, mixed>}
     */
    public function forModel(Model $source, string $clientType): array
    {
        $invalidPlan = null;

        if (array_key_exists('plan_id', $source->getAttributes()) && $source->plan_id) {
            $directPlan = Plan::withTrashed()->find($source->plan_id);

            if ($directPlan
                && !$directPlan->trashed()
                && $directPlan->is_active
                && $directPlan->client_type === $clientType
            ) {
                return $this->activeAccess($directPlan);
            }

            $invalidPlan = $directPlan;
        }

        if (method_exists($source, 'subscriptions')) {
            $subscriptions = $source->subscriptions()
                ->with(['plan' => fn ($query) => $query->withTrashed()])
                ->latest()
                ->get();

            $activeSubscription = $subscriptions->first(function ($subscription) use ($clientType) {
                return $subscription->isActive()
                    && $subscription->plan?->is_active
                    && !$subscription->plan?->trashed()
                    && $subscription->plan?->client_type === $clientType;
            });

            if ($activeSubscription?->plan) {
                return $this->activeAccess($activeSubscription->plan);
            }

            $invalidSubscription = $subscriptions->first(fn ($subscription) => $subscription->plan !== null);
            if ($invalidSubscription?->plan) {
                $invalidPlan ??= $invalidSubscription->plan;
            }
        }

        if ($invalidPlan) {
            $status = $invalidPlan->client_type !== $clientType
                ? 'plan_mismatch'
                : 'inactive_plan';

            return $this->lockedAccess($invalidPlan, $status);
        }

        return $this->lockedAccess(null, 'no_plan');
    }

    /**
     * @return array{plan: ?Plan, status: string, locked: bool, lock_reason: ?string, entitlements: array<string, mixed>}
     */
    public function forRelationship(MentorshipRelationship $relationship): array
    {
        $source = $relationship->relationLoaded('source')
            ? $relationship->source
            : $relationship->source()->first();

        if (!$source) {
            return $this->lockedAccess(null, 'missing_source');
        }

        return $this->forModel($source, $relationship->type);
    }

    public function allows(?Plan $plan, string $key, bool $legacyDefault = false): bool
    {
        if (!$plan) {
            return false;
        }

        $entitlements = $this->entitlementsFor($plan);
        if (!array_key_exists($key, $entitlements)) {
            return $legacyDefault;
        }

        $value = $entitlements[$key];

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** @param array{plan: ?Plan, locked: bool} $access */
    public function allowsAccess(array $access, string $key): bool
    {
        return !$access['locked'] && $this->allows($access['plan'], $key);
    }

    public function mediaEntitlement(string $purpose): ?string
    {
        return match ($purpose) {
            'challenge_content', 'challenge_submission' => self::CHALLENGE_MEDIA,
            'mentor_feedback' => self::CHALLENGE_FEEDBACK,
            default => null,
        };
    }

    public function value(?Plan $plan, string $key, mixed $default = null): mixed
    {
        if (!$plan) {
            return $default;
        }

        return $this->entitlementsFor($plan)[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function entitlementsFor(Plan $plan): array
    {
        $entitlements = $plan->entitlements;
        if (is_string($entitlements)) {
            $entitlements = json_decode($entitlements, true);
        }

        if (!is_array($entitlements)) {
            return [];
        }

        foreach ([
            'neurocognitive' => self::ATTENTION_TRACKING,
            'systemic_map' => self::SYSTEMIC_MAPS,
            'feedback' => self::CHALLENGE_FEEDBACK,
        ] as $alias => $canonical) {
            if (!array_key_exists($alias, $entitlements) && array_key_exists($canonical, $entitlements)) {
                $entitlements[$alias] = $entitlements[$canonical];
            }
        }

        return $entitlements;
    }

    public static function canonicalSourceType(string $sourceType): string
    {
        return MentorshipRelationship::canonicalSourceType($sourceType) ?? $sourceType;
    }

    private function activeAccess(Plan $plan): array
    {
        $workspaceEnabled = !$plan->trashed() && $this->allows($plan, self::MENTOR_WORKSPACE);

        return [
            'plan' => $plan,
            'status' => 'active',
            'locked' => !$workspaceEnabled,
            'lock_reason' => $workspaceEnabled ? null : 'entitlement_missing',
            'entitlements' => $this->entitlementsFor($plan),
        ];
    }

    private function lockedAccess(?Plan $plan, string $status): array
    {
        return [
            'plan' => $plan,
            'status' => $status,
            'locked' => true,
            'lock_reason' => $status,
            'entitlements' => $plan ? $this->entitlementsFor($plan) : [],
        ];
    }

}
