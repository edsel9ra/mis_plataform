<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MentorResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorController
{
    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', 'mentor')->where('is_active', true);

        if ($request->has('client_type')) {
            $query->where('client_type', $request->client_type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('skills')) {
            $skills = explode(',', $request->skills);
            $query->whereHas('skills.skillTag', function ($q) use ($skills) {
                $q->whereIn('name', $skills);
            });
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $mentors = $query->with(['skills.skillTag'])
            ->withCount(['mentorRelationships as active_mentees_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->paginate($perPage);

        return response()->json(MentorResource::collection($mentors));
    }

    public function show(string $id): JsonResponse
    {
        $mentor = User::where('role', 'mentor')
            ->with(['skills.skillTag', 'mentorRelationships' => function ($q) {
                $q->whereIn('status', ['active', 'completed']);
            }])
            ->withCount(['mentorRelationships as active_mentees_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->findOrFail($id);

        return response()->json(new MentorResource($mentor));
    }
}
