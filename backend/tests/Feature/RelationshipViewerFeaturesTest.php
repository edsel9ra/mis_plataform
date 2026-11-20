<?php

use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\Plan;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses()->group('relationships');

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

function checkInPlan(string $code, array $entitlements): Plan
{
    return Plan::create([
        'code' => $code,
        'client_type' => 'personal',
        'name' => ['es' => $code],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_mentors' => 1,
        'entitlements' => $entitlements,
        'is_active' => true,
    ]);
}

function subscribeCheckInUser(User $user, Plan $plan): Subscription
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

function entitledCheckInPlan(string $code = 'eci_full'): Plan
{
    return checkInPlan($code, [
        'mentor_workspace' => true,
        'emotional_check_ins' => true,
    ]);
}

function featuresFor(object $test, MentorshipRelationship $relationship): mixed
{
    $response = $test->actingAs($test->viewer ?? $test->mentee)
        ->getJson('/api/v1/relationships?per_page=100');

    $response->assertOk();
    $item = collect($response->json('data'))->firstWhere('id', $relationship->id);

    return $item['features']['emotional_check_ins'] ?? 'missing';
}

it('reports the feature and allows the endpoint for an entitled mentee', function () {
    subscribeCheckInUser($this->mentee, entitledCheckInPlan());

    $index = $this->actingAs($this->mentee)->getJson('/api/v1/relationships?per_page=100');
    $index->assertOk();
    $item = collect($index->json('data'))->firstWhere('id', $this->relationship->id);
    expect($item['features']['emotional_check_ins'])->toBeTrue();

    $show = $this->actingAs($this->mentee)->getJson("/api/v1/relationships/{$this->relationship->id}");
    $show->assertOk()
        ->assertJsonPath('features.emotional_check_ins', true);

    $this->actingAs($this->mentee)
        ->getJson("/api/v1/relationships/{$this->relationship->id}/emotional-check-ins")
        ->assertOk();
});

it('reports false when the plan lacks the emotional_check_ins entitlement', function () {
    subscribeCheckInUser($this->mentee, checkInPlan('eci_missing', ['mentor_workspace' => true]));

    expect(featuresFor($this, $this->relationship))->toBeFalse();

    $this->actingAs($this->mentee)
        ->getJson("/api/v1/relationships/{$this->relationship->id}/emotional-check-ins")
        ->assertForbidden();
});

it('reports false when the plan lacks the mentor workspace entitlement', function () {
    subscribeCheckInUser($this->mentee, checkInPlan('eci_no_workspace', [
        'mentor_workspace' => false,
        'emotional_check_ins' => true,
    ]));

    expect(featuresFor($this, $this->relationship))->toBeFalse();

    $this->actingAs($this->mentee)
        ->getJson("/api/v1/relationships/{$this->relationship->id}/emotional-check-ins")
        ->assertForbidden();
});

it('reports false when the mentee has no plan', function () {
    expect(featuresFor($this, $this->relationship))->toBeFalse();
});

it('reports false for a pending relationship', function () {
    subscribeCheckInUser($this->mentee, entitledCheckInPlan());
    $otherMentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'client_type' => 'personal',
    ]));
    $otherMentor->assignRole('mentor');
    $pending = MentorshipRelationship::create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $this->mentee->id,
        'mentor_id' => $otherMentor->id,
        'status' => 'pending',
    ]);

    expect(featuresFor($this, $pending))->toBeFalse();
});

it('reports false for a non-personal relationship the viewer belongs to', function () {
    subscribeCheckInUser($this->mentee, entitledCheckInPlan());
    $family = FamilyGroup::create([
        'head_user_id' => $this->mentee->id,
        'family_name' => 'Familia Test',
    ]);
    FamilyMember::create([
        'family_group_id' => $family->id,
        'user_id' => $this->mentee->id,
        'full_name' => $this->mentee->full_name,
        'relationship' => 'head',
    ]);
    $familiar = MentorshipRelationship::create([
        'type' => 'familiar',
        'source_type' => 'family_group',
        'source_id' => $family->id,
        'mentor_id' => $this->mentor->id,
        'status' => 'active',
        'started_at' => now(),
    ]);

    expect(featuresFor($this, $familiar))->toBeFalse();
});

it('reports false when a mentor lists their relationships', function () {
    subscribeCheckInUser($this->mentee, entitledCheckInPlan());

    $this->viewer = $this->mentor;
    expect(featuresFor($this, $this->relationship))->toBeFalse();
    unset($this->viewer);
});

it('resolves the viewer plan once per request', function () {
    subscribeCheckInUser($this->mentee, entitledCheckInPlan());

    foreach ([1, 2] as $n) {
        $otherMentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
            'client_type' => 'personal',
        ]));
        $otherMentor->assignRole('mentor');
        MentorshipRelationship::create([
            'type' => 'personal',
            'source_type' => 'user',
            'source_id' => $this->mentee->id,
            'mentor_id' => $otherMentor->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    DB::enableQueryLog();
    $response = $this->actingAs($this->mentee)->getJson('/api/v1/relationships?per_page=100')->assertOk();
    $queries = collect(DB::getQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    expect(collect($response->json('data'))->where('features.emotional_check_ins', true))->toHaveCount(3)
        ->and($queries->filter(fn ($q) => str_contains($q['query'], 'subscriptions')))->toHaveCount(1)
        ->and($queries->filter(fn ($q) => str_contains($q['query'], 'plans')))->toHaveCount(1);
});

it('does not include features on a relationship nested in a session payload', function () {
    subscribeCheckInUser($this->mentee, entitledCheckInPlan());
    $session = Session::factory()->create([
        'relationship_id' => $this->relationship->id,
    ]);
    SessionAttendee::create([
        'session_id' => $session->id,
        'user_id' => $this->mentee->id,
        'attended' => false,
        'attendance_status' => 'not_confirmed',
    ]);

    $response = $this->actingAs($this->mentee)->getJson("/api/v1/sessions/{$session->id}");
    $response->assertOk();
    expect($response->json('relationship'))->not->toBeNull()
        ->and($response->json('relationship.features'))->toBeNull();
});
