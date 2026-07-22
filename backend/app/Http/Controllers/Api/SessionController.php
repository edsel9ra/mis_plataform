<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateSessionAction;
use App\Http\Requests\Session\StoreSessionRequest;
use App\Http\Resources\SessionResource;
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
        $sessions = Session::forUser($request->user()->id)
            ->with(['relationship.mentor', 'attendees.user'])
            ->orderBy('scheduled_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($sessions);
    }

    public function store(StoreSessionRequest $request): JsonResponse
    {
        $session = $this->createSession->execute($request->validated());

        return response()->json(
            new SessionResource($session),
            201,
        );
    }

    public function show(string $id): JsonResponse
    {
        $session = Session::with([
            'relationship.mentor',
            'relationship.source',
            'attendees.user',
            'reviews',
        ])->findOrFail($id);

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
        $session->update($validated);

        return response()->json(new SessionResource($session->fresh()));
    }

    public function generateMeetLink(string $id): JsonResponse
    {
        $session = Session::findOrFail($id);

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

    public function destroy(string $id): JsonResponse
    {
        $session = Session::findOrFail($id);

        if ($session->meet_event_id) {
            $this->googleMeetService->deleteEvent($session->meet_event_id);
        }

        $session->delete();

        return response()->json(['message' => __('sessions.deleted')]);
    }
}
