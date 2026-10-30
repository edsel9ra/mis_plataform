<?php

use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendanceEvent;
use App\Models\SessionAttendee;
use App\Models\SessionParticipantExclusion;
use App\Models\User;
use App\Services\Progress\Calculators\SessionMetricsCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class)->group('scheduling', 'lifecycle', 'attendance', 'concurrency');

function launchConcurrentLifecycleOperations(array $operations): array
{
    $gatePath = tempnam(sys_get_temp_dir(), 'lifecycle-gate-');
    if ($gatePath === false) {
        throw new RuntimeException('Could not create the concurrency gate.');
    }
    unlink($gatePath);

    $workers = [];
    try {
        foreach ($operations as $operation) {
            $inputPath = tempnam(sys_get_temp_dir(), 'lifecycle-input-');
            $readyPath = tempnam(sys_get_temp_dir(), 'lifecycle-ready-');
            $resultPath = tempnam(sys_get_temp_dir(), 'lifecycle-result-');
            if ($inputPath === false || $readyPath === false || $resultPath === false) {
                throw new RuntimeException('Could not create concurrency worker files.');
            }

            unlink($readyPath);
            file_put_contents($inputPath, json_encode($operation, JSON_THROW_ON_ERROR));
            $pipes = [];
            $process = proc_open([
                PHP_BINARY,
                base_path('tests/Support/SessionLifecycleConcurrencyWorker.php'),
                $inputPath,
                $gatePath,
                $readyPath,
                $resultPath,
            ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());

            if (! is_resource($process)) {
                throw new RuntimeException('Could not start a lifecycle concurrency worker.');
            }

            fclose($pipes[0]);
            $workers[] = compact('process', 'pipes', 'inputPath', 'readyPath', 'resultPath');
        }

        $deadline = microtime(true) + 15;
        do {
            $allReady = collect($workers)->every(fn (array $worker): bool => file_exists($worker['readyPath']));
            if ($allReady) {
                break;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        if (! $allReady) {
            throw new RuntimeException('Concurrent lifecycle workers did not reach the start barrier.');
        }

        file_put_contents($gatePath, 'start');
        $results = [];
        foreach ($workers as $worker) {
            $stdout = stream_get_contents($worker['pipes'][1]);
            $stderr = stream_get_contents($worker['pipes'][2]);
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            $exitCode = proc_close($worker['process']);

            if (! file_exists($worker['resultPath'])) {
                throw new RuntimeException("Lifecycle worker exited {$exitCode}: {$stderr} {$stdout}");
            }

            $results[] = json_decode(file_get_contents($worker['resultPath']), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        foreach ($workers as $worker) {
            if (is_resource($worker['process'])) {
                proc_terminate($worker['process']);
                proc_close($worker['process']);
            }
            foreach (['pipes', 'inputPath', 'readyPath', 'resultPath'] as $key) {
                if ($key === 'pipes') {
                    foreach ($worker['pipes'] as $pipe) {
                        if (is_resource($pipe)) {
                            fclose($pipe);
                        }
                    }
                    continue;
                }
                @unlink($worker[$key]);
            }
        }
        @unlink($gatePath);
    }
}

function removeCommittedLifecycleFixtures(string $relationshipId, array $userIds): void
{
    $sessionIds = DB::table('sessions')->where('relationship_id', $relationshipId)->pluck('id');
    if ($sessionIds->isNotEmpty()) {
        DB::table('activity_log')->where('subject_type', Session::class)->whereIn('subject_id', $sessionIds)->delete();
        $attendeeIds = DB::table('session_attendees')->whereIn('session_id', $sessionIds)->pluck('id');
        DB::table('session_attendance_events')->whereIn('session_attendee_id', $attendeeIds)->delete();
        DB::table('sessions')->whereIn('id', $sessionIds)->delete();
    }
    DB::table('mentorship_relationships')->where('id', $relationshipId)->delete();
    $familyGroupIds = DB::table('family_groups')->whereIn('head_user_id', $userIds)->pluck('id');
    if ($familyGroupIds->isNotEmpty()) {
        DB::table('family_members')->whereIn('family_group_id', $familyGroupIds)->delete();
        DB::table('family_groups')->whereIn('id', $familyGroupIds)->delete();
    }
    DB::table('users')->whereIn('id', $userIds)->delete();
}

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('serializes concurrent member and responsible confirmations while preserving both events and member precedence', function (): void {
    $manager = User::factory()->create();
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    $family = FamilyGroup::create(['head_user_id' => $manager->id, 'family_name' => 'Concurrent attendance']);
    FamilyMember::create([
        'family_group_id' => $family->id,
        'user_id' => $member->id,
        'full_name' => $member->name,
        'age' => 12,
        'relationship' => 'child',
    ]);
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'familiar',
        'source_type' => 'family_group',
        'source_id' => $family->id,
        'mentor_id' => $mentor->id,
    ]);
    $endAt = CarbonImmutable::parse('2026-10-14 13:00:00', 'UTC');
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'session_type' => 'family',
        'scheduled_at' => $endAt->subHour(),
        'duration_minutes' => 60,
    ]);
    $attendee = SessionAttendee::factory()->create([
        'session_id' => $session->id,
        'user_id' => $member->id,
        'created_at' => $endAt->subDays(2),
    ]);

    $connection = DB::connection();
    $connection->commit();
    try {
        $results = launchConcurrentLifecycleOperations([
            [
                'operation' => 'confirm', 'session_id' => $session->id, 'actor_id' => $manager->id,
                'target_user_id' => $member->id, 'status' => 'attended', 'now' => $endAt->addMinute()->toIso8601String(),
            ],
            [
                'operation' => 'confirm', 'session_id' => $session->id, 'actor_id' => $member->id,
                'status' => 'absent', 'now' => $endAt->addMinute()->toIso8601String(),
            ],
        ]);

        $attendee->refresh();
        expect($results)->toBe([['status' => 'ok'], ['status' => 'ok']])
            ->and($attendee->attendance_status)->toBe(AttendanceStatus::Absent)
            ->and($attendee->attended)->toBeFalse()
            ->and($attendee->confirmed_by)->toBe($member->id)
            ->and($attendee->attendanceEvents()->where('event_type', 'confirmation')->count())->toBe(2)
            ->and($attendee->attendanceEvents()->where('recorded_by', $manager->id)->exists())->toBeTrue()
            ->and($attendee->attendanceEvents()->where('recorded_by', $member->id)->exists())->toBeTrue();
    } finally {
        removeCommittedLifecycleFixtures($relationship->id, [$manager->id, $mentor->id, $member->id]);
        $connection->beginTransaction();
    }
});

it('rejects completion and confirmation concurrently at the inclusive auto-close deadline', function (): void {
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $member->id,
        'mentor_id' => $mentor->id,
    ]);
    $endAt = CarbonImmutable::parse('2026-10-14 13:00:00', 'UTC');
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => $endAt->subHour(),
        'duration_minutes' => 60,
    ]);
    $attendee = SessionAttendee::factory()->create([
        'session_id' => $session->id,
        'user_id' => $member->id,
        'created_at' => $endAt->subDays(2),
    ]);
    $deadline = $endAt->addHours(48);

    $connection = DB::connection();
    $connection->commit();
    try {
        $results = launchConcurrentLifecycleOperations([
            [
                'operation' => 'complete', 'session_id' => $session->id, 'actor_id' => $mentor->id,
                'now' => $deadline->toIso8601String(),
            ],
            [
                'operation' => 'confirm', 'session_id' => $session->id, 'actor_id' => $member->id,
                'status' => 'attended', 'now' => $deadline->toIso8601String(),
            ],
        ]);

        expect(collect($results)->pluck('status')->all())->toBe(['rejected', 'rejected']);
        CarbonImmutable::setTestNow($deadline);
        $this->artisan('app:advance-session-lifecycle')->assertSuccessful();

        expect($session->fresh()->status)->toBe(SessionStatus::NotCompleted->value)
            ->and($attendee->fresh()->attendance_status)->toBe(AttendanceStatus::NotConfirmed)
            ->and($attendee->attendanceEvents()->where('event_type', 'confirmation')->exists())->toBeFalse()
            ->and(SessionAttendanceEvent::query()->where('session_attendee_id', $attendee->id)
                ->where('event_type', 'initialization')->count())->toBe(1);
    } finally {
        CarbonImmutable::setTestNow();
        removeCommittedLifecycleFixtures($relationship->id, [$mentor->id, $member->id]);
        $connection->beginTransaction();
    }
});

it('keeps automatic lifecycle unchanged before the no-completed window and closes overdue scheduled sessions', function (): void {
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $member->id,
        'mentor_id' => $mentor->id,
    ]);
    $endAt = CarbonImmutable::parse('2026-10-14 13:00:00', 'UTC');
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => $endAt->subHour(),
        'duration_minutes' => 60,
    ]);
    $attendee = SessionAttendee::factory()->create([
        'session_id' => $session->id,
        'user_id' => $member->id,
        'created_at' => $endAt->subDays(2),
    ]);

    CarbonImmutable::setTestNow($endAt->addHours(48)->subSecond());
    $this->artisan('app:advance-session-lifecycle')->assertSuccessful();
    expect($session->fresh()->status)->toBe(SessionStatus::InProgress->value)
        ->and($attendee->attendanceEvents()->where('event_type', 'initialization')->exists())->toBeFalse();

    CarbonImmutable::setTestNow($endAt->addHours(48));
    $this->artisan('app:advance-session-lifecycle')->assertSuccessful();
    expect($session->fresh()->status)->toBe(SessionStatus::NotCompleted->value)
        ->and($attendee->fresh()->attendance_status)->toBe(AttendanceStatus::NotConfirmed)
        ->and($attendee->fresh()->attended)->toBeFalse()
        ->and($attendee->attendanceEvents()->where('event_type', 'initialization')->count())->toBe(1);
    CarbonImmutable::setTestNow();
});

it('excludes a case 41 member from completion initialization and realized-session metrics', function (): void {
    $mentor = User::factory()->mentor()->create();
    $member = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $member->id,
        'mentor_id' => $mentor->id,
    ]);
    $endAt = CarbonImmutable::parse('2026-10-14 13:00:00', 'UTC');
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => $endAt->subHour(),
        'duration_minutes' => 60,
        'status' => 'completed',
    ]);
    $attendee = SessionAttendee::factory()->create([
        'session_id' => $session->id,
        'user_id' => $member->id,
        'created_at' => $endAt->subDays(2),
        'attendance_status' => AttendanceStatus::NotConfirmed,
        'attended' => false,
    ]);
    SessionParticipantExclusion::create([
        'session_id' => $session->id,
        'user_id' => $member->id,
        'excluded_at' => $endAt->subHour(),
    ]);

    $metric = app(SessionMetricsCalculator::class)->realized(
        [
            ['id' => $session->id, 'status' => 'completed', 'scheduled_at' => $session->scheduled_at],
            ['id' => 'not-completed', 'status' => 'not_completed', 'scheduled_at' => $session->scheduled_at],
        ],
        [
            $session->id => [$member->id => ['attended' => $attendee->attended]],
            'not-completed' => [$member->id => ['attended' => true]],
        ],
        $member->id,
        $endAt,
        'UTC',
    );

    expect(SessionAttendanceEvent::query()->where('session_attendee_id', $attendee->id)->exists())->toBeFalse()
        ->and($metric->value)->toBe(0);
});
