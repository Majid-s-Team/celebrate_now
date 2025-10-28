<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ExcludeBlockedUsersScope;
use App\Traits\ExcludeDeletedUsersScope;



class Follow extends Model
{
    use HasFactory,ExcludeBlockedUsersScope,ExcludeDeletedUsersScope;

    protected $fillable = [
        'follower_id',
        'following_id',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function follower()
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function following()
    {
        return $this->belongsTo(User::class, 'following_id');
    }
}
