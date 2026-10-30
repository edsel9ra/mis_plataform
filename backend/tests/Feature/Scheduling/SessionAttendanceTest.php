<?php

use App\Enums\AttendanceStatus;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendanceEvent;
use App\Models\SessionAttendee;
use App\Models\SessionParticipantExclusion;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class)->group('scheduling', 'attendance');

function attendanceSessionFixture(
    User $mentor,
    User $participant,
    string $sourceType = 'user',
    ?string $sourceId = null,
    string $sessionType = 'individual',
): array {
    $relationship = MentorshipRelationship::factory()->create([
        'type' => match ($sourceType) {
            'family_group' => 'familiar',
            'cohort' => 'grupal',
            'company' => 'empresa',
            default => 'personal',
        },
        'source_type' => $sourceType,
        'source_id' => $sourceId ?? $participant->id,
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

    foreach ([$mentor, $participant] as $user) {
        SessionAttendee::factory()->create([
            'session_id' => $session->id,
            'user_id' => $user->id,
            'created_at' => $endAt->subDays(2),
        ]);
    }

    return [$session->fresh(), $relationship, $endAt];
}

beforeEach(function (): void {
    Carbon::setTestNow();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('lets a participant confirm their own attendance and hides other attendance from them', function (): void {
    $mentor = User::factory()->mentor()->create();
    $participant = User::factory()->create();
    $other = User::factory()->create();
    [$session, , $endAt] = attendanceSessionFixture($mentor, $participant);
    $otherAttendee = SessionAttendee::factory()->create([
        'session_id' => $session->id,
        'user_id' => $other->id,
        'created_at' => $endAt->subDays(2),
        'attendance_status' => AttendanceStatus::Absent,
    ]);
    Carbon::setTestNow($endAt);

    $this->actingAs($participant)
        ->putJson("/api/v1/sessions/{$session->id}/attendance", ['status' => 'attended'])
        ->assertOk()
        ->assertJsonPath('attendance_status', 'attended')
        ->assertJsonPath('confirmed_by', $participant->id)
        ->assertJsonPath('attended', true);

    $this->actingAs($participant)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->assertJsonCount(1, 'attendees')
        ->assertJsonPath('attendees.0.user.id', $participant->id)
        ->assertJsonMissing(['user' => ['id' => $other->id]]);

    $this->actingAs($mentor)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->assertJsonCount(3, 'attendees');

    expect($otherAttendee->fresh()->attendance_status)->toBe(AttendanceStatus::Absent)
        ->and(SessionAttendanceEvent::query()
            ->where('session_attendee_id', SessionAttendee::query()
                ->where('session_id', $session->id)
                ->where('user_id', $participant->id)
                ->value('id'))
            ->where('event_type', 'confirmation')
            ->count())->toBe(1);

    $session->update(['status' => 'completed']);
    $attendee = SessionAttendee::query()
        ->where('session_id', $session->id)
        ->where('user_id', $participant->id)
        ->firstOrFail();
    $metric = app(\App\Services\Progress\Calculators\SessionMetricsCalculator::class)->realized(
        [['id' => $session->id, 'status' => 'completed', 'scheduled_at' => $session->scheduled_at]],
        [$session->id => [$participant->id => ['attended' => $attendee->attended]]],
        $participant->id,
        $endAt,
        'UTC',
    );

    expect($metric->value)->toBe(1);
});

it('preserves manager and member confirmations in both orders while the member remains effective', function (): void {
    $manager = User::factory()->create();
    $mentor = User::factory()->mentor()->create();
    $firstMember = User::factory()->create();
    $secondMember = User::factory()->create();
    $family = FamilyGroup::create([
        'head_user_id' => $manager->id,
        'family_name' => 'Attendance family',
    ]);
    FamilyMember::create([
        'family_group_id' => $family->id,
        'user_id' => $firstMember->id,
        'full_name' => $firstMember->name,
        'age' => 12,
        'relationship' => 'child',
    ]);
    FamilyMember::create([
        'family_group_id' => $family->id,
        'user_id' => $secondMember->id,
        'full_name' => $secondMember->name,
        'age' => 14,
        'relationship' => 'child',
    ]);
    [$firstSession, , $endAt] = attendanceSessionFixture(
        $mentor,
        $firstMember,
        'family_group',
        $family->id,
        'family',
    );
    [$secondSession] = attendanceSessionFixture(
        User::factory()->mentor()->create(),
        $secondMember,
        'family_group',
        $family->id,
        'family',
    );
    SessionAttendee::query()
        ->where('session_id', $secondSession->id)
        ->where('user_id', $secondMember->id)
        ->firstOrFail()
        ->forceFill(['left_at' => $endAt->addSeconds(30)])
        ->save();
    Carbon::setTestNow($endAt->addMinute());

    $this->actingAs($manager)
        ->putJson("/api/v1/sessions/{$firstSession->id}/attendance", [
            'status' => 'attended',
            'attendee_user_id' => $firstMember->id,
        ])->assertOk();
    $this->actingAs($firstMember)
        ->putJson("/api/v1/sessions/{$firstSession->id}/attendance", [
            'status' => 'absent',
        ])->assertOk();

    $this->actingAs($secondMember)
        ->putJson("/api/v1/sessions/{$secondSession->id}/attendance", [
            'status' => 'absent',
        ])->assertOk();
    $this->actingAs($manager)
        ->putJson("/api/v1/sessions/{$secondSession->id}/attendance", [
            'status' => 'attended',
            'attendee_user_id' => $secondMember->id,
        ])->assertOk();

    foreach ([[$firstSession, $firstMember], [$secondSession, $secondMember]] as [$session, $member]) {
        $attendee = SessionAttendee::query()
            ->where('session_id', $session->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        expect($attendee->attendance_status)->toBe(AttendanceStatus::Absent)
            ->and($attendee->attended)->toBeFalse()
            ->and($attendee->confirmed_by)->toBe($member->id)
            ->and($attendee->attendanceEvents()->where('event_type', 'confirmation')->count())->toBe(2)
            ->and($attendee->attendanceEvents()->where('recorded_by', $manager->id)->exists())->toBeTrue()
            ->and($attendee->attendanceEvents()->where('recorded_by', $member->id)->exists())->toBeTrue();
    }

    expect(Activity::query()
        ->where('event', 'session.attendance_confirmed_for_participant')
        ->count())->toBe(2);
});

it('allows collective managers to confirm only eligible minors or company employees', function (): void {
    $mentor = User::factory()->mentor()->create();
    $familyManager = User::factory()->create();
    $familyMinor = User::factory()->create();
    $familyAdult = User::factory()->create();
    $family = FamilyGroup::create([
        'head_user_id' => $familyManager->id,
        'family_name' => 'Eligible family',
    ]);
    FamilyMember::create([
        'family_group_id' => $family->id,
        'user_id' => $familyMinor->id,
        'full_name' => $familyMinor->name,
        'age' => 17,
        'relationship' => 'child',
    ]);
    FamilyMember::create([
        'family_group_id' => $family->id,
        'user_id' => $familyAdult->id,
        'full_name' => $familyAdult->name,
        'age' => 18,
        'relationship' => 'child',
    ]);
    [$familySession] = attendanceSessionFixture($mentor, $familyMinor, 'family_group', $family->id, 'family');
    $familyAdultAttendee = SessionAttendee::factory()->create([
        'session_id' => $familySession->id,
        'user_id' => $familyAdult->id,
    ]);

    $cohortManager = User::factory()->create();
    $cohortMinor = User::factory()->create();
    $cohortAdult = User::factory()->create();
    $cohort = Cohort::create([
        'created_by' => $cohortManager->id,
        'name' => 'Attendance cohort',
        'status' => 'active',
    ]);
    CohortMember::create([
        'cohort_id' => $cohort->id,
        'user_id' => $cohortMinor->id,
        'status' => 'active',
    ]);
    CohortMember::create([
        'cohort_id' => $cohort->id,
        'user_id' => $cohortAdult->id,
        'status' => 'active',
    ]);
    FamilyMember::create([
        'family_group_id' => $family->id,
        'user_id' => $cohortMinor->id,
        'full_name' => $cohortMinor->name,
        'age' => 15,
        'relationship' => 'child',
    ]);
    [$cohortSession] = attendanceSessionFixture($mentor, $cohortMinor, 'cohort', $cohort->id, 'group');
    SessionAttendee::factory()->create([
        'session_id' => $cohortSession->id,
        'user_id' => $cohortAdult->id,
    ]);

    $companyManager = User::factory()->create();
    $employeeUser = User::factory()->create(['role' => 'employee']);
    $company = Company::create([
        'legal_name' => 'Attendance company',
        'tax_id' => 'attendance-company',
        'admin_id' => $companyManager->id,
    ]);
    Employee::create([
        'company_id' => $company->id,
        'user_id' => $employeeUser->id,
        'status' => 'active',
    ]);
    [$companySession] = attendanceSessionFixture($mentor, $employeeUser, 'company', $company->id, 'corporate');
    $endAt = CarbonImmutable::parse('2026-10-14 13:00:00', 'UTC');
    Carbon::setTestNow($endAt);

    $this->actingAs($familyManager)
        ->putJson("/api/v1/sessions/{$familySession->id}/attendance", [
            'status' => 'attended',
            'attendee_user_id' => $familyMinor->id,
        ])->assertOk();
    $this->actingAs($familyManager)
        ->putJson("/api/v1/sessions/{$familySession->id}/attendance", [
            'status' => 'attended',
            'attendee_user_id' => $familyAdult->id,
        ])->assertForbidden();
    $this->actingAs($cohortManager)
        ->putJson("/api/v1/sessions/{$cohortSession->id}/attendance", [
            'status' => 'attended',
            'attendee_user_id' => $cohortMinor->id,
        ])->assertOk();
    $this->actingAs($cohortManager)
        ->putJson("/api/v1/sessions/{$cohortSession->id}/attendance", [
            'status' => 'attended',
            'attendee_user_id' => $cohortAdult->id,
        ])->assertForbidden();
    $this->actingAs($companyManager)
        ->putJson("/api/v1/sessions/{$companySession->id}/attendance", [
            'status' => 'attended',
            'attendee_user_id' => $employeeUser->id,
        ])->assertOk();

    expect($familyAdultAttendee->fresh()->attendance_status)->toBe(AttendanceStatus::NotConfirmed);
});

it('rejects confirmations outside the window, on terminal sessions, and by unauthorized actors', function (): void {
    $mentor = User::factory()->mentor()->create();
    $participant = User::factory()->create();
    $outsider = User::factory()->create();
    [$session, , $endAt] = attendanceSessionFixture($mentor, $participant);
    Carbon::setTestNow($endAt->subSecond());

    $this->actingAs($participant)
        ->putJson("/api/v1/sessions/{$session->id}/attendance", ['status' => 'attended'])
        ->assertUnprocessable();

    Carbon::setTestNow($endAt);
    $this->actingAs($mentor)
        ->putJson("/api/v1/sessions/{$session->id}/attendance", [
            'status' => 'attended',
            'attendee_user_id' => $participant->id,
        ])->assertForbidden();

    $this->actingAs($outsider)
        ->putJson("/api/v1/sessions/{$session->id}/attendance", ['status' => 'attended'])
        ->assertNotFound();

    $admin = User::factory()->admin()->create();
    $permission = Permission::findOrCreate('scheduling.manage.support', 'web');
    $admin->givePermissionTo($permission);
    $this->actingAs($admin)
        ->putJson("/api/v1/sessions/{$session->id}/attendance", [
            'status' => 'attended',
            'attendee_user_id' => $participant->id,
        ])->assertForbidden();

    foreach (['canceled', 'not_completed'] as $status) {
        $terminal = Session::factory()->create([
            'relationship_id' => $session->relationship_id,
            'scheduled_at' => $endAt->subHour(),
            'duration_minutes' => 60,
            'status' => $status,
        ]);
        $terminalAttendee = SessionAttendee::factory()->create([
            'session_id' => $terminal->id,
            'user_id' => $participant->id,
            'created_at' => $endAt->subDays(2),
        ]);
        $this->actingAs($participant)
            ->putJson("/api/v1/sessions/{$terminal->id}/attendance", ['status' => 'attended'])
            ->assertUnprocessable();

        expect($terminalAttendee->fresh()->attendance_status)->toBe(AttendanceStatus::NotConfirmed)
            ->and($terminalAttendee->attendanceEvents()->where('event_type', 'confirmation')->exists())->toBeFalse();
    }

    Carbon::setTestNow($endAt->addHours(48));
    $this->actingAs($participant)
        ->putJson("/api/v1/sessions/{$session->id}/attendance", ['status' => 'attended'])
        ->assertUnprocessable();
});

it('rejects a case 41 participant exclusion without recording confirmation', function (): void {
    $mentor = User::factory()->mentor()->create();
    $participant = User::factory()->create();
    [$session, , $endAt] = attendanceSessionFixture($mentor, $participant);
    $attendee = SessionAttendee::query()
        ->where('session_id', $session->id)
        ->where('user_id', $participant->id)
        ->firstOrFail();
    SessionParticipantExclusion::create([
        'session_id' => $session->id,
        'user_id' => $participant->id,
        'excluded_at' => $endAt->subHour(),
    ]);
    Carbon::setTestNow($endAt);

    $this->actingAs($participant)
        ->putJson("/api/v1/sessions/{$session->id}/attendance", ['status' => 'attended'])
        ->assertUnprocessable();

    expect($attendee->fresh()->attendance_status)->toBe(AttendanceStatus::NotConfirmed)
        ->and($attendee->attendanceEvents()->where('event_type', 'confirmation')->exists())->toBeFalse();
});
