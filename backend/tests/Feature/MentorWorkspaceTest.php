<?php

use App\Models\Challenge;
use App\Models\ChallengeCheckIn;
use App\Models\MediaAsset;
use App\Models\MentorshipRelationship;
use App\Models\NeurocognitiveAlert;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemicMap;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses()->group('mentor-workspace');

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

function workspacePlan(string $code = 'personal_basic'): Plan
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
            'emotional_check_ins' => true,
            'challenges' => true,
            'challenge_media' => true,
            'challenge_feedback' => true,
            'mentor_observations' => true,
            'systemic_maps' => true,
            'max_active_challenges' => 3,
        ],
        'is_active' => true,
    ]);
}

function subscribeUser(User $user, Plan $plan): Subscription
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

it('lists a personal assignment without a plan as locked', function () {
    $response = $this->actingAs($this->mentor)
        ->getJson('/api/v1/mentor/users?per_page=100');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $this->mentee->id)
        ->assertJsonPath('data.0.assignments.0.locked', true)
        ->assertJsonPath('data.0.assignments.0.plan_status', 'no_plan');

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/workspace?relationship_id={$this->relationship->id}")
        ->assertOk()
        ->assertJsonPath('locked', true)
        ->assertJsonPath('lock_reason', 'no_plan')
        ->assertJsonPath('challenges', []);

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/profile?relationship_id={$this->relationship->id}")
        ->assertOk()
        ->assertJsonPath('locked', true)
        ->assertJsonPath('lock_reason', 'no_plan');

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/personality?relationship_id={$this->relationship->id}")
        ->assertOk()
        ->assertJsonPath('assessment', null)
        ->assertJsonPath('user.assignments.0.relationship_id', $this->relationship->id)
        ->assertJsonPath('locked', true);
});

it('does not grant workspace access from null or soft-deleted plan entitlements', function () {
    $plan = workspacePlan('no-entitlements');
    $plan->update(['entitlements' => null]);
    subscribeUser($this->mentee, $plan);

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/workspace?relationship_id={$this->relationship->id}")
        ->assertOk()
        ->assertJsonPath('locked', true)
        ->assertJsonPath('lock_reason', 'entitlement_missing');

    $plan->delete();

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/workspace?relationship_id={$this->relationship->id}")
        ->assertOk()
        ->assertJsonPath('locked', true)
        ->assertJsonPath('lock_reason', 'inactive_plan');
});

it('supports personal challenge content, dual attention sources, submissions and private feedback media', function () {
    Storage::fake('local');
    subscribeUser($this->mentee, workspacePlan());

    $challengeResponse = $this->actingAs($this->mentor)->post(
        "/api/v1/mentor/users/{$this->mentee->id}/challenges?relationship_id={$this->relationship->id}",
        [
            'title' => 'Practice pause',
            'instructions' => 'Pause before replying.',
            'files' => [UploadedFile::fake()->create('prompt.pdf', 20, 'application/pdf')],
        ],
    );

    $challengeResponse->assertCreated()
        ->assertJsonPath('title', 'Practice pause')
        ->assertJsonPath('media.0.original_name', 'prompt.pdf')
        ->assertJsonMissingPath('media.0.path');
    expect($challengeResponse->json('media.0.download_url'))
        ->toBe('/api/v1/media/' . $challengeResponse->json('media.0.id'));
    $challenge = Challenge::firstOrFail();
    $asset = MediaAsset::firstOrFail();
    Storage::disk('local')->assertExists($asset->path);

    $this->actingAs($this->mentee)
        ->get($asset->download_url)
        ->assertOk();

    $this->actingAs($this->mentee)
        ->postJson("/api/v1/challenges/{$challenge->id}/check-ins", [
            'attention_score' => 4,
            'note' => 'I stayed focused.',
        ])
        ->assertCreated()
        ->assertJsonPath('source', 'mentee_self_report')
        ->assertJsonPath('attention_score', 4);

    $this->actingAs($this->mentor)
        ->postJson("/api/v1/mentor/users/{$this->mentee->id}/observations?relationship_id={$this->relationship->id}", [
            'attention_score' => 3,
            'engagement_score' => 4,
            'challenge_id' => $challenge->id,
        ])
        ->assertCreated()
        ->assertJsonPath('source', 'mentor_observation')
        ->assertJsonPath('attention_score', 3);

    $this->actingAs($this->mentee)
        ->post(
            "/api/v1/challenges/{$challenge->id}/submissions",
            [
                'note' => 'Completed the exercise.',
                'files' => [UploadedFile::fake()->create('evidence.txt', 2, 'text/plain')],
            ],
        )
        ->assertCreated()
        ->assertJsonPath('media.0.original_name', 'evidence.txt');

    $this->actingAs($this->mentor)
        ->post(
            "/api/v1/mentor/challenges/{$challenge->id}/feedback?relationship_id={$this->relationship->id}",
            [
                'body' => 'Good progress.',
                'files' => [UploadedFile::fake()->create('feedback.pdf', 10, 'application/pdf')],
            ],
        )
        ->assertCreated()
        ->assertJsonPath('body', 'Good progress.')
        ->assertJsonPath('media.0.original_name', 'feedback.pdf');

    expect(MediaAsset::query()->where('purpose', 'mentor_feedback')->count())->toBe(1);
});

it('keeps systemic maps mentor-only and rejects group relationships at the personal boundary', function () {
    subscribeUser($this->mentee, workspacePlan('personal_premium'));

    $response = $this->actingAs($this->mentor)->putJson(
        "/api/v1/mentor/users/{$this->mentee->id}/systemic-map?relationship_id={$this->relationship->id}",
        [
            'title' => 'Private map',
            'nodes' => [
                ['key' => 'self', 'label' => 'Self', 'kind' => 'person'],
                ['key' => 'goal', 'label' => 'Goal', 'kind' => 'context'],
            ],
            'edges' => [
                ['from' => 'self', 'to' => 'goal', 'label' => 'supports'],
            ],
        ],
    );

    $response->assertOk()
        ->assertJsonPath('is_private', true)
        ->assertJsonCount(2, 'nodes')
        ->assertJsonCount(1, 'edges');

    $map = SystemicMap::firstOrFail();
    $this->actingAs($this->mentee)
        ->getJson("/api/v1/mentor/systemic-maps/{$map->id}")
        ->assertForbidden();

    $groupRelationship = MentorshipRelationship::create([
        'type' => 'grupal',
        'source_type' => 'cohort',
        'source_id' => $this->relationship->source_id,
        'mentor_id' => $this->mentor->id,
        'status' => 'active',
    ]);

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/workspace?relationship_id={$groupRelationship->id}")
        ->assertNotFound();
});

it('creates stagnation and disengagement alerts idempotently', function () {
    subscribeUser($this->mentee, workspacePlan());
    $challenge = Challenge::create([
        'relationship_id' => $this->relationship->id,
        'mentor_id' => $this->mentor->id,
        'mentee_id' => $this->mentee->id,
        'title' => 'Old challenge',
        'status' => 'assigned',
    ]);
    $challenge->forceFill([
        'created_at' => now()->subDays(8),
        'updated_at' => now()->subDays(8),
    ])->saveQuietly();

    $this->artisan('app:detect-mentor-alerts')->assertExitCode(0);
    $this->artisan('app:detect-mentor-alerts')->assertExitCode(0);

    expect(NeurocognitiveAlert::query()->where('relationship_id', $this->relationship->id)->count())->toBe(2);
    $this->assertDatabaseCount('notifications', 2);
    expect($challenge->fresh()->status)->toBe('assigned');
});

it('rejects URL media and invalid attention scores', function () {
    subscribeUser($this->mentee, workspacePlan());

    $this->actingAs($this->mentor)
        ->postJson("/api/v1/mentor/users/{$this->mentee->id}/observations?relationship_id={$this->relationship->id}", [
            'attention_score' => 6,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attention_score']);

    $this->actingAs($this->mentor)
        ->postJson('/api/v1/mentor/media', [
            'relationship_id' => $this->relationship->id,
            'purpose' => 'challenge_content',
            'challenge_id' => 'https://example.com/recording',
            'recording_url' => 'https://example.com/recording',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file', 'recording_url']);
});

it('supports the frontend mentor catalog and profile contract', function () {
    Storage::fake('local');
    subscribeUser($this->mentee, workspacePlan());

    $template = $this->actingAs($this->mentor)->post(
        '/api/v1/mentor/challenges',
        [
            'title' => 'Template pause',
            'description' => 'A reusable exercise.',
            'due_at' => '2030-01-15 10:00:00',
            'content_file' => UploadedFile::fake()->create('template.pdf', 10, 'application/pdf'),
        ],
    );

    $template->assertCreated()->assertJsonPath('status', 'template');
    $templateDueAt = $template->json('due_at');
    expect($templateDueAt)->not->toBeNull();
    $templateId = $template->json('id');

    $this->actingAs($this->mentor)
        ->getJson('/api/v1/mentor/challenges')
        ->assertOk()
        ->assertJsonPath('data.0.id', $templateId);

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/challenges/{$templateId}")
        ->assertOk()
        ->assertJsonPath('id', $templateId);

    $this->actingAs($this->mentor)
        ->putJson("/api/v1/mentor/challenges/{$templateId}", ['title' => 'Updated template'])
        ->assertOk()
        ->assertJsonPath('title', 'Updated template');

    $assignment = $this->actingAs($this->mentor)->postJson(
        "/api/v1/mentor/users/{$this->mentee->id}/challenges?relationship_id={$this->relationship->id}",
        ['challenge_id' => $templateId],
    );
    $assignment->assertCreated()
        ->assertJsonPath('template_id', $templateId)
        ->assertJsonPath('due_at', $templateDueAt);
    $challengeId = $assignment->json('id');

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/profile?relationship_id={$this->relationship->id}")
        ->assertOk()
        ->assertJsonPath('user.id', $this->mentee->id)
        ->assertJsonPath('plan.code', 'personal_basic')
        ->assertJsonPath('assignment.relationship_id', $this->relationship->id);

    $this->actingAs($this->mentor)
        ->putJson("/api/v1/mentor/users/{$this->mentee->id}/neurocognitive", [
            'relationship_id' => $this->relationship->id,
            'mentor_observation' => [
                'focus' => 80,
                'regulation' => 0,
                'stress' => 30,
                'energy' => 75,
                'note' => 'Observed during the exercise.',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('focus', 80)
        ->assertJsonPath('regulation', 0);

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/neurocognitive?relationship_id={$this->relationship->id}")
        ->assertOk()
        ->assertJsonPath('mentor_observation.focus', 80);

    $this->actingAs($this->mentor)
        ->postJson("/api/v1/challenges/{$challengeId}/check-ins", [
            'status' => 'in_progress',
            'note' => 'Started by the mentor.',
        ])
        ->assertForbidden();

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/challenges/{$challengeId}/check-ins")
        ->assertForbidden();

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/challenges/{$challengeId}")
        ->assertForbidden();

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/challenges/{$challengeId}/feedback")
        ->assertForbidden();

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/challenges/{$challengeId}/submissions")
        ->assertForbidden();

    $this->actingAs($this->mentor)
        ->post(
            '/api/v1/mentor/feedback',
            [
                'user_id' => $this->mentee->id,
                'relationship_id' => $this->relationship->id,
                'note' => 'Reusable feedback.',
                'feedback_file' => UploadedFile::fake()->create('feedback.mp3', 10, 'audio/mpeg'),
            ],
        )
        ->assertCreated()
        ->assertJsonPath('note', 'Reusable feedback.');

    $this->actingAs($this->mentor)
        ->getJson('/api/v1/mentor/feedback')
        ->assertOk()
        ->assertJsonPath('data.0.note', 'Reusable feedback.');

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/emotional-check-ins?relationship_id={$this->relationship->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('hides each workspace feature when its entitlement is missing', function () {
    Storage::fake('local');
    $plan = workspacePlan('locked-features');
    $plan->update([
        'entitlements' => [
            'mentor_workspace' => true,
            'attention_tracking' => false,
            'emotional_check_ins' => false,
            'challenges' => true,
            'challenge_media' => false,
            'challenge_feedback' => false,
            'mentor_observations' => false,
            'systemic_maps' => false,
            'max_active_challenges' => 3,
        ],
    ]);
    subscribeUser($this->mentee, $plan);

    $challenge = Challenge::factory()->forRelationship($this->relationship)->create();
    $asset = MediaAsset::factory()->forRelationship($this->relationship)->create([
        'purpose' => 'challenge_content',
    ]);
    $asset->mediaable()->associate($challenge);
    $asset->save();
    Storage::disk('local')->put($asset->path, 'private content');

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/workspace?relationship_id={$this->relationship->id}")
        ->assertOk()
        ->assertJsonPath('attention', [])
        ->assertJsonPath('emotional_check_ins', [])
        ->assertJsonPath('alerts', [])
        ->assertJsonPath('challenges.0.id', $challenge->id)
        ->assertJsonMissingPath('challenges.0.content_url')
        ->assertJsonMissingPath('challenges.0.media.0.download_url');

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/observations?relationship_id={$this->relationship->id}")
        ->assertForbidden();

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/alerts?relationship_id={$this->relationship->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.locked', true);

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/media/{$asset->id}")
        ->assertForbidden();

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/challenges/{$challenge->id}/feedback?relationship_id={$this->relationship->id}")
        ->assertForbidden();
});

it('allows attention reads without exposing mentor observations without that entitlement', function () {
    $plan = workspacePlan('attention-only');
    $plan->update([
        'entitlements' => [
            'mentor_workspace' => true,
            'attention_tracking' => true,
            'mentor_observations' => false,
        ],
    ]);
    subscribeUser($this->mentee, $plan);
    $checkIn = ChallengeCheckIn::create([
        'relationship_id' => $this->relationship->id,
        'mentee_id' => $this->mentee->id,
        'author_id' => $this->mentee->id,
        'source' => 'mentee_self_report',
        'attention_score' => 4,
        'checked_in_at' => now(),
    ]);

    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->mentee->id}/neurocognitive?relationship_id={$this->relationship->id}")
        ->assertOk()
        ->assertJsonPath('self_report.id', $checkIn->id)
        ->assertJsonPath('mentor_observation', null);
});

it('keeps challenge status unchanged when a mentee submits and rejects protected challenge fields', function () {
    subscribeUser($this->mentee, workspacePlan('challenge-status'));
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create([
        'status' => 'assigned',
    ]);

    $this->actingAs($this->mentee)
        ->postJson("/api/v1/challenges/{$challenge->id}/submissions", [
            'note' => 'Completed the exercise.',
        ])
        ->assertCreated();

    expect($challenge->fresh()->status)->toBe('assigned');

    $this->actingAs($this->mentor)
        ->putJson("/api/v1/mentor/challenges/{$challenge->id}?relationship_id={$this->relationship->id}", [
            'relationship_id' => $this->relationship->id,
            'template_id' => $challenge->id,
            'mentor_id' => $this->mentor->id,
            'mentee_id' => $this->mentee->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['relationship_id', 'template_id', 'mentor_id', 'mentee_id']);

    expect($challenge->fresh()->relationship_id)->toBe($this->relationship->id)
        ->and($challenge->fresh()->mentor_id)->toBe($this->mentor->id)
        ->and($challenge->fresh()->mentee_id)->toBe($this->mentee->id);
});

it('stores a content_file submission and rejects completed or canceled challenges', function () {
    Storage::fake('local');
    subscribeUser($this->mentee, workspacePlan('submission-media'));
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create([
        'status' => 'assigned',
    ]);

    $this->actingAs($this->mentee)
        ->post(
            "/api/v1/challenges/{$challenge->id}/submissions",
            ['content_file' => UploadedFile::fake()->create('evidence.pdf', 12, 'application/pdf')],
        )
        ->assertCreated()
        ->assertJsonPath('media.0.original_name', 'evidence.pdf');

    expect(MediaAsset::query()->where('purpose', 'challenge_submission')->count())->toBe(1);

    foreach (['completed', 'canceled'] as $status) {
        $terminalChallenge = Challenge::factory()->forRelationship($this->relationship)->create([
            'status' => $status,
        ]);

        $this->actingAs($this->mentee)
            ->postJson("/api/v1/challenges/{$terminalChallenge->id}/submissions", ['note' => 'Too late.'])
            ->assertUnprocessable();
    }
});

it('enforces the file count across upload field aliases', function () {
    Storage::fake('local');

    $response = $this->actingAs($this->mentor)->withHeader('Accept', 'application/json')->post(
        '/api/v1/mentor/challenges',
        [
            'title' => 'Too many files',
            'files' => array_map(
                fn (int $index) => UploadedFile::fake()->create("file-{$index}.txt", 1, 'text/plain'),
                range(1, 5),
            ),
            'content_file' => UploadedFile::fake()->create('extra.txt', 1, 'text/plain'),
        ],
    );

    $response->assertUnprocessable()->assertJsonValidationErrors(['files']);
    expect(Challenge::query()->count())->toBe(0)
        ->and(MediaAsset::query()->count())->toBe(0);
});

it('rejects future activity timestamps', function () {
    subscribeUser($this->mentee, workspacePlan('timestamps'));
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create();
    $future = now()->addMinute()->toISOString();

    $this->actingAs($this->mentee)
        ->postJson("/api/v1/challenges/{$challenge->id}/check-ins", [
            'attention_score' => 4,
            'checked_in_at' => $future,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['checked_in_at']);

    $this->actingAs($this->mentor)
        ->postJson("/api/v1/mentor/users/{$this->mentee->id}/observations?relationship_id={$this->relationship->id}", [
            'attention_score' => 4,
            'observed_at' => $future,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['observed_at']);
});
