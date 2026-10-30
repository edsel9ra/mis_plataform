<?php

use App\Models\MentorAvailabilityException;
use App\Models\MentorAvailabilityWindow;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class)->group('scheduling', 'availability');

function availabilityApiMentor(array $attributes = []): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create(array_merge([
        'is_active' => true,
    ], $attributes)));
}

it('allows a mentor to list and view only their own availability, including their private exception reason', function (): void {
    $mentor = availabilityApiMentor();
    $otherMentor = availabilityApiMentor();
    $window = MentorAvailabilityWindow::factory()->for($mentor, 'mentor')->create();
    $exception = MentorAvailabilityException::factory()->for($mentor, 'mentor')->create([
        'reason' => 'Motivo privado de vacaciones',
    ]);
    $otherWindow = MentorAvailabilityWindow::factory()->for($otherMentor, 'mentor')->create();

    $this->actingAs($mentor)
        ->getJson('/api/v1/availability/windows')
        ->assertOk()
        ->assertJsonFragment(['id' => $window->id])
        ->assertJsonMissing(['id' => $otherWindow->id]);

    $this->actingAs($mentor)
        ->getJson("/api/v1/availability/exceptions/{$exception->id}")
        ->assertOk()
        ->assertJsonPath('reason', 'Motivo privado de vacaciones');
});

it('creates, updates and deletes the authenticated mentor availability windows', function (): void {
    $mentor = availabilityApiMentor();

    $created = $this->actingAs($mentor)
        ->postJson('/api/v1/availability/windows', [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:30',
            'timezone' => 'America/Mexico_City',
        ])
        ->assertCreated()
        ->assertJsonPath('day_of_week', 1)
        ->assertJsonPath('timezone', 'America/Mexico_City');

    $windowId = $created->json('id');

    $this->putJson("/api/v1/availability/windows/{$windowId}", [
        'day_of_week' => 2,
        'start_time' => '10:00',
        'end_time' => '13:00',
        'timezone' => 'Europe/Madrid',
    ])->assertOk()->assertJsonPath('day_of_week', 2);

    $this->deleteJson("/api/v1/availability/windows/{$windowId}")->assertNoContent();

    $this->assertDatabaseMissing('mentor_availability_windows', ['id' => $windowId]);
});

it('creates, updates and deletes the authenticated mentor availability exceptions with private reasons', function (): void {
    $mentor = availabilityApiMentor();

    $created = $this->actingAs($mentor)
        ->postJson('/api/v1/availability/exceptions', [
            'mode' => 'block',
            'start_date' => '2026-12-24',
            'end_date' => '2026-12-26',
            'start_time' => '10:00',
            'end_time' => '15:00',
            'timezone' => 'Europe/Madrid',
            'reason' => 'Cita privada',
        ])
        ->assertCreated()
        ->assertJsonPath('reason', 'Cita privada');

    $exceptionId = $created->json('id');

    $this->putJson("/api/v1/availability/exceptions/{$exceptionId}", [
        'mode' => 'open',
        'start_date' => '2026-12-27',
        'end_date' => null,
        'start_time' => null,
        'end_time' => null,
        'timezone' => 'UTC',
        'reason' => 'Horario especial',
    ])->assertOk()->assertJsonPath('mode', 'open');

    $this->deleteJson("/api/v1/availability/exceptions/{$exceptionId}")->assertNoContent();

    $this->assertDatabaseMissing('mentor_availability_exceptions', ['id' => $exceptionId]);
});

it('hides another mentors availability and denies access even to support administrators', function (): void {
    $owner = availabilityApiMentor();
    $outsider = availabilityApiMentor();
    $window = MentorAvailabilityWindow::factory()->for($owner, 'mentor')->create();
    $exception = MentorAvailabilityException::factory()->for($owner, 'mentor')->create([
        'reason' => 'Secreto',
    ]);
    $beforeWindow = $window->getAttributes();
    $supportAdmin = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]));
    Role::findOrCreate('admin', 'web');
    $supportAdmin->assignRole('admin');
    $supportAdmin->givePermissionTo(Permission::findOrCreate('scheduling.manage.support', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($outsider)
        ->getJson("/api/v1/availability/windows/{$window->id}")
        ->assertNotFound();

    $this->actingAs($outsider)
        ->putJson("/api/v1/availability/windows/{$window->id}", [
            'day_of_week' => 3,
            'start_time' => '11:00',
            'end_time' => '12:00',
            'timezone' => 'UTC',
        ])
        ->assertNotFound();

    $this->actingAs($outsider)
        ->deleteJson("/api/v1/availability/windows/{$window->id}")
        ->assertNotFound();

    $this->actingAs($outsider)
        ->getJson("/api/v1/availability/exceptions/{$exception->id}")
        ->assertNotFound()
        ->assertJsonMissing(['reason' => 'Secreto']);

    $this->actingAs($outsider)
        ->deleteJson("/api/v1/availability/exceptions/{$exception->id}")
        ->assertNotFound();

    $this->actingAs($outsider)
        ->putJson("/api/v1/availability/exceptions/{$exception->id}", [
            'mode' => 'open',
            'start_date' => '2026-12-27',
            'timezone' => 'UTC',
        ])
        ->assertNotFound();

    $this->actingAs($supportAdmin)
        ->getJson("/api/v1/availability/exceptions/{$exception->id}")
        ->assertNotFound();

    $this->actingAs($supportAdmin)
        ->deleteJson("/api/v1/availability/windows/{$window->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('mentor_availability_windows', [
        'id' => $window->id,
        'user_id' => $owner->id,
        'day_of_week' => $beforeWindow['day_of_week'],
    ]);
    $this->assertDatabaseHas('mentor_availability_exceptions', [
        'id' => $exception->id,
        'user_id' => $owner->id,
        'reason' => 'Secreto',
    ]);
});

it('validates timezones, weekday, time ordering, date ranges and paired optional times', function (): void {
    $mentor = availabilityApiMentor();

    $this->actingAs($mentor)
        ->postJson('/api/v1/availability/windows', [
            'day_of_week' => 7,
            'start_time' => '13:00',
            'end_time' => '09:00',
            'timezone' => 'Mars/Olympus',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['day_of_week', 'end_time', 'timezone']);

    $this->actingAs($mentor)
        ->postJson('/api/v1/availability/exceptions', [
            'mode' => 'block',
            'start_date' => '2026-12-26',
            'end_date' => '2026-12-24',
            'start_time' => '10:00',
            'timezone' => 'UTC',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date', 'end_time']);
});

it('rejects overlapping recurring windows for the same weekday but permits adjacent windows', function (): void {
    $mentor = availabilityApiMentor();
    MentorAvailabilityWindow::factory()->for($mentor, 'mentor')->create([
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $this->actingAs($mentor)
        ->postJson('/api/v1/availability/windows', [
            'day_of_week' => 1,
            'start_time' => '11:00',
            'end_time' => '13:00',
            'timezone' => 'UTC',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['day_of_week']);

    $this->postJson('/api/v1/availability/windows', [
        'day_of_week' => 1,
        'start_time' => '12:00',
        'end_time' => '13:00',
        'timezone' => 'UTC',
    ])->assertCreated();
});

it('allows a timed exception to cross midnight when both times are present', function (): void {
    $mentor = availabilityApiMentor();

    $this->actingAs($mentor)
        ->postJson('/api/v1/availability/exceptions', [
            'mode' => 'block',
            'start_date' => '2026-12-24',
            'end_date' => '2026-12-24',
            'start_time' => '22:00',
            'end_time' => '02:00',
            'timezone' => 'UTC',
        ])
        ->assertCreated();
});

it('does not expose exception reasons from serialized availability models', function (): void {
    $exception = MentorAvailabilityException::factory()->create(['reason' => 'Solo para mentor']);

    expect($exception->toArray())->not->toHaveKey('reason');
});

it('returns only free slots in the requester timezone without exposing exception details', function (): void {
    $this->travelTo(Carbon::parse('2026-10-12 08:00:00 UTC'));

    $mentor = availabilityApiMentor();
    $mentee = User::factory()->create([
        'role' => 'mentee',
        'timezone' => 'America/Los_Angeles',
        'is_active' => true,
    ]);
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);

    MentorAvailabilityWindow::factory()->for($mentor, 'mentor')->create([
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'timezone' => 'America/Los_Angeles',
    ]);
    MentorAvailabilityException::factory()->for($mentor, 'mentor')->create([
        'mode' => 'block',
        'start_date' => '2026-10-12',
        'end_date' => '2026-10-12',
        'start_time' => '10:00:00',
        'end_time' => '10:30:00',
        'timezone' => 'America/Los_Angeles',
        'reason' => 'Consulta médica privada',
    ]);

    Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => '2026-10-12 16:30:00 UTC',
        'duration_minutes' => 30,
        'status' => 'scheduled',
    ]);
    Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => '2026-10-12 16:00:00 UTC',
        'duration_minutes' => 30,
        'status' => 'canceled',
    ]);
    Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => '2026-10-12 17:30:00 UTC',
        'duration_minutes' => 30,
        'status' => 'not_completed',
    ]);

    $this->actingAs($mentee)
        ->getJson("/api/v1/mentors/{$mentor->id}/availability/slots?from=2026-10-12T09:00:00&to=2026-10-12T12:00:00&duration_minutes=30")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0', [
            'start' => '2026-10-12T09:00:00-07:00',
            'end' => '2026-10-12T09:30:00-07:00',
            'timezone' => 'America/Los_Angeles',
        ])
        ->assertJsonPath('data.1', [
            'start' => '2026-10-12T10:30:00-07:00',
            'end' => '2026-10-12T11:00:00-07:00',
            'timezone' => 'America/Los_Angeles',
        ])
        ->assertJsonMissing(['mode' => 'block'])
        ->assertJsonMissing(['reason' => 'Consulta médica privada']);
});

it('allows only a legitimate mentor or scheduler to consult the mentors free slots', function (): void {
    $mentor = availabilityApiMentor();
    $mentee = User::factory()->create(['role' => 'mentee', 'is_active' => true]);
    MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
    $outsider = User::factory()->create(['role' => 'mentee', 'is_active' => true]);
    $query = "?from=2026-10-12T16:00:00Z&to=2026-10-12T17:00:00Z&duration_minutes=30";

    $this->actingAs($mentee)
        ->getJson("/api/v1/mentors/{$mentor->id}/availability/slots{$query}")
        ->assertOk();

    $this->actingAs($mentor)
        ->getJson("/api/v1/mentors/{$mentor->id}/availability/slots{$query}")
        ->assertOk();

    $this->actingAs($outsider)
        ->getJson("/api/v1/mentors/{$mentor->id}/availability/slots{$query}")
        ->assertNotFound();
});

it('validates slot range ordering and duration bounds', function (): void {
    $mentor = availabilityApiMentor();
    $mentee = User::factory()->create(['role' => 'mentee', 'is_active' => true]);
    MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);

    $this->actingAs($mentee)
        ->getJson("/api/v1/mentors/{$mentor->id}/availability/slots?from=2026-10-12T17:00:00Z&to=2026-10-12T16:00:00Z&duration_minutes=10")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to', 'duration_minutes']);
});
