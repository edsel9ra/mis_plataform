<?php

namespace App\Actions;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartTrialSubscriptionAction
{
    public function execute(User $subscriber, string $planId): Subscription
    {
        try {
            return DB::transaction(function () use ($subscriber, $planId) {
                $subscriber = User::query()->lockForUpdate()->findOrFail($subscriber->id);
                $plan = Plan::query()
                    ->whereKey($planId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (!$plan) {
                    throw ValidationException::withMessages([
                        'plan_id' => [__('subscriptions.plan_unavailable')],
                    ]);
                }

                if ($plan->client_type !== $subscriber->client_type) {
                    throw ValidationException::withMessages([
                        'plan_id' => [__('subscriptions.plan_client_type')],
                    ]);
                }

                $subscriptions = Subscription::query()
                    ->where('subscriber_type', 'user')
                    ->where('subscriber_id', $subscriber->id);

                if ((clone $subscriptions)->whereNotNull('trial_ends_at')->exists()) {
                    throw ValidationException::withMessages([
                        'subscription' => [__('subscriptions.trial_already_used')],
                    ]);
                }

                if ((clone $subscriptions)->current()->exists()) {
                    throw ValidationException::withMessages([
                        'subscription' => [__('subscriptions.active_exists')],
                    ]);
                }

                $now = now();

                (clone $subscriptions)
                    ->whereIn('status', ['trial', 'active'])
                    ->where(function ($query) use ($now) {
                        $query->where('ends_at', '<=', $now)
                            ->orWhere(function ($query) use ($now) {
                                $query->where('status', 'trial')
                                    ->where('trial_ends_at', '<=', $now);
                            });
                    })
                    ->update(['status' => 'expired']);

                return Subscription::create([
                    'subscriber_type' => 'user',
                    'subscriber_id' => $subscriber->id,
                    'plan_id' => $plan->id,
                    'status' => 'trial',
                    'trial_ends_at' => $now->copy()->addDays(14),
                    'starts_at' => $now,
                    'ends_at' => $now->copy()->addDays(14),
                ])->load('plan');
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? $exception->getCode()) === '23505') {
                throw ValidationException::withMessages([
                    'subscription' => [__('subscriptions.active_exists')],
                ]);
            }

            throw $exception;
        }
    }
}
