<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ExcludeBlockedUsersScope;
use App\Traits\ExcludeDeletedUsersScope;

class Notification extends Model
{
    use ExcludeBlockedUsersScope,ExcludeDeletedUsersScope;
    protected $fillable = [
        'user_id','receiver_id', 'title', 'message','data','type', 'is_read'
    ];
    protected $casts = [
    'data' => 'array',
];

      public function sender()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 🔹 Notification received by which user
     */
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
