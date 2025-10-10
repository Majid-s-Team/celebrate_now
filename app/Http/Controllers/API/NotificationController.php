<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get all notifications (paginated)
     */
    public function index()
    {
        $user = auth()->user();

        $notifications = Notification::with('sender')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return $this->sendResponse('Notifications fetched successfully', $notifications);
    }

    /**
     * Get unread notification count
     */
    public function unreadCount()
    {
        $user = auth()->user();
        $count = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return $this->sendResponse('Unread notifications count fetched successfully', [
            'unread_count' => $count
        ]);
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead($id)
    {
        $user = auth()->user();
        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return $this->sendError('Notification not found', [], 404);
        }

        $notification->is_read = true;
        $notification->save();

        return $this->sendResponse('Notification marked as read', $notification);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $user = auth()->user();
        Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return $this->sendResponse('All notifications marked as read');
    }

    /**
     * Delete single notification
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return $this->sendError('Notification not found', [], 404);
        }

        $notification->delete();

        return $this->sendResponse('Notification deleted successfully');
    }

    /**
     * Delete all notifications
     */
    public function clearAll()
    {
        $user = auth()->user();
        Notification::where('user_id', $user->id)->delete();

        return $this->sendResponse('All notifications deleted successfully');
    }

    /**
     * Create notification (custom use: like, comment, follow etc.)
     */
    public function create(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'receiver_id' =>['nullable', 'exists:users,id'],
            'title'   => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'type'=> ['required','in:coinPurchase']

        ]);

        $notification = Notification::create([
            'user_id' => $data['user_id'],
            'receiver_id' => $data['receiver_id'],
            'title'   => $data['title'],
            'message' => $data['message'] ?? null,
            'type' => $data['type'],
            'is_read' => false,
        ]);

        return $this->sendResponse('Notification created successfully', $notification);
    }
}
