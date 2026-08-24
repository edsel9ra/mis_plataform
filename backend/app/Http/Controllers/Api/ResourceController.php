<?php

namespace App\Http\Controllers\Api;

use App\Models\LearningResource;
use App\Services\ContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController
{
    public function __construct(private ContentAccessService $contentAccess) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->contentAccess->resources($request->user())
            ->with('module.learningPath');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('module_id')) {
            $query->where('module_id', $request->module_id);
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $resources = $query->orderBy('order')->paginate($perPage);

        return response()->json($resources);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $resource = $this->contentAccess->resources($request->user())
            ->with('module.learningPath')
            ->findOrFail($id);

        return response()->json($resource);
    }
}
