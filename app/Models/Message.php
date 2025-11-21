<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ExcludeDeletedUsersScope;



class Message extends Model
{
    use HasFactory,ExcludeDeletedUsersScope;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'message_type',
        'status',
        'media_url',
        'created_at',
        'updated_at',
        'hidden_for_receiver'
    ];

    protected $casts = [
    'hidden_for_receiver' => 'boolean',
];
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id')->withTrashed();
    }
}
