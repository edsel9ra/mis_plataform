<?php

use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class)->group('scheduling', 'calendar', 'performance');

it('loads a representative calendar range under 2s p95 without N+1', function () {
    $participant = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
        'is_active' => true,
    ]));
    $mentor = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'role' => 'mentor',
        'client_type' => 'personal',
        'is_active' => true,
    ]));
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $participant->id,
        'mentor_id' => $mentor->id,
    ]);

    $start = Carbon\CarbonImmutable::parse('2026-05-01T00:00:00Z');
    for ($i = 0; $i < 300; $i++) {
        Session::factory()->create([
            'relationship_id' => $relationship->id,
            'scheduled_at' => $start->addMinutes($i * 60)->toDateTimeString(),
        ]);
    }

    $from = '2026-05-01T00:00:00Z';
    $to = '2026-05-31T23:59:59Z';
    $url = '/api/v1/sessions?from='.$from.'&to='.$to.'&per_page=100';

    // Warm-up (excludes cold caches / first query plan).
    $this->actingAs($participant)->getJson($url)->assertOk();

    $samples = [];
    for ($i = 0; $i < 30; $i++) {
        $before = microtime(true);
        $this->actingAs($participant)->getJson($url)->assertOk();
        $samples[] = (microtime(true) - $before) * 1000;
    }

    sort($samples);
    $p95 = $samples[(int) ceil(0.95 * count($samples)) - 1];

    // Query count control (no N+1): a single paginated query plus its eager loads.
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $this->actingAs($participant)->getJson($url)->assertOk();
    // Reset listener by re-querying count already captured.
    expect($queries)->toBeLessThanOrEqual(10);

    expect($p95)->toBeLessThanOrEqual(2000.0);
});
