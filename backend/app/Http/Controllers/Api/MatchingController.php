<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MatchingController
{
    public function suggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_type' => ['required', 'string', 'in:personal,familiar,grupal,corporate'],
            'context_id' => ['required', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();

        try {
            $response = Http::timeout(30)->post(
                config('services.matching.url') . '/api/v1/matching/suggestions',
                [
                    'user_id' => $user->id,
                    'client_type' => $validated['client_type'],
                    'context_id' => $validated['context_id'],
                    'limit' => $validated['limit'] ?? 10,
                ]
            );

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json([
                'message' => __('matching.service_unavailable'),
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mentor_id' => ['required', 'exists:users,id'],
            'client_type' => ['required', 'string', 'in:personal,familiar,grupal,corporate'],
            'context_id' => ['required', 'string'],
        ]);

        $user = $request->user();

        try {
            $response = Http::timeout(30)->post(
                config('services.matching.url') . '/api/v1/matching/calculate',
                [
                    'user_id' => $user->id,
                    'mentor_id' => $validated['mentor_id'],
                    'client_type' => $validated['client_type'],
                    'context_id' => $validated['context_id'],
                ]
            );

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json([
                'message' => __('matching.service_unavailable'),
                'error' => $e->getMessage(),
            ], 503);
        }
    }
}
