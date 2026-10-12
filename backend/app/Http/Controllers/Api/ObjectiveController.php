<?php

namespace App\Http\Controllers\Api;

use App\Actions\AttachObjectiveEvidenceAction;
use App\Actions\CreateObjectiveAction;
use App\Actions\ProposeObjectiveAction;
use App\Actions\TransitionObjectiveStatusAction;
use App\Actions\UpdateObjectiveAction;
use App\Http\Requests\Objective\ProposeObjectiveRequest;
use App\Http\Requests\Objective\StoreObjectiveEvidenceRequest;
use App\Http\Requests\Objective\StoreObjectiveRequest;
use App\Http\Requests\Objective\TransitionObjectiveStatusRequest;
use App\Http\Requests\Objective\UpdateObjectiveRequest;
use App\Http\Resources\ObjectiveEvidenceResource;
use App\Http\Resources\ObjectiveResource;
use App\Models\MentorshipRelationship;
use App\Models\Objective;
use App\Models\User;
use App\Policies\ObjectivePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ObjectiveController
{
    public function __construct(
        private CreateObjectiveAction $create,
        private ProposeObjectiveAction $propose,
        private UpdateObjectiveAction $update,
        private TransitionObjectiveStatusAction $transition,
        private AttachObjectiveEvidenceAction $attachEvidence,
        private ObjectivePolicy $policy,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $ownerType = (string) $request->input('subject_type', 'user');
        $ownerId = (string) $request->input('subject_id', $user->id);

        $this->assertCanReadSubject($user, $ownerType, $ownerId);

        $objectives = Objective::query()
            ->forOwner($ownerType, $ownerId)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->with(['evidences.user', 'evidences.mediaAsset', 'activities.causer'])
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->paginate(25);

        return ObjectiveResource::collection($objectives);
    }

    public function store(StoreObjectiveRequest $request): JsonResponse
    {
        $objective = $this->create->execute($request->user(), $request->validated());

        return (new ObjectiveResource($this->loadForDisplay($objective)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateObjectiveRequest $request, Objective $objective): ObjectiveResource
    {
        $objective = $this->update->execute($request->user(), $objective, $request->validated());

        return new ObjectiveResource($this->loadForDisplay($objective));
    }

    public function status(TransitionObjectiveStatusRequest $request, Objective $objective): ObjectiveResource
    {
        $objective = $this->transition->execute($request->user(), $objective, $request->validated()['status']);

        return new ObjectiveResource($this->loadForDisplay($objective));
    }

    public function proposals(ProposeObjectiveRequest $request): JsonResponse
    {
        $objective = $this->propose->execute($request->user(), $request->validated());

        return (new ObjectiveResource($this->loadForDisplay($objective)))
            ->response()
            ->setStatusCode(201);
    }

    public function evidences(StoreObjectiveEvidenceRequest $request, Objective $objective): JsonResponse
    {
        $evidence = $this->attachEvidence->execute($request->user(), $objective, $request->validated());

        return (new ObjectiveEvidenceResource($evidence->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    private function assertCanReadSubject(User $user, string $ownerType, string $ownerId): void
    {
        if ($ownerType === 'user' && $ownerId === $user->id) {
            return;
        }

        if ($this->policy->isSupport($user)) {
            return;
        }

        if ($user->isAdmin()) {
            abort(403, __('progress.messages.access_denied'));
        }

        if (in_array($ownerType, ['user', User::class], true)) {
            $isMentor = MentorshipRelationship::query()
                ->where('mentor_id', $user->id)
                ->whereIn('source_type', ['user', User::class])
                ->where('source_id', $ownerId)
                ->where('status', 'active')
                ->exists();

            if ($isMentor) {
                return;
            }

            abort(403, __('progress.messages.access_denied'));
        }

        if (MentorshipRelationship::userCanAccessSource($user, $ownerType, $ownerId)) {
            return;
        }

        abort(403, __('progress.messages.access_denied'));
    }

    private function loadForDisplay(Objective $objective): Objective
    {
        return $objective->load(['evidences.user', 'evidences.mediaAsset', 'activities.causer']);
    }
}
