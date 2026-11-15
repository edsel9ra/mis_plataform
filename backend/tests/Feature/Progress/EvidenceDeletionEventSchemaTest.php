<?php

use App\Models\EvidenceDeletionActorLink;
use App\Models\EvidenceDeletionEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class)->group('progress');

function edeInsertEvent(array $attributes): string
{
    $id = (string) Str::uuid();

    DB::table('evidence_deletion_events')->insert(array_merge([
        'id' => $id,
        'source_type' => 'assessment_result',
        'competency_type' => 'skill_tag',
        'competency_key' => 'tag-1',
        'change_type' => 'source_deleted',
        'actor_pseudonym' => (string) Str::uuid(),
        'occurred_at' => now(),
        'expires_at' => now()->addYear(),
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));

    return $id;
}

function edeInsertActorLink(string $eventId, ?string $actorId): string
{
    $id = (string) Str::uuid();

    DB::table('evidence_deletion_actor_links')->insert([
        'id' => $id,
        'event_id' => $eventId,
        'actor_id' => $actorId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

/**
 * Asserts the statement fails with the given Postgres SQLSTATE. The nested
 * transaction gives each violating statement its own savepoint, so after the
 * expected failure the outer test transaction stays usable — without it,
 * Postgres would abort the whole transaction (25P02) and later statements
 * would fail for the wrong reason.
 */
function edeExpectViolation(Closure $statement, string $sqlstate): void
{
    try {
        DB::transaction($statement);
    } catch (QueryException $e) {
        expect($e->errorInfo[0] ?? (string) $e->getCode())->toBe($sqlstate);

        return;
    }

    throw new RuntimeException("Expected SQLSTATE {$sqlstate}, statement succeeded.");
}

it('creates both tables with only the minimal-event columns', function (): void {
    expect(Schema::hasTable('evidence_deletion_events'))->toBeTrue();
    expect(Schema::hasTable('evidence_deletion_actor_links'))->toBeTrue();

    expect(Schema::hasColumns('evidence_deletion_events', [
        'id', 'subject_id', 'source_type', 'competency_type', 'competency_key',
        'change_type', 'actor_pseudonym', 'occurred_at', 'expires_at',
        'created_at', 'updated_at',
    ]))->toBeTrue();

    foreach ([
        'actor_id', 'source_id', 'assessment_result_id', 'personality_assessment_id',
        'link_id', 'answers', 'score', 'raw_scores', 'results', 'level',
    ] as $forbidden) {
        expect(Schema::hasColumn('evidence_deletion_events', $forbidden))
            ->toBeFalse("forbidden column '{$forbidden}' exists");
    }

    expect(Schema::hasColumns('evidence_deletion_actor_links', [
        'id', 'event_id', 'actor_id', 'created_at', 'updated_at',
    ]))->toBeTrue();
    expect(Schema::getColumnType('evidence_deletion_events', 'actor_pseudonym'))->toBe('uuid');
    expect(Schema::getColumnType('evidence_deletion_actor_links', 'actor_id'))->toBe('uuid');
});

it('accepts a valid event with its separate actor link', function (): void {
    $subject = User::factory()->create();
    $actor = User::factory()->create();

    $eventId = edeInsertEvent(['subject_id' => $subject->id]);
    $linkId = edeInsertActorLink($eventId, $actor->id);

    expect(DB::table('evidence_deletion_events')->where('id', $eventId)->exists())->toBeTrue();
    expect(DB::table('evidence_deletion_actor_links')->where('id', $linkId)->exists())->toBeTrue();
    expect(DB::table('evidence_deletion_actor_links')->where('event_id', $eventId)->value('actor_id'))
        ->toBe($actor->id);
});

it('rejects values outside the discriminated CHECK sets', function (): void {
    $subject = User::factory()->create();

    // bad source_type — check_violation (23514)
    edeExpectViolation(fn () => edeInsertEvent([
        'subject_id' => $subject->id,
        'source_type' => 'session',
    ]), '23514');

    // bad competency_type — 23514
    edeExpectViolation(fn () => edeInsertEvent([
        'subject_id' => $subject->id,
        'competency_type' => 'course',
    ]), '23514');

    // bad change_type — 23514
    edeExpectViolation(fn () => edeInsertEvent([
        'subject_id' => $subject->id,
        'change_type' => 'source_updated',
    ]), '23514');

    // each of the allowed values inserts
    edeInsertEvent(['subject_id' => $subject->id, 'source_type' => 'personality_assessment']);
    edeInsertEvent(['subject_id' => $subject->id, 'competency_type' => 'ocean_factor', 'competency_key' => 'O']);
});

it('rejects a duplicate pseudonym and a second actor link per event', function (): void {
    $subject = User::factory()->create();
    $actor = User::factory()->create();
    $pseudonym = (string) Str::uuid();

    edeInsertEvent(['subject_id' => $subject->id, 'actor_pseudonym' => $pseudonym]);
    edeExpectViolation(fn () => edeInsertEvent([
        'subject_id' => $subject->id,
        'actor_pseudonym' => $pseudonym,
    ]), '23505');

    $eventId = edeInsertEvent(['subject_id' => $subject->id]);
    edeInsertActorLink($eventId, $actor->id);
    edeExpectViolation(fn () => edeInsertActorLink($eventId, null), '23505');
});

it('rejects an actor link pointing at a nonexistent event', function (): void {
    edeExpectViolation(fn () => edeInsertActorLink((string) Str::uuid(), null), '23503');
});

it('removes the actor link when its event is deleted', function (): void {
    $subject = User::factory()->create();
    $actor = User::factory()->create();

    $eventId = edeInsertEvent(['subject_id' => $subject->id]);
    edeInsertActorLink($eventId, $actor->id);

    DB::table('evidence_deletion_events')->where('id', $eventId)->delete();

    expect(DB::table('evidence_deletion_actor_links')->count())->toBe(0);
});

it('nulls the actor reference when the actor is hard-deleted, keeping both rows', function (): void {
    $subject = User::factory()->create();
    $actor = User::factory()->create();

    $eventId = edeInsertEvent(['subject_id' => $subject->id]);
    $linkId = edeInsertActorLink($eventId, $actor->id);

    $actor->forceDelete();

    expect(DB::table('evidence_deletion_events')->where('id', $eventId)->exists())->toBeTrue();
    expect(DB::table('evidence_deletion_actor_links')->where('id', $linkId)->value('actor_id'))->toBeNull();
});

it('keeps events on subject soft-delete and cascades on hard-delete', function (): void {
    $subject = User::factory()->create();
    $actor = User::factory()->create();

    $eventId = edeInsertEvent(['subject_id' => $subject->id]);
    edeInsertActorLink($eventId, $actor->id);

    // Soft delete (User uses SoftDeletes): the minimal event is retained.
    $subject->delete();
    expect(DB::table('evidence_deletion_events')->where('id', $eventId)->exists())->toBeTrue();
    expect(DB::table('evidence_deletion_actor_links')->where('event_id', $eventId)->exists())->toBeTrue();

    // Hard delete: events and their actor links cascade away.
    $subject->forceDelete();
    expect(DB::table('evidence_deletion_events')->where('id', $eventId)->exists())->toBeFalse();
    expect(DB::table('evidence_deletion_actor_links')->where('event_id', $eventId)->exists())->toBeFalse();
});

it('never serializes the internal subject reference on the event model', function (): void {
    $event = EvidenceDeletionEvent::factory()->create();

    expect($event->toArray())->not->toHaveKey('subject_id');
    expect($event->toJson())->not->toContain('subject_id');
});

it('exposes the actor link relations without exposing the actor from the event', function (): void {
    $actor = User::factory()->create();
    $event = EvidenceDeletionEvent::factory()->create();
    $link = EvidenceDeletionActorLink::factory()->create([
        'event_id' => $event->id,
        'actor_id' => $actor->id,
    ]);

    expect($link->event->is($event))->toBeTrue();
    expect($link->actor->is($actor))->toBeTrue();
    expect($event->subject())->not->toBeNull();
    expect(method_exists($event, 'actor'))->toBeFalse();
    expect(method_exists($event, 'actorLink'))->toBeFalse();
});

it('reverses and reapplies cleanly', function (): void {
    $migrationPath = 'database/migrations/2026_11_15_000001_create_evidence_deletion_events_tables.php';

    Artisan::call('migrate:rollback', ['--path' => $migrationPath]);
    expect(Schema::hasTable('evidence_deletion_actor_links'))->toBeFalse();
    expect(Schema::hasTable('evidence_deletion_events'))->toBeFalse();

    Artisan::call('migrate', ['--path' => $migrationPath]);
    expect(Schema::hasTable('evidence_deletion_events'))->toBeTrue();
    expect(Schema::hasTable('evidence_deletion_actor_links'))->toBeTrue();
});
