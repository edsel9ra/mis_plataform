<?php

namespace App\Services\Progress;

use App\Models\MentorshipRelationship;
use App\Models\User;
use App\Policies\ObjectivePolicy;
use App\Services\PlanEntitlementService;
use Illuminate\Support\Facades\Log;

/**
 * §9.5 — server-side panel/collective authorization (RF-9, RF-12, P3).
 *
 * The UI never decides access (CA-12.3). A mentor without an active
 * relationship gets a 404 so another mentor's mentees are not disclosed
 * (DT-11, CA-9.3); a member of a collective he cannot access gets a 403.
 */
class ProgressAccessService
{
    public function __construct(
        private PlanEntitlementService $entitlements,
        private ObjectivePolicy $policy,
    ) {}

    /**
     * @return array{role: string, relationship: ?MentorshipRelationship}
     */
    public function assertUserPanel(User $viewer, User $subject): array
    {
        if ($subject->id === $viewer->id) {
            return ['role' => 'self', 'relationship' => null];
        }

        if ($this->policy->isSupport($viewer)) {
            $this->audit($viewer, $subject->id);

            return ['role' => 'support', 'relationship' => null];
        }

        // Admins only act with the explicit support permission (D11, RF-12).
        if ($viewer->isAdmin()) {
            $this->denied($viewer, $subject->id, 'admin_without_permission');
            abort(403, __('progress.messages.access_denied'));
        }

        if ($viewer->isMentor()) {
            $relationship = MentorshipRelationship::query()
                ->where('mentor_id', $viewer->id)
                ->whereIn('source_type', ['user', User::class])
                ->where('source_id', $subject->id)
                ->where('status', 'active')
                ->latest('started_at')
                ->first();

            if ($relationship === null) {
                $this->denied($viewer, $subject->id, 'mentee_not_assigned');
                abort(404);
            }

            $access = $this->entitlements->forModel($subject, 'personal');

            if (! $this->entitlements->allows($access['plan'], PlanEntitlementService::MENTOR_WORKSPACE)) {
                $this->denied($viewer, $subject->id, 'workspace_locked');
                abort(403, __('progress.messages.workspace_locked'));
            }

            return ['role' => 'mentor', 'relationship' => $relationship];
        }

        $this->denied($viewer, $subject->id, 'not_authorized');
        abort(404);
    }

    /**
     * @return array{role: string}
     */
    public function assertCollective(User $viewer, string $type, string $id): array
    {
        if ($this->policy->isSupport($viewer)) {
            $this->audit($viewer, $id);

            return ['role' => 'support'];
        }

        // Admins only act with the explicit support permission (D11, RF-12).
        if ($viewer->isAdmin()) {
            $this->denied($viewer, $id, 'admin_without_permission');
            abort(403, __('progress.messages.access_denied'));
        }

        if (MentorshipRelationship::userCanManageSource($viewer, $type, $id)) {
            return ['role' => 'manager'];
        }

        if (MentorshipRelationship::userCanAccessSource($viewer, $type, $id)) {
            return ['role' => 'member'];
        }

        $this->denied($viewer, $id, 'not_a_member');
        abort(403, __('progress.messages.access_denied'));
    }

    /**
     * Support access is auditable without storing personal content (P5, RNF-8).
     */
    private function audit(User $viewer, string $subjectId): void
    {
        Log::info('progress.support.view', [
            'user_id' => $viewer->id,
            'subject_id' => $subjectId,
        ]);
    }

    /**
     * Structured denial record: identifiers and a reason, never panel content
     * nor personal data (P20, RNF-8).
     */
    private function denied(User $viewer, string $subjectId, string $reason): void
    {
        Log::warning('progress.access_denied', [
            'user_id' => $viewer->id,
            'subject_id' => $subjectId,
            'reason' => $reason,
        ]);
    }
}
