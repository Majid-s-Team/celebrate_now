<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = ['name','profile_image','description', 'created_by'];

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members() {
        return $this->hasMany(GroupMember::class);
    }

    public function messages() {
        return $this->hasMany(GroupMessage::class);
    }
    public function lastMessage()
    {
        return $this->hasOne(GroupMessage::class, 'group_id')
            ->latestOfMany();
    }
}
