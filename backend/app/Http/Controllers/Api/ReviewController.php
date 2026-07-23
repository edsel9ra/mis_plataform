<?php

namespace App\Http\Controllers\Api;

use App\Models\Review;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'exists:sessions,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $session = Session::findOrFail($validated['session_id']);
        abort_unless($session->involvesUser($request->user()), 403);

        $exists = Review::where('session_id', $validated['session_id'])
            ->where('rater_id', $request->user()->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => __('reviews.already_exists')], 422);
        }

        $review = Review::create([
            'session_id' => $validated['session_id'],
            'rater_id' => $request->user()->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json($review, 201);
    }

    public function index(Request $request, string $sessionId): JsonResponse
    {
        $session = Session::findOrFail($sessionId);
        abort_unless($session->involvesUser($request->user()), 403);

        $reviews = Review::where('session_id', $sessionId)
            ->with('rater')
            ->get();

        return response()->json($reviews);
    }
}
