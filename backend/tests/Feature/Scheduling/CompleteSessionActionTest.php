<?php

use App\Actions\CompleteSessionAction;
use App\Enums\SessionAttendanceEventType;
use App\Enums\SessionStatus;
use App\Events\SessionLifecycleTransitioned;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendanceEvent;
use App\Models\SessionAttendee;
use App\Models\SessionParticipantExclusion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class)->group('scheduling', 'lifecycle', 'attendance');

function completeActionSession(string $start, int $duration = 60, string $status = 'scheduled'): array
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
        'scheduled_at' => Carbon::parse($start, 'UTC'),
        'duration_minutes' => $duration,
        'status' => $status,
    ]);

    return [$session, $mentor, $mentee];
}

it('allows only the relationship mentor to complete the session', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-14 11:00:00', 'UTC'));
    [$session, $mentor] = completeActionSession('2026-10-14 10:00:00');
    $admin = User::factory()->admin()->create();
    $mentee = User::factory()->create();

    foreach ([$admin, $mentee] as $actor) {
        expect(fn () => app(CompleteSessionAction::class)->execute($session->id, $actor, now()))
            ->toThrow(AuthorizationException::class);
    }

    expect($session->fresh()->status)->toBe(SessionStatus::Scheduled->value);

    app(CompleteSessionAction::class)->execute(
        $session->id,
        $mentor,
        Carbon::parse('2026-10-14 11:00:00', 'UTC'),
    );

    expect($session->fresh()->status)->toBe(SessionStatus::Completed->value);
});

it('rejects completion before end and at the inclusive 48 hour deadline', function (): void {
    [$session, $mentor] = completeActionSession('2026-10-14 10:00:00');

    expect(fn () => app(CompleteSessionAction::class)->execute(
        $session->id,
        $mentor,
        Carbon::parse('2026-10-14 10:59:59', 'UTC'),
    ))->toThrow(ValidationException::class);

    expect(fn () => app(CompleteSessionAction::class)->execute(
        $session->id,
        $mentor,
        Carbon::parse('2026-10-16 11:00:00', 'UTC'),
    ))->toThrow(ValidationException::class);

    expect($session->fresh()->status)->toBe(SessionStatus::Scheduled->value);
});

it('rejects terminal sessions without changing their state', function (string $status): void {
    [$session, $mentor] = completeActionSession('2026-10-14 10:00:00', status: $status);

    expect(fn () => app(CompleteSessionAction::class)->execute(
        $session->id,
        $mentor,
        Carbon::parse('2026-10-14 11:00:00', 'UTC'),
    ))->toThrow(ValidationException::class);

    expect($session->fresh()->status)->toBe($status);
})->with(['completed', 'canceled', 'not_completed']);

it('promotes a scheduled session then completes and audits both transitions at exact end', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-14 11:00:00', 'UTC'));
    Event::fake([SessionLifecycleTransitioned::class]);
    [$session, $mentor] = completeActionSession('2026-10-14 10:00:00');

    app(CompleteSessionAction::class)->execute(
        $session->id,
        $mentor,
        Carbon::parse('2026-10-14 11:00:00', 'UTC'),
    );

    $session->refresh();
    $activities = Activity::query()
        ->where('subject_type', Session::class)
        ->where('subject_id', $session->id)
        ->where('event', 'mentor.session.status_transition')
        ->get();
    $transitionsByPreviousStatus = $activities->keyBy(
        static fn (Activity $activity): string => $activity->properties['before']['status'],
    );

    expect($session->status)->toBe(SessionStatus::Completed->value)
        ->and($session->getRawOriginal('completed_at'))->toBe('2026-10-14 11:00:00')
        ->and($session->completed_by)->toBe($mentor->id)
        ->and($activities)->toHaveCount(2)
        ->and($transitionsByPreviousStatus['scheduled']->properties['after']['status'])->toBe('in_progress')
        ->and($transitionsByPreviousStatus['in_progress']->properties['after']['status'])->toBe('completed')
        ->and($transitionsByPreviousStatus['in_progress']->causer_id)->toBe($mentor->id);

    Event::assertDispatchedTimes(SessionLifecycleTransitioned::class, 2);
});

it('initializes exactly the registered participants at end without overwriting confirmations', function (): void {
    $end = Carbon::parse('2026-10-14 11:00:00', 'UTC');
    [$session, $mentor] = completeActionSession('2026-10-14 10:00:00');
    $activeAtEnd = User::factory()->create();
    $leftAfterEnd = User::factory()->create();
    $joinedAfterEnd = User::factory()->create();
    $leftByEnd = User::factory()->create();
    $excluded = User::factory()->create();
    $confirmed = User::factory()->create();

    $attendees = [];
    foreach ([$activeAtEnd, $leftAfterEnd, $joinedAfterEnd, $confirmed] as $participant) {
        $attendees[$participant->id] = SessionAttendee::factory()->create([
            'session_id' => $session->id,
            'user_id' => $participant->id,
            'created_at' => $participant->is($joinedAfterEnd)
                ? Carbon::parse('2026-10-14 11:00:01', 'UTC')
                : Carbon::parse('2026-10-14 09:00:00', 'UTC'),
            'left_at' => $participant->is($leftAfterEnd)
                ? Carbon::parse('2026-10-14 11:30:00', 'UTC')
                : null,
            'attendance_status' => $participant->is($confirmed) ? 'attended' : 'not_confirmed',
            'attended' => $participant->is($confirmed),
            'confirmed_by' => $participant->is($confirmed) ? $confirmed->id : null,
            'confirmed_at' => $participant->is($confirmed)
                ? Carbon::parse('2026-10-14 11:30:00', 'UTC')
                : null,
        ]);
    }
    $leftByEndAttendee = SessionAttendee::factory()->create([
        'session_id' => $session->id,
        'user_id' => $leftByEnd->id,
        'created_at' => Carbon::parse('2026-10-14 09:00:00', 'UTC'),
        'left_at' => $end,
    ]);
    SessionAttendanceEvent::factory()->confirmation()->create([
        'session_attendee_id' => $attendees[$confirmed->id]->id,
        'recorded_by' => $confirmed->id,
        'occurred_at' => Carbon::parse('2026-10-14 11:30:00', 'UTC'),
    ]);
    SessionParticipantExclusion::create([
        'session_id' => $session->id,
        'user_id' => $excluded->id,
        'excluded_at' => Carbon::parse('2026-10-14 09:30:00', 'UTC'),
    ]);

    $now = Carbon::parse('2026-10-14 12:00:00', 'UTC');
    app(CompleteSessionAction::class)->execute($session->id, $mentor, $now);
    expect(fn () => app(CompleteSessionAction::class)->execute($session->id, $mentor, $now))
        ->toThrow(ValidationException::class);

    $initializations = SessionAttendanceEvent::query()
        ->where('event_type', SessionAttendanceEventType::Initialization->value)
        ->get();

    expect($initializations)->toHaveCount(4)
        ->and($initializations->pluck('session_attendee_id')->all())->toContain(
            $attendees[$activeAtEnd->id]->id,
            $attendees[$leftAfterEnd->id]->id,
        )
        ->and(SessionAttendee::query()->where('session_id', $session->id)->where('user_id', $mentor->id)->exists())->toBeTrue()
        ->and(SessionAttendee::query()->where('session_id', $session->id)->where('user_id', $excluded->id)->exists())->toBeFalse()
        ->and($initializations->pluck('session_attendee_id')->all())->not->toContain(
            $attendees[$joinedAfterEnd->id]->id,
            $leftByEndAttendee->id,
        )
        ->and($attendees[$confirmed->id]->fresh()->attendance_status->value)->toBe('attended')
        ->and($attendees[$confirmed->id]->fresh()->confirmed_by)->toBe($confirmed->id)
        ->and($attendees[$confirmed->id]->fresh()->getRawOriginal('confirmed_at'))->toBe('2026-10-14 11:30:00');

    foreach ($initializations as $initialization) {
        expect($initialization->attendance_status->value)->toBe('not_confirmed')
            ->and($initialization->recorded_by)->toBeNull()
            ->and($initialization->getRawOriginal('occurred_at'))->toBe($session->getRawOriginal('end_at'));
    }
});
