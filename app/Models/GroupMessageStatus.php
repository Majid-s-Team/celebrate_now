<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupMessageStatus extends Model
{
    use HasFactory;

    protected $table = 'group_message_status';

    protected $fillable = [
        'group_id',
        'sender_id',
        'receiver_id',
        'message_id',
        'is_read',
    ];

    /**
     * Relationships
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function message()
    {
        return $this->belongsTo(GroupMessage::class, 'message_id');
    }
}
