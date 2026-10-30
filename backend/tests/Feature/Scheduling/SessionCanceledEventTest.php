<?php

use App\Actions\CancelSessionAction;
use App\Events\SessionCanceled;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\User;
use App\Services\Scheduling\SchedulingAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class)->group('scheduling', 'cancellation', 'events');

beforeEach(function () {
    DB::commit();
});

function canceledEventFixture(array $overrides = []): array
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
        'scheduled_at' => Carbon::parse('2026-10-20 14:00:00', 'UTC'),
        'duration_minutes' => 60,
        'status' => 'scheduled',
        'meet_link' => 'https://meet.example/historical-secret',
        'meet_event_id' => 'google-event-123',
    ], $overrides));
    SessionAttendee::factory()->create(['session_id' => $session->id, 'user_id' => $mentor->id]);
    SessionAttendee::factory()->create(['session_id' => $session->id, 'user_id' => $mentee->id]);

    return [$session, $mentor, $mentee];
}

function executeEventCancel(Session $session, User $actor, string $reason = 'Cambio solicitado'): Session
{
    return app(CancelSessionAction::class)->execute(
        $session->id,
        $reason,
        $actor,
        Carbon::parse('2026-10-13 10:00:00', 'UTC'),
    );
}

it('dispatches one stable minimal cancellation event after commit and retains the historical Meet values', function () {
    [$session, , $mentee] = canceledEventFixture();
    Event::fake([SessionCanceled::class]);

    executeEventCancel($session, $mentee);

    Event::assertDispatchedTimes(SessionCanceled::class, 1);
    Event::assertDispatched(SessionCanceled::class, function (SessionCanceled $event) use ($session, $mentee) {
        $propertyNames = array_keys(get_object_vars($event));
        sort($propertyNames);

        return $event->eventId === $session->id
            && $event->sessionId === $session->id
            && $event->actorId === $mentee->id
            && $event->type === 'session.canceled'
            && $event->occurredAt === '2026-10-13T04:00:00-06:00'
            && $event->reason === 'Cambio solicitado'
            && $event->late === false
            && $event->leadMinutes === 10680
            && $propertyNames === ['actorId', 'eventId', 'late', 'leadMinutes', 'occurredAt', 'reason', 'sessionId', 'type'];
    });

    expect($session->fresh()->meet_link)->toBe('https://meet.example/historical-secret')
        ->and($session->fresh()->meet_event_id)->toBe('google-event-123');
});

it('does not dispatch a cancellation event when validation or persistence rolls back', function () {
    [$session, , $mentee] = canceledEventFixture();
    Event::fake([SessionCanceled::class]);

    expect(fn () => app(CancelSessionAction::class)->execute(
        $session->id,
        null,
        $mentee,
        Carbon::parse('2026-10-13 10:00:00', 'UTC'),
    ))->toThrow(ValidationException::class);

    $auditLogger = Mockery::mock(SchedulingAuditLogger::class);
    $auditLogger->shouldReceive('snapshot')->once()->andReturn([]);
    $auditLogger->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit write failed'));
    app()->instance(SchedulingAuditLogger::class, $auditLogger);

    expect(fn () => executeEventCancel($session, $mentee))
        ->toThrow(RuntimeException::class, 'Audit write failed');

    Event::assertNotDispatched(SessionCanceled::class);
    expect($session->fresh()->status)->toBe('scheduled')
        ->and($session->fresh()->meet_link)->toBe('https://meet.example/historical-secret');
});

it('keeps cancellation and historical Meet values committed when a consumer fails', function () {
    [$session, , $mentee] = canceledEventFixture();
    Event::listen(SessionCanceled::class, function (): never {
        throw new RuntimeException('Consumer unavailable');
    });

    expect(fn () => executeEventCancel($session, $mentee))
        ->toThrow(RuntimeException::class, 'Consumer unavailable');

    expect($session->fresh()->status)->toBe('canceled')
        ->and($session->fresh()->meet_link)->toBe('https://meet.example/historical-secret')
        ->and($session->fresh()->meet_event_id)->toBe('google-event-123');
});
