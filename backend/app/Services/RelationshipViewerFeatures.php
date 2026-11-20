<?php

namespace App\Services;

use App\Models\MentorshipRelationship;
use App\Models\Plan;
use App\Models\User;

class RelationshipViewerFeatures
{
    /** @var array<string, array{plan: ?Plan, status: string, locked: bool, lock_reason: ?string, entitlements: array<string, mixed>}> */
    private array $accessByViewer = [];

    public function __construct(private PlanEntitlementService $entitlements) {}

    /**
     * Per-viewer feature flags for a relationship payload. `emotional_check_ins`
     * mirrors exactly the gate used by the check-in endpoints, so `true` means
     * those endpoints answer 200 for this viewer.
     *
     * @return array{emotional_check_ins: bool}
     */
    public function for(User $viewer, MentorshipRelationship $relationship): array
    {
        $allowed = !$viewer->isMentor()
            && $relationship->status === 'active'
            && $relationship->type === 'personal'
            && MentorshipRelationship::canonicalSourceType($relationship->source_type) === 'user'
            && $relationship->source_id === $viewer->id
            && $this->entitlements->allowsAccess(
                $this->accessFor($viewer),
                PlanEntitlementService::EMOTIONAL_CHECK_INS,
            );

        return ['emotional_check_ins' => $allowed];
    }

    /**
     * @return array{plan: ?Plan, status: string, locked: bool, lock_reason: ?string, entitlements: array<string, mixed>}
     */
    private function accessFor(User $viewer): array
    {
        return $this->accessByViewer[$viewer->id] ??= $this->entitlements->forModel($viewer, 'personal');
    }
}
