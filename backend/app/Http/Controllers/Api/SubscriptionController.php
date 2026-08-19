<?php

namespace App\Http\Controllers\Api;

use App\Actions\StartTrialSubscriptionAction;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController
{
    public function store(
        StoreSubscriptionRequest $request,
        StartTrialSubscriptionAction $startTrialSubscription,
    ): JsonResponse
    {
        $validated = $request->validated();
        $subscription = $startTrialSubscription->execute($request->user(), $validated['plan_id']);

        return response()->json(new SubscriptionResource($subscription), 201);
    }

    public function active(Request $request): JsonResponse
    {
        $subscription = Subscription::where('subscriber_type', 'user')
            ->where('subscriber_id', $request->user()->id)
            ->current()
            ->with('plan')
            ->first();

        return response()->json($subscription ? new SubscriptionResource($subscription) : null);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);

        abort_unless(
            $request->user()->isAdmin()
                || ($subscription->subscriber_type === 'user' && $subscription->subscriber_id === $request->user()->id),
            403,
        );

        $subscription->update(['status' => 'canceled', 'ends_at' => now()]);

        return response()->json(new SubscriptionResource($subscription->load('plan')));
    }
}
