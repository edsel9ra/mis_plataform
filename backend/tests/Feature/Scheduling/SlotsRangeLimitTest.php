<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class)->group('scheduling', 'availability');

function slotsRangeMentor(array $attributes = []): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create(array_merge([
        'is_active' => true,
    ], $attributes)));
}

it('accepts a range of exactly 31 days measured as real instants', function (): void {
    $mentor = slotsRangeMentor();

    $this->actingAs($mentor)
        ->getJson("/api/v1/mentors/{$mentor->id}/availability/slots?from=2026-10-01T00:00:00Z&to=2026-11-01T00:00:00Z&duration_minutes=30")
        ->assertOk();
});

it('rejects a range of 31 days plus one minute with a translated error on `to`', function (string $locale, string $message): void {
    $mentor = slotsRangeMentor();

    $this->actingAs($mentor)
        ->withHeader('X-MIS-Locale', $locale)
        ->getJson("/api/v1/mentors/{$mentor->id}/availability/slots?from=2026-10-01T00:00:00Z&to=2026-11-01T00:01:00Z&duration_minutes=30")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to'])
        ->assertJsonPath('errors.to.0', $message);
})->with([
    'es' => ['es', 'El rango de consulta no puede superar 31 días.'],
    'en' => ['en', 'The query range cannot exceed 31 days.'],
    'pt' => ['pt', 'O intervalo de consulta não pode exceder 31 dias.'],
]);

it('measures the range in real instants across a DST change, not in wall-clock hours', function (): void {
    // America/New_York falls back on 2026-11-01 02:00, inside both ranges.
    // 31 wall-clock days (Oct 25 → Nov 25) span 31 days + 1 real hour and
    // are rejected; 30 days 23 h of wall time (Oct 25 → Nov 24 23:00) span
    // exactly 31 real days and are accepted.
    $mentor = slotsRangeMentor(['timezone' => 'America/New_York']);
    $base = "/api/v1/mentors/{$mentor->id}/availability/slots?duration_minutes=30";

    $this->actingAs($mentor)
        ->getJson($base.'&from=2026-10-25T00:00:00&to=2026-11-25T00:00:00')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);

    $this->actingAs($mentor)
        ->getJson($base.'&from=2026-10-25T00:00:00&to=2026-11-24T23:00:00')
        ->assertOk();
});

it('respects a configured limit different from the default', function (): void {
    config(['scheduling.slots_max_range_days' => 7]);
    $mentor = slotsRangeMentor();
    $base = "/api/v1/mentors/{$mentor->id}/availability/slots?duration_minutes=30";

    $this->actingAs($mentor)
        ->getJson($base.'&from=2026-10-01T00:00:00Z&to=2026-10-08T00:00:00Z')
        ->assertOk();

    $this->actingAs($mentor)
        ->getJson($base.'&from=2026-10-01T00:00:00Z&to=2026-10-08T00:00:01Z')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
});

it('does not invoke the slots service when the range is rejected', function (): void {
    $mentor = slotsRangeMentor();

    // AvailabilitySlotsService always reads mentor availability and session
    // data; proving none of those tables is touched proves it never ran.
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->actingAs($mentor)
        ->getJson("/api/v1/mentors/{$mentor->id}/availability/slots?from=2026-10-01T00:00:00Z&to=2027-10-01T00:00:00Z&duration_minutes=30")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);

    expect(collect($queries)->filter(
        fn (string $sql): bool => str_contains($sql, 'availability') || str_contains($sql, 'sessions')
    ))->toBeEmpty();
});
