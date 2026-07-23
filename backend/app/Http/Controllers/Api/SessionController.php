<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateSessionAction;
use App\Http\Requests\Session\StoreSessionRequest;
use App\Http\Resources\SessionResource;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Services\GoogleMeetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController
{
    public function __construct(
        private CreateSessionAction $createSession,
        private GoogleMeetService $googleMeetService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $sessions = Session::forUser($request->user())
            ->with(['relationship.mentor', 'attendees.user'])
            ->orderBy('scheduled_at')
            ->paginate($perPage);

        return response()->json($sessions);
    }

    public function store(StoreSessionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $relationship = MentorshipRelationship::findOrFail($validated['relationship_id']);

        abort_unless($relationship->involvesUser($request->user()), 403);

        $session = $this->createSession->execute($validated);

        return response()->json(
            new SessionResource($session),
            201,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $session = Session::with([
            'relationship.mentor',
            'relationship.source',
            'attendees.user',
            'reviews',
        ])->findOrFail($id);

        abort_unless($session->involvesUser($request->user()), 403);

        return response()->json(new SessionResource($session));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['sometimes', 'date', 'after:now'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:480'],
        ]);

        $session = Session::findOrFail($id);

        abort_unless($session->involvesUser($request->user()), 403);

        $session->update($validated);

        return response()->json(new SessionResource($session->fresh()));
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:scheduled,in_progress,completed,canceled'],
            'mentor_notes' => ['nullable', 'string'],
            'mentee_notes' => ['nullable', 'string'],
        ]);

        $session = Session::findOrFail($id);

        abort_unless($session->involvesUser($request->user()), 403);

        $session->update($validated);

        return response()->json(new SessionResource($session->fresh()));
    }

    public function generateMeetLink(Request $request, string $id): JsonResponse
    {
        $session = Session::findOrFail($id);

        abort_unless($session->involvesUser($request->user()), 403);

        if ($session->meet_link) {
            return response()->json(['meet_link' => $session->meet_link]);
        }

        $meetData = $this->googleMeetService->createMeetEvent(
            $session->title,
            $session->scheduled_at,
            $session->duration_minutes,
        );

        $session->update([
            'meet_link' => $meetData['meet_link'],
            'meet_event_id' => $meetData['event_id'],
        ]);

        return response()->json(['meet_link' => $session->meet_link]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $session = Session::findOrFail($id);

        abort_unless($session->involvesUser($request->user()), 403);

        if ($session->meet_event_id) {
            $this->googleMeetService->deleteEvent($session->meet_event_id);
        }

        $session->delete();

        return response()->json(['message' => __('sessions.deleted')]);
    }
}
