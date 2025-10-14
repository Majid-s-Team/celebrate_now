<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\User;
use App\Traits\ApiResponseTrait;


class MessageController extends Controller
{
    use ApiResponseTrait;

    public function socketStore(Request $request)
    {
        $validated = $request->validate([
            'sender_id' => 'required|exists:users,id',
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string',
        ]);
        $msg = Message::create([
            'sender_id' => $validated['sender_id'],
            'receiver_id' => $validated['receiver_id'],
            'message' => $validated['message'],
            'status' => 'sent',
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
}
