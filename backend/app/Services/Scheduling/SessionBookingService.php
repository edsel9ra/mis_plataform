<?php

namespace App\Services\Scheduling;

use App\Enums\AvailabilityMode;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorAvailabilityException;
use App\Models\MentorAvailabilityWindow;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\User;
use App\Services\ContentAccessService;
use App\Services\Scheduling\Rules\AvailabilityRules;
use App\Services\Scheduling\Rules\DuplicateDetector;
use App\Services\Scheduling\Rules\OverlapDetector;
use App\Services\Scheduling\Rules\TimezoneRules;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SessionBookingService
{
    public function __construct(
        private ContentAccessService $contentAccess,
        private SchedulingAuditLogger $auditLogger,
    ) {}

    public function validateAndCreate(array $data, User $actor, Carbon $now): Session
    {
        return DB::transaction(function () use ($data, $actor, $now): Session {
            $relationship = MentorshipRelationship::query()
                ->lockForUpdate()
                ->findOrFail($data['relationship_id']);

            $source = $this->lockSource($relationship);
            Gate::forUser($actor)->authorize('create', [Session::class, $relationship]);

            if ($relationship->status !== 'active') {
                $this->reject('relationship_id', __('sessions.relationship_inactive'));
            }

            $expectedType = $this->expectedSessionType($relationship->type);
            if ($expectedType === null || $expectedType !== ($data['session_type'] ?? null)) {
                $this->reject('session_type', __('sessions.type_mismatch'));
            }

            $scheduledAt = $this->scheduledAt($data['scheduled_at'] ?? null);
            $duration = $this->duration($data['duration_minutes'] ?? null);
            $title = $this->title($data['title'] ?? null);
            $timezone = $this->timezone($data['timezone'] ?? null, $actor);
            $endAt = $scheduledAt->copy()->addMinutes($duration);

            if ($scheduledAt->lessThanOrEqualTo($now)) {
                $this->reject('scheduled_at', __('sessions.date_not_future'));
            }

            $participantIds = $this->participantIds($relationship, $source);
            $this->lockParticipants($participantIds);

            $existing = $this->existingSessions($participantIds, $scheduledAt, $endAt);
            $candidate = [
                'relationship_id' => $relationship->id,
                'start' => $scheduledAt,
                'duration_minutes' => $duration,
                'title' => $title,
            ];
            $duplicateId = DuplicateDetector::isDuplicate(
                $candidate,
                $this->duplicateCandidates($relationship, $scheduledAt, $endAt),
                $now,
            );

            if ($duplicateId !== null) {
                return Session::with(['relationship.mentor', 'attendees.user'])->findOrFail($duplicateId);
            }

            $conflicts = OverlapDetector::conflicts([
                'start' => $scheduledAt,
                'end' => $endAt,
                'participants' => $participantIds,
            ], $existing, $now);

            if ($conflicts !== []) {
                $conflictSessionId = $conflicts[0]['session_id'];
                $this->reject(
                    'scheduled_at',
                    __('sessions.session_overlap', ['session' => $conflictSessionId]),
                );
            }

            $this->validateAvailability($relationship->mentor_id, $timezone, $scheduledAt, $endAt, $now);
            $this->validateMonthlyLimit($relationship, $scheduledAt);

            $session = Session::create([
                'relationship_id' => $relationship->id,
                'session_type' => $expectedType,
                'title' => $title,
                'description' => $data['description'] ?? null,
                'scheduled_at' => $scheduledAt,
                'timezone' => $timezone,
                'duration_minutes' => $duration,
                'status' => 'scheduled',
            ]);

            foreach ($participantIds as $userId) {
                SessionAttendee::create([
                    'session_id' => $session->id,
                    'user_id' => $userId,
                ]);
            }

            $session->load(['relationship.mentor', 'attendees.user']);
            $this->auditLogger->record(
                $session,
                $actor,
                'session.created',
                ['relationship_id', 'session_type', 'title', 'scheduled_at', 'duration_minutes', 'timezone'],
            );

            return $session;
        });
    }

    /**
     * Lock a session in the booking lock order: relationship, source, session.
     * The caller must already be inside the transaction that performs the change.
     */
    public function lockSessionForReschedule(string $sessionId): Session
    {
        $relationshipId = Session::query()->whereKey($sessionId)->value('relationship_id');
        if ($relationshipId === null) {
            return Session::query()->findOrFail($sessionId);
        }

        $relationship = MentorshipRelationship::query()
            ->lockForUpdate()
            ->findOrFail($relationshipId);
        $source = $this->lockSource($relationship);
        $session = Session::query()
            ->where('relationship_id', $relationship->id)
            ->lockForUpdate()
            ->findOrFail($sessionId);

        $relationship->setRelation('source', $source);
        $session->setRelation('relationship', $relationship);

        return $session;
    }

    /**
     * Revalidate a rescheduling candidate with the same scheduling rules as creation.
     * Must be called inside the caller's transaction after locking the session.
     *
     * @return array{scheduled_at: Carbon, duration_minutes: int, timezone: string}
     */
    public function validateReschedule(Session $session, array $data, User $actor, Carbon $now): array
    {
        $relationship = $session->relationship;
        $source = $relationship->source;

        if ($relationship->status !== 'active') {
            $this->reject('relationship_id', __('sessions.relationship_inactive'));
        }

        $expectedType = $this->expectedSessionType($relationship->type);
        if ($expectedType === null || $expectedType !== $session->session_type) {
            $this->reject('session_type', __('sessions.type_mismatch'));
        }

        $scheduledAt = $this->scheduledAtForReschedule($data['scheduled_at'] ?? null);
        $duration = $this->duration($data['duration_minutes'] ?? $session->duration_minutes);
        $timezone = $this->timezone(
            array_key_exists('timezone', $data) ? $data['timezone'] : $session->timezone,
            $actor,
        );
        $endAt = $scheduledAt->copy()->addMinutes($duration);

        if ($scheduledAt->lessThanOrEqualTo($now)) {
            $this->reject('scheduled_at', __('sessions.date_not_future'));
        }

        $participantIds = $this->participantIds($relationship, $source);
        $this->lockParticipants($participantIds);

        $existing = $this->existingSessions($participantIds, $scheduledAt, $endAt, $session->id);
        $candidate = [
            'relationship_id' => $relationship->id,
            'start' => $scheduledAt,
            'duration_minutes' => $duration,
            'title' => $session->title,
        ];
        $duplicateId = DuplicateDetector::isDuplicate(
            $candidate,
            $this->duplicateCandidates($relationship, $scheduledAt, $endAt, $session->id),
            $now,
        );

        if ($duplicateId !== null) {
            $this->reject('scheduled_at', __('sessions.session_duplicate'));
        }

        $conflicts = OverlapDetector::conflicts([
            'start' => $scheduledAt,
            'end' => $endAt,
            'participants' => $participantIds,
        ], $existing, $now);

        if ($conflicts !== []) {
            $this->reject(
                'scheduled_at',
                __('sessions.session_overlap', ['session' => $conflicts[0]['session_id']]),
            );
        }

        $this->validateAvailability($relationship->mentor_id, $timezone, $scheduledAt, $endAt, $now);
        $this->validateMonthlyLimit($relationship, $scheduledAt, $session->id);

        return [
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => $duration,
            'timezone' => $timezone,
        ];
    }

    private function scheduledAt(mixed $value): Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            $this->reject('scheduled_at', __('sessions.date_not_future'));
        }
    }

    private function scheduledAtForReschedule(mixed $value): Carbon
    {
        try {
            return Carbon::parse($value)->setTimezone((string) config('app.timezone'));
        } catch (\Throwable) {
            $this->reject('scheduled_at', __('sessions.date_not_future'));
        }
    }

    private function duration(mixed $value): int
    {
        $minimum = (int) config('scheduling.min_duration_minutes', 15);
        $maximum = (int) config('scheduling.max_duration_minutes', 480);

        if (filter_var($value, FILTER_VALIDATE_INT) === false || $value < $minimum || $value > $maximum) {
            $this->reject('duration_minutes', __('sessions.duration_out_of_range', [
                'min' => $minimum,
                'max' => $maximum,
            ]));
        }

        return (int) $value;
    }

    private function title(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 200) {
            $this->reject('title', __('sessions.title_required'));
        }

        return $value;
    }

    private function timezone(?string $requestedTimezone, User $actor): string
    {
        $systemTimezone = (string) config('scheduling.default_timezone');

        if ($requestedTimezone !== null && ! $this->isValidTimezone($requestedTimezone)) {
            $this->reject('timezone', __('sessions.timezone_invalid'));
        }

        if ($requestedTimezone !== null) {
            return $requestedTimezone;
        }

        return TimezoneRules::resolveProfileTimezone($actor->timezone, $systemTimezone);
    }

    private function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true);
    }

    private function expectedSessionType(string $relationshipType): ?string
    {
        return match ($relationshipType) {
            'personal' => 'individual',
            'familiar' => 'family',
            'grupal' => 'group',
            'empresa' => 'corporate',
            default => null,
        };
    }

    private function lockSource(MentorshipRelationship $relationship): mixed
    {
        return match ($relationship->source_type) {
            'user' => User::query()->lockForUpdate()->findOrFail($relationship->source_id),
            'family_group' => FamilyGroup::query()->lockForUpdate()->findOrFail($relationship->source_id),
            'cohort' => Cohort::query()->lockForUpdate()->findOrFail($relationship->source_id),
            'company' => Company::query()->lockForUpdate()->findOrFail($relationship->source_id),
            default => throw ValidationException::withMessages([
                'relationship_id' => [__('sessions.source_unavailable')],
            ]),
        };
    }

    private function participantIds(MentorshipRelationship $relationship, mixed $source): array
    {
        $participantIds = collect([$relationship->mentor_id]);

        if ($source instanceof User) {
            $participantIds->push($source->id);
        }

        if ($source instanceof FamilyGroup) {
            $participantIds->push($source->head_user_id);
            $participantIds->push(...FamilyMember::query()
                ->where('family_group_id', $source->id)
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->all());
        }

        if ($source instanceof Cohort) {
            $participantIds->push(...CohortMember::query()
                ->where('cohort_id', $source->id)
                ->where('status', 'active')
                ->pluck('user_id')
                ->all());
        }

        if ($source instanceof Company) {
            $participantIds->push($source->admin_id);
            $participantIds->push(...Employee::query()
                ->where('company_id', $source->id)
                ->where('status', 'active')
                ->pluck('user_id')
                ->all());
        }

        return $participantIds
            ->filter()
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function lockParticipants(array $participantIds): void
    {
        User::query()
            ->whereIn('id', $participantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    private function existingSessions(
        array $participantIds,
        Carbon $start,
        Carbon $end,
        ?string $excludeSessionId = null,
    ): array
    {
        $sessions = Session::query()
            ->whereNotIn('status', ['canceled', 'not_completed'])
            ->when($excludeSessionId !== null, fn ($query) => $query->whereKeyNot($excludeSessionId))
            ->where('scheduled_at', '<', $end)
            ->whereRaw("scheduled_at + (duration_minutes * INTERVAL '1 minute') > ?", [$start])
            ->where(function ($query) use ($participantIds): void {
                $query->whereHas('attendees', fn ($attendees) => $attendees->whereIn('user_id', $participantIds))
                    ->orWhereHas('relationship', fn ($relationship) => $relationship->whereIn('mentor_id', $participantIds))
                    ->orWhereHas('relationship', function ($relationship) use ($participantIds): void {
                        $relationship->whereIn('source_type', ['user', User::class])
                            ->whereIn('source_id', $participantIds);
                    });
            })
            ->with(['attendees:user_id,session_id', 'relationship:id,source_type,source_id,mentor_id'])
            ->get();

        return $sessions->map(function (Session $session): array {
            $relationship = $session->relationship;
            $participants = $session->attendees->pluck('user_id')->map(fn ($id): string => (string) $id);
            $participants->push((string) $relationship->mentor_id);

            if (in_array($relationship->source_type, ['user', User::class], true)) {
                $participants->push((string) $relationship->source_id);
            }

            $sessionStart = $session->scheduled_at;

            return [
                'id' => (string) $session->id,
                'start' => $sessionStart,
                'end' => $sessionStart->copy()->addMinutes((int) $session->duration_minutes),
                'status' => (string) $session->status,
                'participants' => $participants->unique()->values()->all(),
            ];
        })->all();
    }

    private function duplicateCandidates(
        MentorshipRelationship $relationship,
        Carbon $start,
        Carbon $end,
        ?string $excludeSessionId = null,
    ): array
    {
        return Session::query()
            ->where('relationship_id', $relationship->id)
            ->whereNotIn('status', ['canceled', 'not_completed'])
            ->when($excludeSessionId !== null, fn ($query) => $query->whereKeyNot($excludeSessionId))
            ->where('scheduled_at', '<', $end)
            ->whereRaw("scheduled_at + (duration_minutes * INTERVAL '1 minute') > ?", [$start])
            ->get(['id', 'relationship_id', 'scheduled_at', 'duration_minutes', 'title'])
            ->map(fn (Session $session): array => [
                'id' => (string) $session->id,
                'relationship_id' => (string) $session->relationship_id,
                'start' => $session->scheduled_at,
                'duration_minutes' => (int) $session->duration_minutes,
                'title' => (string) $session->title,
            ])
            ->all();
    }

    private function validateAvailability(
        string $mentorId,
        string $timezone,
        Carbon $start,
        Carbon $end,
        Carbon $now,
    ): void {
        $windows = MentorAvailabilityWindow::query()
            ->where('user_id', $mentorId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->map(fn (MentorAvailabilityWindow $window): array => [
                'day_of_week' => (int) $window->day_of_week,
                'start_time' => (string) $window->start_time,
                'end_time' => (string) $window->end_time,
                'timezone' => $window->timezone,
            ])
            ->all();
        $exceptions = MentorAvailabilityException::query()
            ->where('user_id', $mentorId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->map(fn (MentorAvailabilityException $exception): array => [
                'mode' => $exception->mode instanceof AvailabilityMode
                    ? $exception->mode
                    : AvailabilityMode::from((string) $exception->mode),
                'start_date' => $exception->start_date->toDateString(),
                'end_date' => $exception->end_date?->toDateString(),
                'start_time' => $exception->start_time?->format('H:i:s'),
                'end_time' => $exception->end_time?->format('H:i:s'),
                'timezone' => $exception->timezone,
            ])
            ->all();

        $decision = AvailabilityRules::check(
            $windows,
            $exceptions,
            ['start' => $start, 'end' => $end],
            $now,
            $timezone,
        );

        if (! $decision['allowed']) {
            $message = match ($decision['reason']) {
                'blocked' => __('sessions.availability_blocked'),
                default => __('sessions.outside_availability'),
            };
            $this->reject('scheduled_at', $message);
        }
    }

    private function validateMonthlyLimit(
        MentorshipRelationship $relationship,
        Carbon $scheduledAt,
        ?string $excludeSessionId = null,
    ): void
    {
        $plan = $this->contentAccess->activePlanForSource(
            $relationship->source_type,
            $relationship->source_id,
            $relationship->type,
        );

        if (! $plan) {
            return;
        }

        $sessionCount = Session::query()
            ->whereHas('relationship', function ($query) use ($relationship): void {
                $query->whereIn('source_type', $this->sourceTypes($relationship->source_type))
                    ->where('source_id', $relationship->source_id);
            })
            ->whereBetween('scheduled_at', [
                $scheduledAt->copy()->startOfMonth(),
                $scheduledAt->copy()->endOfMonth(),
            ])
            ->whereNotIn('status', ['canceled', 'not_completed'])
            ->when($excludeSessionId !== null, fn ($query) => $query->whereKeyNot($excludeSessionId))
            ->count();

        if ($sessionCount >= $plan->max_sessions_per_month) {
            $this->reject('session', __('sessions.plan_limit_reached'));
        }
    }

    private function sourceTypes(string $sourceType): array
    {
        return match ($sourceType) {
            'user', User::class => ['user', User::class],
            'family_group', FamilyGroup::class => ['family_group', FamilyGroup::class],
            'cohort', Cohort::class => ['cohort', Cohort::class],
            'company', Company::class => ['company', Company::class],
            default => [$sourceType],
        };
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
