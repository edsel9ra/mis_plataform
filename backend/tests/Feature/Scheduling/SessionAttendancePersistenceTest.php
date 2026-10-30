<?php

use App\Enums\SessionStatus;
use App\Models\Session;
use App\Models\SessionAttendanceEvent;
use App\Models\SessionAttendee;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses()->group('scheduling', 'attendance');

it('adds attendance state and immutable attendance-event persistence additively', function (): void {
    expect(Schema::hasColumn('sessions', 'status'))->toBeTrue()
        ->and(Schema::hasColumn('session_attendees', 'attended'))->toBeTrue()
        ->and(Schema::hasColumn('session_attendees', 'left_at'))->toBeTrue()
        ->and(Schema::hasColumn('session_attendees', 'attendance_status'))->toBeTrue()
        ->and(Schema::hasColumn('session_attendees', 'confirmed_by'))->toBeTrue()
        ->and(Schema::hasColumn('session_attendees', 'confirmed_at'))->toBeTrue()
        ->and(Schema::hasTable('session_attendance_events'))->toBeTrue();
});

it('adds not completed as a persisted terminal session status without changing old values', function (): void {
    expect(SessionStatus::NotCompleted->value)->toBe('not_completed')
        ->and(SessionStatus::Scheduled->value)->toBe('scheduled')
        ->and(SessionStatus::InProgress->value)->toBe('in_progress')
        ->and(SessionStatus::Completed->value)->toBe('completed')
        ->and(SessionStatus::Canceled->value)->toBe('canceled')
        ->and(SessionStatus::NotCompleted->isTerminal())->toBeTrue()
        ->and(SessionStatus::Completed->isTerminal())->toBeTrue()
        ->and(SessionStatus::Canceled->isTerminal())->toBeTrue()
        ->and(SessionStatus::Scheduled->isTerminal())->toBeFalse()
        ->and(SessionStatus::InProgress->isTerminal())->toBeFalse();

    $session = Session::factory()->notCompleted()->create();

    expect($session->fresh()->status)->toBe(SessionStatus::NotCompleted->value);
});

it('backfills legacy attended rows into the new effective state without changing the boolean', function (): void {
    $attendee = SessionAttendee::factory()->create();
    DB::table('session_attendees')->where('id', $attendee->id)->update(['attended' => true]);

    Schema::dropIfExists('session_attendance_events');
    Schema::table('session_attendees', function (Blueprint $table): void {
        $table->dropForeign(['confirmed_by']);
        $table->dropColumn(['attendance_status', 'confirmed_by', 'confirmed_at']);
    });

    $migration = require database_path('migrations/2026_10_13_090007_add_session_attendance_audit.php');
    $migration->up();
    $attendee->refresh();

    expect($attendee->attendance_status->value)->toBe('attended')
        ->and($attendee->attended)->toBeTrue();
});

it('persists effective attendance status and confirmation attribution while retaining attended', function (): void {
    $session = Session::factory()->create();
    $user = User::factory()->create();
    $confirmer = User::factory()->create();
    $confirmedAt = Carbon::parse('2026-10-13 12:30:00');

    $attendee = SessionAttendee::factory()->create([
        'session_id' => $session->id,
        'user_id' => $user->id,
        'attendance_status' => 'attended',
        'attended' => true,
        'confirmed_by' => $confirmer->id,
        'confirmed_at' => $confirmedAt,
    ]);

    $attendee->refresh();

    expect($attendee->attendance_status->value)->toBe('attended')
        ->and($attendee->attended)->toBeTrue()
        ->and($attendee->confirmed_by)->toBe($confirmer->id)
        ->and($attendee->confirmed_at->equalTo($confirmedAt))->toBeTrue();
});

it('defaults new attendee records to not confirmed and does not presume attendance', function (): void {
    $attendee = SessionAttendee::factory()->create();

    expect($attendee->fresh()->attendance_status->value)->toBe('not_confirmed')
        ->and($attendee->fresh()->attended)->toBeFalse()
        ->and($attendee->fresh()->confirmed_by)->toBeNull()
        ->and($attendee->fresh()->confirmed_at)->toBeNull();
});

it('retains one initialization event and multiple confirmation events per attendee', function (): void {
    $attendee = SessionAttendee::factory()->create();
    $confirmer = User::factory()->create();

    $initialization = SessionAttendanceEvent::factory()->initialization()->create([
        'session_attendee_id' => $attendee->id,
    ]);

    $firstConfirmation = SessionAttendanceEvent::factory()->confirmation()->create([
        'session_attendee_id' => $attendee->id,
        'recorded_by' => $confirmer->id,
        'attendance_status' => 'attended',
    ]);
    $secondConfirmation = SessionAttendanceEvent::factory()->confirmation()->create([
        'session_attendee_id' => $attendee->id,
        'recorded_by' => $confirmer->id,
        'attendance_status' => 'absent',
    ]);

    expect($attendee->attendanceEvents()->count())->toBe(3)
        ->and($initialization->event_type->value)->toBe('initialization')
        ->and($initialization->attendance_status->value)->toBe('not_confirmed')
        ->and($initialization->recorded_by)->toBeNull()
        ->and($firstConfirmation->occurred_at)->not->toBeNull()
        ->and($secondConfirmation->attendance_status->value)->toBe('absent');

    expect(fn () => DB::transaction(fn () => SessionAttendanceEvent::factory()->initialization()->create([
        'session_attendee_id' => $attendee->id,
    ])))->toThrow(QueryException::class);
});

it('rejects event actor and status combinations that violate the attendance audit contract', function (): void {
    $attendee = SessionAttendee::factory()->create();

    expect(fn () => DB::transaction(fn () => SessionAttendanceEvent::factory()->create([
        'session_attendee_id' => $attendee->id,
        'event_type' => 'confirmation',
        'attendance_status' => 'attended',
        'recorded_by' => null,
    ])))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn () => SessionAttendanceEvent::factory()->create([
        'session_attendee_id' => $attendee->id,
        'event_type' => 'initialization',
        'attendance_status' => 'attended',
        'recorded_by' => null,
    ])))->toThrow(QueryException::class);
});

it('makes attendance events append only through the model', function (): void {
    $event = SessionAttendanceEvent::factory()->initialization()->create();

    expect(fn () => $event->forceFill(['attendance_status' => 'absent'])->save())
        ->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class);
});
