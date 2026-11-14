<?php

use App\Models\LearningPath;
use App\Models\PathModule;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class)->group('progress');

function schemaTestModule(): PathModule
{
    $path = LearningPath::create([
        'title' => ['es' => 'Ruta de prueba'],
        'is_active' => true,
    ]);

    return PathModule::create([
        'learning_path_id' => $path->id,
        'title' => 'Módulo de prueba',
        'order' => 1,
    ]);
}

function schemaTestChallenge(array $attributes = []): string
{
    $relationship = App\Models\MentorshipRelationship::factory()->create();
    $id = (string) Str::uuid();

    DB::table('challenges')->insert(array_merge([
        'id' => $id,
        'relationship_id' => $relationship->id,
        'mentor_id' => $relationship->mentor_id,
        'mentee_id' => $relationship->source_id,
        'title' => 'Reto de prueba',
        'status' => 'assigned',
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));

    return $id;
}

it('adds a nullable uuid path_module_id to challenges and creates user_module_completions', function (): void {
    expect(Schema::hasColumn('challenges', 'path_module_id'))->toBeTrue();
    expect(Schema::getColumnType('challenges', 'path_module_id'))->toBe('uuid');

    $nullable = DB::selectOne(
        "select is_nullable from information_schema.columns where table_name = 'challenges' and column_name = 'path_module_id'"
    );
    expect($nullable->is_nullable)->toBe('YES');

    expect(Schema::hasTable('user_module_completions'))->toBeTrue();
    expect(Schema::hasColumns('user_module_completions', [
        'id', 'user_id', 'path_module_id', 'completed_at', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('rejects a challenge linked to a nonexistent path module', function (): void {
    expect(fn () => schemaTestChallenge(['path_module_id' => (string) Str::uuid()]))
        ->toThrow(QueryException::class);
});

it('sets challenges.path_module_id to null when the path module is deleted', function (): void {
    $module = schemaTestModule();
    $challengeId = schemaTestChallenge(['path_module_id' => $module->id]);

    $module->delete();

    expect(DB::table('challenges')->where('id', $challengeId)->value('path_module_id'))->toBeNull();
});

it('enforces the unique (user_id, path_module_id) completion and its foreign keys', function (): void {
    $module = schemaTestModule();
    $user = User::factory()->create();

    DB::table('user_module_completions')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'path_module_id' => $module->id,
        'completed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('user_module_completions')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'path_module_id' => $module->id,
        'completed_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('user_module_completions')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => (string) Str::uuid(),
        'path_module_id' => $module->id,
        'completed_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('user_module_completions')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'path_module_id' => (string) Str::uuid(),
        'completed_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('cascades completion rows when the user or the module is deleted', function (): void {
    $module = schemaTestModule();
    $otherModule = schemaTestModule();
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    foreach ([[$user->id, $module->id], [$user->id, $otherModule->id], [$otherUser->id, $module->id]] as $pair) {
        DB::table('user_module_completions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $pair[0],
            'path_module_id' => $pair[1],
            'completed_at' => now(),
        ]);
    }

    $user->forceDelete();
    expect(DB::table('user_module_completions')->count())->toBe(1);

    $module->delete();
    expect(DB::table('user_module_completions')->count())->toBe(0);
});

it('reverses and reapplies cleanly, keeping existing challenges with a null link', function (): void {
    $migrationPath = 'database/migrations/2026_11_14_000001_add_challenge_path_module_and_module_completions.php';

    // Roll back just this migration and seed a representative challenge
    // while the column is gone.
    Artisan::call('migrate:rollback', ['--path' => $migrationPath]);
    expect(Schema::hasColumn('challenges', 'path_module_id'))->toBeFalse();
    expect(Schema::hasTable('user_module_completions'))->toBeFalse();

    $challengeId = schemaTestChallenge();

    Artisan::call('migrate', ['--path' => $migrationPath]);
    expect(Schema::hasColumn('challenges', 'path_module_id'))->toBeTrue();
    expect(Schema::hasTable('user_module_completions'))->toBeTrue();
    expect(DB::table('challenges')->where('id', $challengeId)->value('path_module_id'))->toBeNull();

    // A second rollback/migrate roundtrip leaves the schema in the same
    // migrated state for the rest of the suite.
    Artisan::call('migrate:rollback', ['--path' => $migrationPath]);
    Artisan::call('migrate', ['--path' => $migrationPath]);

    expect(Schema::hasColumn('challenges', 'path_module_id'))->toBeTrue();
    expect(Schema::hasTable('user_module_completions'))->toBeTrue();
    expect(DB::table('challenges')->where('id', $challengeId)->value('path_module_id'))->toBeNull();
});
