<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ExcludeBlockedUsersScope;
use App\Traits\ExcludeDeletedUsersScope;


class PostLike extends Model
{
    use HasFactory,ExcludeBlockedUsersScope,ExcludeDeletedUsersScope;

    protected $fillable = ['post_id', 'user_id'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
