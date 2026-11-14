<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MentorResource;
use App\Support\LikePattern;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorController
{
    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', 'mentor')->where('is_active', true);

        if ($request->filled('client_type')) {
            $query->where('client_type', $request->client_type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', LikePattern::contains($search))
                  ->orWhere('last_name', 'ilike', LikePattern::contains($search));
            });
        }

        if ($request->has('skills')) {
            $skills = array_values(array_filter(array_map('trim', explode(',', $request->skills))));

            if ($skills !== []) {
                $query->whereHas('skills', function ($q) use ($skills) {
                    $q->whereHas('skillTag', function ($q) use ($skills) {
                        $q->where('is_active', true)->whereIn('name', $skills);
                    });
                });
            }
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $mentors = $query->with(['skills' => function ($q) {
                $q->whereHas('skillTag', fn ($q) => $q->where('is_active', true))
                    ->with('skillTag');
            }])
            ->withCount(['mentorRelationships as active_mentees_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->paginate($perPage);

        return MentorResource::collection($mentors)->response();
    }

    public function show(string $id): JsonResponse
    {
        $mentor = User::where('role', 'mentor')
            ->where('is_active', true)
            ->with(['skills' => function ($q) {
                $q->whereHas('skillTag', fn ($q) => $q->where('is_active', true))
                    ->with('skillTag');
            }, 'mentorRelationships' => function ($q) {
                $q->whereIn('status', ['active', 'completed']);
            }])
            ->withCount(['mentorRelationships as active_mentees_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->findOrFail($id);

        return response()->json(new MentorResource($mentor));
    }
}
