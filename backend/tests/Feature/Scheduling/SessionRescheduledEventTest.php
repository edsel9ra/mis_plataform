<?php

use App\Actions\RescheduleSessionAction;
use App\Events\SessionRescheduled;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\SessionScheduleChange;
use App\Models\User;
use App\Services\Scheduling\SchedulingAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class)->group('scheduling', 'rescheduling', 'events');

beforeEach(function () {
    // Let the action's own transaction reach a real commit so after-commit
    // events can be observed in these integration tests.
    DB::commit();
});

function rescheduleEventFixture(array $overrides = []): array
{
    $mentor = User::factory()->mentor()->create();
    $mentee = User::factory()->create(['role' => 'mentee']);
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
    $session = Session::factory()->create(array_merge([
        'relationship_id' => $relationship->id,
        'session_type' => 'individual',
        'scheduled_at' => Carbon::parse('2026-10-20 14:00:00', 'UTC')->setTimezone((string) config('app.timezone')),
        'timezone' => 'Europe/Madrid',
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ], $overrides));
    SessionAttendee::factory()->create(['session_id' => $session->id, 'user_id' => $mentor->id]);
    SessionAttendee::factory()->create(['session_id' => $session->id, 'user_id' => $mentee->id]);

    return [$session, $mentor, $mentee];
}

function executeEventReschedule(Session $session, User $actor): Session
{
    return app(RescheduleSessionAction::class)->execute($session->id, [
        'scheduled_at' => '2026-10-21T15:30:00+00:00',
        'duration_minutes' => 90,
        'timezone' => 'Asia/Tokyo',
        'reason' => 'Cambio de agenda',
    ], $actor, Carbon::parse('2026-10-13 10:00:00', 'UTC'));
}

it('dispatches one stable event after commit with the persisted schedule snapshots', function () {
    [$session, , $mentee] = rescheduleEventFixture();
    Event::fake([SessionRescheduled::class]);

    executeEventReschedule($session, $mentee);

    Event::assertDispatchedTimes(SessionRescheduled::class, 1);
    Event::assertDispatched(SessionRescheduled::class, function (SessionRescheduled $event) use ($session) {
        $change = SessionScheduleChange::query()->sole();

        return $event->eventId === $change->id
            && $event->sessionId === $session->id
            && $event->type === 'session.rescheduled'
            && $event->occurredAt === $change->occurred_at->toIso8601String()
            && $event->previousSchedule === [
                'scheduled_at' => $change->scheduled_at_before->toIso8601String(),
                'timezone' => 'Europe/Madrid',
                'duration_minutes' => 60,
            ]
            && $event->newSchedule === [
                'scheduled_at' => $change->scheduled_at_after->toIso8601String(),
                'timezone' => 'Asia/Tokyo',
                'duration_minutes' => 90,
                'reason' => 'Cambio de agenda',
            ];
    });
});

it('does not dispatch an event when validation rejects or the action transaction rolls back', function () {
    [$session, , $mentee] = rescheduleEventFixture();
    Event::fake([SessionRescheduled::class]);

    expect(fn () => app(RescheduleSessionAction::class)->execute(
        $session->id,
        ['scheduled_at' => '2026-10-21T15:30:00+00:00', 'duration_minutes' => 90, 'timezone' => 'Asia/Tokyo'],
        $mentee,
        Carbon::parse('2026-10-20 13:00:00', 'UTC'),
    ))->toThrow(ValidationException::class);

    Event::assertNotDispatched(SessionRescheduled::class);
    $auditLogger = Mockery::mock(SchedulingAuditLogger::class);
    $auditLogger->shouldReceive('snapshot')->once()->andReturn([]);
    $auditLogger->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit write failed'));
    app()->instance(SchedulingAuditLogger::class, $auditLogger);

    expect(fn () => executeEventReschedule($session->fresh(), $mentee))
        ->toThrow(RuntimeException::class, 'Audit write failed');

    Event::assertNotDispatched(SessionRescheduled::class);
    expect(SessionScheduleChange::query()->count())->toBe(0)
        ->and(Session::query()->findOrFail($session->id)->timezone)->toBe('Europe/Madrid');
});

it('keeps the reschedule committed when an event listener fails after commit', function () {
    [$session, , $mentee] = rescheduleEventFixture();
    Event::listen(SessionRescheduled::class, function (): never {
        throw new RuntimeException('Consumer unavailable');
    });

    expect(fn () => executeEventReschedule($session, $mentee))
        ->toThrow(RuntimeException::class, 'Consumer unavailable');

    expect(SessionScheduleChange::query()->count())->toBe(1)
        ->and(Session::query()->findOrFail($session->id)->timezone)->toBe('Asia/Tokyo');
});
