<?php

use App\Models\EvidenceDeletionActorLink;
use App\Models\EvidenceDeletionEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * T014.12 — audit-only identification of the real actor behind a minimal
 * deletion event (política-retención §4.3–§4.5, plan §9). Only the dedicated
 * audit.identify_actor permission resolves the pseudonym; every lookup is
 * audited without reproducing event content or the identified identity; no
 * other surface exposes events, pseudonyms or correspondences.
 */
uses(RefreshDatabase::class)->group('progress');

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->permission = Permission::findOrCreate('audit.identify_actor', 'web');
    foreach (['super_admin', 'admin', 'mentor', 'mentee', 'company_admin', 'employee'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->travelTo(CarbonImmutable::parse('2026-11-15T10:00:00Z'));
});

function edaUser(string $role, array $attributes = []): User
{
    $user = User::withoutSyncingToSearch(fn () => User::factory()->create(array_merge([
        'client_type' => 'personal',
        'is_active' => true,
        'role' => $role,
    ], $attributes)));
    $user->assignRole($role);

    return $user;
}

function edaEvent(User $subject, User $actor, array $attributes = []): EvidenceDeletionEvent
{
    $event = EvidenceDeletionEvent::factory()->create(array_merge([
        'subject_id' => $subject->id,
        'occurred_at' => now(),
        'expires_at' => now()->addMonths(12),
    ], $attributes));

    EvidenceDeletionActorLink::factory()->create([
        'event_id' => $event->id,
        'actor_id' => $actor->id,
    ]);

    return $event;
}

function edaActivities(): \Illuminate\Database\Eloquent\Collection
{
    return Activity::query()
        ->where('event', 'audit.evidence_deletion_actor_identified')
        ->latest('id')
        ->get();
}

it('identifies the actor for a super_admin with the audit permission and audits the lookup', function (): void {
    $auditor = edaUser('super_admin');
    $auditor->givePermissionTo($this->permission);
    $subject = edaUser('mentee');
    $actor = edaUser('admin', ['name' => 'Actora Borrada']);
    $event = edaEvent($subject, $actor);

    $response = $this->actingAs($auditor)
        ->getJson("/api/v1/admin/audit/evidence-deletion-events/{$event->id}/actor")
        ->assertOk();

    expect($response->json('data.event_id'))->toBe($event->id)
        ->and($response->json('data.actor'))->toEqual([
            'id' => $actor->id,
            'name' => $actor->name,
        ]);

    $activities = edaActivities();
    expect($activities)->toHaveCount(1);

    $activity = $activities->first();
    expect($activity->causer_id)->toBe($auditor->id)
        ->and($activity->causer_type)->toBe($auditor->getMorphClass())
        ->and($activity->properties->toArray())->toHaveCount(3)
        ->toHaveKeys(['event_id', 'auditor_id', 'occurred_at'])
        ->and($activity->properties['event_id'])->toBe($event->id)
        ->and($activity->properties['auditor_id'])->toBe($auditor->id)
        ->and($activity->properties['occurred_at'])->toBe(CarbonImmutable::now()->toIso8601String());

    $json = json_encode($activity->properties->toArray());
    expect($json)->not->toContain($actor->id)
        ->and($json)->not->toContain($actor->name)
        ->and($json)->not->toContain($event->actor_pseudonym)
        ->and($json)->not->toContain($subject->id);
});

it('identifies the actor for an admin with the audit permission', function (): void {
    $auditor = edaUser('admin');
    $auditor->givePermissionTo($this->permission);
    $event = edaEvent(edaUser('mentee'), edaUser('admin'));

    $this->actingAs($auditor)
        ->getJson("/api/v1/admin/audit/evidence-deletion-events/{$event->id}/actor")
        ->assertOk()
        ->assertJsonPath('data.event_id', $event->id);
});

it('forbids an admin without the audit permission and records no audit activity', function (): void {
    $auditor = edaUser('admin');
    $event = edaEvent(edaUser('mentee'), edaUser('admin'));

    $this->actingAs($auditor)
        ->getJson("/api/v1/admin/audit/evidence-deletion-events/{$event->id}/actor")
        ->assertForbidden();

    expect(edaActivities())->toHaveCount(0);
});

it('forbids mentors and mentees through the role group', function (): void {
    $event = edaEvent(edaUser('mentee'), edaUser('admin'));
    $mentor = edaUser('mentor');
    $mentor->givePermissionTo($this->permission);
    $mentee = edaUser('mentee');
    $mentee->givePermissionTo($this->permission);

    $this->actingAs($mentor)
        ->getJson("/api/v1/admin/audit/evidence-deletion-events/{$event->id}/actor")
        ->assertForbidden();

    $this->actingAs($mentee)
        ->getJson("/api/v1/admin/audit/evidence-deletion-events/{$event->id}/actor")
        ->assertForbidden();

    expect(edaActivities())->toHaveCount(0);
});

it('rejects unauthenticated requests', function (): void {
    $event = edaEvent(edaUser('mentee'), edaUser('admin'));

    $this->getJson("/api/v1/admin/audit/evidence-deletion-events/{$event->id}/actor")
        ->assertUnauthorized();
});

it('returns 404 for a nonexistent or malformed event id', function (): void {
    $auditor = edaUser('super_admin');
    $auditor->givePermissionTo($this->permission);

    $this->actingAs($auditor)
        ->getJson('/api/v1/admin/audit/evidence-deletion-events/'.Str::uuid().'/actor')
        ->assertNotFound();

    $this->actingAs($auditor)
        ->getJson('/api/v1/admin/audit/evidence-deletion-events/not-a-uuid/actor')
        ->assertNotFound();
});

it('returns 404 for an expired event that has not been purged yet', function (): void {
    $auditor = edaUser('super_admin');
    $auditor->givePermissionTo($this->permission);
    $event = edaEvent(edaUser('mentee'), edaUser('admin'), [
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($auditor)
        ->getJson("/api/v1/admin/audit/evidence-deletion-events/{$event->id}/actor")
        ->assertNotFound();
});

it('returns null actor for a hard-deleted actor and still identifies a soft-deleted one', function (): void {
    $auditor = edaUser('super_admin');
    $auditor->givePermissionTo($this->permission);
    $subject = edaUser('mentee');

    $hardDeleted = edaUser('admin');
    $expiredActorEvent = edaEvent($subject, $hardDeleted);
    $hardDeleted->forceDelete();

    $this->actingAs($auditor)
        ->getJson("/api/v1/admin/audit/evidence-deletion-events/{$expiredActorEvent->id}/actor")
        ->assertOk()
        ->assertJsonPath('data.actor', null);

    $softDeleted = edaUser('admin', ['name' => 'Suave Borrado']);
    $softEvent = edaEvent($subject, $softDeleted);
    $softDeleted->delete();

    $this->actingAs($auditor)
        ->getJson("/api/v1/admin/audit/evidence-deletion-events/{$softEvent->id}/actor")
        ->assertOk()
        ->assertJsonPath('data.actor.id', $softDeleted->id)
        ->assertJsonPath('data.actor.name', 'Suave Borrado');
});

it('grants the permission only to super_admin and admin via the seeder', function (): void {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Role::findByName('super_admin')->hasPermissionTo('audit.identify_actor'))->toBeTrue()
        ->and(Role::findByName('admin')->hasPermissionTo('audit.identify_actor'))->toBeTrue()
        ->and(Role::findByName('mentor')->hasPermissionTo('audit.identify_actor'))->toBeFalse()
        ->and(Role::findByName('mentee')->hasPermissionTo('audit.identify_actor'))->toBeFalse()
        ->and(Role::findByName('company_admin')->hasPermissionTo('audit.identify_actor'))->toBeFalse()
        ->and(Role::findByName('employee')->hasPermissionTo('audit.identify_actor'))->toBeFalse();
});

it('leaks no event, pseudonym or actor identity through the progress panels', function (): void {
    $subject = edaUser('mentee');
    $actor = edaUser('admin');
    $event = edaEvent($subject, $actor);

    $own = $this->actingAs($subject)
        ->getJson('/api/v1/progress/panel')
        ->assertOk();
    $ownJson = json_encode($own->json());
    expect($ownJson)->not->toContain($event->actor_pseudonym)
        ->and($ownJson)->not->toContain($actor->id)
        ->and($ownJson)->not->toContain('evidence_deletion');

    Permission::findOrCreate('progress.view.support', 'web');
    $admin = edaUser('admin');
    $admin->givePermissionTo('progress.view.support');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $support = $this->actingAs($admin)
        ->getJson("/api/v1/admin/progress/panel?user_id={$subject->id}")
        ->assertOk();
    $supportJson = json_encode($support->json());
    expect($supportJson)->not->toContain($event->actor_pseudonym)
        ->and($supportJson)->not->toContain($actor->id)
        ->and($supportJson)->not->toContain('evidence_deletion');
});
