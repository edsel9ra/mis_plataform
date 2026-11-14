<?php

use App\Console\Commands\AdvanceSessionLifecycle;
use App\Enums\AttendanceStatus;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\User;
use App\Services\Scheduling\Rules\MeetAutomationRules;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class)->group('scheduling', 'timezone');

function tzSessionFixture(
    User $mentor,
    array $sessionAttributes,
    array $attendeeUserIds = [],
    ?User $source = null,
): Session {
    $source ??= User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $source->id,
        'mentor_id' => $mentor->id,
    ]);
    $session = Session::factory()->create(array_merge([
        'relationship_id' => $relationship->id,
        'session_type' => 'individual',
    ], $sessionAttributes));

    foreach ($attendeeUserIds as $userId) {
        SessionAttendee::factory()->create([
            'session_id' => $session->id,
            'user_id' => $userId,
            'created_at' => Carbon::now()->subDays(2),
        ]);
    }

    return $session->fresh();
}

beforeEach(function (): void {
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-14 12:00:00', 'UTC'));
});

it('rejects completing a session before its real end and accepts it after', function (): void {
    $mentor = User::factory()->mentor()->create();
    $session = tzSessionFixture($mentor, [
        'scheduled_at' => Carbon::now()->addHour(),
        'duration_minutes' => 30,
    ]);

    try {
        app(App\Actions\CompleteSessionAction::class)->execute($session->id, $mentor, Carbon::now());
        $this->fail('Completion succeeded before the real session end.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->not->toBeEmpty();
    }

    Carbon::setTestNow(Carbon::now()->addHour()->addMinutes(31));
    $completed = app(App\Actions\CompleteSessionAction::class)
        ->execute($session->id, $mentor, Carbon::now());
    expect($completed->status)->toBe('completed');
});

it('rejects attendance confirmation before real end, accepts after end, rejects after the window', function (): void {
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    $session = tzSessionFixture(
        $mentor,
        [
            'scheduled_at' => Carbon::now()->addHour(),
            'duration_minutes' => 30,
            'status' => 'completed',
        ],
        [$member->id],
        $member,
    );

    $confirm = fn () => app(App\Actions\ConfirmAttendanceAction::class)->execute(
        $session->id,
        $member,
        AttendanceStatus::Attended,
        $member->id,
        Carbon::now(),
    );

    try {
        $confirm();
        $this->fail('Attendance confirmed before the real session end.');
    } catch (ValidationException) {
        // expected: not_ended
    }

    Carbon::setTestNow(Carbon::now()->addHour()->addMinutes(31));
    $confirm();
    expect($session->fresh()->attendees->firstWhere('user_id', $member->id)->attendance_status)
        ->toBe(AttendanceStatus::Attended);

    $member2 = User::factory()->create();
    $session2 = tzSessionFixture(
        $mentor,
        [
            'scheduled_at' => Carbon::now()->subHours(49),
            'duration_minutes' => 30,
            'status' => 'completed',
        ],
        [$member2->id],
        $member2,
    );

    try {
        app(App\Actions\ConfirmAttendanceAction::class)->execute(
            $session2->id,
            $member2,
            AttendanceStatus::Attended,
            $member2->id,
            Carbon::now(),
        );
        $this->fail('Attendance confirmed after the real 48h window.');
    } catch (ValidationException) {
        // expected: window_closed
    }
});

it('advances the session lifecycle at real instants, not six hours early', function (): void {
    $mentor = User::factory()->mentor()->create();
    $session = tzSessionFixture($mentor, [
        'scheduled_at' => Carbon::now()->addMinutes(10),
        'duration_minutes' => 30,
        'status' => 'scheduled',
    ]);

    Artisan::call(AdvanceSessionLifecycle::class);
    expect($session->fresh()->status)->toBe('scheduled')
        ->and($session->fresh()->status)->not->toBe('in_progress');

    Carbon::setTestNow(Carbon::now()->addMinutes(11));
    Artisan::call(AdvanceSessionLifecycle::class);
    expect($session->fresh()->status)->toBe('in_progress');

    Carbon::setTestNow(Carbon::now()->addMinutes(11)->addMinutes(30)->addHours(48)->addMinute());
    Artisan::call(AdvanceSessionLifecycle::class);
    expect($session->fresh()->status)->toBe('not_completed');
});

it('treats a future session as eligible for meet automation', function (): void {
    $mentor = User::factory()->mentor()->create();
    $session = tzSessionFixture($mentor, [
        'scheduled_at' => Carbon::now()->addHours(2),
        'duration_minutes' => 30,
    ]);

    expect(MeetAutomationRules::eligible($session, Carbon::now()))->toBeTrue();
});

it('fires reminder horizons at real times', function (): void {
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    $session = tzSessionFixture(
        $mentor,
        ['scheduled_at' => Carbon::now()->addHours(2), 'duration_minutes' => 60],
        [$member->id],
        $member,
    );

    Artisan::call('app:send-session-reminders');

    expect(\App\Models\Notification::query()
        ->where('type', 'session_reminder')
        ->where('user_id', $member->id)
        ->whereJsonContains('data->session_id', $session->id)
        ->exists())->toBeTrue();
});

it('reports complete as not_ended on the detail contract before the real end', function (): void {
    $mentor = User::factory()->mentor()->create();
    $session = tzSessionFixture($mentor, [
        'scheduled_at' => Carbon::now()->addHour(),
        'duration_minutes' => 30,
    ]);

    $actions = $this->actingAs($mentor)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->json('available_actions');

    expect($actions['complete'])->toBe(['allowed' => false, 'reason' => 'not_ended'])
        ->and($actions['reschedule'])->toBe(['allowed' => false, 'reason' => 'insufficient_notice']);
});
