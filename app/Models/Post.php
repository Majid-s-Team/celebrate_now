<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ExcludeBlockedUsersScope;


class Post extends Model
{
    use HasFactory, SoftDeletes,ExcludeBlockedUsersScope;

    protected $fillable = [
        'user_id',
        'caption',
        'photo',
        'event_category_id',
        'privacy',
        'event_id',

    ];

    public function media()
    {
        return $this->hasMany(PostMedia::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }

    public function tags()
    {
        return $this->hasMany(PostTag::class);
    }

    public function likes()
    {
        return $this->hasMany(PostLike::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function replies()
    {
        return $this->hasMany(Reply::class);
    }
    public function reports()
    {
        return $this->hasMany(PostReport::class);
    }
    public function event()
    {
        return $this->belongsTo(Event::class);
    }



}
