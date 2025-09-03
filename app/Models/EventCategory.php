<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ExcludeBlockedUsersScope;


class EventCategory extends Model
{
    use HasFactory,ExcludeBlockedUsersScope;

    protected $fillable = ['name'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
