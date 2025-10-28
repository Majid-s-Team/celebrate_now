<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ExcludeBlockedUsersScope;
use App\Traits\ExcludeDeletedUsersScope;


class ReplyLike extends Model
{
    use HasFactory,ExcludeBlockedUsersScope,ExcludeDeletedUsersScope;

    protected $fillable = ['reply_id', 'user_id'];

    public function reply()
    {
        return $this->belongsTo(Reply::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
