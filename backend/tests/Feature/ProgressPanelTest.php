<?php

use App\Models\Objective;
use App\Models\User;
use App\Services\PlanEntitlementService;
use Illuminate\Support\Facades\Log;

uses()->group('progress', 'panel');

beforeEach(function () {
    $this->user = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
    ]));
});

it('returns the six blocks for the user own panel', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/progress/panel')
        ->assertOk()
        ->assertJsonPath('data.viewer_role', 'self')
        ->assertJsonPath('data.subject.id', $this->user->id)
        ->assertJsonStructure(['data' => [
            'subject' => ['id', 'name'],
            'period',
            'incomplete_blocks',
            'objectives' => ['state', 'metric', 'by_status', 'overdue', 'allow_proposal'],
            'sessions' => ['state', 'metric', 'scheduled'],
            'activities' => ['state', 'metric', 'pending', 'overdue', 'completed'],
            'competencies' => ['state', 'skills', 'ocean'],
            'commitments' => ['state', 'metric', 'items'],
            'collectives',
        ]]);
});

it('distinguishes no_data from a real zero', function () {
    // Without objectives the achievement is "no_data", not 0%.
    $this->actingAs($this->user)
        ->getJson('/api/v1/progress/panel')
        ->assertOk()
        ->assertJsonPath('data.objectives.metric.state', 'no_data')
        ->assertJsonPath('data.objectives.metric.value', null);

    Objective::factory()->individual($this->user)->create(['status' => 'not_started']);
    Objective::factory()->individual($this->user)->create(['status' => 'in_progress']);

    // With objectives and none achieved, the percentage is a real 0.
    $this->actingAs($this->user)
        ->getJson('/api/v1/progress/panel')
        ->assertOk()
        ->assertJsonPath('data.objectives.metric.state', 'zero')
        ->assertJsonPath('data.objectives.metric.value', 0)
        ->assertJsonPath('data.objectives.metric.numerator', 0)
        ->assertJsonPath('data.objectives.metric.denominator', 2);

    Objective::factory()->individual($this->user)->create(['status' => 'achieved']);

    $this->actingAs($this->user)
        ->getJson('/api/v1/progress/panel')
        ->assertOk()
        ->assertJsonPath('data.objectives.metric.state', 'value')
        ->assertJsonPath('data.objectives.metric.numerator', 1)
        ->assertJsonPath('data.objectives.metric.denominator', 3);
});

it('exposes the period that matches each block nature', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/progress/panel')
        ->assertOk()
        ->assertJsonPath('data.objectives.metric.period.type', 'current_state')
        ->assertJsonPath('data.sessions.metric.period.type', 'current_month')
        ->assertJsonPath('data.commitments.metric.period.type', 'next_7_days');
});

it('marks a single failed block incomplete without blanking the panel', function () {
    $this->mock(PlanEntitlementService::class, function ($mock) {
        $mock->shouldReceive('forModel')->andThrow(new RuntimeException('boom'));
    });

    Log::spy();

    $this->actingAs($this->user)
        ->getJson('/api/v1/progress/panel')
        ->assertOk()
        ->assertJsonPath('data.competencies.state', 'incomplete')
        ->assertJsonPath('data.incomplete_blocks.0', 'competencies')
        ->assertJsonStructure(['data' => ['objectives', 'sessions', 'activities', 'commitments']]);

    // The failure is logged structurally, with no objective/personal content (RNF-8).
    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message, $context = []) => $message === 'progress.block_failed'
            && ($context['block'] ?? null) === 'competencies'
            && ($context['subject_id'] ?? null) === $this->user->id
            && ! array_key_exists('objectives', $context)
            && ! array_key_exists('title', $context))
        ->once();
});

it('returns a guided empty state when there is no active process', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/progress/panel')
        ->assertOk()
        ->assertJsonPath('data.objectives.metric.state', 'no_data')
        ->assertJsonPath('data.sessions.metric.state', 'no_data')
        ->assertJsonPath('data.activities.metric.state', 'no_data');
});
