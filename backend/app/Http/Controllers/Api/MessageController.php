<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\MentorshipRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

        $messages = Message::whereHas('relationship', function ($query) use ($user) {
            $query->forUser($user);
        })
        ->with('sender')
        ->latest()
        ->paginate($perPage);

        return response()->json($messages);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'relationship_id' => ['required', 'exists:mentorship_relationships,id'],
            'content' => ['required', 'string', 'max:5000'],
            'type' => ['sometimes', 'string', 'in:text,file,system'],
            'attachment_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $relationship = MentorshipRelationship::findOrFail($validated['relationship_id']);
        abort_unless($relationship->involvesUser($request->user()), 403);

        $message = Message::create([
            'relationship_id' => $validated['relationship_id'],
            'sender_id' => $request->user()->id,
            'content' => $validated['content'],
            'type' => $validated['type'] ?? 'text',
            'attachment_url' => $validated['attachment_url'] ?? null,
        ]);

        broadcast(new MessageSent($message))->toOthers();

        $message->load('sender');

        return response()->json($message, 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $message = Message::with(['sender', 'relationship'])->findOrFail($id);

        abort_unless($message->relationship->involvesUser($request->user()), 403);

        return response()->json($message);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $message = Message::with('relationship')->findOrFail($id);

        abort_unless($message->relationship->involvesUser($request->user()), 403);

        $message->update(['read_at' => now()]);

        return response()->json($message);
    }
}
