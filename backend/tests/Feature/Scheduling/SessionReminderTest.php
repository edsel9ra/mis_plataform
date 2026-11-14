<?php

use App\Events\SessionReminder;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\Notification;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\SessionParticipantExclusion;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses()->group('scheduling', 'reminders');

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-13 09:00:00', config('app.timezone')));
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-13 09:00:00', config('app.timezone')));
});

afterEach(function (): void {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

function processSessionReminderBroadcastJob(BroadcastEvent $queued, BroadcastingFactory $factory): void
{
    $originalFactory = app(BroadcastingFactory::class);
    app()->instance(BroadcastingFactory::class, $factory);
    $data = ['command' => serialize($queued)];
    $payload = [
        'job' => CallQueuedHandler::class.'@call',
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'displayName' => BroadcastEvent::class,
        'data' => $data,
        'maxTries' => 1,
    ];
    $job = new SyncJob(app(), json_encode($payload, JSON_THROW_ON_ERROR), 'sync', 'default');

    try {
        app(CallQueuedHandler::class)->call($job, $data);
    } catch (Throwable $exception) {
        $job->fail($exception);
        throw $exception;
    } finally {
        app()->instance(BroadcastingFactory::class, $originalFactory);
    }
}

it('sends current localized reminders only to participants registered under the 005 contract', function (): void {
    Event::fake([SessionReminder::class]);

    $mentor = User::factory()->create(['role' => 'mentor', 'locale' => 'es']);
    $activeMember = User::factory()->create([
        'locale' => 'en',
        'timezone' => 'America/Mexico_City',
    ]);
    $departedMember = User::factory()->create();
    $excludedMember = User::factory()->create();
    $responsible = User::factory()->create();
    $cohort = Cohort::create([
        'created_by' => $responsible->id,
        'name' => 'Reminder cohort',
        'status' => 'active',
    ]);
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'grupal',
        'source_type' => 'cohort',
        'source_id' => $cohort->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'session_type' => 'group',
        'title' => 'Current session',
        'scheduled_at' => Carbon::parse('2026-10-13 11:00:00', config('app.timezone')),
        'duration_minutes' => 60,
        'timezone' => 'America/Mexico_City',
        'status' => 'scheduled',
        'meet_link' => 'https://meet.example/current',
    ]);
    CohortMember::create([
        'cohort_id' => $cohort->id,
        'user_id' => $activeMember->id,
        'status' => 'active',
    ]);
    SessionAttendee::create(['session_id' => $session->id, 'user_id' => $mentor->id]);
    SessionAttendee::create([
        'session_id' => $session->id,
        'user_id' => $departedMember->id,
        'left_at' => Carbon::parse('2026-10-13 08:00:00', 'UTC'),
    ]);
    SessionParticipantExclusion::create([
        'session_id' => $session->id,
        'user_id' => $excludedMember->id,
        'excluded_at' => Carbon::parse('2026-10-12 12:00:00', 'UTC'),
    ]);
    SessionAttendee::create(['session_id' => $session->id, 'user_id' => $excludedMember->id]);

    $this->artisan('app:send-session-reminders')->assertExitCode(0);

    $notifications = Notification::query()->where('type', 'session_reminder')->get();
    expect($notifications->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$mentor->id, $activeMember->id])->sort()->values()->all())
        ->and($notifications->pluck('status')->unique()->all())->toBe(['pending']);

    $memberReminder = $notifications->firstWhere('user_id', $activeMember->id);
    expect($memberReminder->data['scheduled_at'])->toBe('2026-10-13T17:00:00+00:00')
        ->and($memberReminder->data['timezone'])->toBe('America/Mexico_City')
        ->and($memberReminder->data['hours_until'])->toBe(2)
        ->and($memberReminder->data['meet_link'])->toBe('https://meet.example/current')
        ->and($memberReminder->data['message'])->toStartWith('Your session "Current session"')
        ->and($memberReminder->data['message'])->toContain('11:00')
        ->and($memberReminder->data['message'])->toContain('Google Meet link: https://meet.example/current');

    Event::assertDispatched(SessionReminder::class, 2);
    Event::assertDispatched(SessionReminder::class, fn (SessionReminder $event): bool =>
        in_array($event->notification->user_id, [$mentor->id, $activeMember->id], true)
    );
    Event::assertNotDispatched(SessionReminder::class, fn (SessionReminder $event): bool =>
        in_array($event->notification->user_id, [
            $departedMember->id,
            $excludedMember->id,
            $responsible->id,
        ], true)
    );
});

it('sends a localized pending-link reminder to a valid family participant and not its non-member responsible', function (): void {
    Event::fake([SessionReminder::class]);

    $mentor = User::factory()->create(['role' => 'mentor']);
    $member = User::factory()->create(['locale' => 'pt']);
    $responsible = User::factory()->create();
    $family = FamilyGroup::create([
        'head_user_id' => $responsible->id,
        'family_name' => 'Reminder family',
    ]);
    FamilyMember::create([
        'family_group_id' => $family->id,
        'user_id' => $member->id,
        'full_name' => $member->name,
        'relationship' => 'adult',
    ]);
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'familiar',
        'source_type' => 'family_group',
        'source_id' => $family->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'session_type' => 'family',
        'scheduled_at' => Carbon::parse('2026-10-13 10:00:00', config('app.timezone')),
        'duration_minutes' => 60,
        'timezone' => 'Europe/Lisbon',
        'status' => 'scheduled',
        'meet_link' => null,
    ]);
    SessionAttendee::create(['session_id' => $session->id, 'user_id' => $mentor->id]);
    SessionAttendee::create(['session_id' => $session->id, 'user_id' => $member->id]);

    $this->artisan('app:send-session-reminders')->assertExitCode(0);

    $notification = Notification::query()->where('user_id', $member->id)->firstOrFail();
    expect(Notification::query()->where('user_id', $responsible->id)->exists())->toBeFalse()
        ->and($notification->data['meet_link'])->toBeNull()
        ->and($notification->data['message'])->toStartWith('A sua sessão')
        ->and($notification->data['message'])->toContain('pendente')
        ->and($notification->data['message'])->toContain('17:00')
        ->and($notification->data['scheduled_at'])->toBe('2026-10-13T16:00:00+00:00')
        ->and($notification->data['timezone'])->toBe('Europe/Lisbon');

    Event::assertDispatched(SessionReminder::class, 2);
    Event::assertNotDispatched(SessionReminder::class, fn (SessionReminder $event): bool =>
        $event->notification->user_id === $responsible->id
    );
});

it('includes a company employee added after booking while excluding its responsible from ordinary reminders', function (): void {
    Event::fake([SessionReminder::class]);

    $mentor = User::factory()->create(['role' => 'mentor']);
    $employee = User::factory()->create(['locale' => 'es']);
    $responsible = User::factory()->create();
    $company = Company::create([
        'admin_id' => $responsible->id,
        'legal_name' => 'Reminder company',
        'tax_id' => fake()->unique()->numerify('########'),
        'industry' => 'Education',
        'size' => 'small',
    ]);
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'empresa',
        'source_type' => 'company',
        'source_id' => $company->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'session_type' => 'corporate',
        'scheduled_at' => Carbon::parse('2026-10-13 10:00:00', config('app.timezone')),
        'duration_minutes' => 60,
        'timezone' => 'America/Sao_Paulo',
        'status' => 'scheduled',
    ]);
    Employee::create([
        'company_id' => $company->id,
        'user_id' => $employee->id,
        'status' => 'active',
    ]);

    $this->artisan('app:send-session-reminders')->assertExitCode(0);

    expect(Notification::query()->where('user_id', $employee->id)->exists())->toBeTrue()
        ->and(Notification::query()->where('user_id', $mentor->id)->exists())->toBeTrue()
        ->and(Notification::query()->where('user_id', $responsible->id)->exists())->toBeFalse();

    Event::assertDispatched(SessionReminder::class, 2);
    Event::assertNotDispatched(SessionReminder::class, fn (SessionReminder $event): bool =>
        $event->notification->user_id === $responsible->id
    );
});

it('does not send reminders for canceled or not-completed sessions', function (): void {
    Event::fake([SessionReminder::class]);
    $user = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $user->id,
    ]);

    foreach (['canceled', 'not_completed'] as $status) {
        $session = Session::factory()->create([
            'relationship_id' => $relationship->id,
            'scheduled_at' => Carbon::parse('2026-10-13 11:00:00', 'UTC'),
            'status' => $status,
        ]);
        SessionAttendee::create(['session_id' => $session->id, 'user_id' => $user->id]);
    }

    $this->artisan('app:send-session-reminders')->assertExitCode(0);

    expect(Notification::query()->where('type', 'session_reminder')->count())->toBe(0);
    Event::assertNotDispatched(SessionReminder::class);
});

it('retries a dispatch-failed ordinary reminder without duplicating the pending broadcast', function (): void {
    $user = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $user->id,
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => Carbon::parse('2026-10-13 11:00:00', 'UTC'),
        'timezone' => 'Europe/Madrid',
        'status' => 'scheduled',
        'meet_link' => null,
    ]);
    SessionAttendee::create(['session_id' => $session->id, 'user_id' => $user->id]);
    Event::listen(SessionReminder::class, function (SessionReminder $event) use ($user): void {
        if ($event->notification->user_id === $user->id) {
            throw new RuntimeException('Broadcast provider rejected delivery with private details');
        }
    });
    Log::spy();

    $this->artisan('app:send-session-reminders')->assertExitCode(0);

    $failed = Notification::query()
        ->where('type', 'session_reminder')
        ->where('user_id', $user->id)
        ->sole();
    expect($failed->status)->toBe('failed')
        ->and($failed->sent_at)->toBeNull()
        ->and($failed->data['hours_until'])->toBe(2)
        ->and($failed->data['timezone'])->toBe('Europe/Madrid');
    Log::shouldHaveReceived('warning')->with('scheduling.reminder_delivery_failed', Mockery::on(
        fn (array $context): bool => $context['session_id'] === $session->id
            && $context['notification_id'] === $failed->id
            && $context['horizon_hours'] === 2
            && $context['exception'] === RuntimeException::class
            && ! str_contains(serialize($context), 'private details')
            && ! str_contains(serialize($context), $user->email),
    ))->once();

    Event::forget(SessionReminder::class);
    Event::fake([SessionReminder::class]);
    $this->artisan('app:send-session-reminders')->assertExitCode(0);
    $this->artisan('app:send-session-reminders')->assertExitCode(0);

    $sent = Notification::query()
        ->where('type', 'session_reminder')
        ->where('user_id', $user->id)
        ->sole();
    expect(Notification::query()->where('type', 'session_reminder')->count())->toBe(2)
        ->and($sent->id)->toBe($failed->id)
        ->and($sent->status)->toBe('pending')
        ->and($sent->sent_at)->toBeNull();
    Event::assertDispatched(SessionReminder::class, 1);
});

it('skips a due session whose mentorship relationship is no longer available', function (): void {
    Event::fake([SessionReminder::class]);
    $user = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $user->id,
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => Carbon::parse('2026-10-13 11:00:00', config('app.timezone')),
        'status' => 'scheduled',
    ]);
    SessionAttendee::create(['session_id' => $session->id, 'user_id' => $user->id]);
    $relationship->delete();

    $this->artisan('app:send-session-reminders')->assertExitCode(0);

    expect(Notification::query()->where('type', 'session_reminder')->exists())->toBeFalse();
    Event::assertNotDispatched(SessionReminder::class);
});

it('marks a reminder delivered only after its queued broadcast job succeeds and retries a failed job', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $user->id,
        'mentor_id' => $user->id,
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => Carbon::parse('2026-10-13 11:00:00', 'UTC'),
        'timezone' => 'Europe/Madrid',
        'status' => 'scheduled',
    ]);
    SessionAttendee::create(['session_id' => $session->id, 'user_id' => $user->id]);

    $this->artisan('app:send-session-reminders')->assertExitCode(0);

    $notification = Notification::query()
        ->where('type', 'session_reminder')
        ->where('user_id', $user->id)
        ->sole();
    $queued = Queue::pushed(
        BroadcastEvent::class,
        fn (BroadcastEvent $job): bool => $job->event instanceof SessionReminder
            && (string) $job->event->notification->user_id === (string) $user->id,
    )->first();
    expect($queued)->not->toBeNull();
    expect($notification->status)->toBe('pending')
        ->and($notification->sent_at)->toBeNull()
        ->and($queued->event->notification->id)->toBe($notification->id);

    $connection = Mockery::mock(Broadcaster::class);
    $connection->shouldReceive('broadcast')->once()->andThrow(
        new RuntimeException('provider failure after dispatch'),
    );
    $factory = Mockery::mock(BroadcastingFactory::class);
    $factory->shouldReceive('connection')->once()->andReturn($connection);
    try {
        processSessionReminderBroadcastJob($queued, $factory);
    } catch (RuntimeException) {
        // The queued BroadcastEvent reports terminal failure through its failed callback.
    }

    expect($notification->fresh()->status)->toBe('failed')
        ->and($notification->fresh()->sent_at)->toBeNull();

    Carbon::setTestNow(Carbon::parse('2026-10-13 09:05:00', 'UTC'));
    $this->artisan('app:send-session-reminders')->assertExitCode(0);
    $retry = Queue::pushed(
        BroadcastEvent::class,
        fn (BroadcastEvent $job): bool => $job->event instanceof SessionReminder
            && (string) $job->event->notification->user_id === (string) $user->id,
    )->last();
    expect($retry->event->notification->id)->toBe($notification->id)
        ->and($notification->fresh()->status)->toBe('pending');

    $connection = Mockery::mock(Broadcaster::class);
    $connection->shouldReceive('broadcast')->once()->andReturnNull();
    $factory = Mockery::mock(BroadcastingFactory::class);
    $factory->shouldReceive('connection')->once()->andReturn($connection);
    processSessionReminderBroadcastJob($retry, $factory);

    expect($notification->fresh()->status)->toBe('sent')
        ->and($notification->fresh()->sent_at)->not->toBeNull();

    $this->artisan('app:send-session-reminders')->assertExitCode(0);
    expect(Queue::pushed(
        BroadcastEvent::class,
        fn (BroadcastEvent $job): bool => $job->event instanceof SessionReminder,
    ))->toHaveCount(2);
});

it('retries a failed change-notice broadcast from its durable notification snapshot', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $notification = Notification::query()->create([
        'user_id' => $user->id,
        'type' => 'session_change',
        'dedupe_key' => 'session_change:session-a:event-a:'.$user->id,
        'status' => 'failed',
        'data' => [
            'event_type' => 'session.rescheduled',
            'event_id' => 'event-a',
            'session_id' => 'session-a',
            'scheduled_at' => '2026-10-14T12:00:00+00:00',
            'message' => 'Session rescheduled',
        ],
    ]);

    $this->artisan('app:send-session-reminders')->assertExitCode(0);

    $queued = Queue::pushed(
        BroadcastEvent::class,
        fn (BroadcastEvent $job): bool => $job->event->notification->id === $notification->id,
    )->first();
    expect($queued)->not->toBeNull()
        ->and($notification->fresh()->status)->toBe('pending')
        ->and($queued->event->notification->data['event_id'])->toBe('event-a');

    $connection = Mockery::mock(Broadcaster::class);
    $connection->shouldReceive('broadcast')->once()->andReturnNull();
    $factory = Mockery::mock(BroadcastingFactory::class);
    $factory->shouldReceive('connection')->once()->andReturn($connection);
    processSessionReminderBroadcastJob($queued, $factory);

    expect($notification->fresh()->status)->toBe('sent')
        ->and($notification->fresh()->sent_at)->not->toBeNull();
});
