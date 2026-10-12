<?php

use App\Events\SessionReminder;
use App\Models\Cohort;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Event;

uses()->group('scheduler');

it('creates each session reminder once per time window', function () {
    Event::fake([SessionReminder::class]);
    $user = User::factory()->create();
    $relationship = \App\Models\MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $user->id,
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => now()->addHours(2),
        'status' => 'scheduled',
    ]);
    SessionAttendee::create(['session_id' => $session->id, 'user_id' => $user->id]);

    $this->artisan('app:send-session-reminders')->assertExitCode(0);
    $this->artisan('app:send-session-reminders')->assertExitCode(0);

    $this->assertDatabaseCount('notifications', 1);
    Event::assertDispatched(SessionReminder::class, 1);
});

it('cleans expired trials independently of ends_at', function () {
    $user = User::factory()->create();
    $plan = Plan::create([
        'client_type' => 'personal',
        'name' => ['es' => 'Personal'],
        'price_monthly' => 10,
        'price_yearly' => 100,
    ]);
    Subscription::create([
        'subscriber_type' => 'user',
        'subscriber_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'trial',
        'trial_ends_at' => now()->subMinute(),
        'ends_at' => null,
    ]);

    $this->artisan('app:cleanup-expired-subscriptions')->assertExitCode(0);

    $this->assertDatabaseHas('subscriptions', [
        'subscriber_id' => $user->id,
        'status' => 'expired',
    ]);
});

it('reports the matching sync as an explicit safe no-op', function () {
    $this->artisan('app:sync-pending-match-scores')
        ->assertExitCode(0)
        ->expectsOutputToContain('No-op');
});
