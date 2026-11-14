<?php

use App\Models\Session;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses()->group('scheduling', 'authorization');

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('super_admin', 'web');
    Role::findOrCreate('mentee', 'web');
});

function agendaAuthzActor(array $attributes = []): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->create(array_merge([
        'client_type' => 'personal',
        'is_active' => true,
    ], $attributes)));
}

function agendaAuthzSession(): Session
{
    return Session::factory()->create();
}

it('requires authentication on every agenda surface endpoint', function (string $method, string $uri) {
    $session = agendaAuthzSession();

    $uri = str_replace(['{session}', '{mentor}'], [$session->id, $session->relationship->mentor_id], $uri);

    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'GET /sessions' => ['GET', '/api/v1/sessions'],
    'GET /sessions/{id}' => ['GET', '/api/v1/sessions/{session}'],
    'PUT /sessions/{id}/reschedule' => ['PUT', '/api/v1/sessions/{session}/reschedule'],
    'POST /sessions/{id}/cancel' => ['POST', '/api/v1/sessions/{session}/cancel'],
    'PUT /sessions/{id}/attendance' => ['PUT', '/api/v1/sessions/{session}/attendance'],
    'GET /availability/windows' => ['GET', '/api/v1/availability/windows'],
    'GET /mentors/{mentor}/availability/slots' => ['GET', '/api/v1/mentors/{mentor}/availability/slots'],
    'GET /admin/sessions' => ['GET', '/api/v1/admin/sessions'],
    'GET /admin/sessions/{id}' => ['GET', '/api/v1/admin/sessions/{session}'],
    'POST /admin/sessions' => ['POST', '/api/v1/admin/sessions'],
    'PUT /admin/sessions/{id}' => ['PUT', '/api/v1/admin/sessions/{session}'],
    'PUT /admin/sessions/{id}/status' => ['PUT', '/api/v1/admin/sessions/{session}/status'],
    'DELETE /admin/sessions/{id}' => ['DELETE', '/api/v1/admin/sessions/{session}'],
]);

it('denies every admin session endpoint to an authenticated non-admin user', function (string $method, string $uri, array $payload) {
    $mentee = agendaAuthzActor(['role' => 'mentee']);
    $mentee->assignRole('mentee');
    $session = agendaAuthzSession();

    $uri = str_replace('{session}', $session->id, $uri);

    $this->actingAs($mentee)
        ->json($method, $uri, $payload)
        ->assertForbidden();
})->with([
    'GET /admin/sessions' => ['GET', '/api/v1/admin/sessions', []],
    'GET /admin/sessions/{id}' => ['GET', '/api/v1/admin/sessions/{session}', []],
    'POST /admin/sessions' => ['POST', '/api/v1/admin/sessions', [
        'relationship_id' => 'x', 'session_type' => 'individual', 'title' => 'X',
        'scheduled_at' => '2030-01-01T10:00:00Z', 'duration_minutes' => 30, 'status' => 'scheduled',
    ]],
    'PUT /admin/sessions/{id}' => ['PUT', '/api/v1/admin/sessions/{session}', ['title' => 'X']],
    'PUT /admin/sessions/{id}/status' => ['PUT', '/api/v1/admin/sessions/{session}/status', [
        'status' => 'canceled', 'reason' => 'x',
    ]],
    'DELETE /admin/sessions/{id}' => ['DELETE', '/api/v1/admin/sessions/{session}', []],
]);

it('never lists an outsider session inside an actor calendar range', function () {
    $outsiderSession = agendaAuthzSession();
    $actor = agendaAuthzActor();

    $response = $this->actingAs($actor)
        ->getJson('/api/v1/sessions?from=' . now()->subMonth()->toIso8601String()
            . '&to=' . now()->addMonth()->toIso8601String());

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id'))->not->toContain($outsiderSession->id);
});
