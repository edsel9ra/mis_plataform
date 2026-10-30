<?php

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\SessionParticipantExclusion;
use App\Models\User;
use App\Services\Scheduling\SessionParticipantSyncService;
use App\Services\Scheduling\Rules\ParticipantRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

uses()->group('scheduling', 'participants');

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-13 09:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function membershipSourceFixture(string $kind, User $mentor, User $user): array
{
    $manager = User::factory()->create();

    if ($kind === 'family') {
        $source = FamilyGroup::create([
            'head_user_id' => $manager->id,
            'family_name' => 'Membership test family',
        ]);
        $sourceType = 'family_group';
        $sessionType = 'family';
    } elseif ($kind === 'cohort') {
        $source = Cohort::create([
            'created_by' => $manager->id,
            'name' => 'Membership test cohort',
            'status' => 'active',
        ]);
        $sourceType = 'cohort';
        $sessionType = 'group';
    } else {
        $source = Company::create([
            'admin_id' => $manager->id,
            'legal_name' => 'Membership test company',
            'tax_id' => fake()->unique()->numerify('########'),
            'industry' => 'Education',
            'size' => 'small',
        ]);
        $sourceType = 'company';
        $sessionType = 'corporate';
    }

    $relationship = MentorshipRelationship::factory()->create([
        'type' => match ($kind) {
            'family' => 'familiar',
            'cohort' => 'grupal',
            default => 'empresa',
        },
        'source_type' => $sourceType,
        'source_id' => $source->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'session_type' => $sessionType,
        'scheduled_at' => now()->addDay(),
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ]);

    return [$source, $session];
}

function createMembership(string $kind, mixed $source, User $user, string $status = 'active'): Model
{
    return match ($kind) {
        'family' => FamilyMember::create([
            'family_group_id' => $source->id,
            'user_id' => $user->id,
            'full_name' => $user->name,
            'relationship' => 'adult',
        ]),
        'cohort' => CohortMember::create([
            'cohort_id' => $source->id,
            'user_id' => $user->id,
            'status' => $status,
        ]),
        default => Employee::create([
            'company_id' => $source->id,
            'user_id' => $user->id,
            'status' => $status,
        ]),
    };
}

it('synchronizes eligible active members into future collective sessions for each source type idempotently', function (): void {
    foreach (['family', 'cohort', 'company'] as $kind) {
        $mentor = User::factory()->create(['role' => 'mentor']);
        $user = User::factory()->create();
        [$source, $session] = membershipSourceFixture($kind, $mentor, $user);

        $membership = createMembership($kind, $source, $user);

        expect(SessionAttendee::query()
            ->where('session_id', $session->id)
            ->where('user_id', $user->id)
            ->count())->toBe(1);

        app(SessionParticipantSyncService::class)->onMembershipChange($source, $user, 'added', now());

        expect(SessionAttendee::query()
            ->where('session_id', $session->id)
            ->where('user_id', $user->id)
            ->count())->toBe(1)
            ->and($membership->exists)->toBeTrue();
    }
});

it('excludes a new member with an overlapping personal session without changing the collective session', function (): void {
    $mentor = User::factory()->create(['role' => 'mentor']);
    $user = User::factory()->create();
    [$source, $collectiveSession] = membershipSourceFixture('cohort', $mentor, $user);
    $otherMentor = User::factory()->create(['role' => 'mentor']);
    $personalRelationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $user->id,
        'mentor_id' => $otherMentor->id,
        'status' => 'active',
    ]);
    $personalSession = Session::factory()->create([
        'relationship_id' => $personalRelationship->id,
        'scheduled_at' => $collectiveSession->scheduled_at->copy()->addMinutes(30),
        'duration_minutes' => 30,
        'status' => 'scheduled',
    ]);
    SessionAttendee::create(['session_id' => $personalSession->id, 'user_id' => $user->id]);

    createMembership('cohort', $source, $user);

    expect($collectiveSession->fresh()->status)->toBe('scheduled')
        ->and(SessionAttendee::query()
            ->where('session_id', $collectiveSession->id)
            ->where('user_id', $user->id)
            ->exists())->toBeFalse()
        ->and(SessionParticipantExclusion::query()
            ->where('session_id', $collectiveSession->id)
            ->where('user_id', $user->id)
            ->exists())->toBeTrue();
});

it('records a prospective departure without deleting confirmed attendance and leaves ended sessions unchanged', function (): void {
    $mentor = User::factory()->create(['role' => 'mentor']);
    $user = User::factory()->create();
    [$source, $session] = membershipSourceFixture('company', $mentor, $user);
    $membership = createMembership('company', $source, $user);
    $attendee = SessionAttendee::query()
        ->where('session_id', $session->id)
        ->where('user_id', $user->id)
        ->firstOrFail();
    $attendee->update(['attended' => true]);
    $endedSession = Session::factory()->create([
        'relationship_id' => $session->relationship_id,
        'scheduled_at' => now()->subHours(2),
        'duration_minutes' => 60,
        'status' => 'completed',
    ]);
    $endedAttendee = SessionAttendee::create([
        'session_id' => $endedSession->id,
        'user_id' => $user->id,
        'attended' => true,
    ]);

    $membership->update(['status' => 'inactive']);
    $departureAt = $attendee->fresh()->left_at;
    app(SessionParticipantSyncService::class)->onMembershipChange($source, $user, 'removed', now()->addMinute());

    expect($departureAt)->toEqual(now())
        ->and($attendee->fresh()->attended)->toBeTrue()
        ->and($attendee->fresh()->left_at)->toEqual($departureAt)
        ->and($endedAttendee->fresh()->left_at)->toBeNull()
        ->and(SessionAttendee::query()->whereKey($attendee->id)->exists())->toBeTrue();
});

it('does not add invited members and synchronizes them when they become active', function (): void {
    $mentor = User::factory()->create(['role' => 'mentor']);
    $user = User::factory()->create();
    [$source, $session] = membershipSourceFixture('company', $mentor, $user);

    $employee = createMembership('company', $source, $user, 'invited');

    expect(SessionAttendee::query()
        ->where('session_id', $session->id)
        ->where('user_id', $user->id)
        ->exists())->toBeFalse();

    $employee->update(['status' => 'active']);

    expect(SessionAttendee::query()
        ->where('session_id', $session->id)
        ->where('user_id', $user->id)
        ->exists())->toBeTrue();
});

it('handles each collective membership departure event without deleting attendee history', function (): void {
    foreach (['family', 'cohort', 'company'] as $kind) {
        $mentor = User::factory()->create(['role' => 'mentor']);
        $user = User::factory()->create();
        [$source] = membershipSourceFixture($kind, $mentor, $user);
        $membership = createMembership($kind, $source, $user);
        $attendee = SessionAttendee::query()
            ->whereHas('session', fn ($sessions) => $sessions->whereHas('relationship', fn ($relationship) =>
                $relationship->where('source_id', $source->id)))
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($kind === 'family') {
            $membership->delete();
        } else {
            $membership->update(['status' => $kind === 'cohort' ? 'dropped' : 'inactive']);
        }

        $leftAt = $attendee->fresh()->left_at;
        app(SessionParticipantSyncService::class)->onMembershipChange($source, $user, 'removed', now()->addMinute());

        expect($leftAt)->toEqual(now())
            ->and($attendee->fresh()->left_at)->toEqual($leftAt)
            ->and($attendee->fresh()->exists)->toBeTrue();
    }
});

it('does not add a member to a collective session whose end time has passed', function (): void {
    $mentor = User::factory()->create(['role' => 'mentor']);
    $user = User::factory()->create();
    [$source, $session] = membershipSourceFixture('cohort', $mentor, $user);
    $session->update([
        'scheduled_at' => now()->subHours(2),
        'duration_minutes' => 60,
        'status' => 'completed',
    ]);

    createMembership('cohort', $source, $user);

    expect(SessionAttendee::query()
        ->where('session_id', $session->id)
        ->where('user_id', $user->id)
        ->exists())->toBeFalse();
});

it('keeps a case 41 membership valid and permanently excludes the member for every collective type', function (): void {
    foreach (['family', 'cohort', 'company'] as $kind) {
        $mentor = User::factory()->create(['role' => 'mentor']);
        $user = User::factory()->create();
        [$source, $collectiveSession] = membershipSourceFixture($kind, $mentor, $user);
        $otherMentor = User::factory()->create(['role' => 'mentor']);
        $personalRelationship = MentorshipRelationship::factory()->create([
            'type' => 'personal',
            'source_type' => 'user',
            'source_id' => $user->id,
            'mentor_id' => $otherMentor->id,
            'status' => 'active',
        ]);
        $personalSession = Session::factory()->create([
            'relationship_id' => $personalRelationship->id,
            'scheduled_at' => $collectiveSession->scheduled_at->copy()->addMinutes(30),
            'duration_minutes' => 30,
            'status' => 'scheduled',
        ]);
        SessionAttendee::create(['session_id' => $personalSession->id, 'user_id' => $user->id]);

        $membership = createMembership($kind, $source, $user);
        $exclusion = SessionParticipantExclusion::query()
            ->where('session_id', $collectiveSession->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Retries after the conflicting session changes must not undo case 41.
        $personalSession->update(['status' => 'canceled']);
        app(SessionParticipantSyncService::class)->onMembershipChange($source, $user, 'added', now()->addMinute());

        expect($membership->exists)->toBeTrue()
            ->and($collectiveSession->fresh()->status)->toBe('scheduled')
            ->and($collectiveSession->fresh()->scheduled_at)->toEqual($collectiveSession->scheduled_at)
            ->and(SessionAttendee::query()
                ->where('session_id', $collectiveSession->id)
                ->where('user_id', $user->id)
                ->exists())->toBeFalse()
            ->and($exclusion->fresh()->exists)->toBeTrue();

        $endAt = $collectiveSession->scheduled_at->copy()->addMinutes($collectiveSession->duration_minutes);
        $registeredAttendees = $collectiveSession->attendees
            ->map(fn (SessionAttendee $attendee): array => [
                'user_id' => (string) $attendee->user_id,
                'created_at' => $attendee->created_at,
                'left_at' => $attendee->left_at,
            ])
            ->all();
        $excludedIds = $collectiveSession->participantExclusions->pluck('user_id')->map(fn ($id): string => (string) $id)->all();
        $finalParticipants = ParticipantRules::participantsAtEnd(
            ['mentor_id' => (string) $mentor->id, 'excluded_user_ids' => $excludedIds],
            $registeredAttendees,
            $endAt,
            now()->addDay(),
        );

        // Contract for 006's attendance snapshot and 008's reminder recipient set.
        expect($finalParticipants)->not->toContain((string) $user->id)
            ->and($finalParticipants)->toContain((string) $mentor->id)
            ->and(SessionAttendee::query()
                ->where('session_id', $collectiveSession->id)
                ->where('user_id', $user->id)
                ->exists())->toBeFalse();
    }
});

it('does not rewrite participation when a member leaves after the scheduled end even before lifecycle advancement', function (): void {
    foreach (['family', 'cohort', 'company'] as $kind) {
        $mentor = User::factory()->create(['role' => 'mentor']);
        $user = User::factory()->create();
        [$source, $session] = membershipSourceFixture($kind, $mentor, $user);
        Carbon::setTestNow('2026-10-13 06:00:00');
        $membership = createMembership($kind, $source, $user);
        Carbon::setTestNow('2026-10-13 09:00:00');
        $attendee = SessionAttendee::query()
            ->where('session_id', $session->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $attendee->update(['attended' => true]);
        $session->update(['scheduled_at' => now()->subHours(2), 'status' => 'scheduled']);
        $endAt = $session->scheduled_at->copy()->addMinutes($session->duration_minutes);

        if ($kind === 'family') {
            $membership->delete();
        } else {
            $membership->update(['status' => $kind === 'cohort' ? 'dropped' : 'inactive']);
        }

        $attendee->refresh();
        $participants = ParticipantRules::participantsAtEnd(
            ['mentor_id' => (string) $mentor->id],
            [[
                'user_id' => (string) $attendee->user_id,
                'created_at' => $attendee->created_at,
                'left_at' => $attendee->left_at,
            ]],
            $endAt,
            now(),
        );

        expect($attendee->left_at)->toBeNull()
            ->and($attendee->attended)->toBeTrue()
            ->and($participants)->toContain((string) $user->id);
    }
});

it('applies a departure before end at the participant anchor and does not revive it on duplicate removal', function (): void {
    foreach (['family', 'cohort', 'company'] as $kind) {
        $mentor = User::factory()->create(['role' => 'mentor']);
        $user = User::factory()->create();
        [$source, $session] = membershipSourceFixture($kind, $mentor, $user);
        $membership = createMembership($kind, $source, $user);
        $attendee = SessionAttendee::query()
            ->where('session_id', $session->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $departureAt = now()->addMinutes(30);
        $endAt = $session->scheduled_at->copy()->addMinutes($session->duration_minutes);

        Carbon::setTestNow($departureAt);
        if ($kind === 'family') {
            $membership->delete();
        } else {
            $membership->update(['status' => $kind === 'cohort' ? 'dropped' : 'inactive']);
        }
        app(SessionParticipantSyncService::class)->onMembershipChange($source, $user, 'removed', $departureAt->copy()->addMinute());

        $attendee->refresh();
        $participants = ParticipantRules::participantsAtEnd(
            ['mentor_id' => (string) $mentor->id],
            [[
                'user_id' => (string) $attendee->user_id,
                'created_at' => $attendee->created_at,
                'left_at' => $attendee->left_at,
            ]],
            $endAt,
            $departureAt->copy()->addDay(),
        );

        expect($attendee->left_at)->toEqual($departureAt)
            ->and($participants)->not->toContain((string) $user->id);
        Carbon::setTestNow('2026-10-13 09:00:00');
    }
});
