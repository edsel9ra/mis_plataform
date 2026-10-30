<?php

use App\Enums\AvailabilityMode;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\MentorAvailabilityException;
use App\Models\MentorshipRelationship;
use App\Models\Plan;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses()->group('scheduling', 'booking');

function bookingActor(array $attributes = []): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->create(array_merge([
        'client_type' => 'personal',
        'is_active' => true,
    ], $attributes)));
}

function bookingRelationship(User $mentor, User $mentee, array $attributes = []): MentorshipRelationship
{
    return MentorshipRelationship::factory()->create(array_merge([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ], $attributes));
}

function bookingPayload(MentorshipRelationship $relationship, array $overrides = []): array
{
    return array_merge([
        'relationship_id' => $relationship->id,
        'session_type' => 'individual',
        'title' => 'Booking test',
        'scheduled_at' => now()->addDays(3)->startOfHour()->toIso8601String(),
        'duration_minutes' => 60,
    ], $overrides);
}

function addBookingAttendees(Session $session, string ...$userIds): void
{
    foreach (array_unique($userIds) as $userId) {
        SessionAttendee::create([
            'session_id' => $session->id,
            'user_id' => $userId,
        ]);
    }
}

it('creates an authorized booking with the creator timezone and attendee list', function () {
    $mentor = bookingActor(['role' => 'mentor', 'timezone' => 'Europe/Madrid']);
    $mentee = bookingActor();
    $relationship = bookingRelationship($mentor, $mentee);
    $payload = bookingPayload($relationship);

    $response = $this->actingAs($mentor)->postJson('/api/v1/sessions', $payload);

    $response->assertCreated();
    $session = Session::findOrFail($response->json('id'));
    expect($session->timezone)->toBe('Europe/Madrid')
        ->and($session->created_at)->not->toBeNull()
        ->and($session->attendees->pluck('user_id')->all())->toContain($mentor->id, $mentee->id);
    expect(Activity::query()
        ->where('subject_type', Session::class)
        ->where('subject_id', $session->id)
        ->where('causer_id', $mentor->id)
        ->exists())->toBeTrue();
});

it('accepts an explicitly selected valid session timezone', function () {
    $mentor = bookingActor(['role' => 'mentor', 'timezone' => 'Europe/Madrid']);
    $mentee = bookingActor();
    $relationship = bookingRelationship($mentor, $mentee);

    $response = $this->actingAs($mentor)->postJson('/api/v1/sessions', bookingPayload($relationship, [
        'timezone' => 'Asia/Tokyo',
    ]));

    $response->assertCreated();
    expect(Session::findOrFail($response->json('id'))->timezone)->toBe('Asia/Tokyo');
});

it('rechecks create authorization in the booking action', function () {
    $mentor = bookingActor(['role' => 'mentor']);
    $mentee = bookingActor();
    $outsider = bookingActor();
    $relationship = bookingRelationship($mentor, $mentee);

    expect(fn () => app(\App\Actions\CreateSessionAction::class)->execute(
        bookingPayload($relationship),
        $outsider,
    ))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('sessions', 0);
});

it('revalidates duration bounds when the action is called without a Form Request', function () {
    $mentor = bookingActor(['role' => 'mentor']);
    $mentee = bookingActor();
    $relationship = bookingRelationship($mentor, $mentee);

    expect(fn () => app(\App\Actions\CreateSessionAction::class)->execute(
        bookingPayload($relationship, ['duration_minutes' => 14]),
        $mentor,
    ))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('sessions', 0);
});

it('returns the same session for a normalized duplicate request', function () {
    $mentor = bookingActor(['role' => 'mentor']);
    $mentee = bookingActor();
    $relationship = bookingRelationship($mentor, $mentee);
    $payload = bookingPayload($relationship, ['title' => "  Sesión\t  de   Mentoría "]);

    $first = $this->actingAs($mentor)->postJson('/api/v1/sessions', $payload)->assertCreated();
    $second = $this->actingAs($mentor)->postJson('/api/v1/sessions', [
        ...$payload,
        'title' => 'sesión de mentoría',
    ])->assertOk();

    expect($second->json('id'))->toBe($first->json('id'));
    $this->assertDatabaseCount('sessions', 1);
});

it('allows the mentee to create a session for their own active relationship', function () {
    $mentor = bookingActor(['role' => 'mentor']);
    $mentee = bookingActor(['role' => 'mentee']);
    $relationship = bookingRelationship($mentor, $mentee);

    $response = $this->actingAs($mentee)
        ->postJson('/api/v1/sessions', bookingPayload($relationship));

    $response->assertCreated();
    expect(Session::findOrFail($response->json('id'))->attendees->pluck('user_id')->all())
        ->toContain($mentor->id, $mentee->id);
});

it('allows support users with explicit manage permission to create a session', function () {
    $mentor = bookingActor(['role' => 'mentor']);
    $mentee = bookingActor();
    $support = bookingActor(['role' => 'admin']);
    \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    $support->assignRole('admin');
    $support->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate(
        'scheduling.manage.support',
        'web',
    ));
    $relationship = bookingRelationship($mentor, $mentee);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($support)
        ->postJson('/api/v1/sessions', bookingPayload($relationship))
        ->assertNoContent();

    $this->assertDatabaseHas('sessions', ['relationship_id' => $relationship->id]);
});

it('rejects an inactive relationship and a mismatched relationship type', function () {
    $mentor = bookingActor(['role' => 'mentor']);
    $mentee = bookingActor();
    $inactive = bookingRelationship($mentor, $mentee, ['status' => 'paused']);

    $this->actingAs($mentor)
        ->postJson('/api/v1/sessions', bookingPayload($inactive))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('relationship_id');

    $active = bookingRelationship($mentor, $mentee);
    $this->actingAs($mentor)
        ->postJson('/api/v1/sessions', bookingPayload($active, ['session_type' => 'group']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('session_type');
});

it('enforces configured duration bounds in the booking action', function () {
    $mentor = bookingActor(['role' => 'mentor']);
    $mentee = bookingActor();
    $relationship = bookingRelationship($mentor, $mentee);

    foreach ([14, 481] as $duration) {
        $this->actingAs($mentor)
            ->postJson('/api/v1/sessions', bookingPayload($relationship, ['duration_minutes' => $duration]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration_minutes');
    }
});

it('rejects a booking that overlaps an existing session of the same mentor', function () {
    $mentor = bookingActor(['role' => 'mentor']);
    $firstMentee = bookingActor();
    $secondMentee = bookingActor();
    $firstRelationship = bookingRelationship($mentor, $firstMentee);
    $secondRelationship = bookingRelationship($mentor, $secondMentee);
    $start = now()->addDays(3)->startOfHour();
    $existing = Session::factory()->create([
        'relationship_id' => $firstRelationship->id,
        'scheduled_at' => $start,
        'duration_minutes' => 60,
    ]);
    addBookingAttendees($existing, $mentor->id, $firstMentee->id);

    $this->actingAs($mentor)
        ->postJson('/api/v1/sessions', bookingPayload($secondRelationship, [
            'scheduled_at' => $start->copy()->addMinutes(30)->toIso8601String(),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('scheduled_at');

    $this->assertDatabaseCount('sessions', 1);
});

it('rejects a mentee conflict even when the sessions belong to different relationships', function () {
    $mentee = bookingActor();
    $otherMentor = bookingActor(['role' => 'mentor']);
    $mentor = bookingActor(['role' => 'mentor']);
    $occupiedRelationship = bookingRelationship($otherMentor, $mentee);
    $candidateRelationship = bookingRelationship($mentor, $mentee);
    $start = now()->addDays(3)->startOfHour();
    $existing = Session::factory()->create([
        'relationship_id' => $occupiedRelationship->id,
        'scheduled_at' => $start,
        'duration_minutes' => 60,
    ]);
    addBookingAttendees($existing, $otherMentor->id, $mentee->id);

    $this->actingAs($mentor)
        ->postJson('/api/v1/sessions', bookingPayload($candidateRelationship, [
            'scheduled_at' => $start->copy()->addMinutes(30)->toIso8601String(),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('scheduled_at');

    $this->assertDatabaseCount('sessions', 1);
});

it('rejects a collective reservation entirely when one active member is busy', function () {
    $mentor = bookingActor(['role' => 'mentor']);
    $member = bookingActor();
    $manager = bookingActor();
    $cohort = Cohort::create([
        'created_by' => $manager->id,
        'name' => 'Booking cohort',
        'status' => 'active',
    ]);
    CohortMember::create([
        'cohort_id' => $cohort->id,
        'user_id' => $member->id,
        'status' => 'active',
    ]);
    $groupRelationship = MentorshipRelationship::factory()->create([
        'type' => 'grupal',
        'source_type' => 'cohort',
        'source_id' => $cohort->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
    $otherMentor = bookingActor(['role' => 'mentor']);
    $occupiedRelationship = bookingRelationship($otherMentor, $member);
    $start = now()->addDays(3)->startOfHour();
    $existing = Session::factory()->create([
        'relationship_id' => $occupiedRelationship->id,
        'scheduled_at' => $start,
        'duration_minutes' => 60,
    ]);
    addBookingAttendees($existing, $otherMentor->id, $member->id);

    $this->actingAs($mentor)
        ->postJson('/api/v1/sessions', bookingPayload($groupRelationship, [
            'session_type' => 'group',
            'scheduled_at' => $start->copy()->addMinutes(30)->toIso8601String(),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('scheduled_at');

    $this->assertDatabaseCount('sessions', 1);
});

it('keeps the monthly plan limit while excluding canceled and not completed sessions', function () {
    $mentee = bookingActor();
    $mentor = bookingActor(['role' => 'mentor']);
    $plan = Plan::create([
        'client_type' => 'personal',
        'name' => ['es' => 'Limited'],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_sessions_per_month' => 1,
        'is_active' => true,
    ]);
    Subscription::create([
        'subscriber_type' => 'user',
        'subscriber_id' => $mentee->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);
    $relationship = bookingRelationship($mentor, $mentee);
    Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => Carbon::parse(bookingPayload($relationship)['scheduled_at']),
        'title' => 'Closed not completed',
        'status' => 'not_completed',
    ]);

    $this->actingAs($mentor)
        ->postJson('/api/v1/sessions', bookingPayload($relationship))
        ->assertCreated();

    $this->postJson('/api/v1/sessions', bookingPayload($relationship, [
        'scheduled_at' => now()->addDays(5)->startOfHour()->toIso8601String(),
        'title' => 'Second active session',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors('session');
});

it('rejects a blocking availability exception even when the mentor has no windows', function () {
    $mentor = bookingActor(['role' => 'mentor']);
    $mentee = bookingActor();
    $relationship = bookingRelationship($mentor, $mentee);
    $start = now()->addDays(3)->setTime(12, 0);
    MentorAvailabilityException::factory()->create([
        'user_id' => $mentor->id,
        'mode' => AvailabilityMode::Block,
        'start_date' => $start->toDateString(),
        'end_date' => null,
        'start_time' => null,
        'end_time' => null,
        'timezone' => config('scheduling.default_timezone'),
    ]);

    $this->actingAs($mentor)
        ->postJson('/api/v1/sessions', bookingPayload($relationship, [
            'scheduled_at' => $start->toIso8601String(),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('scheduled_at');

    $this->assertDatabaseCount('sessions', 0);
});

it('does not reuse canceled or not completed sessions as duplicates', function () {
    $mentor = bookingActor(['role' => 'mentor']);
    $mentee = bookingActor();
    $relationship = bookingRelationship($mentor, $mentee);
    $payload = bookingPayload($relationship);
    $start = Carbon::parse($payload['scheduled_at']);
    foreach (['canceled', 'not_completed'] as $status) {
        Session::factory()->create([
            'relationship_id' => $relationship->id,
            'scheduled_at' => $start,
            'duration_minutes' => 60,
            'title' => $payload['title'],
            'status' => $status,
        ]);
    }

    $this->actingAs($mentor)
        ->postJson('/api/v1/sessions', $payload)
        ->assertCreated();

    $this->assertDatabaseCount('sessions', 3);
});
