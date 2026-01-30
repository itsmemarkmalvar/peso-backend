<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return $this->success($notifications, 'Notifications retrieved');
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $notification = Notification::where('user_id', $user->id)->findOrFail($id);

        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return $this->success($notification, 'Notification marked as read');
    }

    public function respond(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:available,not_available',
        ]);

        $user = $request->user();

        $notification = Notification::where('user_id', $user->id)->findOrFail($id);

        if ($notification->type !== 'schedule_availability_request') {
            return $this->error('This notification does not accept responses.', 422);
        }

        $data = $notification->data ?? [];
        $data['response'] = [
            'status' => $validated['status'],
            'responded_at' => now()->toISOString(),
        ];

        $notification->update([
            'data' => $data,
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $this->success($notification, 'Response saved');
    }
}
