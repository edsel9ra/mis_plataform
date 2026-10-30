<?php

use App\Models\MentorshipRelationship;
use App\Models\Session;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('defaults the session timezone to the configured system timezone', function (): void {
    expect((new Session())->timezone)->toBe(config('app.timezone'));
});

it('backfills legacy sessions without changing their scheduled instant', function (): void {
    $session = Session::factory()->create([
        'relationship_id' => MentorshipRelationship::factory(),
        'scheduled_at' => '2026-04-05 13:45:00',
    ]);
    $scheduledAtBefore = DB::table('sessions')
        ->where('id', $session->id)
        ->value('scheduled_at');
    $createdTimezone = DB::table('sessions')
        ->where('id', $session->id)
        ->value('timezone');
    $timezoneColumn = DB::selectOne(
        "SELECT is_nullable, column_default FROM information_schema.columns WHERE table_name = 'sessions' AND column_name = 'timezone'",
    );

    expect($createdTimezone)->toBe(config('scheduling.default_timezone'))
        ->and($timezoneColumn->is_nullable)->toBe('NO')
        ->and($timezoneColumn->column_default)->toContain(config('scheduling.default_timezone'));

    Schema::table('sessions', function (Blueprint $table): void {
        $table->dropColumn('timezone');
    });

    $migration = require database_path('migrations/2026_10_12_090003_add_timezone_to_sessions_table.php');
    $migration->up();

    $session->refresh();

    expect($session->timezone)->toBe(config('app.timezone'))
        ->and(DB::table('sessions')->where('id', $session->id)->value('scheduled_at'))
        ->toBe($scheduledAtBefore);
});
