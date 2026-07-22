<?php

namespace App\Http\Controllers\Api;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'payment_provider' => ['nullable', 'string', 'in:stripe,mercadopago'],
            'payment_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($validated['plan_id']);

        $activeSubscription = Subscription::where('subscriber_type', 'user')
            ->where('subscriber_id', $user->id)
            ->whereIn('status', ['trial', 'active'])
            ->first();

        if ($activeSubscription) {
            throw ValidationException::withMessages([
                'subscription' => [__('subscriptions.active_exists')],
            ]);
        }

        $subscription = Subscription::create([
            'subscriber_type' => 'user',
            'subscriber_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'payment_provider' => $validated['payment_provider'] ?? null,
            'payment_id' => $validated['payment_id'] ?? null,
        ]);

        $subscription->load('plan');

        return response()->json($subscription, 201);
    }

    public function active(Request $request): JsonResponse
    {
        $subscription = Subscription::where('subscriber_type', 'user')
            ->where('subscriber_id', $request->user()->id)
            ->whereIn('status', ['trial', 'active'])
            ->with('plan')
            ->first();

        return response()->json($subscription);
    }

    public function cancel(string $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->update(['status' => 'canceled', 'ends_at' => now()]);

        return response()->json($subscription);
    }
}
