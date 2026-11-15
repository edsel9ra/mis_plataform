<?php

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\PersonalityAssessment;
use App\Models\SkillTag;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class)->group('progress');

function calTestAssessmentResult(User $user): AssessmentResult
{
    $assessment = Assessment::create([
        'slug' => 'cal-test-'.Str::random(8),
        'title' => ['es' => 'Evaluación de prueba'],
        'questions' => [['id' => 1, 'options' => ['a', 'b']]],
        'is_active' => true,
    ]);

    return AssessmentResult::create([
        'user_id' => $user->id,
        'assessment_id' => $assessment->id,
        'answers' => [['question_id' => 1, 'option_index' => 0]],
        'score' => 80,
        'passed' => true,
        'completed_at' => now(),
    ]);
}

function calTestSkillTag(): SkillTag
{
    return SkillTag::create([
        'name' => 'cal-skill-'.Str::random(8),
        'category' => 'test',
    ]);
}

function calInsertLink(array $attributes): string
{
    $id = (string) Str::uuid();

    DB::table('competency_assessment_links')->insert(array_merge([
        'id' => $id,
        'assessment_result_id' => null,
        'personality_assessment_id' => null,
        'skill_tag_id' => null,
        'ocean_factor' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));

    return $id;
}

/**
 * Asserts the statement fails with the given Postgres SQLSTATE. The nested
 * transaction gives each violating statement its own savepoint, so after the
 * expected failure the outer test transaction stays usable — without it,
 * Postgres would abort the whole transaction (25P02) and later statements
 * would fail for the wrong reason.
 */
function calExpectViolation(Closure $statement, string $sqlstate): void
{
    try {
        DB::transaction($statement);
    } catch (QueryException $e) {
        expect($e->errorInfo[0] ?? (string) $e->getCode())->toBe($sqlstate);

        return;
    }

    throw new RuntimeException("Expected SQLSTATE {$sqlstate}, statement succeeded.");
}

it('creates the table with discriminated source and competency columns', function (): void {
    expect(Schema::hasTable('competency_assessment_links'))->toBeTrue();
    expect(Schema::hasColumns('competency_assessment_links', [
        'id', 'user_id', 'assessment_result_id', 'personality_assessment_id',
        'skill_tag_id', 'ocean_factor', 'created_at', 'updated_at',
    ]))->toBeTrue();
    expect(Schema::getColumnType('competency_assessment_links', 'assessment_result_id'))->toBe('uuid');
    expect(Schema::getColumnType('competency_assessment_links', 'personality_assessment_id'))->toBe('uuid');
    expect(Schema::getColumnType('competency_assessment_links', 'skill_tag_id'))->toBe('uuid');
});

it('accepts a catalog-result to skill link and a personality to OCEAN link', function (): void {
    $user = User::factory()->create();
    $result = calTestAssessmentResult($user);
    $tag = calTestSkillTag();
    $personality = PersonalityAssessment::factory()->create(['user_id' => $user->id]);

    $skillLink = calInsertLink([
        'user_id' => $user->id,
        'assessment_result_id' => $result->id,
        'skill_tag_id' => $tag->id,
    ]);
    $oceanLink = calInsertLink([
        'user_id' => $user->id,
        'personality_assessment_id' => $personality->id,
        'ocean_factor' => 'O',
    ]);

    expect(DB::table('competency_assessment_links')->where('id', $skillLink)->exists())->toBeTrue();
    expect(DB::table('competency_assessment_links')->where('id', $oceanLink)->exists())->toBeTrue();
});

it('rejects rows without exactly one source and exactly one competency', function (): void {
    $user = User::factory()->create();
    $result = calTestAssessmentResult($user);
    $personality = PersonalityAssessment::factory()->create(['user_id' => $user->id]);
    $tag = calTestSkillTag();

    // Both sources set — check_violation (23514)
    calExpectViolation(fn () => calInsertLink([
        'user_id' => $user->id,
        'assessment_result_id' => $result->id,
        'personality_assessment_id' => $personality->id,
        'skill_tag_id' => $tag->id,
    ]), '23514');

    // No source — 23514
    calExpectViolation(fn () => calInsertLink([
        'user_id' => $user->id,
        'skill_tag_id' => $tag->id,
    ]), '23514');

    // Both competency targets set — 23514
    calExpectViolation(fn () => calInsertLink([
        'user_id' => $user->id,
        'assessment_result_id' => $result->id,
        'skill_tag_id' => $tag->id,
        'ocean_factor' => 'C',
    ]), '23514');

    // No competency target — 23514
    calExpectViolation(fn () => calInsertLink([
        'user_id' => $user->id,
        'assessment_result_id' => $result->id,
    ]), '23514');
});

it('rejects an ocean_factor outside O, C, E, A, N', function (): void {
    $user = User::factory()->create();
    $personality = PersonalityAssessment::factory()->create(['user_id' => $user->id]);

    calExpectViolation(fn () => calInsertLink([
        'user_id' => $user->id,
        'personality_assessment_id' => $personality->id,
        'ocean_factor' => 'X',
    ]), '23514');
});

it('rejects duplicate links through the partial unique indexes', function (): void {
    $user = User::factory()->create();
    $result = calTestAssessmentResult($user);
    $personality = PersonalityAssessment::factory()->create(['user_id' => $user->id]);
    $tag = calTestSkillTag();

    calInsertLink([
        'user_id' => $user->id,
        'assessment_result_id' => $result->id,
        'skill_tag_id' => $tag->id,
    ]);
    calExpectViolation(fn () => calInsertLink([
        'user_id' => $user->id,
        'assessment_result_id' => $result->id,
        'skill_tag_id' => $tag->id,
    ]), '23505');

    calInsertLink([
        'user_id' => $user->id,
        'personality_assessment_id' => $personality->id,
        'ocean_factor' => 'E',
    ]);
    calExpectViolation(fn () => calInsertLink([
        'user_id' => $user->id,
        'personality_assessment_id' => $personality->id,
        'ocean_factor' => 'E',
    ]), '23505');
});

it('rejects links pointing at nonexistent rows', function (): void {
    $user = User::factory()->create();
    $result = calTestAssessmentResult($user);
    $tag = calTestSkillTag();

    // All four foreign keys — foreign_key_violation (23503)
    calExpectViolation(fn () => calInsertLink([
        'user_id' => (string) Str::uuid(),
        'assessment_result_id' => $result->id,
        'skill_tag_id' => $tag->id,
    ]), '23503');

    calExpectViolation(fn () => calInsertLink([
        'user_id' => $user->id,
        'assessment_result_id' => (string) Str::uuid(),
        'skill_tag_id' => $tag->id,
    ]), '23503');

    calExpectViolation(fn () => calInsertLink([
        'user_id' => $user->id,
        'personality_assessment_id' => (string) Str::uuid(),
        'ocean_factor' => 'A',
    ]), '23503');

    calExpectViolation(fn () => calInsertLink([
        'user_id' => $user->id,
        'assessment_result_id' => $result->id,
        'skill_tag_id' => (string) Str::uuid(),
    ]), '23503');
});

it('blocks deleting a source or skill tag still referenced by a link', function (): void {
    $user = User::factory()->create();
    $result = calTestAssessmentResult($user);
    $personality = PersonalityAssessment::factory()->create(['user_id' => $user->id]);
    $tag = calTestSkillTag();

    calInsertLink([
        'user_id' => $user->id,
        'assessment_result_id' => $result->id,
        'skill_tag_id' => $tag->id,
    ]);
    calInsertLink([
        'user_id' => $user->id,
        'personality_assessment_id' => $personality->id,
        'ocean_factor' => 'N',
    ]);

    // Non-destructive FKs: sources and tags cannot be silently deleted
    // while a link references them (history is never dropped by cascade).
    calExpectViolation(fn () => DB::table('assessment_results')->where('id', $result->id)->delete(), '23503');
    calExpectViolation(fn () => DB::table('personality_assessments')->where('id', $personality->id)->delete(), '23503');
    calExpectViolation(fn () => DB::table('skill_tags')->where('id', $tag->id)->delete(), '23503');
});

it('removes the links when the owning user is deleted', function (): void {
    $user = User::factory()->create();
    $result = calTestAssessmentResult($user);
    $tag = calTestSkillTag();

    calInsertLink([
        'user_id' => $user->id,
        'assessment_result_id' => $result->id,
        'skill_tag_id' => $tag->id,
    ]);

    $user->forceDelete();

    expect(DB::table('competency_assessment_links')->count())->toBe(0);
});

it('reverses and reapplies cleanly', function (): void {
    $migrationPath = 'database/migrations/2026_11_14_000002_create_competency_assessment_links_table.php';

    Artisan::call('migrate:rollback', ['--path' => $migrationPath]);
    expect(Schema::hasTable('competency_assessment_links'))->toBeFalse();

    Artisan::call('migrate', ['--path' => $migrationPath]);
    expect(Schema::hasTable('competency_assessment_links'))->toBeTrue();
});
