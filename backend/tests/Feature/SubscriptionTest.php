<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

uses()->group('subscriptions');

beforeEach(function () {
    $this->user = User::factory()->create(['client_type' => 'personal']);
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->plan = Plan::create([
        'client_type' => 'personal',
        'name' => ['es' => 'Personal'],
        'price_monthly' => 100,
        'price_yearly' => 1000,
        'is_active' => true,
    ]);
});

it('starts one safely serialized trial', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/subscriptions', ['plan_id' => $this->plan->id])
        ->assertCreated()
        ->assertJsonPath('status', 'trial')
        ->assertJsonPath('plan_id', $this->plan->id)
        ->assertJsonMissingPath('payment_provider')
        ->assertJsonMissingPath('payment_id')
        ->assertJsonMissingPath('subscriber_id');
});

it('rejects payment identifiers on the trial endpoint', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/subscriptions', [
            'plan_id' => $this->plan->id,
            'payment_provider' => 'stripe',
            'payment_id' => 'external-id',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment_provider', 'payment_id']);

    $this->assertDatabaseCount('subscriptions', 0);
});

it('prevents a second lifetime trial', function () {
    Subscription::create([
        'subscriber_type' => 'user',
        'subscriber_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'status' => 'expired',
        'trial_ends_at' => now()->subDay(),
        'starts_at' => now()->subDays(15),
        'ends_at' => now()->subDay(),
    ]);

    $this->withToken($this->token)
        ->postJson('/api/v1/subscriptions', ['plan_id' => $this->plan->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subscription']);

    $this->assertDatabaseCount('subscriptions', 1);
});

it('does not return a subscription expired by time as active', function () {
    Subscription::create([
        'subscriber_type' => 'user',
        'subscriber_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->subMinute(),
    ]);

    $this->withToken($this->token)
        ->getJson('/api/v1/subscriptions/active')
        ->assertOk()
        ->assertJsonMissingPath('id');
});
