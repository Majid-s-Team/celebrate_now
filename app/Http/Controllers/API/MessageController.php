<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBlock;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;

class MessageController extends Controller
{
    use ApiResponseTrait;

    /**
     * Send a message (no broadcasting)
     */
    public function socketStore(Request $request)
    {
        $validated = $request->validate([
            'sender_id' => 'required|exists:users,id',
            'receiver_id' => 'required|exists:users,id',
            'message' => 'nullable|string',
            'message_type' => 'nullable|string|in:text,image,video,file,emoji,link,audio',
            'media_url' => 'nullable|string',
        ]);

        if (empty($validated['message'] ?? null) && empty($validated['media_url'] ?? null)) {
            return $this->apiResponse('Either message or media_url is required', null, 422);
        }

        $senderId = $validated['sender_id'];
        $receiverId = $validated['receiver_id'];

        // CASE 1: Sender has blocked receiver → cannot send
        if (UserBlock::isBlocked($senderId, $receiverId)) {
            return $this->apiResponse("You can't send messages to this user because you have blocked them.", null, 403);
        }

        // CASE 2: Receiver has blocked sender → hide from receiver
        $isBlockedByReceiver = UserBlock::isBlocked($receiverId, $senderId);

        $msg = Message::create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => $validated['message'] ?? '',
            'message_type' => $validated['message_type'] ?? 'text',
            'media_url' => $validated['media_url'] ?? '',
            'status' => 'sent',
            'hidden_for_receiver' => $isBlockedByReceiver,
        ]);

        $msg->load(['sender:id,first_name,last_name,profile_image,email', 'receiver:id,first_name,last_name,profile_image,email']);

        return $this->apiResponse('Message sent successfully', $msg, 201);
    }

    /**
     * Fetch chat history between two users
     */
public function chatHistory($user1, $user2)
{
    // 🔹 Fetch all messages between both users
    $messages = Message::with([
            'sender:id,first_name,last_name,profile_image,email',
            'receiver:id,first_name,last_name,profile_image,email'
        ])
        ->where(function ($q) use ($user1, $user2) {
            $q->where('sender_id', $user1)->where('receiver_id', $user2);
        })
        ->orWhere(function ($q) use ($user1, $user2) {
            $q->where('sender_id', $user2)->where('receiver_id', $user1);
        })
        ->orderBy('created_at', 'asc')
        ->get();

    // 🔹 Get all block and unblock timeline records (for reference)
    $blocks = UserBlock::where(function ($q) use ($user1, $user2) {
            $q->where('blocker_id', $user1)->where('blocked_id', $user2);
        })
        ->orWhere(function ($q) use ($user1, $user2) {
            $q->where('blocker_id', $user2)->where('blocked_id', $user1);
        })
        ->orderBy('blocked_at', 'asc')
        ->get();

    // 🔹 Apply logic for visibility
    $filtered = $messages->filter(function ($msg) use ($user1, $user2, $blocks) {

        // Case 1️: Message hidden for receiver
        if ($msg->hidden_for_receiver && $msg->receiver_id == $user1) {
            return false;
        }

        // Get last block record (if exists)
        $activeBlock = $blocks->where('is_active', true)->first();
        $lastBlock = $blocks->sortByDesc('blocked_at')->first();

        // Case 2️: If current user is blocker (user1 blocked user2)
        if ($activeBlock && $activeBlock->blocker_id == $user1) {
            // Blocker cannot see any chat (even old ones) after block
            if ($msg->sender_id == $user2 && $msg->created_at > $activeBlock->blocked_at) {
                return false;  // Hide new messages from blocked user after blocking
            }
        }

        // Case 3️: After unblock, hide all messages sent during block period
        if ($lastBlock && $lastBlock->blocker_id == $user1) {
            // hide messages from blocked user that were created during block period
            if (
                $msg->sender_id == $lastBlock->blocked_id &&
                $msg->created_at > $lastBlock->blocked_at &&
                ($lastBlock->unblocked_at == null || $msg->created_at < $lastBlock->unblocked_at)
            ) {
                return false;
            }
        }

        // Otherwise visible
        return true;
    });

    // 🔹 Current block status
    $is_block = UserBlock::where('blocker_id', $user1)
        ->where('blocked_id', $user2)
        ->where('is_active', true)
        ->exists();

    // 🔹 Check if user2 deleted
    $is_deleted = User::withTrashed()
        ->where('id', $user2)
        ->whereNotNull('deleted_at')
        ->exists();

    // 🔹 Add meta
    $filtered->transform(function ($msg) use ($is_block, $is_deleted) {
        $msg->is_block = $is_block;
        $msg->is_deleted = $is_deleted;
        return $msg;
    });

    return $this->apiResponse('Chat history loaded', $filtered->values());
}


    /**
     * Unseen messages
     */
    public function unseenMessages($user_id)
    {
        $messages = Message::with(['sender:id,first_name,last_name,profile_image,email', 'receiver:id,first_name,last_name,profile_image,email'])
            ->where('receiver_id', $user_id)
            ->where('status', 'sent')
            ->where('hidden_for_receiver', false)
            ->orderByDesc('created_at')
            ->get();

        Message::whereIn('id', $messages->pluck('id'))->update(['status' => 'delivered']);

        return $this->apiResponse('Unseen messages fetched', $messages);
    }

    /**
     * Unseen messages
     */
    public function undeliveredMessages($user_id)
    {
        $messages = Message::with(['sender:id,first_name,last_name,profile_image,email', 'receiver:id,first_name,last_name,profile_image,email'])
            ->where('receiver_id', $user_id)
            ->where('status', 'sent')
            ->where('hidden_for_receiver', false)
            ->orderByDesc('created_at')
            ->get();

        Message::whereIn('id', $messages->pluck('id'))->update(['status' => 'sent']);

        return $this->apiResponse('Unseen messages fetched', $messages);
    }

    /**
     * Mark as seen
     */
    public function markSeen(Request $request)
    {
        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:messages,id',
        ]);

        Message::whereIn('id', $request->message_ids)->update(['status' => 'read']);
        return $this->apiResponse('Messages marked as read');
    }


      /**
     * Mark as delivered
     */
    public function markDelivered(Request $request)
    {
        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:messages,id',
        ]);

        Message::whereIn('id', $request->message_ids)->update(['status' => 'delivered']);
        return $this->apiResponse('Messages marked as delivered');
    }

    /**
     * Inbox list
     */
public function inbox($user_id)
{
    $blockedUsers = UserBlock::where('blocker_id', $user_id)
        ->where('is_active', true)
        ->pluck('blocked_id');

    $inbox = Message::selectRaw('
        CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as chat_with_id,
        MAX(created_at) as last_message_time
    ', [$user_id])
        ->where(function ($q) use ($user_id) {
            $q->where('sender_id', $user_id)
              ->orWhere('receiver_id', $user_id);
        })
        ->groupBy('chat_with_id')
        ->get()
        ->map(function ($chat) use ($user_id, $blockedUsers) {

            $lastMsg = Message::where(function ($q) use ($user_id, $chat) {
                    $q->where('sender_id', $user_id)
                      ->where('receiver_id', $chat->chat_with_id);
                })
                ->orWhere(function ($q) use ($user_id, $chat) {
                    $q->where('sender_id', $chat->chat_with_id)
                      ->where('receiver_id', $user_id);
                })
                ->where('hidden_for_receiver', false)
                ->latest()
                ->first();

            if (!$lastMsg) return null;

            $chatWithUser = User::withoutGlobalScopes()->withTrashed()->find($chat->chat_with_id);
            if (!$chatWithUser) return null;

            // 🔹 Unread message count
            $unreadCount = Message::where('receiver_id', $user_id)
                ->where('sender_id', $chat->chat_with_id)
                ->where('status', '!=', 'read')
                ->where('hidden_for_receiver', false)
                ->count();

            // 🔹 Check if user is blocked by current user
            $isBlocked = $blockedUsers->contains($chat->chat_with_id);

            return [
                'chat_with' => $chatWithUser,
                'last_message' => $lastMsg->message,
                'media_url' => $lastMsg->media_url,
                'message_type' => $lastMsg->message_type ?? 'text',
                'created_at' => $lastMsg->created_at,
                'unreadCount' => $unreadCount,
                'is_blocked' => $isBlocked,
                'is_deleted' => $chatWithUser->deleted_at ? true : false,
            ];
        })
        ->filter()
        ->values();

    return $this->apiResponse('Inbox loaded', $inbox);
}




    /**
     * Upload media message
     */
    public function uploadMedia(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,mp4,mov,avi,pdf,doc,docx,zip,mp3|max:204800',
            'sender_id' => 'required|exists:users,id',
            'receiver_id' => 'required|exists:users,id',
        ]);

        $file = $request->file('file');
        $type = $file->getMimeType();

        if (str_contains($type, 'image')) {
            $messageType = 'image';
        } elseif (str_contains($type, 'video')) {
            $messageType = 'video';
        } else {
            $messageType = 'file';
        }

        $path = $file->store('chat_media', 'public');
        $mediaUrl = asset('storage/' . $path);

        $msg = Message::create([
            'sender_id' => $request->sender_id,
            'receiver_id' => $request->receiver_id,
            'message' => $file->getClientOriginalName(),
            'message_type' => $messageType,
            'media_url' => $mediaUrl,
            'status' => 'sent',
            'hidden_for_receiver' => UserBlock::isBlocked($request->receiver_id, $request->sender_id),
        ]);

        $msg->load(['sender:id,first_name,last_name,profile_image,email', 'receiver:id,first_name,last_name,profile_image,email']);

        return $this->apiResponse('Media uploaded successfully', $msg, 201);
    }

    /**
     * Fetch all media in chat
     */
    public function chatMedia($user2)
    {
        $user1 = auth()->id();

        if (!$user1) {
            return $this->apiResponse('Unauthorized', null, 401);
        }

        $mediaMessages = Message::where(function ($q) use ($user1, $user2) {
                $q->where(function ($sub) use ($user1, $user2) {
                    $sub->where('sender_id', $user1)
                        ->where('receiver_id', $user2);
                })
                ->orWhere(function ($sub) use ($user1, $user2) {
                    $sub->where('sender_id', $user2)
                        ->where('receiver_id', $user1);
                });
            })
            ->where('message_type', '!=', 'text')
            ->where('hidden_for_receiver', false)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'sender_id', 'receiver_id', 'media_url', 'message_type', 'created_at']);

        return $this->apiResponse('Chat media loaded successfully', $mediaMessages);
    }
}
