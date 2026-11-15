<?php

use App\Models\CompetencyAssessmentLink;
use App\Models\EvidenceDeletionActorLink;
use App\Models\EvidenceDeletionEvent;
use App\Models\PersonalityAssessment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * T014.13 — automatic expiry of minimal deletion events after their
 * retention window (política-retención §5.2 and the §7 exception, plan §9).
 * This command is the only purge the policy enables; it always records the
 * run on the progress channel without reproducing any deleted data.
 */
uses(RefreshDatabase::class)->group('progress');

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->auditPermission = Permission::findOrCreate('audit.identify_actor', 'web');
    Role::findOrCreate('super_admin', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('mentee', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->travelTo(CarbonImmutable::parse('2026-11-15T10:00:00Z'));
});

function edeUser(string $role, array $attributes = []): User
{
    $user = User::withoutSyncingToSearch(fn () => User::factory()->create(array_merge([
        'client_type' => 'personal',
        'is_active' => true,
        'role' => $role,
    ], $attributes)));
    $user->assignRole($role);

    return $user;
}

function edeEvent(array $attributes = []): EvidenceDeletionEvent
{
    $event = EvidenceDeletionEvent::factory()->create($attributes);
    EvidenceDeletionActorLink::factory()->create([
        'event_id' => $event->id,
        'actor_id' => User::factory(),
    ]);

    return $event->fresh();
}

function edeExpiredActivities(): \Illuminate\Database\Eloquent\Collection
{
    return Activity::query()
        ->where('event', 'competency.evidence_deletion_events_expired')
        ->latest('id')
        ->get();
}

it('purges only events at or past their expiry and cascades the actor links', function (): void {
    $past = edeEvent(['expires_at' => now()->subDay()]);
    $edge = edeEvent(['expires_at' => now()]);
    $future = edeEvent(['expires_at' => now()->addDay()]);

    $this->artisan('app:expire-evidence-deletion-events')->assertSuccessful();

    expect(EvidenceDeletionEvent::query()->find($past->id))->toBeNull()
        ->and(EvidenceDeletionEvent::query()->find($edge->id))->toBeNull()
        ->and(EvidenceDeletionEvent::query()->find($future->id))->not->toBeNull()
        ->and(EvidenceDeletionActorLink::query()->where('event_id', $past->id)->count())->toBe(0)
        ->and(EvidenceDeletionActorLink::query()->where('event_id', $edge->id)->count())->toBe(0)
        ->and(EvidenceDeletionActorLink::query()->where('event_id', $future->id)->count())->toBe(1);
});

it('makes purged events unresolvable through the audit endpoint', function (): void {
    $auditor = edeUser('super_admin');
    $auditor->givePermissionTo($this->auditPermission);

    $past = edeEvent(['expires_at' => now()->subDay()]);
    $future = edeEvent(['expires_at' => now()->addDay()]);

    $this->artisan('app:expire-evidence-deletion-events')->assertSuccessful();

    $this->actingAs($auditor)
        ->getJson("/api/v1/admin/audit/evidence-deletion-events/{$past->id}/actor")
        ->assertNotFound();

    $this->actingAs($auditor)
        ->getJson("/api/v1/admin/audit/evidence-deletion-events/{$future->id}/actor")
        ->assertOk();
});

it('records exactly one sanitized activity per run without reproducing purged data', function (): void {
    $subject = edeUser('mentee');
    $actor = edeUser('admin');
    $past = edeEvent(['subject_id' => $subject->id, 'expires_at' => now()->subDay()]);
    $edge = edeEvent(['subject_id' => $subject->id, 'expires_at' => now()]);
    EvidenceDeletionActorLink::query()->where('event_id', $past->id)->update(['actor_id' => $actor->id]);
    EvidenceDeletionActorLink::query()->where('event_id', $edge->id)->update(['actor_id' => $actor->id]);
    edeEvent(['expires_at' => now()->addDay()]);

    $this->artisan('app:expire-evidence-deletion-events')->assertSuccessful();

    $activities = edeExpiredActivities();
    expect($activities)->toHaveCount(1);

    $activity = $activities->first();
    expect($activity->causer_id)->toBeNull()
        ->and($activity->causer_type)->toBeNull()
        ->and($activity->properties->toArray())->toHaveCount(2)
        ->and($activity->properties['expired_count'])->toBe(2)
        ->and($activity->properties['occurred_at'])->toBe(CarbonImmutable::now()->toIso8601String());

    $json = json_encode($activity->properties->toArray());
    expect($json)->not->toContain($past->id)
        ->and($json)->not->toContain($edge->id)
        ->and($json)->not->toContain($past->actor_pseudonym)
        ->and($json)->not->toContain($edge->actor_pseudonym)
        ->and($json)->not->toContain($subject->id)
        ->and($json)->not->toContain($actor->id);
});

it('succeeds and records a zero count when nothing is expired', function (): void {
    $future = edeEvent(['expires_at' => now()->addDay()]);

    $this->artisan('app:expire-evidence-deletion-events')->assertSuccessful();

    expect(EvidenceDeletionEvent::query()->find($future->id))->not->toBeNull();

    $activities = edeExpiredActivities();
    expect($activities)->toHaveCount(1)
        ->and($activities->first()->properties['expired_count'])->toBe(0);
});

it('purges real recorded events exactly when their written expiry arrives', function (): void {
    $admin = edeUser('super_admin');
    $user = edeUser('mentee');
    $source = PersonalityAssessment::factory()->create([
        'user_id' => $user->id,
        'completed_at' => now(),
    ]);
    CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($source)
        ->forOceanFactor('O')
        ->create(['user_id' => $user->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/admin/assessments/{$source->id}")
        ->assertOk();

    $event = EvidenceDeletionEvent::query()->sole();
    // The stored timestampTz keeps the app-timezone wall clock: reinterpret
    // it in the app zone so CarbonImmutable::now() writes the same string.
    $expiryWall = CarbonImmutable::parse(
        $event->expires_at->format('Y-m-d H:i:s'),
        config('app.timezone'),
    );

    $this->travelTo($expiryWall->subMinute());
    $this->artisan('app:expire-evidence-deletion-events')->assertSuccessful();
    expect(EvidenceDeletionEvent::query()->find($event->id))->not->toBeNull();

    $this->travelTo($expiryWall);
    $this->artisan('app:expire-evidence-deletion-events')->assertSuccessful();
    expect(EvidenceDeletionEvent::query()->find($event->id))->toBeNull()
        ->and(EvidenceDeletionActorLink::query()->where('event_id', $event->id)->count())->toBe(0);
});

it('still purges already-expired events when retention is disabled', function (): void {
    config(['progress.deletion_event_retention_months' => 0]);
    $past = edeEvent(['expires_at' => now()->subDay()]);

    $this->artisan('app:expire-evidence-deletion-events')->assertSuccessful();

    expect(EvidenceDeletionEvent::query()->find($past->id))->toBeNull()
        ->and(edeExpiredActivities()->first()->properties['expired_count'])->toBe(1);
});

it('schedules the purge daily without overlapping', function (): void {
    $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());
    $event = $events->first(
        fn ($event) => str_contains((string) $event->command, 'app:expire-evidence-deletion-events'),
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
