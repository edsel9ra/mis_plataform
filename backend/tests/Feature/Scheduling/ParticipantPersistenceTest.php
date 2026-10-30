<?php

use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\SessionParticipantExclusion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

uses()->group('scheduling', 'participants');

it('adds a nullable departure timestamp without changing existing attendance columns', function (): void {
    expect(Schema::hasColumn('session_attendees', 'attended'))->toBeTrue()
        ->and(Schema::hasColumn('session_attendees', 'left_at'))->toBeTrue()
        ->and(Schema::hasTable('session_participant_exclusions'))->toBeTrue();
});

it('retains an attendee and their attendance when marking their departure', function (): void {
    $session = Session::factory()->create();
    $user = User::factory()->create();
    $attendee = SessionAttendee::create([
        'session_id' => $session->id,
        'user_id' => $user->id,
        'attended' => true,
    ]);
    $originalCreatedAt = $attendee->created_at;
    $leftAt = Carbon::parse('2026-10-12 15:30:00');

    $attendee->left_at = $leftAt;
    $attendee->save();
    $attendee->refresh();

    expect($attendee->left_at)->toBeInstanceOf(Carbon::class)
        ->and($attendee->left_at->equalTo($leftAt))->toBeTrue()
        ->and($attendee->attended)->toBeTrue()
        ->and($attendee->created_at->equalTo($originalCreatedAt))->toBeTrue()
        ->and(SessionAttendee::whereKey($attendee->id)->exists())->toBeTrue();
});

it('persists a case 41 exclusion separately from attendee rows', function (): void {
    $session = Session::factory()->create([
        'relationship_id' => MentorshipRelationship::factory(),
    ]);
    $user = User::factory()->create();
    $excludedAt = Carbon::parse('2026-10-12 15:30:00');

    $exclusion = SessionParticipantExclusion::create([
        'session_id' => $session->id,
        'user_id' => $user->id,
        'excluded_at' => $excludedAt,
    ]);

    expect($exclusion->excluded_at)->toBeInstanceOf(Carbon::class)
        ->and($exclusion->excluded_at->equalTo($excludedAt))->toBeTrue()
        ->and($session->participantExclusions()->whereKey($exclusion->id)->exists())->toBeTrue()
        ->and($session->attendees()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('allows only one permanent exclusion per session and user', function (): void {
    $session = Session::factory()->create();
    $user = User::factory()->create();
    $attributes = [
        'session_id' => $session->id,
        'user_id' => $user->id,
        'excluded_at' => now(),
    ];

    SessionParticipantExclusion::create($attributes);

    expect(fn () => SessionParticipantExclusion::create($attributes))
        ->toThrow(QueryException::class);
});
