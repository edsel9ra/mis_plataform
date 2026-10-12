<?php

namespace App\Actions;

use App\Enums\ObjectiveScope;
use App\Models\MentorshipRelationship;
use App\Models\Objective;
use App\Models\User;
use App\Policies\ObjectivePolicy;
use Illuminate\Auth\Access\AuthorizationException;

class CreateObjectiveAction
{
    public function __construct(
        private ObjectivePolicy $policy,
    ) {}

    /**
     * Create an objective owned by a titular (individual) or a collective.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): Objective
    {
        $scope = ($data['scope'] ?? ObjectiveScope::Individual->value) === ObjectiveScope::Collective->value
            ? ObjectiveScope::Collective
            : ObjectiveScope::Individual;

        $ownerType = $scope === ObjectiveScope::Collective
            ? (string) ($data['owner_type'] ?? '')
            : 'user';

        $ownerId = (string) ($data['owner_id'] ?? '');

        $relationship = $this->resolveRelationship($ownerType, $ownerId, $data['relationship_id'] ?? null);

        $allowed = $scope === ObjectiveScope::Collective
            ? $this->policy->createCollective($actor, $ownerType, $ownerId)
            : $this->policy->createIndividual($actor, $ownerId, $relationship);

        if (! $allowed) {
            throw new AuthorizationException(__('progress.messages.access_denied'));
        }

        return Objective::create([
            'scope' => $scope->value,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'relationship_id' => $relationship?->id,
            'created_by' => $actor->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'success_criteria' => $data['success_criteria'],
            'status' => 'not_started',
            'due_at' => $data['due_at'] ?? null,
        ]);
    }

    private function resolveRelationship(string $ownerType, string $ownerId, ?string $relationshipId): ?MentorshipRelationship
    {
        if ($relationshipId !== null) {
            return MentorshipRelationship::find($relationshipId);
        }

        if ($ownerType !== 'user') {
            return null;
        }

        return MentorshipRelationship::query()
            ->whereIn('source_type', ['user', User::class])
            ->where('source_id', $ownerId)
            ->where('status', 'active')
            ->latest('started_at')
            ->first();
    }
}
