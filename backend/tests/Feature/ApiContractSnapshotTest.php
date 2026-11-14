<?php

use App\Models\Assessment;
use App\Models\Certificate;
use App\Models\LearningPath;
use App\Models\LearningResource;
use App\Models\Notification;
use App\Models\PathModule;
use App\Models\PersonalityAssessment;
use App\Models\Plan;
use App\Models\Session;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

uses()->group('contract');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->superAdmin()->create();
    $this->admin->assignRole('super_admin');
    $this->adminToken = $this->admin->createToken('test')->plainTextToken;

    $this->user = User::factory()->create(['client_type' => 'personal']);
    $this->userToken = $this->user->createToken('test')->plainTextToken;

    $this->session = Session::factory()->create();
    $this->personality = PersonalityAssessment::factory()->create(['user_id' => $this->user->id]);
    $this->catalogAssessment = Assessment::create([
        'slug' => 'snap-eval',
        'title' => ['es' => 'Eval'],
        'questions' => [['id' => 1, 'text' => 'Q', 'options' => ['a', 'b']]],
        'passing_score' => 50,
        'is_active' => true,
    ]);
    $this->plan = Plan::create([
        'code' => 'snap-plan',
        'client_type' => 'personal',
        'name' => ['es' => 'Plan'],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_members' => 5,
    ]);
    $this->path = LearningPath::create([
        'title' => ['es' => 'Camino'],
        'client_type' => 'personal',
        'level' => 'beginner',
        'is_active' => true,
    ]);
    $this->module = PathModule::create([
        'learning_path_id' => $this->path->id,
        'title' => 'Modulo 1',
        'order' => 1,
    ]);
    $this->resource = LearningResource::create([
        'module_id' => $this->module->id,
        'type' => 'video',
        'title' => ['es' => 'Video'],
        'url' => 'https://example.com/v',
        'order' => 1,
        'is_free' => true,
    ]);
    $this->notification = Notification::create([
        'user_id' => $this->user->id,
        'type' => 'test',
        'data' => ['foo' => 'bar'],
        'status' => 'pending',
    ]);
    $this->certificate = Certificate::factory()->create(['user_id' => $this->user->id]);
});

function contractAdmin(object $test, string $method, string $uri, array $payload = [])
{
    auth()->forgetGuards();

    return $method === 'get'
        ? $test->withToken($test->adminToken)->getJson($uri)
        : $test->withToken($test->adminToken)->{$method.'Json'}($uri, $payload);
}

function contractUser(object $test, string $method, string $uri, array $payload = [])
{
    auth()->forgetGuards();

    return $method === 'get'
        ? $test->withToken($test->userToken)->getJson($uri)
        : $test->withToken($test->userToken)->{$method.'Json'}($uri, $payload);
}

function contractKeys(array $data): array
{
    $keys = array_keys($data);
    sort($keys);

    return $keys;
}

function expectTopKeys($response, array $expected): void
{
    $response->assertOk();
    expect(contractKeys($response->json()))->toBe($expected);
}

function expectPaginatedKeys($response, array $expectedItemKeys): void
{
    $response->assertOk();
    expect(contractKeys($response->json()))->toBe([
        'current_page', 'data', 'first_page_url', 'from', 'last_page', 'last_page_url',
        'links', 'next_page_url', 'path', 'per_page', 'prev_page_url', 'to', 'total',
    ]);
    expect(contractKeys($response->json('data.0')))->toBe($expectedItemKeys);
}

const ADMIN_USER_KEYS = ['avatar', 'birth_date', 'client_type', 'company', 'company_id', 'created_at', 'deleted_at', 'email', 'email_verified_at', 'id', 'is_active', 'last_name', 'locale', 'mentor_relationships_count', 'name', 'personality_assessment', 'provider', 'provider_id', 'role', 'sex', 'timezone', 'updated_at'];

const ADMIN_SESSION_KEYS = ['attendees', 'canceled_at', 'canceled_by', 'canceled_late', 'cancellation_lead_minutes', 'cancellation_reason', 'completed_at', 'completed_by', 'created_at', 'deleted_at', 'description', 'duration_minutes', 'end_at', 'id', 'meet_attempts', 'meet_event_id', 'meet_last_attempt_at', 'meet_last_error', 'meet_link', 'meet_synced_schedule_hash', 'mentee_notes', 'mentor_notes', 'recording_url', 'relationship', 'relationship_id', 'scheduled_at', 'session_type', 'status', 'timezone', 'title', 'updated_at'];

const ADMIN_ASSESSMENT_ITEM_KEYS = ['answers', 'completed_at', 'created_at', 'id', 'raw_scores', 'results', 'test_version', 'updated_at', 'user', 'user_id'];

const CATALOG_ITEM_KEYS = ['category', 'client_types', 'created_at', 'description', 'id', 'is_active', 'metadata', 'module_id', 'passing_score', 'questions', 'results_count', 'short_name', 'slug', 'time_limit_minutes', 'title', 'updated_at'];

const PLAN_ITEM_KEYS = ['client_type', 'code', 'created_at', 'deleted_at', 'description', 'entitlements', 'features', 'id', 'is_active', 'max_members', 'max_mentors', 'max_sessions_per_month', 'name', 'price_monthly', 'price_yearly', 'subscriptions_count', 'updated_at'];

const RESOURCE_ITEM_KEYS = ['created_at', 'description', 'id', 'is_free', 'module', 'module_id', 'order', 'title', 'type', 'updated_at', 'url'];

const NOTIFICATION_KEYS = ['created_at', 'data', 'dedupe_key', 'id', 'read_at', 'sent_at', 'status', 'type', 'updated_at', 'user_id'];

const LEARNING_PATH_KEYS = ['client_type', 'created_at', 'deleted_at', 'description', 'estimated_hours', 'id', 'is_active', 'level', 'modules', 'personality_tags', 'title', 'updated_at', 'user_progress'];

const CERTIFICATE_KEYS = ['blockchain_contract_address', 'blockchain_token_id', 'blockchain_tx_hash', 'created_at', 'description', 'expires_at', 'id', 'ipfs_cid', 'ipfs_uri', 'issued_at', 'metadata', 'relationship_id', 'revoked', 'title', 'type', 'updated_at', 'user_id'];

it('keeps the admin users contract', function () {
    expectPaginatedKeys(contractAdmin($this, 'get', '/api/v1/admin/users'), ADMIN_USER_KEYS);
});

it('keeps the admin sessions contract', function () {
    expectPaginatedKeys(contractAdmin($this, 'get', '/api/v1/admin/sessions'), ADMIN_SESSION_KEYS);
    expectTopKeys(contractAdmin($this, 'get', "/api/v1/admin/sessions/{$this->session->id}"), ADMIN_SESSION_KEYS);
});

it('keeps the admin assessments contract', function () {
    expectPaginatedKeys(contractAdmin($this, 'get', '/api/v1/admin/assessments'), ADMIN_ASSESSMENT_ITEM_KEYS);
    expectTopKeys(contractAdmin($this, 'get', "/api/v1/admin/assessments/{$this->personality->id}"), ADMIN_ASSESSMENT_ITEM_KEYS);

    contractAdmin($this, 'post', '/api/v1/admin/assessments', [
        'user_id' => User::factory()->create()->id,
        'test_version' => 'ipip-neo-120',
        'answers' => ['q1' => 2],
        'results' => ['o' => 70],
    ])
        ->assertCreated()
        ->assertJsonStructure(['id']);

    $created = contractAdmin($this, 'post', '/api/v1/admin/assessments', [
        'user_id' => User::factory()->create()->id,
        'test_version' => 'ipip-neo-120',
        'answers' => ['q1' => 2],
        'results' => ['o' => 70],
    ]);
    expect(contractKeys($created->json()))->toBe(['answers', 'created_at', 'id', 'results', 'test_version', 'updated_at', 'user_id']);

    $updated = contractAdmin($this, 'put', "/api/v1/admin/assessments/{$this->personality->id}", [
        'completed_at' => now()->toIso8601String(),
    ]);
    expect(contractKeys($updated->json()))->toBe(['answers', 'completed_at', 'created_at', 'id', 'raw_scores', 'results', 'test_version', 'updated_at', 'user_id']);
});

it('keeps the admin evaluation catalog contract', function () {
    expectPaginatedKeys(contractAdmin($this, 'get', '/api/v1/admin/evaluation-catalog'), CATALOG_ITEM_KEYS);
    expectTopKeys(contractAdmin($this, 'get', "/api/v1/admin/evaluation-catalog/{$this->catalogAssessment->id}"), CATALOG_ITEM_KEYS);

    $created = contractAdmin($this, 'post', '/api/v1/admin/evaluation-catalog', [
        'slug' => 'snap-eval-2',
        'title' => ['es' => 'E2'],
        'questions' => [['text' => 'Q', 'options' => ['a', 'b']]],
        'passing_score' => 50,
    ]);
    $created->assertCreated();
    expect(contractKeys($created->json()))->toBe(['created_at', 'id', 'passing_score', 'questions', 'slug', 'title', 'updated_at']);

    $updated = contractAdmin($this, 'put', "/api/v1/admin/evaluation-catalog/{$this->catalogAssessment->id}", [
        'slug' => 'snap-eval',
        'title' => ['es' => 'Eval'],
        'questions' => [['text' => 'Q', 'options' => ['a', 'b']]],
        'passing_score' => 60,
        'is_active' => false,
    ]);
    expect(contractKeys($updated->json()))->toBe(['category', 'client_types', 'created_at', 'description', 'id', 'is_active', 'metadata', 'module_id', 'passing_score', 'questions', 'short_name', 'slug', 'time_limit_minutes', 'title', 'updated_at']);
});

it('keeps the admin plans contract', function () {
    expectPaginatedKeys(contractAdmin($this, 'get', '/api/v1/admin/plans'), PLAN_ITEM_KEYS);
});

it('keeps the resources contract', function () {
    expectPaginatedKeys(contractUser($this, 'get', '/api/v1/resources'), RESOURCE_ITEM_KEYS);
    expectTopKeys(contractUser($this, 'get', "/api/v1/resources/{$this->resource->id}"), RESOURCE_ITEM_KEYS);
});

it('keeps the notifications contract', function () {
    expectPaginatedKeys(contractUser($this, 'get', '/api/v1/notifications'), NOTIFICATION_KEYS);

    $read = contractUser($this, 'put', "/api/v1/notifications/{$this->notification->id}/read");
    expect(contractKeys($read->json()))->toBe(NOTIFICATION_KEYS);
});

it('keeps the learning paths contract', function () {
    $index = contractUser($this, 'get', '/api/v1/learning-paths');
    $index->assertOk();
    expect(contractKeys($index->json('0')))->toBe(LEARNING_PATH_KEYS);

    expectTopKeys(contractUser($this, 'get', "/api/v1/learning-paths/{$this->path->id}"), LEARNING_PATH_KEYS);

    $progress = contractUser($this, 'put', "/api/v1/learning-paths/{$this->path->id}/progress", ['progress' => 50]);
    expect(contractKeys($progress->json()))->toBe(['completed_at', 'created_at', 'current_module_id', 'id', 'learning_path_id', 'progress', 'started_at', 'status', 'updated_at', 'user_id']);
});

it('keeps the certificates contract', function () {
    expectPaginatedKeys(contractUser($this, 'get', '/api/v1/certificates'), CERTIFICATE_KEYS);
});
