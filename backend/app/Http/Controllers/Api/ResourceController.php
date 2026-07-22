<?php

namespace App\Http\Controllers\Api;

use App\Models\LearningResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController
{
    public function index(Request $request): JsonResponse
    {
        $query = LearningResource::with('module.learningPath');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('module_id')) {
            $query->where('module_id', $request->module_id);
        }

        $resources = $query->orderBy('order')->paginate($request->per_page ?? 20);

        return response()->json($resources);
    }

    public function show(string $id): JsonResponse
    {
        $resource = LearningResource::with('module.learningPath')->findOrFail($id);

        return response()->json($resource);
    }
}
