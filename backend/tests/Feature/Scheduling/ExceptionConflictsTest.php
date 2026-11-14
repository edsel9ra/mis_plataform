<?php

use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('scheduling', 'availability');

function exceptionConflictMentor(): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'is_active' => true,
    ]));
}

function exceptionConflictSession(User $mentor, string|DateTimeInterface $start, array $attributes = []): Session
{
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => User::factory()->create()->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);

    return Session::factory()->create(array_merge([
        'relationship_id' => $relationship->id,
        'scheduled_at' => dbInstant($start),
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ], $attributes));
}

function exceptionConflictIds(TestResponse|Illuminate\Testing\TestResponse $response): array
{
    return array_column($response->json('conflicting_sessions') ?? [], 'id');
}

it('lists future scheduled sessions inside a single all-day block', function (): void {
    $mentor = exceptionConflictMentor();
    // App timezone is America/Mexico_City; UTC noon on the exception date is inside it.
    $inside = exceptionConflictSession($mentor, '2026-12-24T17:00:00Z', ['title' => 'Bloqueada']);
    exceptionConflictSession($mentor, '2026-12-26T17:00:00Z', ['title' => 'Fuera']);

    $response = $this->actingAs($mentor)
        ->postJson('/api/v1/availability/exceptions', [
            'mode' => 'block',
            'start_date' => '2026-12-24',
            'end_date' => null,
            'start_time' => null,
            'end_time' => null,
            'timezone' => 'UTC',
        ])
        ->assertCreated()
        ->assertJsonPath('mode', 'block');

    $conflicts = $response->json('conflicting_sessions');

    expect($conflicts)->toBeArray()->toHaveCount(1)
        ->and($conflicts[0]['id'])->toBe($inside->id)
        ->and($conflicts[0]['title'])->toBe('Bloqueada')
        ->and($conflicts[0]['duration_minutes'])->toBe(60)
        ->and($conflicts[0]['scheduled_at'])->toBe($inside->scheduled_at->toJSON())
        ->and(array_keys($conflicts[0]))->toBe(['id', 'title', 'scheduled_at', 'duration_minutes']);
});

it('lists sessions across an inclusive date range', function (): void {
    $mentor = exceptionConflictMentor();
    $first = exceptionConflictSession($mentor, '2026-12-24T17:00:00Z');
    $last = exceptionConflictSession($mentor, '2026-12-26T17:00:00Z');
    exceptionConflictSession($mentor, '2026-12-27T17:00:00Z');

    $response = $this->actingAs($mentor)
        ->postJson('/api/v1/availability/exceptions', [
            'mode' => 'block',
            'start_date' => '2026-12-24',
            'end_date' => '2026-12-26',
            'start_time' => null,
            'end_time' => null,
            'timezone' => 'UTC',
        ])
        ->assertCreated();

    expect(exceptionConflictIds($response))->toBe([$first->id, $last->id]);
});

it('only lists sessions overlapping a time band, not contiguous ones', function (): void {
    $mentor = exceptionConflictMentor();
    $inside = exceptionConflictSession($mentor, '2026-12-24T15:30:00Z'); // 10:30 Mexico City
    exceptionConflictSession($mentor, '2026-12-24T19:00:00Z'); // 14:00, outside band end 13:00
    exceptionConflictSession($mentor, '2026-12-24T15:00:00Z'); // ends 10:00, exactly band start

    $response = $this->actingAs($mentor)
        ->postJson('/api/v1/availability/exceptions', [
            'mode' => 'block',
            'start_date' => '2026-12-24',
            'end_date' => null,
            'start_time' => '10:00',
            'end_time' => '13:00',
            'timezone' => 'America/Mexico_City',
        ])
        ->assertCreated();

    expect(exceptionConflictIds($response))->toBe([$inside->id]);
});

it('excludes sessions that are not in scheduled status', function (): void {
    $mentor = exceptionConflictMentor();
    $listed = exceptionConflictSession($mentor, '2026-12-24T17:00:00Z');
    foreach (['canceled', 'not_completed', 'completed', 'in_progress'] as $status) {
        exceptionConflictSession($mentor, '2026-12-24T18:00:00Z', ['status' => $status]);
    }

    $response = $this->actingAs($mentor)
        ->postJson('/api/v1/availability/exceptions', [
            'mode' => 'block',
            'start_date' => '2026-12-24',
            'end_date' => null,
            'start_time' => null,
            'end_time' => null,
            'timezone' => 'UTC',
        ])
        ->assertCreated();

    expect(exceptionConflictIds($response))->toBe([$listed->id]);
});

it('excludes past sessions and sessions of another mentor', function (): void {
    $mentor = exceptionConflictMentor();
    $other = exceptionConflictMentor();
    $listed = exceptionConflictSession($mentor, '2026-12-24T17:00:00Z');
    exceptionConflictSession($mentor, CarbonImmutable::now()->subHour());
    exceptionConflictSession($other, '2026-12-24T17:00:00Z');

    $response = $this->actingAs($mentor)
        ->postJson('/api/v1/availability/exceptions', [
            'mode' => 'block',
            'start_date' => CarbonImmutable::now()->subDay()->toDateString(),
            'end_date' => '2026-12-31',
            'start_time' => null,
            'end_time' => null,
            'timezone' => 'UTC',
        ])
        ->assertCreated();

    expect(exceptionConflictIds($response))->toBe([$listed->id]);
});

it('compares real instants when the exception timezone differs from the app timezone', function (): void {
    $mentor = exceptionConflictMentor();
    // Exception date 2026-12-24 in America/New_York spans 05:00Z (24th) → 05:00Z (25th).
    $inside = exceptionConflictSession($mentor, '2026-12-25T04:30:00Z'); // 23:30 NY on the 24th
    exceptionConflictSession($mentor, '2026-12-25T05:30:00Z'); // 00:30 NY on the 25th

    $response = $this->actingAs($mentor)
        ->postJson('/api/v1/availability/exceptions', [
            'mode' => 'block',
            'start_date' => '2026-12-24',
            'end_date' => null,
            'start_time' => null,
            'end_time' => null,
            'timezone' => 'America/New_York',
        ])
        ->assertCreated();

    expect(exceptionConflictIds($response))->toBe([$inside->id]);
});

it('lists conflicts when updating an exception to cover a session and omits the key for open mode and reads', function (): void {
    $mentor = exceptionConflictMentor();
    $session = exceptionConflictSession($mentor, '2026-12-24T17:00:00Z');

    $created = $this->actingAs($mentor)
        ->postJson('/api/v1/availability/exceptions', [
            'mode' => 'open',
            'start_date' => '2026-12-24',
            'end_date' => null,
            'start_time' => null,
            'end_time' => null,
            'timezone' => 'UTC',
        ])
        ->assertCreated();

    expect($created->json())->not->toHaveKey('conflicting_sessions');

    $exceptionId = $created->json('id');

    $updated = $this->actingAs($mentor)
        ->putJson("/api/v1/availability/exceptions/{$exceptionId}", [
            'mode' => 'block',
            'start_date' => '2026-12-24',
            'end_date' => null,
            'start_time' => null,
            'end_time' => null,
            'timezone' => 'UTC',
        ])
        ->assertOk();

    expect(exceptionConflictIds($updated))->toBe([$session->id]);

    $blockedAgain = $this->actingAs($mentor)
        ->putJson("/api/v1/availability/exceptions/{$exceptionId}", [
            'mode' => 'open',
            'start_date' => '2026-12-24',
            'end_date' => null,
            'start_time' => null,
            'end_time' => null,
            'timezone' => 'UTC',
        ])
        ->assertOk();

    expect($blockedAgain->json())->not->toHaveKey('conflicting_sessions')
        ->and($session->refresh()->status)->toBe('scheduled');

    $this->actingAs($mentor)
        ->getJson("/api/v1/availability/exceptions/{$exceptionId}")
        ->assertOk()
        ->assertJsonMissingPath('conflicting_sessions');

    $this->actingAs($mentor)
        ->getJson('/api/v1/availability/exceptions')
        ->assertOk()
        ->assertJsonMissingPath('data.0.conflicting_sessions');
});
