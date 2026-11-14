<?php

use App\Models\Challenge;
use App\Models\MentorshipRelationship;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
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
    subscribeTransitionUser($this->mentee, transitionPlan('transitions'));
});

function transitionPlan(string $code): Plan
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
            'attention_tracking' => true,
            'challenges' => true,
            'challenge_feedback' => true,
            'mentor_observations' => true,
            'systemic_maps' => true,
            'max_active_challenges' => 10,
        ],
        'is_active' => true,
    ]);
}

function subscribeTransitionUser(User $user, Plan $plan): Subscription
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

function updateChallengeStatus(Tests\TestCase $test, User $actor, Challenge $challenge, string $relationshipId, array $payload)
{
    return $test->actingAs($actor)
        ->putJson("/api/v1/mentor/challenges/{$challenge->id}?relationship_id={$relationshipId}", $payload);
}

function transitionActivities(Challenge $challenge)
{
    return Activity::query()
        ->where('subject_type', (new Challenge)->getMorphClass())
        ->where('subject_id', $challenge->id)
        ->get();
}

it('persists each transition allowed by RF-012.7 with one audit entry', function (string $from, string $to) {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create([
        'status' => $from,
    ]);

    updateChallengeStatus($this, $this->mentor, $challenge, $this->relationship->id, [
        'status' => $to,
    ])->assertOk();

    expect($challenge->fresh()->status)->toBe($to);

    $activities = transitionActivities($challenge);
    expect($activities)->toHaveCount(1);

    $activity = $activities->first();
    $properties = $activity->properties->toArray();
    expect($activity->causer_id)->toBe($this->mentor->id)
        ->and($properties['challenge_id'])->toBe($challenge->id)
        ->and($properties['actor'])->toBe($this->mentor->id)
        ->and($properties['before']['status'])->toBe($from)
        ->and($properties['after']['status'])->toBe($to)
        ->and($properties['occurred_at'])->not->toBeNull()
        ->and($properties)->not->toHaveKeys(['title', 'description', 'instructions']);
})->with([
    ['assigned', 'abandoned'],
    ['in_progress', 'abandoned'],
    ['abandoned', 'assigned'],
    ['abandoned', 'in_progress'],
]);

it('rejects forbidden transitions without touching status or other fields and without audit', function (string $from, string $to) {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create([
        'status' => $from,
        'title' => 'Original title',
    ]);

    updateChallengeStatus($this, $this->mentor, $challenge, $this->relationship->id, [
        'status' => $to,
        'title' => 'Must not persist',
    ])->assertUnprocessable()->assertJsonValidationErrors(['status']);

    $fresh = $challenge->fresh();
    expect($fresh->status)->toBe($from)
        ->and($fresh->title)->toBe('Original title')
        ->and(transitionActivities($challenge))->toHaveCount(0);
})->with([
    ['abandoned', 'completed'],
    ['abandoned', 'canceled'],
    ['completed', 'abandoned'],
    ['canceled', 'abandoned'],
]);

it('rejects abandoning a template through the owned-template branch without updating fields', function () {
    $template = Challenge::factory()->create([
        'status' => 'template',
        'mentor_id' => $this->mentor->id,
        'relationship_id' => null,
        'mentee_id' => null,
        'title' => 'Template title',
    ]);

    $this->actingAs($this->mentor)
        ->putJson("/api/v1/mentor/challenges/{$template->id}", [
            'status' => 'abandoned',
            'title' => 'Must not persist',
        ])
        ->assertUnprocessable()->assertJsonValidationErrors(['status']);

    $fresh = $template->fresh();
    expect($fresh->status)->toBe('template')
        ->and($fresh->title)->toBe('Template title')
        ->and(transitionActivities($template))->toHaveCount(0);
});

it('treats a self-transition as a no-op without an audit entry', function () {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create([
        'status' => 'in_progress',
    ]);

    updateChallengeStatus($this, $this->mentor, $challenge, $this->relationship->id, [
        'status' => 'in_progress',
    ])->assertOk();

    expect($challenge->fresh()->status)->toBe('in_progress')
        ->and(transitionActivities($challenge))->toHaveCount(0);
});

it('sets completed_at when the challenge is completed', function () {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create([
        'status' => 'in_progress',
        'completed_at' => null,
    ]);

    updateChallengeStatus($this, $this->mentor, $challenge, $this->relationship->id, [
        'status' => 'completed',
    ])->assertOk();

    $fresh = $challenge->fresh();
    expect($fresh->status)->toBe('completed')
        ->and($fresh->completed_at)->not->toBeNull();
});

it('rejects unauthorized actors without an audit entry', function () {
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create([
        'status' => 'assigned',
    ]);

    $otherMentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'client_type' => 'personal',
    ]));
    $otherMentor->assignRole('mentor');

    updateChallengeStatus($this, $otherMentor, $challenge, $this->relationship->id, [
        'status' => 'in_progress',
    ])->assertNotFound();

    $this->actingAs($this->mentee)
        ->putJson("/api/v1/mentor/challenges/{$challenge->id}?relationship_id={$this->relationship->id}", [
            'status' => 'in_progress',
        ])
        ->assertForbidden();

    expect($challenge->fresh()->status)->toBe('assigned')
        ->and(transitionActivities($challenge))->toHaveCount(0);
});
