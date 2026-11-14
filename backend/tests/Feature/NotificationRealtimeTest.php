<?php

use App\Events\NotificationPushed;
use App\Events\SessionReminder;
use App\Models\MentorshipRelationship;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MentorAlertDetectionService;
use App\Services\Scheduling\SessionNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('mentor');
    Role::findOrCreate('mentee');
});

function realtimePersonalPair(): array
{
    $mentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'client_type' => 'personal',
    ]));
    $mentor->assignRole('mentor');
    $mentee = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
    ]));
    $mentee->assignRole('mentee');

    $relationship = MentorshipRelationship::create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
        'started_at' => now(),
    ]);

    return [$mentor, $mentee, $relationship];
}

function realtimePlan(string $code): Plan
{
    return Plan::create([
        'code' => $code,
        'client_type' => 'personal',
        'name' => ['es' => $code],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_mentors' => 1,
        'entitlements' => [
            'mentor_workspace' => true,
            'attention_tracking' => true,
        ],
        'is_active' => true,
    ]);
}

it('dispatches NotificationPushed once when an alert notification is created', function (): void {
    Event::fake([NotificationPushed::class]);

    [$mentor, $mentee] = realtimePersonalPair();
    $plan = realtimePlan('rt-alert-plan');
    Subscription::create([
        'subscriber_type' => 'user',
        'subscriber_id' => $mentee->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    app(MentorAlertDetectionService::class)->detect();

    Event::assertDispatched(NotificationPushed::class, fn (NotificationPushed $event): bool => $event->notification->user_id === $mentor->id
        && $event->notification->type === 'mentor_alert');

    app(MentorAlertDetectionService::class)->detect();

    Event::assertDispatchedTimes(NotificationPushed::class, 1);
});

it('dispatches NotificationPushed only for newly inserted session reminders', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-13 09:00:00', config('app.timezone')));
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-13 09:00:00', config('app.timezone')));

    try {
        Event::fake([NotificationPushed::class, SessionReminder::class]);

        [$mentor, $mentee, $relationship] = realtimePersonalPair();
        $session = Session::factory()->create([
            'relationship_id' => $relationship->id,
            'session_type' => 'individual',
            'title' => 'Reminder target',
            'scheduled_at' => Carbon::parse('2026-10-13 11:00:00', config('app.timezone')),
            'duration_minutes' => 60,
            'timezone' => 'UTC',
            'status' => 'scheduled',
        ]);
        SessionAttendee::create(['session_id' => $session->id, 'user_id' => $mentor->id]);
        SessionAttendee::create(['session_id' => $session->id, 'user_id' => $mentee->id]);

        $this->artisan('app:send-session-reminders')->assertExitCode(0);

        $inserted = Notification::query()->where('type', 'session_reminder')->count();
        Event::assertDispatchedTimes(NotificationPushed::class, $inserted);
        expect($inserted)->toBeGreaterThan(0);

        $this->artisan('app:send-session-reminders')->assertExitCode(0);

        Event::assertDispatchedTimes(NotificationPushed::class, $inserted);
    } finally {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }
});

it('dispatches NotificationPushed when the session service inserts a notification', function (): void {
    Event::fake([NotificationPushed::class, SessionReminder::class]);

    [$mentor, $mentee, $relationship] = realtimePersonalPair();
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'session_type' => 'individual',
        'title' => 'Immediate reminder target',
        'scheduled_at' => now()->addHours(2),
        'duration_minutes' => 60,
        'timezone' => 'UTC',
        'status' => 'scheduled',
    ]);
    SessionAttendee::create(['session_id' => $session->id, 'user_id' => $mentor->id]);
    SessionAttendee::create(['session_id' => $session->id, 'user_id' => $mentee->id]);

    app(SessionNotificationService::class)->handleCreated($session->id, CarbonImmutable::now());

    $inserted = Notification::query()->where('type', 'session_reminder')->count();
    expect($inserted)->toBeGreaterThan(0);
    Event::assertDispatchedTimes(NotificationPushed::class, $inserted);
    Event::assertDispatched(NotificationPushed::class, fn (NotificationPushed $event): bool => $event->notification->user_id === $mentor->id
        || $event->notification->user_id === $mentee->id);

    app(SessionNotificationService::class)->handleCreated($session->id, CarbonImmutable::now());

    Event::assertDispatchedTimes(NotificationPushed::class, $inserted);
});

it('returns only the authenticated user unread notification count', function (): void {
    [$mentor, $mentee] = realtimePersonalPair();
    $other = User::factory()->create();

    Notification::query()->create([
        'user_id' => $mentor->id,
        'type' => 'mentor_alert',
        'dedupe_key' => 'rt-unread-1',
        'status' => 'sent',
        'data' => [],
    ]);
    Notification::query()->create([
        'user_id' => $mentor->id,
        'type' => 'mentor_alert',
        'dedupe_key' => 'rt-unread-2',
        'status' => 'sent',
        'data' => [],
        'read_at' => now(),
    ]);
    Notification::query()->create([
        'user_id' => $other->id,
        'type' => 'mentor_alert',
        'dedupe_key' => 'rt-unread-other',
        'status' => 'sent',
        'data' => [],
    ]);

    $this->withToken($mentor->createToken('test')->plainTextToken)
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertExactJson(['unread_count' => 1]);
});

it('rejects unauthenticated unread-count requests', function (): void {
    $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
});
