<?php

namespace App\Http\Controllers\Api;

use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController
{
    public function index(Request $request): JsonResponse
    {
        $query = Plan::where('is_active', true);

        if ($request->has('client_type')) {
            $query->where('client_type', $request->client_type);
        }

        $plans = $query->orderBy('price_monthly')->get();

        return response()->json($plans);
    }

    public function show(string $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        return response()->json($plan);
    }
}
