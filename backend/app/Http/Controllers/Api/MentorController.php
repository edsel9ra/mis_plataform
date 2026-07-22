<?php

namespace App\Http\Controllers\Api;

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

        $mentors = $query->with(['skills.skillTag', 'personalityAssessment'])
            ->paginate($request->per_page ?? 20);

        return response()->json($mentors);
    }

    public function show(string $id): JsonResponse
    {
        $mentor = User::where('role', 'mentor')
            ->with(['skills.skillTag', 'personalityAssessment', 'mentorRelationships' => function ($q) {
                $q->whereIn('status', ['active', 'completed']);
            }])
            ->findOrFail($id);

        return response()->json($mentor);
    }
}
