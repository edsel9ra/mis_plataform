<?php

use App\Models\MentorshipRelationship;
use App\Models\Session;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses()->group('scheduling', 'lifecycle');

it('persists a session end derived from its absolute start and duration', function (): void {
    expect(Schema::hasColumn('sessions', 'end_at'))->toBeTrue();

    $session = Session::factory()->create([
        'relationship_id' => MentorshipRelationship::factory(),
        'scheduled_at' => Carbon::parse('2026-03-08 06:30:00', 'UTC'),
        'duration_minutes' => 90,
    ]);
    $session->refresh();

    expect($session->end()->equalTo($session->scheduled_at->copy()->addMinutes(90)))->toBeTrue()
        ->and(DB::table('sessions')->where('id', $session->id)->value('end_at'))
        ->toBe('2026-03-08 08:00:00');
});

it('recalculates the persisted end when a session start or duration changes', function (): void {
    $session = Session::factory()->create([
        'scheduled_at' => Carbon::parse('2026-10-13 10:00:00', 'UTC'),
        'duration_minutes' => 60,
    ]);

    $session->update([
        'scheduled_at' => Carbon::parse('2026-10-13 11:15:00', 'UTC'),
        'duration_minutes' => 45,
    ]);
    $session->refresh();

    expect(DB::table('sessions')->where('id', $session->id)->value('scheduled_at'))
        ->toBe('2026-10-13 11:15:00')
        ->and($session->end()->equalTo($session->scheduled_at->copy()->addMinutes(45)))->toBeTrue()
        ->and(DB::table('sessions')->where('id', $session->id)->value('end_at'))
        ->toBe('2026-10-13 12:00:00');
});

it('backfills existing sessions without moving their scheduled instants and indexes end times', function (): void {
    $session = Session::factory()->create([
        'scheduled_at' => Carbon::parse('2026-03-08 06:30:00', 'UTC'),
        'duration_minutes' => 90,
    ]);

    Schema::table('sessions', function (Blueprint $table): void {
        $table->dropIndex('sessions_status_end_at_index');
        $table->dropIndex('sessions_end_at_index');
        $table->dropColumn('end_at');
    });

    $scheduledAtBefore = DB::table('sessions')->where('id', $session->id)->value('scheduled_at');
    $migration = require database_path('migrations/2026_10_13_090008_add_end_at_to_sessions_table.php');
    $migration->up();

    $session->refresh();
    $endAtColumn = DB::selectOne(
        "SELECT is_nullable FROM information_schema.columns WHERE table_name = 'sessions' AND column_name = 'end_at'",
    );
    $indexes = DB::table('pg_indexes')
        ->where('tablename', 'sessions')
        ->pluck('indexname')
        ->all();

    expect(DB::table('sessions')->where('id', $session->id)->value('scheduled_at'))->toBe($scheduledAtBefore)
        ->and($session->end()->equalTo($session->scheduled_at->copy()->addMinutes(90)))->toBeTrue()
        ->and(DB::table('sessions')->where('id', $session->id)->value('end_at'))
        ->toBe('2026-03-08 08:00:00')
        ->and($endAtColumn->is_nullable)->toBe('NO')
        ->and($indexes)->toContain('sessions_end_at_index', 'sessions_status_end_at_index');
});

it('configures the uncompleted lifecycle deadline at 48 hours', function (): void {
    expect(config('scheduling.not_completed_after_hours'))->toBe(48);
});
