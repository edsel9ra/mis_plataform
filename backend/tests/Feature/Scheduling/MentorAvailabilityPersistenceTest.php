<?php

use App\Enums\AvailabilityMode;
use App\Models\MentorAvailabilityException;
use App\Models\MentorAvailabilityWindow;
use App\Models\Session;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('persists a recurring availability window with its mentor and time values', function (): void {
    $mentor = User::factory()->mentor()->create();

    $window = MentorAvailabilityWindow::factory()->for($mentor, 'mentor')->create([
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:30:00',
        'timezone' => 'America/Mexico_City',
    ]);

    expect($window->id)->toBeString()
        ->and($window->mentor->is($mentor))->toBeTrue()
        ->and($window->start_time->format('H:i:s'))->toBe('09:00:00')
        ->and($window->end_time->format('H:i:s'))->toBe('12:30:00')
        ->and($mentor->availabilityWindows()->whereKey($window->id)->exists())->toBeTrue();
});

it('enforces recurring availability uniqueness and valid weekday and time order in postgres', function (): void {
    $mentor = User::factory()->mentor()->create();
    $attributes = [
        'user_id' => $mentor->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'timezone' => 'UTC',
    ];

    MentorAvailabilityWindow::factory()->create($attributes);

    expect(fn () => MentorAvailabilityWindow::factory()->create($attributes))
        ->toThrow(QueryException::class);
    expect(fn () => MentorAvailabilityWindow::factory()->create([...$attributes, 'day_of_week' => 7]))
        ->toThrow(QueryException::class);
    expect(fn () => MentorAvailabilityWindow::factory()->create([
        ...$attributes,
        'day_of_week' => 2,
        'start_time' => '12:00:00',
        'end_time' => '09:00:00',
    ]))->toThrow(QueryException::class);
});

it('persists availability exceptions with enum and date/time casts while keeping the reason private', function (): void {
    $mentor = User::factory()->mentor()->create();

    $exception = MentorAvailabilityException::factory()->for($mentor, 'mentor')->create([
        'mode' => AvailabilityMode::Block,
        'start_date' => '2026-12-24',
        'end_date' => '2026-12-26',
        'start_time' => '10:00:00',
        'end_time' => '15:00:00',
        'timezone' => 'Europe/Madrid',
        'reason' => 'Motivo privado',
    ]);

    expect($exception->id)->toBeString()
        ->and($exception->mentor->is($mentor))->toBeTrue()
        ->and($exception->mode)->toBe(AvailabilityMode::Block)
        ->and($exception->start_date->format('Y-m-d'))->toBe('2026-12-24')
        ->and($exception->end_date->format('Y-m-d'))->toBe('2026-12-26')
        ->and($exception->start_time->format('H:i:s'))->toBe('10:00:00')
        ->and($exception->end_time->format('H:i:s'))->toBe('15:00:00')
        ->and($exception->reason)->toBe('Motivo privado')
        ->and($exception->toArray())->not->toHaveKey('reason')
        ->and($mentor->availabilityExceptions()->whereKey($exception->id)->exists())->toBeTrue();
});

it('allows all-day single-date exceptions and open exceptions', function (): void {
    $exception = MentorAvailabilityException::factory()->create([
        'mode' => AvailabilityMode::Open,
        'start_date' => '2026-12-24',
        'end_date' => null,
        'start_time' => null,
        'end_time' => null,
    ]);

    expect($exception->mode)->toBe(AvailabilityMode::Open)
        ->and($exception->end_date)->toBeNull()
        ->and($exception->start_time)->toBeNull()
        ->and($exception->end_time)->toBeNull();
});

it('enforces valid exception mode, date range, and paired optional times in postgres', function (): void {
    $attributes = [
        'user_id' => User::factory()->mentor()->create()->id,
        'mode' => 'block',
        'start_date' => '2026-12-24',
        'end_date' => '2026-12-26',
        'start_time' => null,
        'end_time' => null,
        'timezone' => 'UTC',
    ];

    expect(fn () => DB::table('mentor_availability_exceptions')->insert([
        'id' => (string) str()->uuid(),
        ...$attributes,
        'mode' => 'invalid',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('mentor_availability_exceptions')->insert([
        'id' => (string) str()->uuid(),
        ...$attributes,
        'start_date' => '2026-12-26',
        'end_date' => '2026-12-24',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('mentor_availability_exceptions')->insert([
        'id' => (string) str()->uuid(),
        ...$attributes,
        'start_time' => '10:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('adds availability tables without changing existing session rows', function (): void {
    $session = Session::factory()->create([
        'scheduled_at' => '2026-04-05 13:45:00',
        'duration_minutes' => 45,
    ]);
    $scheduledAt = $session->fresh()->scheduled_at->toDateTimeString();

    expect($scheduledAt)->toBe('2026-04-05 13:45:00')
        ->and($session->fresh()->duration_minutes)->toBe(45);
});

it('rolls back only the availability tables and leaves session data intact', function (): void {
    $session = Session::factory()->create();
    $migration = require database_path('migrations/2026_10_12_090004_create_mentor_availability_tables.php');

    $migration->down();

    expect(Schema::hasTable('mentor_availability_exceptions'))->toBeFalse()
        ->and(Schema::hasTable('mentor_availability_windows'))->toBeFalse()
        ->and(Session::query()->whereKey($session->id)->exists())->toBeTrue();

    $migration->up();

    expect(Schema::hasTable('mentor_availability_windows'))->toBeTrue()
        ->and(Schema::hasTable('mentor_availability_exceptions'))->toBeTrue();
});
