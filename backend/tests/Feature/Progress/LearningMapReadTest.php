<?php

use App\Models\Challenge;
use App\Models\LearningPath;
use App\Models\MentorshipRelationship;
use App\Models\PathModule;
use App\Models\User;
use App\Models\UserLearningProgress;
use App\Models\UserModuleCompletion;
use App\Services\Progress\Map\MapPath;
use App\Services\Progress\PathProgressService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Individual map read (RF-013.1–.4, .9): the service returns MapPath DTOs
 * limited to the subject's consultable paths — the same map the mentee sees
 * and the only one the mentor endpoint will be able to serve.
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

function mapTestPath(array $attributes = []): LearningPath
{
    return LearningPath::create(array_merge([
        'title' => ['es' => 'Ruta', 'en' => 'Path'],
        'is_active' => true,
    ], $attributes));
}

function mapTestModule(LearningPath $path, int $order = 1, ?string $title = null): PathModule
{
    return PathModule::create([
        'learning_path_id' => $path->id,
        'title' => $title ?? "Módulo {$order}",
        'order' => $order,
    ]);
}

function mapTestProgress(User $subject, LearningPath $path): void
{
    UserLearningProgress::create([
        'user_id' => $subject->id,
        'learning_path_id' => $path->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);
}

it('returns only the paths the subject can consult', function () {
    $visible = mapTestPath();
    mapTestProgress($this->mentee, $visible);
    mapTestModule($visible, 1);

    $hidden = mapTestPath();
    mapTestModule($hidden, 1);

    $map = app(PathProgressService::class)->mapForSubject($this->mentee);

    expect(array_map(fn (MapPath $path): string => $path->id, $map))
        ->toBe([$visible->id]);
});

it('limits the map to the subject paths so a mentor view can only see those', function () {
    // mapForSubject(subject) is the single source for own and mentor reads:
    // a path the subject cannot consult never reaches any viewer.
    $otherMentee = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
    ]));
    $foreign = mapTestPath();
    mapTestProgress($otherMentee, $foreign);
    mapTestModule($foreign, 1);

    $map = app(PathProgressService::class)->mapForSubject($this->mentee);

    expect($map)->toBe([]);
});

it('orders modules by order with the deterministic id tie-break', function () {
    $path = mapTestPath();
    mapTestProgress($this->mentee, $path);

    $b = mapTestModule($path, 1, 'B');
    $a = mapTestModule($path, 1, 'A');
    $c = mapTestModule($path, 2, 'C');
    [$first, $second] = [$a->id < $b->id ? $a : $b, $a->id < $b->id ? $b : $a];

    $entry = app(PathProgressService::class)->mapForSubject($this->mentee)[0];

    expect(array_map(fn ($module): string => $module->id, $entry->modules))
        ->toBe([$first->id, $second->id, $c->id]);
    expect($entry->versionKey)->toMatch('/^[0-9a-f]{32}$/');
});

it('places linked challenges as elements with their raw source status', function () {
    $path = mapTestPath();
    mapTestProgress($this->mentee, $path);
    $module = mapTestModule($path, 1);

    $done = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'completed']);
    $done->forceFill(['path_module_id' => $module->id])->save();
    $odd = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'assigned']);
    $odd->forceFill(['path_module_id' => $module->id])->save();
    DB::table('challenges')->where('id', $odd->id)->update(['status' => 'bogus']);

    $entry = app(PathProgressService::class)->mapForSubject($this->mentee)[0];
    $elements = $entry->modules[0]->elements;

    expect($elements)->toHaveCount(2);
    $byId = collect($elements)->keyBy('id');
    expect($byId[$done->id]->type->value)->toBe('challenge');
    expect($byId[$done->id]->sourceStatus)->toBe('completed');
    expect($byId[$done->id]->title)->toBe($done->title);
    expect($byId[$odd->id]->sourceStatus)->toBe('bogus');
});

it('never places unlinked challenges or links to modules outside the subject paths', function () {
    $path = mapTestPath();
    mapTestProgress($this->mentee, $path);
    $module = mapTestModule($path, 1);

    $hiddenPath = mapTestPath();
    $hiddenModule = mapTestModule($hiddenPath, 1);

    Challenge::factory()->forRelationship($this->relationship)->create(); // sin vínculo
    $toHidden = Challenge::factory()->forRelationship($this->relationship)->create();
    $toHidden->forceFill(['path_module_id' => $hiddenModule->id])->save();
    $linked = Challenge::factory()->forRelationship($this->relationship)->create();
    $linked->forceFill(['path_module_id' => $module->id])->save();

    $entry = app(PathProgressService::class)->mapForSubject($this->mentee)[0];

    expect($entry->modules[0]->elements)->toHaveCount(1);
    expect($entry->modules[0]->elements[0]->id)->toBe($linked->id);
});

it('keeps metric parity with forSubject for the same data', function () {
    $path = mapTestPath();
    mapTestProgress($this->mentee, $path);
    $moduleA = mapTestModule($path, 1);
    $moduleB = mapTestModule($path, 2);

    $done = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'completed']);
    $pending = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'in_progress']);
    $done->forceFill(['path_module_id' => $moduleA->id])->save();
    $pending->forceFill(['path_module_id' => $moduleB->id])->save();
    UserModuleCompletion::create([
        'user_id' => $this->mentee->id,
        'path_module_id' => $moduleA->id,
        'completed_at' => now(),
    ]);

    $service = app(PathProgressService::class);
    $map = $service->mapForSubject($this->mentee)[0];
    $plain = collect($service->forSubject($this->mentee))->firstWhere('learning_path.id', $path->id);

    expect($map->rate->toArray())->toBe($plain['rate']->toArray());
    foreach ($map->modules as $index => $module) {
        expect($module->activityRate->toArray())->toBe($plain['modules'][$index]['activity_rate']->toArray());
        expect($module->completion->state)->toBe($plain['modules'][$index]['completion']['state']);
        expect($module->completion->completedAt)->toBe($plain['modules'][$index]['completion']['completed_at']);
    }
});

it('keeps the query count bounded regardless of modules and challenges', function () {
    $path = mapTestPath();
    mapTestProgress($this->mentee, $path);
    foreach (range(1, 4) as $order) {
        $module = mapTestModule($path, $order);
        foreach (range(1, 3) as $i) {
            Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'completed'])
                ->forceFill(['path_module_id' => $module->id])->save();
        }
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(PathProgressService::class)->mapForSubject($this->mentee);
    $count = count(DB::getQueryLog());

    expect($count)->toBeLessThanOrEqual(5);
});
