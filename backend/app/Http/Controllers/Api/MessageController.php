<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $messages = Message::whereHas('relationship', function ($query) use ($user) {
            $query->where('mentor_id', $user->id)
                ->orWhere(function ($q) use ($user) {
                    $q->where('source_type', 'user')
                      ->where('source_id', $user->id);
                });
        })
        ->with('sender')
        ->latest()
        ->paginate($request->per_page ?? 50);

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

    public function show(string $id): JsonResponse
    {
        $message = Message::with('sender')->findOrFail($id);

        return response()->json($message);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $message = Message::findOrFail($id);
        $message->update(['read_at' => now()]);

        return response()->json($message);
    }
}
