<?php

use App\Models\MentorshipRelationship;
use App\Models\Objective;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses()->group('progress', 'objectives');

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

it('lets an assigned mentor create an individual objective with criteria and titular', function () {
    $response = $this->actingAs($this->mentor)->postJson('/api/v1/objectives', [
        'scope' => 'individual',
        'owner_id' => $this->mentee->id,
        'title' => 'Mejorar mi comunicación',
        'success_criteria' => 'Dar feedback a mi equipo semanalmente',
        'due_at' => now()->addMonth()->toIso8601String(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.scope', 'individual')
        ->assertJsonPath('data.owner_id', $this->mentee->id)
        ->assertJsonPath('data.status', 'not_started')
        ->assertJsonPath('data.created_by', $this->mentor->id)
        ->assertJsonPath('data.success_criteria', 'Dar feedback a mi equipo semanalmente');

    expect(Objective::count())->toBe(1)
        ->and(Objective::first()->relationship_id)->toBe($this->relationship->id);
});

it('rejects an objective without criteria, titular or due date', function () {
    $this->actingAs($this->mentor)
        ->postJson('/api/v1/objectives', [
            'scope' => 'individual',
            'title' => 'Sin criterio ni titular',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['owner_id', 'success_criteria', 'due_at']);

    expect(Objective::count())->toBe(0);
});

it('rejects prohibited administration fields on creation', function () {
    $this->actingAs($this->mentor)
        ->postJson('/api/v1/objectives', [
            'scope' => 'individual',
            'owner_id' => $this->mentee->id,
            'title' => 'Intento',
            'success_criteria' => 'Criterio',
            'status' => 'achieved',
            'created_by' => $this->mentee->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status', 'created_by']);
});

it('validates status transitions and records the history', function () {
    $objective = Objective::factory()->individual($this->mentee)->create([
        'relationship_id' => $this->relationship->id,
        'created_by' => $this->mentor->id,
        'status' => 'not_started',
    ]);

    $this->actingAs($this->mentor)
        ->postJson("/api/v1/objectives/{$objective->id}/status", ['status' => 'in_progress'])
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->actingAs($this->mentor)
        ->postJson("/api/v1/objectives/{$objective->id}/status", ['status' => 'achieved'])
        ->assertOk()
        ->assertJsonPath('data.status', 'achieved')
        ->assertJsonPath('data.achieved_at', fn ($value) => $value !== null)
        ->assertJsonPath('data.history.0.new_status', 'achieved')
        ->assertJsonPath('data.history.0.old_status', 'in_progress');

    // A self-transition is rejected.
    $this->actingAs($this->mentor)
        ->postJson("/api/v1/objectives/{$objective->id}/status", ['status' => 'achieved'])
        ->assertStatus(422);

    expect(Objective::find($objective->id)->achieved_at)->not->toBeNull();
});

it('attaches evidence with note and author', function () {
    $objective = Objective::factory()->individual($this->mentee)->create([
        'relationship_id' => $this->relationship->id,
        'created_by' => $this->mentor->id,
    ]);

    $this->actingAs($this->mentee)
        ->postJson("/api/v1/objectives/{$objective->id}/evidences", ['note' => 'Documenté avances'])
        ->assertCreated()
        ->assertJsonPath('data.note', 'Documenté avances')
        ->assertJsonPath('data.user_id', $this->mentee->id);

    expect($objective->evidences()->count())->toBe(1);
});

it('lists the objectives of the requested subject for an authorized reader', function () {
    Objective::factory()->individual($this->mentee)->create([
        'relationship_id' => $this->relationship->id,
        'created_by' => $this->mentor->id,
    ]);

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/objectives?subject_type=user&subject_id={$this->mentee->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // The mentee reads their own objectives by default.
    $this->actingAs($this->mentee)
        ->getJson('/api/v1/objectives')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
