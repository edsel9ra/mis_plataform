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
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

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

function pathApiPlan(string $code): Plan
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

function pathApiSubscribe(User $user, Plan $plan): Subscription
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

function apiPath(array $attributes = []): LearningPath
{
    return LearningPath::create(array_merge([
        'title' => ['es' => 'Ruta', 'en' => 'Path'],
        'is_active' => true,
    ], $attributes));
}

function apiModule(LearningPath $path, int $order = 1): PathModule
{
    return PathModule::create([
        'learning_path_id' => $path->id,
        'title' => "Módulo {$order}",
        'order' => $order,
    ]);
}

function apiVisibleModule(User $subject, int $order = 1): PathModule
{
    $path = apiPath();
    UserLearningProgress::create([
        'user_id' => $subject->id,
        'learning_path_id' => $path->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    return apiModule($path, $order);
}

it('links a challenge to a consultable module through the endpoint and returns the contract', function () {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create();
    $module = apiVisibleModule($this->mentee);

    $this->actingAs($this->mentee)
        ->putJson("/api/v1/challenges/{$challenge->id}/path-module", [
            'path_module_id' => $module->id,
        ])
        ->assertOk()
        ->assertJsonPath('challenge_id', $challenge->id)
        ->assertJsonPath('path_module.id', $module->id)
        ->assertJsonPath('path_module.title', $module->title)
        ->assertJsonPath('path_module.learning_path_id', $module->learning_path_id);

    expect($challenge->fresh()->path_module_id)->toBe($module->id);
});

it('removes the link with null and rejects invalid bodies', function () {
    $module = apiVisibleModule($this->mentee);
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create();
    $challenge->forceFill(['path_module_id' => $module->id])->save();

    $this->actingAs($this->mentee)
        ->putJson("/api/v1/challenges/{$challenge->id}/path-module", [
            'path_module_id' => null,
        ])
        ->assertOk()->assertJsonPath('path_module', null);
    expect($challenge->fresh()->path_module_id)->toBeNull();

    $this->actingAs($this->mentee)
        ->putJson("/api/v1/challenges/{$challenge->id}/path-module", [])
        ->assertUnprocessable()->assertJsonValidationErrors(['path_module_id']);
    $this->actingAs($this->mentee)
        ->putJson("/api/v1/challenges/{$challenge->id}/path-module", [
            'path_module_id' => 'not-a-uuid',
        ])
        ->assertUnprocessable()->assertJsonValidationErrors(['path_module_id']);
});

it('denies linking another users challenge and a module on a non-consultable path', function () {
    $otherMentee = User::withoutSyncingToSearch(fn () => User::factory()->create(['client_type' => 'personal']));
    $otherRelationship = MentorshipRelationship::create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $otherMentee->id,
        'mentor_id' => $this->mentor->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    $foreign = Challenge::factory()->forRelationship($otherRelationship)->create();
    $module = apiVisibleModule($this->mentee);

    $this->actingAs($this->mentee)
        ->putJson("/api/v1/challenges/{$foreign->id}/path-module", [
            'path_module_id' => $module->id,
        ])
        ->assertNotFound();
    expect($foreign->fresh()->path_module_id)->toBeNull();

    $hidden = apiModule(apiPath());
    $own = Challenge::factory()->forRelationship($this->relationship)->create();
    $this->actingAs($this->mentee)
        ->putJson("/api/v1/challenges/{$own->id}/path-module", [
            'path_module_id' => $hidden->id,
        ])
        ->assertNotFound();
    expect($own->fresh()->path_module_id)->toBeNull();
});

it('serves the subjects own learning-path metrics with the exact contract', function () {
    $path = apiPath();
    UserLearningProgress::create([
        'user_id' => $this->mentee->id,
        'learning_path_id' => $path->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);
    $moduleA = apiModule($path, 1);
    $moduleB = apiModule($path, 2);

    $done = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'completed']);
    $pending = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'in_progress']);
    $done->forceFill(['path_module_id' => $moduleA->id])->save();
    $pending->forceFill(['path_module_id' => $moduleB->id])->save();
    UserModuleCompletion::create([
        'user_id' => $this->mentee->id,
        'path_module_id' => $moduleA->id,
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($this->mentee)
        ->getJson('/api/v1/progress/learning-paths')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [[
                'learning_path' => ['id', 'title'],
                'rate' => ['state', 'value', 'numerator', 'denominator', 'source'],
                'modules' => [[
                    'id', 'title', 'position',
                    'activity_rate' => ['state', 'value', 'numerator', 'denominator', 'source'],
                    'completion' => ['state', 'completed_at'],
                ]],
            ]],
        ]);

    $entry = collect($response->json('data'))->firstWhere('learning_path.id', $path->id);
    expect($entry)->not->toBeNull()
        ->and($entry['rate']['state'])->toBe('value')
        ->and($entry['rate']['value'])->toBe(50)
        ->and($entry['rate']['numerator'])->toBe(1)
        ->and($entry['rate']['denominator'])->toBe(2);

    $first = collect($entry['modules'])->firstWhere('id', $moduleA->id);
    $second = collect($entry['modules'])->firstWhere('id', $moduleB->id);
    expect($first['activity_rate']['value'])->toBe(100)
        ->and($first['completion']['state'])->toBe('completed')
        ->and($first['completion']['completed_at'])->not->toBeNull()
        ->and($second['activity_rate']['value'])->toBe(0)
        ->and($second['completion']['state'])->toBe('not_completed')
        ->and($second['completion']['completed_at'])->toBeNull();
});

it('reports no_data for modules without eligible challenges and zero as a real metric', function () {
    $path = apiPath();
    UserLearningProgress::create([
        'user_id' => $this->mentee->id,
        'learning_path_id' => $path->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);
    $emptyModule = apiModule($path, 1);
    $zeroModule = apiModule($path, 2);
    Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'assigned'])
        ->forceFill(['path_module_id' => $zeroModule->id])->save();

    $entry = collect(
        $this->actingAs($this->mentee)->getJson('/api/v1/progress/learning-paths')->json('data')
    )->firstWhere('learning_path.id', $path->id);

    $empty = collect($entry['modules'])->firstWhere('id', $emptyModule->id);
    $zero = collect($entry['modules'])->firstWhere('id', $zeroModule->id);
    expect($empty['activity_rate']['state'])->toBe('no_data')
        ->and($empty['activity_rate']['value'])->toBeNull()
        ->and($empty['completion']['state'])->toBe('no_data')
        ->and($zero['activity_rate']['state'])->toBe('zero')
        ->and($zero['activity_rate']['value'])->toBe(0)
        ->and($zero['activity_rate']['numerator'])->toBe(0)
        ->and($zero['activity_rate']['denominator'])->toBe(1)
        ->and($entry['rate']['state'])->toBe('zero');
});

it('marks the whole path incomplete when a linked challenge has an unknown status', function () {
    $path = apiPath();
    UserLearningProgress::create([
        'user_id' => $this->mentee->id,
        'learning_path_id' => $path->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);
    $module = apiModule($path, 1);
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'assigned']);
    $challenge->forceFill(['path_module_id' => $module->id])->save();
    DB::table('challenges')->where('id', $challenge->id)->update(['status' => 'bogus']);

    $entry = collect(
        $this->actingAs($this->mentee)->getJson('/api/v1/progress/learning-paths')->json('data')
    )->firstWhere('learning_path.id', $path->id);

    $moduleEntry = collect($entry['modules'])->firstWhere('id', $module->id);
    expect($moduleEntry['activity_rate']['state'])->toBe('incomplete')
        ->and($moduleEntry['activity_rate']['value'])->toBeNull()
        ->and($moduleEntry['completion']['state'])->toBe('incomplete')
        ->and($entry['rate']['state'])->toBe('incomplete');
});

it('lets the assigned mentor read the mentee learning-path metrics and hides other mentees', function () {
    pathApiSubscribe($this->mentee, pathApiPlan('path-api'));
    $module = apiVisibleModule($this->mentee);

    $response = $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/progress/learning-paths")
        ->assertOk();
    expect(collect($response->json('data'))->firstWhere('learning_path.id', $module->learning_path_id))
        ->not->toBeNull();

    $otherMentee = User::withoutSyncingToSearch(fn () => User::factory()->create(['client_type' => 'personal']));
    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$otherMentee->id}/progress/learning-paths")
        ->assertNotFound();

    $this->actingAs($this->mentee)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/progress/learning-paths")
        ->assertForbidden();
});

it('keeps the query count bounded regardless of the number of modules and challenges', function () {
    $path = apiPath();
    UserLearningProgress::create([
        'user_id' => $this->mentee->id,
        'learning_path_id' => $path->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);
    foreach (range(1, 4) as $order) {
        $module = apiModule($path, $order);
        foreach (range(1, 3) as $i) {
            Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'completed'])
                ->forceFill(['path_module_id' => $module->id])->save();
        }
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($this->mentee)->getJson('/api/v1/progress/learning-paths')->assertOk();
    $count = count(DB::getQueryLog());

    expect($count)->toBeLessThanOrEqual(8);
});
