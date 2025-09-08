<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\Message;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    public function startConversation($receiverId)
    {
        $userId = Auth::id();
        $conversation = Conversation::where(function ($q) use ($userId, $receiverId) {
            $q->where('user_one', $userId)->where('user_two', $receiverId);
        })->orWhere(function ($q) use ($userId, $receiverId) {
            $q->where('user_one', $receiverId)->where('user_two', $userId);
        })->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one' => $userId,
                'user_two' => $receiverId,
            ]);
        }

        return response()->json($conversation);
    }

public function sendMessage(Request $request)
{
    $request->validate([
        'conversation_id' => 'required|exists:conversations,id',
        'message' => 'required|string',
    ]);

    $message = Message::create([
        'conversation_id' => $request->conversation_id,
        'sender_id' => Auth::id(),
        'message' => $request->message,
    ]);

    // Optional Laravel event (for internal usage/logs)
    $event = new MessageSent($message);

    // 🔥 Send to your Node.js Socket.IO server via HTTP POST
    Http::post('http://localhost:6001/broadcast', [
        'event' => 'chat message',
        'data' => $event->dataForSocket(),
    ]);

    return response()->json($message);
}

    public function getMessages($conversationId)
    {
        $messages = Message::where('conversation_id', $conversationId)
            ->with('sender')
            ->orderBy('created_at')
            ->get();

        return response()->json($messages);
    }

    public function markAsSeen($conversationId)
    {
        Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', Auth::id())
            ->update(['status' => 'seen']);

        return response()->json(['message' => 'Seen']);
    }
}
