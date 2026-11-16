<?php

use App\Actions\SetCompetencyAssessmentLinkAction;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\CompetencyAssessmentLink;
use App\Models\EvidenceDeletionEvent;
use App\Models\MentorshipRelationship;
use App\Models\Objective;
use App\Models\ObjectiveEvidence;
use App\Models\PersonalityAssessment;
use App\Models\Plan;
use App\Models\SkillTag;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Progress\History\HistoryAdapter;
use App\Services\Progress\History\ProgressHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * T015.7 — GET /progress/history, /mentor/users/{user}/progress/history and
 * /admin/progress/history: the same ProgressAccessService matrix as the
 * panel (self, assigned mentor + workspace, support), the canonical event
 * contract and tamper-proof cursor validation (RF-015.1/.7/.8, plan §4).
 */
uses(RefreshDatabase::class)->group('progress');

beforeEach(function (): void {
    Role::findOrCreate('mentor');
    Role::findOrCreate('mentee');
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
    Permission::findOrCreate('progress.view.support');

    $this->mentor = User::factory()->mentor()->create(['client_type' => 'personal']);
    $this->mentor->assignRole('mentor');
    $this->subject = User::factory()->create(['client_type' => 'personal']);
    $this->subject->assignRole('mentee');
    $this->relationship = MentorshipRelationship::create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $this->subject->id,
        'mentor_id' => $this->mentor->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
});

function apiHistPlan(string $code): Plan
{
    return Plan::create([
        'code' => $code,
        'client_type' => 'personal',
        'name' => ['es' => $code],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_mentors' => 1,
        'entitlements' => ['mentor_workspace' => true],
        'is_active' => true,
    ]);
}

function apiHistSubscribe(User $user, Plan $plan): void
{
    Subscription::create([
        'subscriber_type' => 'user',
        'subscriber_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);
}

function apiHistSupportAdmin(): User
{
    $admin = User::factory()->admin()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('progress.view.support');

    return $admin;
}

function apiHistCatalogResult(User $user): AssessmentResult
{
    $assessment = Assessment::create([
        'slug' => 'api-hist-'.Str::random(8),
        'title' => ['es' => 'Catálogo'],
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

function apiHistEventIds(array $events): array
{
    return array_column($events, 'id');
}

it('requires authentication on all three routes', function (): void {
    $this->getJson('/api/v1/progress/history')->assertUnauthorized();
    $this->getJson("/api/v1/mentor/users/{$this->subject->id}/progress/history")->assertUnauthorized();
    $this->getJson('/api/v1/admin/progress/history?user_id='.$this->subject->id)->assertUnauthorized();
});

it('serves the own history with the exact contract and stable cursor pagination', function (): void {
    Objective::factory()->individual($this->subject)->count(3)->create();

    $response = $this->actingAs($this->subject)
        ->getJson('/api/v1/progress/history')
        ->assertOk();

    expect(array_keys($response->json()))->toBe(['data', 'undated', 'next_cursor']);
    expect($response->json('undated'))->toBe([]);

    foreach ($response->json('data') as $event) {
        expect(array_keys($event))->toBe([
            'id', 'type', 'domain', 'occurred_at', 'actor', 'subject_ref', 'before', 'after', 'incomplete',
        ]);
        expect($event['occurred_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
    }

    $expected = apiHistEventIds(
        $this->actingAs($this->subject)->getJson('/api/v1/progress/history?limit=50')->json('data')
    );
    expect(count($expected))->toBeGreaterThanOrEqual(3);

    // Follow next_cursor over HTTP: every id exactly once, same order.
    $seen = [];
    $url = '/api/v1/progress/history?limit=1';
    $guard = 0;
    do {
        $page = $this->actingAs($this->subject)->getJson($url)->assertOk();
        $seen = array_merge($seen, apiHistEventIds($page->json('data')));
        $cursor = $page->json('next_cursor');
        $url = '/api/v1/progress/history?limit=1&cursor='.urlencode((string) $cursor);
    } while ($cursor !== null && ++$guard < 60);

    expect($seen)->toBe($expected);
    expect($page->json('undated'))->toBe([]);

    // A foreign user_id on the own route is ignored — never leaks.
    $other = User::factory()->create(['client_type' => 'personal']);
    Objective::factory()->individual($other)->create();
    $json = $this->actingAs($this->subject)
        ->getJson('/api/v1/progress/history?user_id='.$other->id)
        ->assertOk()
        ->getContent();
    expect($json)->not->toContain($other->id);
    expect(apiHistEventIds(json_decode($json, true)['data']))->toBe($expected);
});

it('lets the assigned mentor read the mentee history inside the access matrix', function (): void {
    Objective::factory()->individual($this->subject)->create();
    apiHistSubscribe($this->subject, apiHistPlan('hist-ws'));

    $response = $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$this->subject->id}/progress/history")
        ->assertOk();

    expect(collect($response->json('data'))->pluck('type')->all())
        ->toContain('objective.created');

    // Unassigned mentor → 404 (no subject disclosure).
    $other = User::factory()->create(['client_type' => 'personal']);
    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$other->id}/progress/history")
        ->assertNotFound();

    // Assigned but no workspace entitlement → 403 with the locked message.
    $noPlan = User::factory()->create(['client_type' => 'personal']);
    MentorshipRelationship::create([
        'type' => 'personal', 'source_type' => 'user', 'source_id' => $noPlan->id,
        'mentor_id' => $this->mentor->id, 'status' => 'active', 'started_at' => now(),
    ]);
    $this->actingAs($this->mentor)
        ->getJson("/api/v1/mentor/users/{$noPlan->id}/progress/history")
        ->assertForbidden()
        ->assertJsonPath('message', __('progress.messages.workspace_locked'));

    // A non-uuid {user} never reaches the database.
    $this->actingAs($this->mentor)
        ->getJson('/api/v1/mentor/users/not-a-uuid/progress/history')
        ->assertNotFound();

    // A mentee cannot reach the mentor route at all.
    $this->actingAs($this->subject)
        ->getJson("/api/v1/mentor/users/{$this->subject->id}/progress/history")
        ->assertForbidden();
});

it('serves the support history only to admins holding the permission', function (): void {
    Objective::factory()->individual($this->subject)->create();
    $admin = apiHistSupportAdmin();

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/admin/progress/history?user_id={$this->subject->id}")
        ->assertOk();
    expect(apiHistEventIds($response->json('data')))->not->toBe([]);

    // Admin role without the explicit permission → 403.
    $plain = User::factory()->admin()->create();
    $plain->assignRole('admin');
    $this->actingAs($plain)
        ->getJson("/api/v1/admin/progress/history?user_id={$this->subject->id}")
        ->assertForbidden();

    // A mentee is stopped by the role group.
    $this->actingAs($this->subject)
        ->getJson("/api/v1/admin/progress/history?user_id={$this->subject->id}")
        ->assertForbidden();

    // user_id must be present and a uuid; unknown uuid → 404.
    $this->actingAs($admin)
        ->getJson('/api/v1/admin/progress/history')
        ->assertJsonValidationErrors('user_id');
    $this->actingAs($admin)
        ->getJson('/api/v1/admin/progress/history?user_id=nope')
        ->assertJsonValidationErrors('user_id');
    $this->actingAs($admin)
        ->getJson('/api/v1/admin/progress/history?user_id='.Str::uuid())
        ->assertNotFound();
});

it('never lets a cursor cross subjects', function (): void {
    Objective::factory()->individual($this->subject)->count(2)->create();
    $other = User::factory()->create(['client_type' => 'personal']);
    Objective::factory()->individual($other)->count(2)->create();

    $otherPage = $this->actingAs($other)
        ->getJson('/api/v1/progress/history?limit=1')->assertOk();
    $otherIds = apiHistEventIds($otherPage->json('data'));
    $cursor = $otherPage->json('next_cursor');
    expect($cursor)->not->toBeNull();

    $json = $this->actingAs($this->subject)
        ->getJson('/api/v1/progress/history?cursor='.urlencode($cursor))
        ->assertOk()
        ->getContent();

    foreach ($otherIds as $id) {
        expect($json)->not->toContain($id);
    }
    expect($json)->not->toContain($other->id);
});

it('rejects tampered cursors and limits with 422 before any query', function (): void {
    $badCursor = rtrim(strtr(base64_encode(json_encode(['x', 'y'])), '+/', '-_'), '=');

    foreach (['not-a-cursor', $badCursor, str_repeat('c', 600)] as $cursor) {
        $this->actingAs($this->subject)
            ->getJson('/api/v1/progress/history?cursor='.urlencode($cursor))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cursor');
    }

    $this->actingAs($this->subject)
        ->getJson('/api/v1/progress/history?cursor[]=x')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cursor');

    foreach (['0', '51', 'abc'] as $limit) {
        $this->actingAs($this->subject)
            ->getJson("/api/v1/progress/history?limit={$limit}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');
    }
});

it('never leaks private source content, hidden actors or other subjects', function (): void {
    $secretNote = 'SECRET-EVIDENCE-NOTE-'.Str::random(6);
    $secretSubmission = 'SECRET-SUBMISSION-NOTE-'.Str::random(6);

    $objective = Objective::factory()->individual($this->subject)->create();
    ObjectiveEvidence::factory()->create([
        'objective_id' => $objective->id, 'user_id' => $this->subject->id, 'note' => $secretNote,
    ]);
    $challenge = Challenge::factory()->forRelationship($this->relationship)->create(['status' => 'in_progress']);
    ChallengeSubmission::factory()->forChallenge($challenge)->create(['note' => $secretSubmission]);

    // Manual link event + a source deletion with its hidden-actor event.
    app(SetCompetencyAssessmentLinkAction::class)->execute(
        $this->subject, $this->subject, null,
        'assessment_result', apiHistCatalogResult($this->subject)->id,
        'skill_tag', SkillTag::create(['name' => 'api-tag-'.Str::random(6), 'category' => 't'])->id,
        now(),
    );
    $source = PersonalityAssessment::factory()->create([
        'user_id' => $this->subject->id, 'completed_at' => now(),
    ]);
    CompetencyAssessmentLink::factory()->forPersonalityAssessment($source)->forOceanFactor('O')
        ->create(['user_id' => $this->subject->id]);

    $deleter = User::factory()->superAdmin()->create(['name' => 'Admin '.Str::random(8)]);
    $deleter->assignRole('super_admin');
    $this->actingAs($deleter)->deleteJson("/api/v1/admin/assessments/{$source->id}")->assertOk();
    $pseudonym = EvidenceDeletionEvent::query()->latest('created_at')->value('actor_pseudonym');
    expect($pseudonym)->not->toBeNull();

    // An unrelated user's presence must not leak either.
    $other = User::factory()->create(['client_type' => 'personal']);
    Objective::factory()->individual($other)->create();

    apiHistSubscribe($this->subject, apiHistPlan('hist-priv'));
    $admin = apiHistSupportAdmin();

    $bodies = [
        $this->actingAs($this->subject)->getJson('/api/v1/progress/history')->assertOk()->getContent(),
        $this->actingAs($this->mentor)->getJson("/api/v1/mentor/users/{$this->subject->id}/progress/history")->assertOk()->getContent(),
        $this->actingAs($admin)->getJson("/api/v1/admin/progress/history?user_id={$this->subject->id}")->assertOk()->getContent(),
    ];

    foreach ($bodies as $json) {
        foreach ([
            '"note"', $secretNote, $secretSubmission, 'answers', 'raw_scores', '"score"',
            $pseudonym, $source->id, $deleter->id, $deleter->name, $other->id,
        ] as $forbidden) {
            expect($json)->not->toContain($forbidden);
        }
    }
});

it('keeps the collective aggregate free of history keys', function (): void {
    $manager = User::factory()->create(['client_type' => 'personal']);
    $cohort = Cohort::create([
        'created_by' => $manager->id, 'name' => 'Cohorte hist', 'status' => 'active',
    ]);
    foreach (array_merge([$this->subject], User::factory()->count(2)->create()->all()) as $member) {
        CohortMember::create(['cohort_id' => $cohort->id, 'user_id' => $member->id, 'status' => 'active']);
    }
    Objective::factory()->individual($this->subject)->create();

    $response = $this->actingAs($this->subject)
        ->getJson("/api/v1/progress/collectives/cohort/{$cohort->id}")
        ->assertOk();

    $json = $response->getContent();
    foreach (['undated', 'next_cursor', 'objective.created', 'challenge.status_transition', 'occurred_at'] as $absent) {
        expect($json)->not->toContain($absent);
    }
});

it('logs history failures with sanitized context and rethrows', function (): void {
    Log::spy();

    $throwing = new class implements HistoryAdapter {
        public function dated(User $subject, ?array $after, int $limit): array
        {
            throw new RuntimeException('secret detail');
        }

        public function undated(User $subject, int $cap): array
        {
            throw new RuntimeException('secret detail');
        }
    };
    $this->app->instance(ProgressHistoryService::class, new ProgressHistoryService([$throwing]));

    $this->actingAs($this->subject)
        ->getJson('/api/v1/progress/history')
        ->assertServerError();

    // The framework reports the 500 through the logger too; only the
    // progress.history_failed call must carry the sanitized context.
    Log::shouldHaveReceived('error')
        ->with('progress.history_failed', Mockery::on(function (array $context): bool {
            expect(array_keys($context))->toBe(['viewer_id', 'subject_id', 'exception'])
                ->and($context['exception'])->toBe(RuntimeException::class)
                ->and(json_encode($context))->not->toContain('secret detail');

            return true;
        }))
        ->atLeast()->once();
});
