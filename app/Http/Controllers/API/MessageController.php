<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use App\Models\UserBlock;

// If sender blocked receiver, sender cannot see messages in inbox (but can still send if unblocked later).

// If receiver blocked sender, receiver won’t receive messages (they go unseen until unblock).

// Once unblocked, all old messages appear again.
class MessageController extends Controller
{
    use ApiResponseTrait;

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

        // CASE 01: If a recever has block sender then prevent sending
        if (UserBlock::isBlocked($receiverId, $senderId)) {
            return $this->apiResponse('You cannot send messages. The user has blocked you.', null, 403);
        }

        // CASE 2: If sender has block receiver then  allow sending, but messages will not show in inbox until unblocked
        $msg = Message::create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => $validated['message'] ?? '',
            'message_type' => $validated['message_type'] ?? 'text',
            'media_url' => $validated['media_url'] ?? '',
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $msg->load(['sender:id,first_name,last_name,profile_image,email', 'receiver:id,first_name,last_name,profile_image,email']);

        return $this->apiResponse('Message sent successfully', $msg, 201);
    }



    public function chatHistory($user1, $user2)
    {
        $messages = Message::with(['sender:id,first_name,last_name,profile_image,email', 'receiver:id,first_name,last_name,profile_image,email'])
            ->where(function ($q) use ($user1, $user2) {
                $q->where('sender_id', $user1)->where('receiver_id', $user2);
            })
            ->orWhere(function ($q) use ($user1, $user2) {
                $q->where('sender_id', $user2)->where('receiver_id', $user1);
            })
            ->orderBy('created_at', 'asc')
            ->get();


    $is_block = UserBlock::where('blocker_id', $user1)
    ->where('blocked_id',$user2)
    ->exists();

  $is_deleted = User::withTrashed()
    ->where('id', $user2)
    ->whereNotNull('deleted_at')
    ->exists();


$messages->transform(function ($msg) use ($is_block,$is_deleted) {
        $msg->is_block = $is_block;
        $msg->is_deleted = $is_deleted;
        return $msg;
    });

    return $this->apiResponse('Chat history loaded', $messages);
    }

    public function unseenMessages($user_id)
    {
        $messages = Message::with(['sender:id,first_name,last_name,profile_image,email', 'receiver:id,first_name,last_name,profile_image,email'])
            ->where('receiver_id', $user_id)
            ->where('status', 'sent')
            ->orderByDesc('created_at')
            ->get();

        Message::whereIn('id', $messages->pluck('id'))->update(['status' => 'delivered']);

        return $this->apiResponse('Unseen messages fetched', $messages);
    }

    public function markSeen(Request $request)
    {
        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:messages,id',
        ]);

        Message::whereIn('id', $request->message_ids)->update(['status' => 'read']);

        return $this->apiResponse('Messages marked as read');
    }

//     public function inbox($user_id)
//     {
//         $inbox = Message::selectRaw('
//                 CASE
//                     WHEN sender_id = ? THEN receiver_id
//                     ELSE sender_id
//                 END as chat_with_id,
//                 MAX(created_at) as last_message_time
//             ', [$user_id])
//             ->where(function ($q) use ($user_id) {
//                 $q->where('sender_id', $user_id)->orWhere('receiver_id', $user_id);
//             })
//             ->groupBy('chat_with_id')
//             ->with(['sender:id,first_name,last_name,profile_image,email', 'receiver:id,first_name,last_name,profile_image,email'])
//             ->get()
//             ->map(function ($chat) use ($user_id) {
//                 $lastMsg = Message::where(function ($q) use ($user_id, $chat) {
//                     $q->where('sender_id', $user_id)->where('receiver_id', $chat->chat_with_id);
//                 })
//                 ->orWhere(function ($q) use ($user_id, $chat) {
//                     $q->where('sender_id', $chat->chat_with_id)->where('receiver_id', $user_id);
//                 })
//                 ->latest()
//                 ->first();
//      $unreadCount = Message::where('receiver_id', $user_id)
//     ->where('sender_id', $chat->chat_with_id)
//     ->where('status', '!=', 'read')
//     ->count();

//     $is_block = UserBlock::where('blocker_id', $user_id)
//     ->where('blocked_id',$chat->chat_with_id)
//     ->exists();

//     $is_deleted = User::withoutGlobalScopes()
//     ->withTrashed()
//     ->where('id', $chat->chat_with_id)
//     ->whereNotNull('deleted_at')
//     ->exists();

//   $deleted_user = User::withoutGlobalScopes()
//     ->withTrashed()
//     ->select("*")
//     ->where('id', $chat->chat_with_id)
//     ->whereNotNull('deleted_at')
//     ->first();


//     if($is_deleted){

//         return [
//                     'chat_with' => $deleted_user,
//                     'last_message' => $lastMsg->message,
//                     'is_blocked' => $is_block,
//                     'is_deleted' => $is_deleted,
//                     'deleted_user' => $deleted_user,
//                     'media_url' => $lastMsg->media_url,
//                     'message_type' => $lastMsg->message_type ?? 'text',
//                     'created_at' => $lastMsg->created_at,
//                     'time' => $lastMsg->created_at->format('H:i'),
//                     'date' => $lastMsg->created_at->format('Y-m-d'),
//                       'unreadCount'  => $unreadCount, // Added
//                 ];

//     }


// else if (!$is_deleted)
// {

//                 return [
//                     'chat_with' => $lastMsg->sender_id == $user_id ? $lastMsg->receiver : $lastMsg->sender,
//                     'last_message' => $lastMsg->message,
//                     'is_blocked' => $is_block,
//                     'is_deleted' => $is_deleted,
//                     'deleted_user' => $deleted_user,
//                     'media_url' => $lastMsg->media_url,
//                     'message_type' => $lastMsg->message_type ?? 'text',
//                     'created_at' => $lastMsg->created_at,
//                     'time' => $lastMsg->created_at->format('H:i'),
//                     'date' => $lastMsg->created_at->format('Y-m-d'),
//                       'unreadCount'  => $unreadCount, // Added
//                 ];
//             }
//             });

//         return $this->apiResponse('Inbox loaded', $inbox);
//     }
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
                $q->where('sender_id', $user_id)->orWhere('receiver_id', $user_id);
            })
            ->groupBy('chat_with_id')
            ->get()
            ->filter(function ($chat) use ($user_id, $blockedUsers) {
                return !$blockedUsers->contains($chat->chat_with_id);
            })
            ->map(function ($chat) use ($user_id) {
                $lastMsg = Message::where(function ($q) use ($user_id, $chat) {
                    $q->where('sender_id', $user_id)->where('receiver_id', $chat->chat_with_id);
                })
                ->orWhere(function ($q) use ($user_id, $chat) {
                    $q->where('sender_id', $chat->chat_with_id)->where('receiver_id', $user_id);
                })
                ->latest()
                ->first();

                $unreadCount = Message::where('receiver_id', $user_id)
                    ->where('sender_id', $chat->chat_with_id)
                    ->where('status', '!=', 'read')
                    ->count();

                return [
                    'chat_with' => $lastMsg->sender_id == $user_id ? $lastMsg->receiver : $lastMsg->sender,
                    'last_message' => $lastMsg->message,
                    'media_url' => $lastMsg->media_url,
                    'message_type' => $lastMsg->message_type ?? 'text',
                    'created_at' => $lastMsg->created_at,
                    'unreadCount' => $unreadCount,
                ];
            })
            ->values();

        return $this->apiResponse('Inbox loaded', $inbox);
    }


    public function uploadMedia(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,mp4,mov,avi,pdf,doc,docx,zip,mp3|max:204800', // 200MB max
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
        ]);

        $msg->load(['sender:id,first_name,last_name,profile_image,email', 'receiver:id,first_name,last_name,profile_image,email']);

        return $this->apiResponse('Media uploaded successfully', $msg, 201);
    }
public function chatMedia($user2)
{
    $user1 = auth()->id(); // logged-in user

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
        ->orderBy('created_at', 'desc')
        ->get(['id', 'sender_id', 'receiver_id', 'media_url', 'message_type', 'created_at']);

    return $this->apiResponse('Chat media loaded successfully', $mediaMessages);
}


}
