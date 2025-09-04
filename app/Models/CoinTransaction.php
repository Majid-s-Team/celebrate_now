<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinTransaction extends Model
{
    protected $fillable = [
        'sender_id', 'receiver_id', 'coin_package_id',
        'post_id', 'event_id', 'coins', 'type', 'message'
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function package()
    {
        return $this->belongsTo(CoinPackage::class, 'coin_package_id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
