<?php

use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

uses()->group('scheduling', 'booking', 'concurrency');

function concurrencyActor(array $attributes = []): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->create(array_merge([
        'client_type' => 'personal',
        'is_active' => true,
    ], $attributes)));
}

function concurrencyRelationship(User $mentor, User $mentee): MentorshipRelationship
{
    return MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $mentee->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
}

function concurrentBookingPayload(MentorshipRelationship $relationship, Carbon $start, string $title): array
{
    return [
        'relationship_id' => $relationship->id,
        'session_type' => 'individual',
        'title' => $title,
        'scheduled_at' => $start->toIso8601String(),
        'duration_minutes' => 60,
    ];
}

/**
 * Launch two independent PHP/Laravel processes and release them together.
 * Each process opens its own PostgreSQL connection and calls the booking action.
 *
 * @return list<array{status: string, session_id?: string, error?: string}>
 */
function runConcurrentBookings(array $requests): array
{
    $gatePath = tempnam(sys_get_temp_dir(), 'booking-gate-');
    if ($gatePath === false) {
        throw new RuntimeException('Could not create the concurrency gate.');
    }
    unlink($gatePath);

    $workers = [];
    try {
        foreach ($requests as $request) {
            $inputPath = tempnam(sys_get_temp_dir(), 'booking-input-');
            $readyPath = tempnam(sys_get_temp_dir(), 'booking-ready-');
            $resultPath = tempnam(sys_get_temp_dir(), 'booking-result-');
            if ($inputPath === false || $readyPath === false || $resultPath === false) {
                throw new RuntimeException('Could not create concurrency worker files.');
            }
            unlink($readyPath);
            file_put_contents($inputPath, json_encode($request, JSON_THROW_ON_ERROR));

            $pipes = [];
            $process = proc_open([
                PHP_BINARY,
                base_path('tests/Support/SessionBookingConcurrencyWorker.php'),
                $inputPath,
                $gatePath,
                $readyPath,
                $resultPath,
            ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());

            if (! is_resource($process)) {
                throw new RuntimeException('Could not start a concurrency worker.');
            }

            fclose($pipes[0]);
            $workers[] = compact('process', 'pipes', 'inputPath', 'readyPath', 'resultPath');
        }

        $deadline = microtime(true) + 10;
        do {
            $allReady = collect($workers)->every(fn (array $worker): bool => file_exists($worker['readyPath']));
            if ($allReady) {
                break;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        if (! $allReady) {
            throw new RuntimeException('Concurrent booking workers did not reach the start barrier.');
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
                throw new RuntimeException("Concurrency worker exited {$exitCode}: {$stderr} {$stdout}");
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

function removeCommittedConcurrencyFixtures(array $relationshipIds, array $userIds): void
{
    $sessionIds = DB::table('sessions')->whereIn('relationship_id', $relationshipIds)->pluck('id');

    if ($sessionIds->isNotEmpty()) {
        DB::table('activity_log')
            ->where('subject_type', Session::class)
            ->whereIn('subject_id', $sessionIds)
            ->delete();
        DB::table('sessions')->whereIn('id', $sessionIds)->delete();
    }

    DB::table('mentorship_relationships')->whereIn('id', $relationshipIds)->delete();
    DB::table('users')->whereIn('id', $userIds)->delete();
}

it('has a database uniqueness backstop for active bookings in the same relationship and start time', function () {
    $mentor = concurrencyActor(['role' => 'mentor']);
    $mentee = concurrencyActor(['role' => 'mentee']);
    $relationship = concurrencyRelationship($mentor, $mentee);
    $start = now()->addDays(3)->startOfHour();

    Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => $start,
        'status' => 'scheduled',
    ]);

    expect(fn () => DB::transaction(fn () => Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => $start,
        'status' => 'scheduled',
    ])))->toThrow(QueryException::class);

    foreach (['canceled', 'not_completed', 'completed'] as $status) {
        Session::factory()->create([
            'relationship_id' => $relationship->id,
            'scheduled_at' => $start,
            'status' => $status,
        ]);
    }

    expect(DB::table('sessions')->where('relationship_id', $relationship->id)->count())->toBe(4);
});

it('serializes simultaneous duplicate booking requests and returns the created session', function () {
    $mentor = concurrencyActor(['role' => 'mentor']);
    $mentee = concurrencyActor(['role' => 'mentee']);
    $relationship = concurrencyRelationship($mentor, $mentee);
    $payload = concurrentBookingPayload(
        $relationship,
        Carbon::parse(now()->addDays(3)->startOfHour()),
        'Same request',
    );

    $connection = DB::connection();
    $connection->commit();

    try {
        $results = runConcurrentBookings([
            ['actor_id' => $mentor->id, 'payload' => $payload],
            ['actor_id' => $mentor->id, 'payload' => $payload],
        ]);

        expect(collect($results)->pluck('status')->all())->toBe(['ok', 'ok'])
            ->and($results[0]['session_id'])->toBe($results[1]['session_id']);
        expect(collect($results)->pluck('http_status')->sort()->values()->all())->toBe([200, 201]);
        expect(DB::table('sessions')->where('relationship_id', $relationship->id)->count())->toBe(1);
    } finally {
        removeCommittedConcurrencyFixtures([$relationship->id], [$mentor->id, $mentee->id]);
        $connection->beginTransaction();
    }
});

it('serializes simultaneous bookings in distinct relationships that share a participant', function () {
    $mentee = concurrencyActor(['role' => 'mentee']);
    $firstMentor = concurrencyActor(['role' => 'mentor']);
    $secondMentor = concurrencyActor(['role' => 'mentor']);
    $firstRelationship = concurrencyRelationship($firstMentor, $mentee);
    $secondRelationship = concurrencyRelationship($secondMentor, $mentee);
    $start = Carbon::parse(now()->addDays(3)->startOfHour());

    $connection = DB::connection();
    $connection->commit();

    try {
        $results = runConcurrentBookings([
            [
                'actor_id' => $firstMentor->id,
                'payload' => concurrentBookingPayload($firstRelationship, $start, 'First booking'),
            ],
            [
                'actor_id' => $secondMentor->id,
                'payload' => concurrentBookingPayload($secondRelationship, $start->copy()->addMinutes(30), 'Second booking'),
            ],
        ]);

        expect(collect($results)->pluck('status')->sort()->values()->all())->toBe(['ok', 'rejected']);
        expect(collect($results)->pluck('http_status')->sort()->values()->all())->toBe([201, 422]);
        expect(DB::table('sessions')->whereIn('relationship_id', [
            $firstRelationship->id,
            $secondRelationship->id,
        ])->count())->toBe(1);
    } finally {
        removeCommittedConcurrencyFixtures(
            [$firstRelationship->id, $secondRelationship->id],
            [$mentee->id, $firstMentor->id, $secondMentor->id],
        );
        $connection->beginTransaction();
    }
});
