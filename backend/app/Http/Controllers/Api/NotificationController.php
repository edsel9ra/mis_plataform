<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json($notifications);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->update(['read_at' => now()]);

        return response()->json($notification);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => __('notifications.all_read')]);
    }
}
