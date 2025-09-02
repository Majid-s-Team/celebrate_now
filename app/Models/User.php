<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ExcludeBlockedUsersScope;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes,ExcludeBlockedUsersScope;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'contact_no',
        'profile_type',
        'profile_image',
        'password',
        'role',
        'is_active',
        'dob',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function taggedPosts()
    {
        return $this->hasMany(PostTag::class);
    }

    public function postLikes()
    {
        return $this->hasMany(PostLike::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function commentLikes()
    {
        return $this->hasMany(CommentLike::class);
    }

    public function replies()
    {
        return $this->hasMany(Reply::class);
    }

    public function followers()
    {
        return $this->hasMany(Follow::class, 'following_id');
    }

    public function following()
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }

    public function blockedUsers()
    {
        return $this->hasMany(UserBlock::class, 'blocker_id');
    }

    public function blockers()
    {
        return $this->hasMany(UserBlock::class, 'blocked_id');
    }
}
