<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'caption',
        'photo',
        'event_category_id',
        'privacy',

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
    public function followers()
    {
        return $this->hasMany(Follow::class);
    }
     public function following()
    {
        return $this->hasMany(Follow::class);
    }
}
