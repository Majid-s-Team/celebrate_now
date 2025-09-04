<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ExcludeBlockedUsersScope;


class PostReport extends Model
{
    use ExcludeBlockedUsersScope;
    protected $fillable = ['user_id', 'post_id', 'reason_id', 'other_reason'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reason()
    {
        return $this->belongsTo(ReportReason::class);
    }
}
