<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\LearningPath\UpdateLearningProgressRequest;
use App\Models\UserLearningProgress;
use App\Services\ContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningPathController
{
    public function __construct(private ContentAccessService $contentAccess) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $hasEntitledContent = $this->contentAccess->hasEntitledContent($user);
        $query = $this->contentAccess->learningPaths($user);

        if ($request->has('client_type')) {
            $clientType = $request->validate([
                'client_type' => ['nullable', 'string', 'in:personal,familiar,grupal,empresa'],
            ])['client_type'] ?? null;

            if ($clientType !== null) {
                $query->where('client_type', $clientType);
            }
        }

        if ($request->has('personality_type')) {
            $type = $request->validate([
                'personality_type' => ['nullable', 'string', 'max:50'],
            ])['personality_type'] ?? null;

            if ($type !== null) {
                $query->whereJsonContains('personality_tags', $type);
            }
        }

        $paths = $query->with(['modules.resources' => function ($resourceQuery) use ($user, $hasEntitledContent) {
            if (!$user->isAdmin() && !$hasEntitledContent) {
                $resourceQuery->where('is_free', true);
            }
        }])->orderBy('level')->get();

        if ($request->user()) {
            $paths->load(['userProgress' => function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            }]);
        }

        return response()->json($paths);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $hasEntitledContent = $this->contentAccess->hasEntitledContent($user);
        $path = $this->contentAccess->learningPaths($user)
            ->with([
                'modules.resources' => function ($resourceQuery) use ($user, $hasEntitledContent) {
                    if (!$user->isAdmin() && !$hasEntitledContent) {
                        $resourceQuery->where('is_free', true);
                    }
                },
                'userProgress' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->findOrFail($id);

        return response()->json($path);
    }

    public function updateProgress(UpdateLearningProgressRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $path = $this->contentAccess->learningPaths($user)->findOrFail($id);

        $progress = UserLearningProgress::firstOrNew([
            'user_id' => $user->id,
            'learning_path_id' => $path->id,
        ]);
        $progress->fill([
            'current_module_id' => $validated['module_id'] ?? null,
            'progress' => $validated['progress'],
            'status' => $validated['progress'] >= 100 ? 'completed' : 'in_progress',
            'completed_at' => $validated['progress'] >= 100 ? now() : null,
        ]);
        $progress->started_at ??= now();
        $progress->save();

        return response()->json($progress);
    }
}
