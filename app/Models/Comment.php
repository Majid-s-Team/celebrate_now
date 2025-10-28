<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ExcludeBlockedUsersScope;
use App\Traits\ExcludeDeletedUsersScope;



class Comment extends Model
{
 use HasFactory,ExcludeBlockedUsersScope,ExcludeDeletedUsersScope;
    protected $fillable = [
        'post_id',
        'user_id',
        'body',
        'emojis',
    ];

    protected $casts = [
        'emojis' => 'array',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replies()
    {
        return $this->hasMany(Reply::class);
    }

    public function likes()
    {
        return $this->hasMany(CommentLike::class);
    }
}
