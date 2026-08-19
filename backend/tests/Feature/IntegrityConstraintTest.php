<?php

use App\Models\Company;
use App\Models\MentorshipRelationship;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;

uses()->group('integrity');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->plan = Plan::create([
        'client_type' => 'personal',
        'name' => ['es' => 'Personal'],
        'price_monthly' => 100,
        'price_yearly' => 1000,
        'is_active' => true,
    ]);
});

it('enforces one current subscription per subscriber', function () {
    $attributes = [
        'subscriber_type' => 'user',
        'subscriber_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ];

    Subscription::create($attributes);
    Subscription::create($attributes);
})->throws(QueryException::class);

it('enforces one lifetime trial per subscriber', function () {
    $attributes = [
        'subscriber_type' => 'user',
        'subscriber_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'status' => 'expired',
        'trial_ends_at' => now()->subDay(),
        'starts_at' => now()->subDays(15),
        'ends_at' => now()->subDay(),
    ];

    Subscription::create($attributes);
    Subscription::create($attributes);
})->throws(QueryException::class);

it('enforces one live relationship for a source and mentor', function () {
    $mentor = User::factory()->mentor()->create();
    $attributes = [
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $this->user->id,
        'mentor_id' => $mentor->id,
        'status' => 'pending',
    ];

    MentorshipRelationship::create($attributes);
    MentorshipRelationship::create($attributes);
})->throws(QueryException::class);

it('enforces one live company per administrator', function () {
    $attributes = [
        'legal_name' => 'Integrity Company',
        'admin_id' => $this->user->id,
    ];

    Company::create($attributes);
    Company::create($attributes);
})->throws(QueryException::class);
