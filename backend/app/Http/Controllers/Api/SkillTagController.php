<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Admin\StoreSkillTagRequest;
use App\Http\Requests\Admin\UpdateSkillTagRequest;
use App\Http\Resources\SkillTagResource;
use App\Models\SkillTag;
use Illuminate\Http\JsonResponse;

class SkillTagController
{
    public function index(): JsonResponse
    {
        $tags = SkillTag::query()
            ->active()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json(SkillTagResource::collection($tags));
    }

    public function adminIndex(): JsonResponse
    {
        $tags = SkillTag::query()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json(SkillTagResource::collection($tags));
    }

    public function store(StoreSkillTagRequest $request): JsonResponse
    {
        $tag = SkillTag::create($request->validated());

        return response()->json(new SkillTagResource($tag), 201);
    }

    public function update(UpdateSkillTagRequest $request, string $id): JsonResponse
    {
        $tag = SkillTag::findOrFail($id);
        $tag->update($request->validated());

        return response()->json(new SkillTagResource($tag->fresh()));
    }

    public function destroy(string $id): JsonResponse
    {
        $tag = SkillTag::findOrFail($id);
        $tag->update(['is_active' => false]);

        return response()->json([
            'message' => __('admin.skill_tag_archived'),
            'skill_tag' => new SkillTagResource($tag->fresh()),
        ]);
    }
}
