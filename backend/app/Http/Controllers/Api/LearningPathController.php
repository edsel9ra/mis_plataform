<?php

namespace App\Http\Controllers\Api;

use App\Models\LearningPath;
use App\Models\UserLearningProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningPathController
{
    public function index(Request $request): JsonResponse
    {
        $query = LearningPath::where('is_active', true)->with('modules.resources');

        if ($request->has('client_type')) {
            $query->where('client_type', $request->client_type);
        }

        if ($request->has('personality_type')) {
            $type = $request->personality_type;
            $query->whereJsonContains('personality_tags', $type);
        }

        $paths = $query->orderBy('level')->get();

        if ($request->user()) {
            $paths->load(['userProgress' => function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            }]);
        }

        return response()->json($paths);
    }

    public function show(string $id): JsonResponse
    {
        $path = LearningPath::with(['modules.resources', 'userProgress' => function ($q) {
            $q->where('user_id', request()->user()?->id);
        }])->findOrFail($id);

        return response()->json($path);
    }

    public function updateProgress(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'progress' => ['required', 'numeric', 'between:0,100'],
            'module_id' => ['nullable', 'exists:path_modules,id'],
        ]);

        $user = $request->user();

        $progress = UserLearningProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'learning_path_id' => $id,
            ],
            [
                'current_module_id' => $validated['module_id'] ?? null,
                'progress' => $validated['progress'],
                'status' => $validated['progress'] >= 100 ? 'completed' : 'in_progress',
                'started_at' => now(),
                'completed_at' => $validated['progress'] >= 100 ? now() : null,
            ]
        );

        return response()->json($progress);
    }
}
