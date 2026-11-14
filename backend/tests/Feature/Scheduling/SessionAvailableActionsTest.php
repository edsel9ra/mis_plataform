<?php

use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class)->group('scheduling', 'available-actions');

const DETAIL_TOP_KEYS = [
    'attendees', 'available_actions', 'created_at', 'description', 'duration_minutes', 'id',
    'meet_link', 'mentee_notes', 'mentor_notes', 'relationship', 'relationship_id', 'reviews',
    'scheduled_at', 'session_type', 'status', 'timezone', 'title',
];

function availabilityFixture(
    User $mentor,
    array $sessionOverrides = [],
    array $attendees = [],
    string $sourceType = 'user',
    ?string $sourceId = null,
    string $sessionType = 'individual',
    ?User $source = null,
): array {
    $source ??= User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'type' => match ($sourceType) {
            'family_group' => 'familiar',
            'cohort' => 'grupal',
            'company' => 'empresa',
            default => 'personal',
        },
        'source_type' => $sourceType,
        'source_id' => $sourceId ?? $source->id,
        'mentor_id' => $mentor->id,
    ]);
    $session = Session::factory()->create(array_merge([
        'relationship_id' => $relationship->id,
        'session_type' => $sessionType,
        'status' => 'scheduled',
    ], $sessionOverrides));

    foreach ($attendees as $attendee) {
        SessionAttendee::factory()->create(array_merge([
            'session_id' => $session->id,
            'created_at' => CarbonImmutable::parse($session->getRawOriginal('scheduled_at'))->subDays(2),
        ], $attendee));
    }

    return [$session->fresh(), $relationship, $source];
}

beforeEach(function (): void {
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-14 12:00:00', 'UTC'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('adds available_actions to the session detail with the documented shape only', function (): void {
    $mentor = User::factory()->mentor()->create();
    [$session] = availabilityFixture(
        $mentor,
        ['scheduled_at' => Carbon::now()->addDay(), 'duration_minutes' => 60],
        [['user_id' => $mentor->id]],
    );

    $response = $this->actingAs($mentor)->getJson("/api/v1/sessions/{$session->id}")->assertOk();

    $body = $response->json();
    $keys = array_keys($body);
    sort($keys);
    expect($keys)->toBe(DETAIL_TOP_KEYS);

    $actions = $body['available_actions'];
    expect(array_keys($actions))->toBe(['reschedule', 'cancel', 'complete', 'attendance'])
        ->and(array_keys($actions['reschedule']))->toBe(['allowed', 'reason'])
        ->and(array_keys($actions['cancel']))->toBe(['allowed', 'reason', 'late'])
        ->and(array_keys($actions['complete']))->toBe(['allowed', 'reason'])
        ->and(array_keys($actions['attendance'][0]))->toBe(['user_id', 'self', 'allowed', 'reason', 'current_status']);
});

it('does not add available_actions to session listings', function (): void {
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    [$session] = availabilityFixture(
        $mentor,
        ['scheduled_at' => Carbon::now()->addDay()],
        [['user_id' => $member->id]],
        'user',
        null,
        'individual',
        $member,
    );

    $response = $this->actingAs($member)->getJson('/api/v1/sessions?from='
        .Carbon::now()->subDay()->toIso8601String().'&to='.Carbon::now()->addDays(3)->toIso8601String())
        ->assertOk();

    $items = $response->json('data') ?? $response->json();
    $item = collect($items)->firstWhere('id', $session->id);
    expect($item)->not->toBeNull()->and($item)->not->toHaveKey('available_actions')
        ->and($item)->not->toHaveKey('timezone');
});

it('exposes the stored timezone on the detail but not on listings', function (): void {
    $mentor = User::factory()->mentor()->create();
    [$session] = availabilityFixture(
        $mentor,
        ['scheduled_at' => Carbon::now()->addDay(), 'timezone' => 'America/New_York'],
        [['user_id' => $mentor->id]],
    );

    $detail = $this->actingAs($mentor)->getJson("/api/v1/sessions/{$session->id}")->assertOk();
    expect($detail->json('timezone'))->toBe('America/New_York');
});

it('returns not found for unrelated users as before', function (): void {
    $mentor = User::factory()->mentor()->create();
    [$session] = availabilityFixture($mentor, ['scheduled_at' => Carbon::now()->addDay()]);

    $this->actingAs(User::factory()->create())
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertNotFound();
});

it('reports mentor action availability across the session lifecycle', function (
    int $startOffsetSeconds,
    int $durationMinutes,
    string $status,
    array $expectedReschedule,
    array $expectedCancel,
    array $expectedComplete,
): void {
    $mentor = User::factory()->mentor()->create();
    [$session] = availabilityFixture($mentor, [
        'scheduled_at' => Carbon::now()->addSeconds($startOffsetSeconds),
        'duration_minutes' => $durationMinutes,
        'status' => $status,
    ], [['user_id' => $mentor->id]]);

    $actions = $this->actingAs($mentor)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->json('available_actions');

    expect($actions['reschedule'])->toBe($expectedReschedule)
        ->and($actions['cancel'])->toBe($expectedCancel)
        ->and($actions['complete'])->toBe($expectedComplete);
})->with([
    'future beyond notice' => [
        36000, 60, 'scheduled',
        ['allowed' => true, 'reason' => null],
        ['allowed' => true, 'reason' => null, 'late' => false],
        ['allowed' => false, 'reason' => 'not_ended'],
    ],
    'future inside notice' => [
        3600, 60, 'scheduled',
        ['allowed' => false, 'reason' => 'insufficient_notice'],
        ['allowed' => true, 'reason' => null, 'late' => true],
        ['allowed' => false, 'reason' => 'not_ended'],
    ],
    'in progress' => [
        -3600, 120, 'in_progress',
        ['allowed' => false, 'reason' => 'already_started'],
        ['allowed' => true, 'reason' => null, 'late' => true],
        ['allowed' => false, 'reason' => 'not_ended'],
    ],
    'ended inside the completion window' => [
        -7200, 60, 'in_progress',
        ['allowed' => false, 'reason' => 'already_started'],
        ['allowed' => true, 'reason' => null, 'late' => true],
        ['allowed' => true, 'reason' => null],
    ],
    'ended past the completion window' => [
        -180000, 60, 'in_progress',
        ['allowed' => false, 'reason' => 'already_started'],
        ['allowed' => true, 'reason' => null, 'late' => true],
        ['allowed' => false, 'reason' => 'window_closed'],
    ],
    'canceled' => [
        10800, 60, 'canceled',
        ['allowed' => false, 'reason' => 'terminal_status'],
        ['allowed' => false, 'reason' => 'terminal_status', 'late' => false],
        ['allowed' => false, 'reason' => 'terminal_status'],
    ],
    'completed' => [
        -7200, 60, 'completed',
        ['allowed' => false, 'reason' => 'terminal_status'],
        ['allowed' => false, 'reason' => 'terminal_status', 'late' => false],
        ['allowed' => false, 'reason' => 'terminal_status'],
    ],
]);

it('lets a mentee manage their own session but never complete it', function (): void {
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    [$session] = availabilityFixture(
        $mentor,
        ['scheduled_at' => Carbon::now()->addHours(3), 'duration_minutes' => 60],
        [['user_id' => $member->id]],
        'user',
        null,
        'individual',
        $member,
    );

    $actions = $this->actingAs($member)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->json('available_actions');

    expect($actions['reschedule'])->toBe(['allowed' => true, 'reason' => null])
        ->and($actions['cancel']['allowed'])->toBeTrue()
        ->and($actions['complete'])->toBe(['allowed' => false, 'reason' => 'forbidden']);
});

it('reports forbidden actions to actors who can view but not manage the session', function (): void {
    $viewer = User::factory()->admin()->create();
    $viewer->assignRole(Role::findOrCreate('admin', 'web'));
    $viewer->givePermissionTo(Permission::findOrCreate('scheduling.view.support', 'web'));
    $mentor = User::factory()->mentor()->create();
    [$session] = availabilityFixture($mentor, ['scheduled_at' => Carbon::now()->addHours(3)]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $actions = $this->actingAs($viewer)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->json('available_actions');

    expect($actions['reschedule']['allowed'])->toBeFalse()
        ->and($actions['reschedule']['reason'])->toBe('forbidden')
        ->and($actions['cancel']['allowed'])->toBeFalse()
        ->and($actions['cancel']['reason'])->toBe('forbidden')
        ->and($actions['cancel']['late'])->toBeFalse();
});

it('exposes own attendance entry for attendees only', function (): void {
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    [$session] = availabilityFixture(
        $mentor,
        [
            'scheduled_at' => Carbon::now()->subHours(2),
            'duration_minutes' => 60,
            'status' => 'completed',
        ],
        [['user_id' => $member->id]],
        'user',
        null,
        'individual',
        $member,
    );

    $actions = $this->actingAs($member)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->json('available_actions');

    expect($actions['attendance'])->toBe([[
        'user_id' => $member->id,
        'self' => true,
        'allowed' => true,
        'reason' => null,
        'current_status' => 'not_confirmed',
    ]]);

    $this->actingAs($mentor)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->assertJsonPath('available_actions.attendance', []);
});

it('reports not_ended and terminal_status on the own attendance entry', function (string $status, int $startOffsetSeconds, string $expectedReason): void {
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    [$session] = availabilityFixture(
        $mentor,
        [
            'scheduled_at' => Carbon::now()->addSeconds($startOffsetSeconds),
            'duration_minutes' => 60,
            'status' => $status,
        ],
        [['user_id' => $member->id]],
        'user',
        null,
        'individual',
        $member,
    );

    $entry = $this->actingAs($member)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->json('available_actions.attendance.0');

    expect($entry['allowed'])->toBeFalse()->and($entry['reason'])->toBe($expectedReason);
})->with([
    'before end' => ['scheduled', 1800, 'not_ended'],
    'canceled' => ['canceled', -7200, 'terminal_status'],
    'after window' => ['completed', -180000, 'window_closed'],
]);

it('lists chargeable member entries for collective responsibles and honors already-confirmed-by-member', function (bool $selfConfirmed, ?string $expectedReason): void {
    $manager = User::factory()->create();
    $mentor = User::factory()->mentor()->create();
    $minor = User::factory()->create();
    $adult = User::factory()->create();
    $family = FamilyGroup::create(['head_user_id' => $manager->id, 'family_name' => 'Availability family']);
    FamilyMember::create(['family_group_id' => $family->id, 'user_id' => $minor->id, 'full_name' => $minor->name, 'age' => 11, 'relationship' => 'child']);
    FamilyMember::create(['family_group_id' => $family->id, 'user_id' => $adult->id, 'full_name' => $adult->name, 'age' => 35, 'relationship' => 'parent']);

    [$session] = availabilityFixture(
        $mentor,
        [
            'scheduled_at' => Carbon::now()->subHours(2),
            'duration_minutes' => 60,
            'status' => 'completed',
        ],
        [
            array_merge(['user_id' => $minor->id], $selfConfirmed ? ['confirmed_by' => $minor->id] : []),
            ['user_id' => $adult->id],
        ],
        'family_group',
        $family->id,
        'family',
        $minor,
    );

    $entries = $this->actingAs($manager)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->json('available_actions.attendance');

    expect($entries)->toHaveCount(1);
    $entry = $entries[0];
    expect($entry['user_id'])->toBe($minor->id)
        ->and($entry['self'])->toBeFalse()
        ->and($entry['allowed'])->toBe(! $selfConfirmed)
        ->and($entry['reason'])->toBe($expectedReason);
})->with([
    'chargeable' => [false, null],
    'already confirmed by member' => [true, 'already_confirmed_by_member'],
]);

it('exposes the availability structure to support managers', function (): void {
    $admin = User::factory()->admin()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));
    $admin->givePermissionTo([
        Permission::findOrCreate('scheduling.view.support', 'web'),
        Permission::findOrCreate('scheduling.manage.support', 'web'),
    ]);
    $mentor = User::factory()->mentor()->create();
    [$session] = availabilityFixture($mentor, ['scheduled_at' => Carbon::now()->addDay()]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $actions = $this->actingAs($admin)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->json('available_actions');

    expect($actions['reschedule'])->toBe(['allowed' => true, 'reason' => null])
        ->and($actions['cancel']['allowed'])->toBeTrue()
        ->and($actions['complete'])->toBe(['allowed' => false, 'reason' => 'forbidden'])
        ->and($actions['attendance'])->toBe([]);
});
