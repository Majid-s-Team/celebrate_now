<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id','receiver_id', 'title', 'message', 'is_read'
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
