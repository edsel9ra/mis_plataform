<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateRelationshipAction;
use App\Http\Requests\Relationship\StoreRelationshipRequest;
use App\Http\Resources\RelationshipResource;
use App\Models\MentorshipRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RelationshipController
{
    public function __construct(
        private CreateRelationshipAction $createRelationship,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $relationships = MentorshipRelationship::forUser($user->id)
            ->with(['mentor', 'sessions' => function ($q) {
                $q->latest()->limit(5);
            }])
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json($relationships);
    }

    public function store(StoreRelationshipRequest $request): JsonResponse
    {
        $relationship = $this->createRelationship->execute($request->validated());

        return response()->json(
            new RelationshipResource($relationship),
            201,
        );
    }

    public function show(string $id): JsonResponse
    {
        $relationship = MentorshipRelationship::with([
            'mentor.personalityAssessment',
            'source',
            'sessions' => fn($q) => $q->latest(),
            'messages' => fn($q) => $q->latest()->limit(50),
            'certificates',
        ])->findOrFail($id);

        return response()->json(new RelationshipResource($relationship));
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,paused,completed,canceled'],
        ]);

        $relationship = MentorshipRelationship::findOrFail($id);

        $relationship->update([
            'status' => $validated['status'],
            'started_at' => $validated['status'] === 'active' ? now() : $relationship->started_at,
            'completed_at' => in_array($validated['status'], ['completed', 'canceled']) ? now() : $relationship->completed_at,
        ]);

        return response()->json(new RelationshipResource($relationship->fresh()));
    }

    public function destroy(string $id): JsonResponse
    {
        $relationship = MentorshipRelationship::findOrFail($id);
        $relationship->delete();

        return response()->json(['message' => __('relationships.deleted')]);
    }
}
