<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController
{
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $session = Session::findOrFail($validated['session_id']);
        abort_unless($session->involvesUser($request->user()), 403);

        if ($session->status !== 'completed') {
            return response()->json(['message' => __('reviews.session_not_completed')], 422);
        }

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

        return response()->json(new ReviewResource($review), 201);
    }

    public function index(Request $request, string $sessionId): JsonResponse
    {
        $session = Session::findOrFail($sessionId);
        abort_unless($session->involvesUser($request->user()), 403);

        $reviews = Review::where('session_id', $sessionId)
            ->with('rater')
            ->get();

        return ReviewResource::collection($reviews)->response();
    }
}
