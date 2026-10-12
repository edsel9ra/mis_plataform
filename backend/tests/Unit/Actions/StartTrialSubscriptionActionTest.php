<?php

use App\Actions\StartTrialSubscriptionAction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

uses()->group('actions', 'subscriptions');

beforeEach(function () {
    $this->user = User::factory()->create(['client_type' => 'personal']);
    $this->plan = Plan::create([
        'client_type' => 'personal',
        'name' => ['es' => 'Personal'],
        'price_monthly' => 100,
        'price_yearly' => 1000,
        'is_active' => true,
    ]);
});

it('creates a current trial with its plan', function () {
    $subscription = app(StartTrialSubscriptionAction::class)->execute($this->user, $this->plan->id);

    expect($subscription)->toBeInstanceOf(Subscription::class)
        ->and($subscription->relationLoaded('plan'))->toBeTrue()
        ->and($subscription->isActive())->toBeTrue()
        ->and(Subscription::query()->current()->count())->toBe(1);
});

it('rejects an inactive plan', function () {
    $this->plan->update(['is_active' => false]);

    app(StartTrialSubscriptionAction::class)->execute($this->user, $this->plan->id);
})->throws(ValidationException::class);

it('rejects a plan for another client type', function () {
    $this->plan->update(['client_type' => 'familiar']);

    app(StartTrialSubscriptionAction::class)->execute($this->user, $this->plan->id);
})->throws(ValidationException::class);

it('rejects a current paid subscription', function () {
    Subscription::create([
        'subscriber_type' => 'user',
        'subscriber_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    app(StartTrialSubscriptionAction::class)->execute($this->user, $this->plan->id);
})->throws(ValidationException::class);

it('treats expired status dates as inactive', function () {
    $subscription = Subscription::create([
        'subscriber_type' => 'user',
        'subscriber_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->subMinute(),
    ]);

    expect($subscription->isActive())->toBeFalse()
        ->and(Subscription::query()->current()->exists())->toBeFalse();
});
