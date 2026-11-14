<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Requests\Message\IndexMessagesRequest;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\MentorshipRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController
{
    public function index(IndexMessagesRequest $request): JsonResponse
    {
        $user = $request->user();

        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

        $query = Message::whereHas('relationship', function ($query) use ($user) {
            $query->forUser($user);
        })
        ->with('sender')
        ->latest();

        if ($request->filled('relationship_id')) {
            $relationship = MentorshipRelationship::findOrFail($request->input('relationship_id'));
            abort_unless($relationship->involvesUser($user), 403);

            $query->where('relationship_id', $relationship->id);
        }

        return MessageResource::collection($query->paginate($perPage))->response();
    }

    public function store(StoreMessageRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

        return response()->json(new MessageResource($message), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $message = Message::with(['sender', 'relationship'])->findOrFail($id);

        abort_unless($message->relationship->involvesUser($request->user()), 403);

        return response()->json(new MessageResource($message));
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $message = Message::with('relationship')->findOrFail($id);

        abort_unless($message->relationship->involvesUser($request->user()), 403);

        $message->update(['read_at' => now()]);

        return response()->json(new MessageResource($message->load('sender')));
    }
}
