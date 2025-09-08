<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Message;

class MessageSent
{
    use Dispatchable, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message->load('sender');
    }

    public function dataForSocket()
    {
        return [
            'id' => $this->message->id,
            'message' => $this->message->message,
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->first_name,
            ],
            'created_at' => $this->message->created_at->toDateTimeString(),
        ];
    }
}
