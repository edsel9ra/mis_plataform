<?php

use App\Enums\SessionStatus;
use App\Events\SessionLifecycleTransitioned;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendanceEvent;
use App\Models\SessionAttendee;
use App\Models\SessionParticipantExclusion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class)->group('scheduling', 'lifecycle');

function lifecycleSession(string $start, int $duration = 60, string $status = 'scheduled'): array
{
    $mentor = User::factory()->mentor()->create();
    $mentee = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => dbInstant($start),
        'duration_minutes' => $duration,
        'status' => $status,
    ]);

    return [$session, $mentor, $mentee];
}

function lifecycleActivities(Session $session)
{
    return Activity::query()
        ->where('log_name', 'scheduling')
        ->where('subject_type', Session::class)
        ->where('subject_id', $session->id)
        ->where('event', 'system.session.status_transition');
}

it('advances scheduled sessions at the exact start time', function (): void {
    Carbon::setTestNow(dbInstant('2026-10-14 10:00:00'));
    Event::fake([SessionLifecycleTransitioned::class]);
    [$session] = lifecycleSession('2026-10-14 10:00:00');

    $this->artisan('app:advance-session-lifecycle')->assertSuccessful();

    expect($session->fresh()->status)->toBe(SessionStatus::InProgress->value)
        ->and(lifecycleActivities($session)->count())->toBe(1);
    Event::assertDispatched(SessionLifecycleTransitioned::class, fn ($event) =>
        $event->sessionId === $session->id
        && $event->previousStatus === SessionStatus::Scheduled->value
        && $event->newStatus === SessionStatus::InProgress->value
    );
});

it('closes at the exact end plus 48 hours and prioritizes closure after scheduler lag', function (): void {
    Carbon::setTestNow(dbInstant('2026-10-16 11:00:00'));
    Event::fake([SessionLifecycleTransitioned::class]);
    [$session] = lifecycleSession('2026-10-14 10:00:00', 60);

    $this->artisan('app:advance-session-lifecycle')->assertSuccessful();

    expect($session->fresh()->status)->toBe(SessionStatus::NotCompleted->value)
        ->and(lifecycleActivities($session)->count())->toBe(1)
        ->and(lifecycleActivities($session)->first()->properties['before']['status'])->toBe('scheduled');
    Event::assertDispatchedTimes(SessionLifecycleTransitioned::class, 1);
});

it('does not change any terminal session state', function (): void {
    Carbon::setTestNow(dbInstant('2026-10-20 10:00:00'));
    Event::fake([SessionLifecycleTransitioned::class]);
    [$completed] = lifecycleSession('2026-10-14 10:00:00', status: 'completed');
    [$canceled] = lifecycleSession('2026-10-14 10:00:00', status: 'canceled');
    [$notCompleted] = lifecycleSession('2026-10-14 10:00:00', status: 'not_completed');

    $this->artisan('app:advance-session-lifecycle')->assertSuccessful();

    expect($completed->fresh()->status)->toBe('completed')
        ->and($canceled->fresh()->status)->toBe('canceled')
        ->and($notCompleted->fresh()->status)->toBe('not_completed')
        ->and(lifecycleActivities($completed)->count())->toBe(0)
        ->and(lifecycleActivities($canceled)->count())->toBe(0)
        ->and(lifecycleActivities($notCompleted)->count())->toBe(0);
    Event::assertNotDispatched(SessionLifecycleTransitioned::class);
});

it('is idempotent across runs and initializes only end-anchored non-excluded attendees once', function (): void {
    Carbon::setTestNow(dbInstant('2026-10-16 11:00:00'));
    Event::fake([SessionLifecycleTransitioned::class]);
    [$session, $mentor, $mentee] = lifecycleSession('2026-10-14 10:00:00', 60);
    $eligible = User::factory()->create();
    $excludedByCase41 = User::factory()->create();
    $leftBeforeEnd = User::factory()->create();
    $alreadyConfirmed = User::factory()->create();

    $eligibleAttendee = SessionAttendee::factory()->create([
        'session_id' => $session->id,
        'user_id' => $eligible->id,
        'created_at' => dbInstant('2026-10-14 09:00:00'),
    ]);
    SessionParticipantExclusion::create([
        'session_id' => $session->id,
        'user_id' => $excludedByCase41->id,
        'excluded_at' => dbInstant('2026-10-14 09:00:00'),
    ]);
    $leftAttendee = SessionAttendee::factory()->create([
        'session_id' => $session->id,
        'user_id' => $leftBeforeEnd->id,
        'created_at' => dbInstant('2026-10-14 09:00:00'),
        'left_at' => dbInstant('2026-10-14 10:30:00'),
    ]);
    $confirmedAttendee = SessionAttendee::factory()->create([
        'session_id' => $session->id,
        'user_id' => $alreadyConfirmed->id,
        'created_at' => dbInstant('2026-10-14 09:00:00'),
        'attendance_status' => 'attended',
        'attended' => true,
        'confirmed_by' => $alreadyConfirmed->id,
        'confirmed_at' => dbInstant('2026-10-14 11:30:00'),
    ]);
    SessionAttendanceEvent::factory()->confirmation()->create([
        'session_attendee_id' => $confirmedAttendee->id,
        'recorded_by' => $alreadyConfirmed->id,
        'occurred_at' => dbInstant('2026-10-14 11:30:00'),
    ]);

    $this->artisan('app:advance-session-lifecycle')->assertSuccessful();
    $this->artisan('app:advance-session-lifecycle')->assertSuccessful();

    $initializations = SessionAttendanceEvent::query()
        ->where('event_type', 'initialization')
        ->get();
    expect($initializations)->toHaveCount(3)
        ->and($initializations->pluck('session_attendee_id')->all())->toContain($eligibleAttendee->id)
        ->and(SessionAttendee::query()->where('session_id', $session->id)->where('user_id', $excludedByCase41->id)->exists())->toBeFalse()
        ->and($initializations->pluck('session_attendee_id')->all())->not->toContain($leftAttendee->id)
        ->and(SessionAttendee::query()->where('session_id', $session->id)->where('user_id', $mentor->id)->exists())->toBeTrue()
        ->and(SessionAttendee::query()->where('session_id', $session->id)->where('user_id', $mentee->id)->exists())->toBeFalse()
        ->and($confirmedAttendee->fresh()->attendance_status->value)->toBe('attended')
        ->and($confirmedAttendee->fresh()->attended)->toBeTrue()
        ->and(lifecycleActivities($session)->count())->toBe(1)
        ->and(lifecycleActivities($session)->first()->causer_id)->toBeNull()
        ->and(lifecycleActivities($session)->first()->properties['actor'])->toBe('system');

    foreach ($initializations as $initialization) {
        expect($initialization->attendance_status->value)->toBe('not_confirmed')
            ->and($initialization->recorded_by)->toBeNull()
            ->and($initialization->occurred_at->equalTo($session->end_at))->toBeTrue();
    }
    Event::assertDispatchedTimes(SessionLifecycleTransitioned::class, 1);
});
