<?php

namespace App\Http\Controllers\Api;

use App\Actions\ConfirmAttendanceAction;
use App\Actions\CancelSessionAction;
use App\Actions\CompleteSessionAction;
use App\Actions\ListBookableRelationshipsAction;
use App\Actions\CreateSessionAction;
use App\Actions\RescheduleSessionAction;
use App\Enums\AttendanceStatus;
use App\Http\Requests\Session\ConfirmAttendanceRequest;
use App\Http\Requests\Session\CancelSessionRequest;
use App\Http\Requests\Session\IndexSessionsRequest;
use App\Http\Requests\Session\StoreSessionRequest;
use App\Http\Requests\Session\UpdateSessionRequest;
use App\Http\Requests\Session\UpdateSessionStatusRequest;
use App\Http\Requests\Session\RescheduleSessionRequest;
use App\Http\Resources\BookableRelationshipResource;
use App\Http\Resources\SessionAttendeeResource;
use App\Http\Resources\SessionResource;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Services\GoogleMeetService;
use App\Services\Scheduling\Rules\MeetAutomationRules;
use App\Services\Scheduling\SchedulingAuditLogger;
use App\Services\Scheduling\SessionActionAvailability;
use App\Support\DbTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SessionController
{
    public function __construct(
        private CreateSessionAction $createSession,
        private ListBookableRelationshipsAction $listBookableRelationships,
        private ConfirmAttendanceAction $confirmAttendance,
        private CancelSessionAction $cancelSession,
        private RescheduleSessionAction $rescheduleSession,
        private GoogleMeetService $googleMeetService,
        private SchedulingAuditLogger $schedulingAuditLogger,
    ) {}

    public function index(IndexSessionsRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Session::class);

        $user = $request->user();
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $hasRange = $request->validated('from') !== null || $request->validated('to') !== null;
        $isSupportReader = $user->isAdmin();

        $query = Session::query();

        if (! $isSupportReader) {
            $query->whereHas('relationship', function ($relationshipQuery) use ($request, $user, $hasRange): void {
                if (! $hasRange) {
                    $relationshipQuery->forAgendaUser($user);

                    return;
                }

                if ($request->boolean('include_collectives')) {
                    $relationshipQuery->forAgendaUser($user);

                    return;
                }

                $relationshipQuery->forUser($user);
            });
        }

        if ($hasRange) {
            $from = DbTime::toRaw(CarbonImmutable::parse($request->validated('from')));
            $to = DbTime::toRaw(CarbonImmutable::parse($request->validated('to')));

            $query
                ->where('scheduled_at', '>=', $from)
                ->where('scheduled_at', '<', $to);
        }

        $sessions = $query
            ->with(['relationship.mentor', 'attendees.user'])
            ->orderBy('scheduled_at')
            ->paginate($perPage);

        return SessionResource::collection($sessions)->response();
    }

    public function bookableRelationships(Request $request): JsonResponse
    {
        $relationships = $this->listBookableRelationships->execute($request->user());

        return BookableRelationshipResource::collection($relationships)->response();
    }

    public function store(StoreSessionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $relationship = MentorshipRelationship::findOrFail($validated['relationship_id']);

        Gate::authorize('create', [Session::class, $relationship]);

        $session = $this->createSession->execute($validated, $request->user());

        if (! Gate::allows('view', $session)) {
            return response()->json(null, 204);
        }

        return response()->json(
            new SessionResource($session),
            $session->wasRecentlyCreated ? 201 : 200,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $session = Session::with([
            'relationship.mentor',
            'relationship.source',
            'attendees.user',
            'reviews.rater',
        ])->findOrFail($id);

        Gate::authorize('view', $session);

        $resource = new SessionResource($session);
        $resource->withAvailableActions(
            app(SessionActionAvailability::class)->for($session, $request->user(), now())
        );

        return response()->json($resource);
    }

    public function update(UpdateSessionRequest $request, string $id): JsonResponse
    {
        $validated = $request->validated();

        $session = Session::findOrFail($id);

        Gate::authorize('manage', $session);

        $before = $this->schedulingAuditLogger->snapshot($session);
        $session->update($validated);
        $session = $session->fresh();
        $this->schedulingAuditLogger->record(
            $session,
            $request->user(),
            'session.updated',
            array_keys($validated),
            $before,
        );

        if (! Gate::allows('view', $session)) {
            return response()->json(null, 204);
        }

        return response()->json(new SessionResource($session));
    }

    public function updateStatus(
        UpdateSessionStatusRequest $request,
        string $id,
        CompleteSessionAction $completeSession,
    ): JsonResponse
    {
        $validated = $request->validated();

        $session = Session::findOrFail($id);

        if (($validated['status'] ?? null) === 'canceled') {
            $session = $this->cancelSession->execute(
                $session->id,
                $validated['reason'],
                $request->user(),
                now(),
            );

            return Gate::allows('view', $session)
                ? response()->json(new SessionResource($session))
                : response()->json(null, 204);
        }

        if (($validated['status'] ?? null) === 'completed') {
            Gate::authorize('manage', $session);
            $session = $completeSession->execute(
                $session->id,
                $request->user(),
                now(),
            );

            return Gate::allows('view', $session)
                ? response()->json(new SessionResource($session))
                : response()->json(null, 204);
        }

        if (array_key_exists('mentor_notes', $validated)) {
            Gate::authorize('manageStatus', $session);
        } else {
            Gate::authorize('manage', $session);
        }

        if (array_key_exists('status', $validated)) {
            Gate::authorize('manageStatus', $session);
            throw ValidationException::withMessages([
                'status' => [__('sessions.status_transition_not_allowed')],
            ]);
        }

        unset($validated['reason']);
        $before = $this->schedulingAuditLogger->snapshot($session);
        $session->update($validated);
        $session = $session->fresh();
        $this->schedulingAuditLogger->record(
            $session,
            $request->user(),
            'session.status_updated',
            array_keys($validated),
            $before,
        );

        if (! Gate::allows('view', $session)) {
            return response()->json(null, 204);
        }

        return response()->json(new SessionResource($session));
    }

    public function reschedule(RescheduleSessionRequest $request, string $id): JsonResponse
    {
        $session = $this->rescheduleSession->execute(
            $id,
            $request->validated(),
            $request->user(),
            now(),
        );

        return response()->json(new SessionResource($session));
    }

    public function confirmAttendance(ConfirmAttendanceRequest $request, string $id): JsonResponse
    {
        $session = Session::query()
            ->with('relationship')
            ->findOrFail($id);

        if (! Gate::allows('view', $session)) {
            Gate::authorize('manage', $session);
        }

        $validated = $request->validated();
        $attendee = $this->confirmAttendance->execute(
            $session->id,
            $request->user(),
            AttendanceStatus::from($validated['status']),
            $validated['attendee_user_id'] ?? null,
            now(),
        );

        return response()->json(new SessionAttendeeResource($attendee->load('user')));
    }

    public function cancel(CancelSessionRequest $request, string $id): JsonResponse
    {
        $session = $this->cancelSession->execute(
            $id,
            $request->validated()['reason'],
            $request->user(),
            now(),
        );

        if (! Gate::allows('view', $session)) {
            return response()->json(null, 204);
        }

        return response()->json(new SessionResource($session));
    }

    public function generateMeetLink(Request $request, string $id): JsonResponse
    {
        $session = Session::findOrFail($id);

        Gate::authorize('manage', $session);

        if ($session->status === 'canceled') {
            return response()->json(['meet_link' => null]);
        }

        if ($session->meet_link) {
            return response()->json(['meet_link' => $session->meet_link]);
        }

        $meetData = $this->googleMeetService->createMeetEvent(
            $session->title,
            $session->scheduled_at,
            $session->duration_minutes,
            $session->timezone,
            (string) $session->getKey(),
        );

        $before = $this->schedulingAuditLogger->snapshot($session);
        $session->update([
            'meet_link' => $meetData['meet_link'],
            'meet_event_id' => $meetData['event_id'],
            'meet_synced_schedule_hash' => MeetAutomationRules::scheduleFingerprint($session),
        ]);
        $this->schedulingAuditLogger->record(
            $session,
            $request->user(),
            'session.meet_link_generated',
            ['meet_link'],
            $before,
        );

        return response()->json(['meet_link' => $session->meet_link]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $session = Session::findOrFail($id);

        Gate::authorize('delete', $session);

        $before = $this->schedulingAuditLogger->snapshot($session);
        if ($session->meet_event_id) {
            $this->googleMeetService->deleteEvent($session->meet_event_id);
        }

        $session->delete();
        $this->schedulingAuditLogger->record(
            $session,
            $request->user(),
            'session.deleted',
            ['deleted_at'],
            $before,
        );

        return response()->json(['message' => __('sessions.deleted')]);
    }
}
