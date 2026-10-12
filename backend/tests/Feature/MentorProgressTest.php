<?php

use App\Models\MentorshipRelationship;
use App\Models\Objective;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses()->group('progress', 'mentor');

function progressMentorPlan(string $code, bool $workspace): Plan
{
    return Plan::create([
        'code' => $code,
        'client_type' => 'personal',
        'name' => ['es' => $code],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_mentors' => 1,
        'entitlements' => ['mentor_workspace' => $workspace],
        'is_active' => true,
    ]);
}

function progressSubscribe(User $user, Plan $plan): Subscription
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

beforeEach(function () {
    Role::findOrCreate('mentor');
    Role::findOrCreate('mentee');

    $this->mentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'client_type' => 'personal',
    ]));
    $this->mentor->assignRole('mentor');

    $this->mentee = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
        'is_active' => true,
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

    progressSubscribe($this->mentee, progressMentorPlan('progress-workspace', true));
});

it('lets a mentor open the panel of an assigned mentee', function () {
    Objective::factory()->individual($this->mentee)->achieved()->create();

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/progress")
        ->assertOk()
        ->assertJsonPath('data.viewer_role', 'mentor')
        ->assertJsonPath('data.subject.id', $this->mentee->id)
        ->assertJsonPath('data.objectives.metric.numerator', 1)
        ->assertJsonPath('data.objectives.allow_proposal', false);
});

it('returns 404 for a mentee the mentor is not assigned to', function () {
    $otherMentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'client_type' => 'personal',
    ]));
    $otherMentor->assignRole('mentor');

    $this->actingAs($otherMentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/progress")
        ->assertNotFound();
});

it('locks the panel with a reason when the mentee plan lacks the workspace', function () {
    $this->mentee->subscriptions()->delete();
    progressSubscribe($this->mentee, progressMentorPlan('progress-no-workspace', false));

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/progress")
        ->assertForbidden();
});

it('lists the mentor portfolio with flags', function () {
    Objective::factory()->individual($this->mentee)->overdue()->create();
    Objective::factory()->individual($this->mentee)->create(['status' => 'in_progress']);

    $this->actingAs($this->mentor)
        ->getJson('/api/v1/mentor/progress/overview')
        ->assertOk()
        ->assertJsonPath('data.0.mentee.id', $this->mentee->id)
        ->assertJsonPath('data.0.objectives_overdue', 1)
        ->assertJsonPath('data.0.objectives_pending', 2)
        ->assertJsonPath('data.0.workspace_locked', false);
});
