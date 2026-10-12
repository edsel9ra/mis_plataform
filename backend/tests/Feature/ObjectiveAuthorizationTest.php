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

it('lets a mentee propose an objective without gaining administration rights', function () {
    $this->actingAs($this->mentee)
        ->postJson('/api/v1/objectives/proposals', [
            'title' => 'Quiero mejorar mi liderazgo',
            'success_criteria' => 'Liderar una reunión al mes',
            'due_at' => now()->addMonth()->toIso8601String(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.proposed_by', $this->mentee->id)
        ->assertJsonPath('data.owner_id', $this->mentee->id)
        ->assertJsonPath('data.permissions.update', false);

    $objective = Objective::firstOrFail();

    // The proposer cannot manage the objective they proposed.
    $this->actingAs($this->mentee)
        ->putJson("/api/v1/objectives/{$objective->id}", ['title' => 'Cambiado'])
        ->assertForbidden();

    // The assigned mentor can.
    $this->actingAs($this->mentor)
        ->putJson("/api/v1/objectives/{$objective->id}", ['title' => 'Alineado con el mentor'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Alineado con el mentor');
});

it('lets a mentor manage only their own mentee objectives', function () {
    $otherMentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'client_type' => 'personal',
    ]));
    $otherMentor->assignRole('mentor');

    $objective = Objective::factory()->individual($this->mentee)->create([
        'relationship_id' => $this->relationship->id,
        'created_by' => $this->mentor->id,
    ]);

    $this->actingAs($this->mentor)
        ->putJson("/api/v1/objectives/{$objective->id}", ['title' => 'Mío'])
        ->assertOk();

    $this->actingAs($otherMentor)
        ->putJson("/api/v1/objectives/{$objective->id}", ['title' => 'Ajeno'])
        ->assertForbidden();

    expect($objective->fresh()->title)->toBe('Mío');
});

it('denies an outsider any access without leaking data', function () {
    $objective = Objective::factory()->individual($this->mentee)->create([
        'relationship_id' => $this->relationship->id,
        'created_by' => $this->mentor->id,
    ]);

    $outsider = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
    ]));
    $outsider->assignRole('mentee');

    $this->actingAs($outsider)
        ->getJson("/api/v1/objectives?subject_type=user&subject_id={$this->mentee->id}")
        ->assertForbidden();

    $this->actingAs($outsider)
        ->putJson("/api/v1/objectives/{$objective->id}", ['title' => 'Hackeado'])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->postJson("/api/v1/objectives/{$objective->id}/status", ['status' => 'achieved'])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->postJson("/api/v1/objectives/{$objective->id}/evidences", ['note' => 'Falso'])
        ->assertForbidden();

    expect($objective->fresh()->title)->not->toBe('Hackeado')
        ->and($objective->fresh()->status->value)->not->toBe('achieved')
        ->and($objective->evidences()->count())->toBe(0);
});
