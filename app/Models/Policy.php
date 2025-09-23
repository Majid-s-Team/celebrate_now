<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Policy extends Model
{
    protected $fillable = ['type', 'content'];

    public function users()
    {
        return $this->belongsToMany(User::class)
                    ->withPivot('accepted_at')
                    ->withTimestamps();
    }
}
