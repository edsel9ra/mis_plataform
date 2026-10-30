<?php

use App\Contracts\Scheduling\SessionScheduleChangeContract;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionScheduleChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class)->group('scheduling', 'rescheduling');

it('persists prior and new schedule snapshots using the approved audit field contract', function () {
    $mentor = User::factory()->mentor()->create();
    $relationship = MentorshipRelationship::factory()->create(['mentor_id' => $mentor->id]);
    $session = Session::factory()->create(['relationship_id' => $relationship->id]);
    $actor = User::factory()->create();

    $requiredFields = SessionScheduleChangeContract::FIELDS;

    expect(Schema::hasColumns('session_schedule_changes', [
        'id',
        'session_id',
        ...$requiredFields,
        'reason',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    $change = $session->scheduleChanges()->create([
        'scheduled_at_before' => '2026-10-15 09:00:00',
        'scheduled_at_after' => '2026-10-16 11:30:00',
        'timezone_before' => 'America/Mexico_City',
        'timezone_after' => 'America/Bogota',
        'duration_minutes_before' => 60,
        'duration_minutes_after' => 90,
        'actor_id' => $actor->id,
        'occurred_at' => '2026-10-13 10:00:00',
        'reason' => 'Participant requested a new time.',
    ]);

    $change->refresh();

    expect($change)->toBeInstanceOf(SessionScheduleChange::class)
        ->and($change->exists)->toBeTrue()
        ->and($change->id)->not->toBeEmpty()
        ->and($change->session->is($session))->toBeTrue()
        ->and($change->actor->is($actor))->toBeTrue()
        ->and($change->scheduled_at_before->toDateTimeString())->toBe('2026-10-15 09:00:00')
        ->and($change->scheduled_at_after->toDateTimeString())->toBe('2026-10-16 11:30:00')
        ->and($change->timezone_before)->toBe('America/Mexico_City')
        ->and($change->timezone_after)->toBe('America/Bogota')
        ->and($change->duration_minutes_before)->toBe(60)
        ->and($change->duration_minutes_after)->toBe(90)
        ->and($change->occurred_at->toDateTimeString())->toBe('2026-10-13 10:00:00')
        ->and($change->reason)->toBe('Participant requested a new time.')
        ->and($session->scheduleChanges()->count())->toBe(1);
});

it('keeps rescheduling history append-only through the model', function () {
    $session = Session::factory()->create();
    $actor = User::factory()->create();
    $change = $session->scheduleChanges()->create([
        'scheduled_at_before' => now()->addDays(2),
        'scheduled_at_after' => now()->addDays(3),
        'timezone_before' => 'America/Mexico_City',
        'timezone_after' => 'America/Mexico_City',
        'duration_minutes_before' => 60,
        'duration_minutes_after' => 60,
        'actor_id' => $actor->id,
        'occurred_at' => now(),
    ]);

    expect(fn () => $change->update(['reason' => 'rewritten']))
        ->toThrow(LogicException::class);
    expect(fn () => $change->delete())
        ->toThrow(LogicException::class);
});
