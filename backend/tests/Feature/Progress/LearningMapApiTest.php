<?php

use App\Models\Challenge;
use App\Models\LearningPath;
use App\Models\MentorshipRelationship;
use App\Models\PathModule;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserLearningProgress;
use App\Models\UserModuleCompletion;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Map endpoints (RF-013.2, .9): GET /progress/map for the participant and
 * GET /mentor/users/{user}/progress/map for the authorized view — same
 * ProgressAccessService matrix as the panel and learning-paths endpoints.
 */

beforeEach(function () {
    Role::findOrCreate('mentor');
    Role::findOrCreate('mentee');

    $this->mentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'client_type' => 'personal',
    ]));
    $this->mentor->assignRole('mentor');
    $this->mentee = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
    ]));
    $this->mentee->assignRole('mentee');
    $this->relationship = MentorshipRelationship::create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $this->mentee->id,
        'mentor_id' => $this->mentor->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
});

function mapApiPlan(string $code): Plan
{
    return Plan::create([
        'code' => $code,
        'client_type' => 'personal',
        'name' => ['es' => $code],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_mentors' => 1,
        'entitlements' => [
            'mentor_workspace' => true,
            'challenges' => true,
        ],
        'is_active' => true,
    ]);
}

function mapApiSubscribe(User $user, Plan $plan): Subscription
{
    return Subscription::create([
        'subscriber_type' => 'user',
        'subscriber_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);
}

function mapApiPath(array $attributes = []): LearningPath
{
    return LearningPath::create(array_merge([
        'title' => ['es' => 'Ruta', 'en' => 'Path'],
        'is_active' => true,
    ], $attributes));
}

function mapApiModule(LearningPath $path, int $order = 1): PathModule
{
    return PathModule::create([
        'learning_path_id' => $path->id,
        'title' => "Módulo {$order}",
        'order' => $order,
    ]);
}

function mapApiEnroll(User $subject, LearningPath $path): void
{
    UserLearningProgress::create([
        'user_id' => $subject->id,
        'learning_path_id' => $path->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);
}

it('requires authentication on the own map', function () {
    $this->getJson('/api/v1/progress/map')->assertUnauthorized();
    $this->getJson("/api/v1/mentor/users/{$this->mentee->id}/progress/map")->assertUnauthorized();
});

it('serves the own map with the exact contract and only consultable paths', function () {
    $path = mapApiPath();
    mapApiEnroll($this->mentee, $path);
    $module = mapApiModule($path, 1);
    $done = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'completed']);
    $done->forceFill(['path_module_id' => $module->id])->save();
    UserModuleCompletion::create([
        'user_id' => $this->mentee->id,
        'path_module_id' => $module->id,
        'completed_at' => now(),
    ]);

    // Catalog path without assignment or started progress: never shown.
    mapApiModule(mapApiPath(), 1);

    $response = $this->actingAs($this->mentee)
        ->getJson('/api/v1/progress/map')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [[
                'learning_path' => ['id', 'title'],
                'version_key',
                'rate' => ['state', 'value', 'numerator', 'denominator', 'source'],
                'modules' => [[
                    'id', 'title', 'position',
                    'activity_rate' => ['state', 'value', 'numerator', 'denominator', 'source'],
                    'completion' => ['state', 'completed_at'],
                    'elements' => [['type', 'id', 'title', 'source_status']],
                ]],
            ]],
        ]);

    $data = collect($response->json('data'));
    expect($data)->toHaveCount(1);

    $entry = $data->firstWhere('learning_path.id', $path->id);
    expect($entry)->not->toBeNull()
        ->and($entry['version_key'])->toMatch('/^[0-9a-f]{32}$/')
        ->and($entry['modules'][0]['elements'][0]['type'])->toBe('challenge')
        ->and($entry['modules'][0]['elements'][0]['id'])->toBe($done->id)
        ->and($entry['modules'][0]['elements'][0]['source_status'])->toBe('completed')
        ->and($entry['modules'][0]['completion']['state'])->toBe('completed')
        ->and($entry['rate']['value'])->toBe(100);
});

it('lets the assigned mentor read the mentee map with the same representation', function () {
    mapApiSubscribe($this->mentee, mapApiPlan('map-api'));
    $path = mapApiPath();
    mapApiEnroll($this->mentee, $path);
    $module = mapApiModule($path, 1);
    $linked = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'in_progress']);
    $linked->forceFill(['path_module_id' => $module->id])->save();

    $response = $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/progress/map")
        ->assertOk();

    $entry = collect($response->json('data'))->firstWhere('learning_path.id', $path->id);
    expect($entry)->not->toBeNull()
        ->and($entry['modules'][0]['elements'][0]['source_status'])->toBe('in_progress');
});

it('denies the mentor map outside the authorized subject view without leaking data', function () {
    mapApiSubscribe($this->mentee, mapApiPlan('map-api'));

    // IDOR: a mentee the mentor has no active relationship with gets 404.
    $otherMentee = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
    ]));
    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$otherMentee->id}/progress/map")
        ->assertNotFound();

    // A mentee cannot reach the mentor endpoint at all.
    $this->actingAs($this->mentee)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/progress/map")
        ->assertForbidden();
});

it('rejects the mentor view when the mentee has no workspace entitlement', function () {
    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/progress/map")
        ->assertForbidden()
        ->assertJsonPath('message', __('progress.messages.workspace_locked'));
});

it('lets support with the vigente permission read a map through the mentor route', function () {
    Permission::findOrCreate('progress.view.support');
    $support = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'client_type' => 'personal',
    ]));
    $support->assignRole('mentor');
    $support->givePermissionTo('progress.view.support');

    $path = mapApiPath();
    mapApiEnroll($this->mentee, $path);
    mapApiModule($path, 1);

    // Support reads any subject's map without a mentoring relationship,
    // exactly like the panel and learning-paths endpoints already allow.
    $response = $this->actingAs($support)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/progress/map")
        ->assertOk();

    expect(collect($response->json('data'))->firstWhere('learning_path.id', $path->id))
        ->not->toBeNull();
});
