<?php

use App\Enums\AttendanceRelation;
use App\Models\Cohort;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\User;
use App\Services\Scheduling\AttendanceEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class)->group('scheduling', 'attendance');

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function eligibilityFixture(
    User $mentor,
    string $sourceType,
    string $sourceId,
    string $sessionType,
    array $attendeeIds = [],
): array {
    $relationship = MentorshipRelationship::factory()->create([
        'type' => match ($sourceType) {
            'family_group' => 'familiar',
            'cohort' => 'grupal',
            'company' => 'empresa',
            default => 'personal',
        },
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'mentor_id' => $mentor->id,
    ]);
    $endAt = CarbonImmutable::parse('2026-10-14 13:00:00', 'UTC');
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'session_type' => $sessionType,
        'scheduled_at' => $endAt->subHour(),
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ]);

    foreach ($attendeeIds as $userId) {
        SessionAttendee::factory()->create([
            'session_id' => $session->id,
            'user_id' => $userId,
            'created_at' => $endAt->subDays(2),
        ]);
    }

    return [$session->fresh(), $relationship];
}

it('reports the actor as participant when confirming their own attendance', function (): void {
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    [$session, $relationship] = eligibilityFixture($mentor, 'user', $member->id, 'individual', [$member->id]);

    expect(app(AttendanceEligibility::class)->actorRelation($member, $member->id, $relationship))
        ->toBe(AttendanceRelation::Participant);
});

it('reports admins as other before any relationship role', function (): void {
    $admin = User::factory()->admin()->create();
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    [, $relationship] = eligibilityFixture($mentor, 'user', $member->id, 'individual');

    expect(app(AttendanceEligibility::class)->actorRelation($admin, $member->id, $relationship))
        ->toBe(AttendanceRelation::Other);
});

it('reports the relationship mentor', function (): void {
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    [, $relationship] = eligibilityFixture($mentor, 'user', $member->id, 'individual');

    expect(app(AttendanceEligibility::class)->actorRelation($mentor, $member->id, $relationship))
        ->toBe(AttendanceRelation::Mentor);
});

it('reports collective managers as responsible', function (string $sourceType, callable $setup): void {
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    [$sourceId, $manager] = $setup($member);
    [, $relationship] = eligibilityFixture($mentor, $sourceType, $sourceId, 'family', [$member->id]);

    expect(app(AttendanceEligibility::class)->actorRelation($manager, $member->id, $relationship))
        ->toBe(AttendanceRelation::Responsible);
})->with([
    'family head' => ['family_group', function (User $member): array {
        $manager = User::factory()->create();
        $family = FamilyGroup::create(['head_user_id' => $manager->id, 'family_name' => 'Eligibility family']);

        return [$family->id, $manager];
    }],
    'cohort creator' => ['cohort', function (User $member): array {
        $manager = User::factory()->create();
        $cohort = Cohort::create(['name' => 'Eligibility cohort', 'created_by' => $manager->id, 'status' => 'active']);

        return [$cohort->id, $manager];
    }],
    'company admin' => ['company', function (User $member): array {
        $manager = User::factory()->create();
        $company = Company::create(['legal_name' => 'Eligibility Co', 'admin_id' => $manager->id, 'subscription_status' => 'trial']);

        return [$company->id, $manager];
    }],
]);

it('reports unrelated actors and individual source users acting on others as other', function (): void {
    $mentor = User::factory()->mentor()->create();
    $source = User::factory()->create();
    $other = User::factory()->create();
    [, $relationship] = eligibilityFixture($mentor, 'user', $source->id, 'individual');

    $eligibility = app(AttendanceEligibility::class);
    expect($eligibility->actorRelation($other, $source->id, $relationship))->toBe(AttendanceRelation::Other)
        ->and($eligibility->actorRelation($source, $other->id, $relationship))->toBe(AttendanceRelation::Other);
});

it('returns family minors among attendees as chargeable targets', function (): void {
    $mentor = User::factory()->mentor()->create();
    $minor = User::factory()->create();
    $adult = User::factory()->create();
    $outsider = User::factory()->create();
    $family = FamilyGroup::create(['head_user_id' => User::factory()->create()->id, 'family_name' => 'Chargeable family']);
    FamilyMember::create(['family_group_id' => $family->id, 'user_id' => $minor->id, 'full_name' => $minor->name, 'age' => 12, 'relationship' => 'child']);
    FamilyMember::create(['family_group_id' => $family->id, 'user_id' => $adult->id, 'full_name' => $adult->name, 'age' => 40, 'relationship' => 'parent']);
    FamilyMember::create(['family_group_id' => $family->id, 'user_id' => $outsider->id, 'full_name' => $outsider->name, 'age' => 9, 'relationship' => 'child']);

    [$session, $relationship] = eligibilityFixture(
        $mentor, 'family_group', $family->id, 'family',
        [$minor->id, $adult->id],
    );

    expect(app(AttendanceEligibility::class)->chargeableTargetIds($relationship, $session))
        ->toBe([$minor->id]);
});

it('returns company employees among attendees as chargeable targets', function (): void {
    $mentor = User::factory()->mentor()->create();
    $employee = User::factory()->create();
    $nonEmployee = User::factory()->create();
    $absentEmployee = User::factory()->create();
    $company = Company::create(['legal_name' => 'Chargeable Co', 'admin_id' => User::factory()->create()->id, 'subscription_status' => 'trial']);
    Employee::create(['company_id' => $company->id, 'user_id' => $employee->id, 'status' => 'active']);
    Employee::create(['company_id' => $company->id, 'user_id' => $absentEmployee->id, 'status' => 'active']);

    [$session, $relationship] = eligibilityFixture(
        $mentor, 'company', $company->id, 'empresa',
        [$employee->id, $nonEmployee->id],
    );

    $chargeable = app(AttendanceEligibility::class)->chargeableTargetIds($relationship, $session);

    expect($chargeable)->toContain($employee->id)
        ->not->toContain($nonEmployee->id)
        ->not->toContain($absentEmployee->id);
});

it('returns only attendees with a single known minor age for cohorts', function (): void {
    $mentor = User::factory()->mentor()->create();
    $minor = User::factory()->create();
    $adult = User::factory()->create();
    $unknownAge = User::factory()->create();
    $conflictingAge = User::factory()->create();
    $cohort = Cohort::create(['name' => 'Chargeable cohort', 'created_by' => User::factory()->create()->id, 'status' => 'active']);

    FamilyMember::create(['family_group_id' => FamilyGroup::create(['head_user_id' => $minor->id, 'family_name' => 'F1'])->id, 'user_id' => $minor->id, 'full_name' => $minor->name, 'age' => 15, 'relationship' => 'child']);
    $otherFamily = FamilyGroup::create(['head_user_id' => $adult->id, 'family_name' => 'F2']);
    FamilyMember::create(['family_group_id' => $otherFamily->id, 'user_id' => $adult->id, 'full_name' => $adult->name, 'age' => 30, 'relationship' => 'other']);
    FamilyMember::create(['family_group_id' => $otherFamily->id, 'user_id' => $unknownAge->id, 'full_name' => $unknownAge->name, 'age' => null, 'relationship' => 'other']);
    FamilyMember::create(['family_group_id' => $otherFamily->id, 'user_id' => $conflictingAge->id, 'full_name' => $conflictingAge->name, 'age' => 10, 'relationship' => 'child']);
    FamilyMember::create(['family_group_id' => FamilyGroup::create(['head_user_id' => $cohort->created_by, 'family_name' => 'F3'])->id, 'user_id' => $conflictingAge->id, 'full_name' => $conflictingAge->name, 'age' => 20, 'relationship' => 'other']);

    [$session, $relationship] = eligibilityFixture(
        $mentor, 'cohort', $cohort->id, 'grupal',
        [$minor->id, $adult->id, $unknownAge->id, $conflictingAge->id],
    );

    expect(app(AttendanceEligibility::class)->chargeableTargetIds($relationship, $session))
        ->toBe([$minor->id]);
});

it('returns no chargeable targets for individual relationships', function (): void {
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    [$session, $relationship] = eligibilityFixture($mentor, 'user', $member->id, 'individual', [$member->id]);

    expect(app(AttendanceEligibility::class)->chargeableTargetIds($relationship, $session))->toBe([]);
});
