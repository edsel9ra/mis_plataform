<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\FeedbackThread;
use App\Models\MediaAsset;
use App\Models\MentorshipRelationship;
use App\Models\NeurocognitiveAlert;
use App\Models\NeurocognitiveObservation;
use App\Models\SystemicMap;
use App\Models\User;

class MentorWorkspaceAccessService
{
    public function __construct(
        private PlanEntitlementService $entitlements,
    ) {}

    /**
     * A mentor route must always identify the exact relationship and target.
     * Returning 404 for a non-pair avoids disclosing another mentor's mentees.
     *
     * @return array{relationship: MentorshipRelationship, mentee: User, access: array<string, mixed>}
     */
    public function mentorPair(User $mentor, User $mentee, string $relationshipId): array
    {
        abort_unless($mentor->is_active && $mentor->isMentor(), 404);
        abort_unless($mentee->is_active, 404);

        $relationship = MentorshipRelationship::query()
            ->whereKey($relationshipId)
            ->where('mentor_id', $mentor->id)
            ->where('status', 'active')
            ->with('source')
            ->first();

        abort_unless($relationship, 404);
        $this->assertPersonalPair($relationship, $mentee);

        return [
            'relationship' => $relationship,
            'mentee' => $mentee,
            'access' => $this->entitlements->forModel($mentee, 'personal'),
        ];
    }

    /**
     * Resolve a mentor relationship for endpoints that receive only a
     * relationship id (for example, private media uploads).
     *
     * @return array{relationship: MentorshipRelationship, mentee: User, access: array<string, mixed>}
     */
    public function mentorRelationship(User $mentor, string $relationshipId): array
    {
        abort_unless($mentor->is_active && $mentor->isMentor(), 404);

        $relationship = MentorshipRelationship::query()
            ->whereKey($relationshipId)
            ->where('mentor_id', $mentor->id)
            ->where('status', 'active')
            ->with('source')
            ->first();

        abort_unless($relationship, 404);
        $mentee = $relationship->source;
        abort_unless($mentee instanceof User && $mentee->is_active, 404);
        $this->assertPersonalPair($relationship, $mentee);

        return [
            'relationship' => $relationship,
            'mentee' => $mentee,
            'access' => $this->entitlements->forModel($mentee, 'personal'),
        ];
    }

    /** @return array{relationship: MentorshipRelationship, mentee: User, access: array<string, mixed>} */
    public function menteeRelationship(User $mentee, string $relationshipId): array
    {
        $relationship = MentorshipRelationship::query()
            ->whereKey($relationshipId)
            ->where('status', 'active')
            ->with(['mentor', 'source'])
            ->first();

        abort_unless($relationship, 404);
        abort_unless(
            MentorshipRelationship::canonicalSourceType($relationship->source_type) === 'user'
                && $relationship->source_id === $mentee->id,
            404,
        );
        abort_unless($relationship->type === 'personal', 404);

        return [
            'relationship' => $relationship,
            'mentee' => $mentee,
            'access' => $this->entitlements->forModel($mentee, 'personal'),
        ];
    }

    /** @return array{relationship: MentorshipRelationship, mentee: User, access: array<string, mixed>} */
    public function mentorChallenge(User $mentor, Challenge $challenge, ?string $relationshipId = null): array
    {
        $relationship = $challenge->relationLoaded('relationship')
            ? $challenge->relationship
            : $challenge->relationship()->with('source')->first();

        abort_unless($relationship, 404);
        if ($relationshipId !== null) {
            abort_unless($relationship->id === $relationshipId, 404);
        }

        $mentee = $relationship->source;
        abort_unless($mentee instanceof User, 404);
        $authorized = $this->mentorPair($mentor, $mentee, $relationship->id);
        abort_unless($challenge->mentor_id === $mentor->id && $challenge->mentee_id === $mentee->id, 404);

        return $authorized;
    }

    /** @return array{relationship: MentorshipRelationship, mentee: User, access: array<string, mixed>} */
    public function menteeChallenge(User $mentee, Challenge $challenge): array
    {
        $relationship = $challenge->relationLoaded('relationship')
            ? $challenge->relationship
            : $challenge->relationship()->with('source')->first();

        abort_unless($relationship, 404);
        $authorized = $this->menteeRelationship($mentee, $relationship->id);
        abort_unless($challenge->mentee_id === $mentee->id, 404);

        return $authorized;
    }

    /** @return array{relationship: MentorshipRelationship, mentee: User, access: array<string, mixed>} */
    public function mentorSubmission(User $mentor, ChallengeSubmission $submission): array
    {
        $challenge = $submission->relationLoaded('challenge')
            ? $submission->challenge
            : $submission->challenge()->with('relationship.source')->first();

        abort_unless($challenge, 404);
        $authorized = $this->mentorChallenge($mentor, $challenge, $submission->relationship_id);
        abort_unless($submission->mentee_id === $authorized['mentee']->id, 404);

        return $authorized;
    }

    /** @return array{relationship: MentorshipRelationship, mentee: User, access: array<string, mixed>} */
    public function mentorFeedback(User $mentor, FeedbackThread $thread): array
    {
        $relationship = $thread->relationLoaded('relationship')
            ? $thread->relationship
            : $thread->relationship()->with('source')->first();

        abort_unless($relationship, 404);
        $mentee = $relationship->source;
        abort_unless($mentee instanceof User, 404);
        $authorized = $this->mentorPair($mentor, $mentee, $relationship->id);
        abort_unless($thread->mentor_id === $mentor->id && $thread->mentee_id === $mentee->id, 404);

        return $authorized;
    }

    /** @return array{relationship: MentorshipRelationship, mentee: User, access: array<string, mixed>} */
    public function mentorMap(User $mentor, SystemicMap $map): array
    {
        $relationship = $map->relationLoaded('relationship')
            ? $map->relationship
            : $map->relationship()->with('source')->first();

        abort_unless($relationship, 404);
        $mentee = $relationship->source;
        abort_unless($mentee instanceof User, 404);
        $authorized = $this->mentorPair($mentor, $mentee, $relationship->id);
        abort_unless($map->mentor_id === $mentor->id && $map->mentee_id === $mentee->id, 404);

        return $authorized;
    }

    /** @return array{relationship: MentorshipRelationship, mentee: User, access: array<string, mixed>} */
    public function mentorAlert(User $mentor, NeurocognitiveAlert $alert): array
    {
        $authorized = $this->mentorRelationship($mentor, $alert->relationship_id);
        abort_unless($alert->mentor_id === $mentor->id && $alert->mentee_id === $authorized['mentee']->id, 404);

        return $authorized;
    }

    public function mentorMedia(User $mentor, MediaAsset $asset): array
    {
        $authorized = $this->mentorRelationship($mentor, $asset->relationship_id);
        abort_unless($asset->uploaded_by === $mentor->id || $asset->relationship_id === $authorized['relationship']->id, 404);

        return $authorized;
    }

    public function requireEntitlement(array $authorization, string $key): void
    {
        $access = $authorization['access'];

        abort_unless(
            !$access['locked'] && $this->entitlements->allows($access['plan'], $key),
            403,
        );
    }

    public function allowsEntitlement(array $authorization, string $key): bool
    {
        return !$authorization['access']['locked']
            && $this->entitlements->allows($authorization['access']['plan'], $key);
    }

    private function assertPersonalPair(MentorshipRelationship $relationship, User $mentee): void
    {
        abort_unless($relationship->type === 'personal', 404);
        abort_unless(MentorshipRelationship::canonicalSourceType($relationship->source_type) === 'user', 404);
        abort_unless($relationship->source_id === $mentee->id, 404);
        abort_unless($relationship->source instanceof User && $relationship->source->id === $mentee->id, 404);
    }
}
