<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ExcludeBlockedUsersScope;

class Reply extends Model
{
    use HasFactory,ExcludeBlockedUsersScope;

    protected $fillable = [
        'comment_id',
        'user_id',
        'body',
        'emojis',
    ];

    protected $casts = [
        'emojis' => 'array',
    ];

    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function likes()
{
    return $this->hasMany(ReplyLike::class);
}
}
