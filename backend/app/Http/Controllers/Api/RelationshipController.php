<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateRelationshipAction;
use App\Http\Requests\Relationship\StoreRelationshipRequest;
use App\Http\Requests\Relationship\UpdateRelationshipStatusRequest;
use App\Http\Resources\RelationshipResource;
use App\Models\MentorshipRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RelationshipController
{
    public function __construct(
        private CreateRelationshipAction $createRelationship,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $relationships = MentorshipRelationship::forUser($user)
            ->with(['mentor', 'sessions' => function ($q) {
                $q->latest()->limit(5);
            }])
            ->latest()
            ->paginate($perPage);

        return RelationshipResource::collection($relationships)->response();
    }

    public function store(StoreRelationshipRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($validated['mentor_id'] === $request->user()->id) {
            throw ValidationException::withMessages([
                'mentor_id' => [__('relationships.cannot_request_self')],
            ]);
        }

        Gate::authorize('create', [
            MentorshipRelationship::class,
            $validated['source_type'],
            $validated['source_id'],
        ]);

        $relationship = $this->createRelationship->execute($validated);

        return response()->json(
            new RelationshipResource($relationship),
            201,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $relationship = MentorshipRelationship::with([
            'mentor.personalityAssessment',
            'source',
            'sessions' => fn($q) => $q->latest(),
            'messages' => fn($q) => $q->latest()->limit(50),
            'certificates',
        ])->findOrFail($id);

        Gate::authorize('view', $relationship);

        return response()->json(new RelationshipResource($relationship));
    }

    public function updateStatus(UpdateRelationshipStatusRequest $request, string $id): JsonResponse
    {
        $validated = $request->validated();

        $relationship = MentorshipRelationship::findOrFail($id);

        Gate::authorize('updateStatus', $relationship);

        $relationship->update([
            'status' => $validated['status'],
            'started_at' => $validated['status'] === 'active' ? now() : $relationship->started_at,
            'completed_at' => in_array($validated['status'], ['completed', 'canceled']) ? now() : $relationship->completed_at,
        ]);

        return response()->json(new RelationshipResource($relationship->fresh()));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $relationship = MentorshipRelationship::findOrFail($id);

        Gate::authorize('delete', $relationship);

        $relationship->delete();

        return response()->json(['message' => __('relationships.deleted')]);
    }
}
