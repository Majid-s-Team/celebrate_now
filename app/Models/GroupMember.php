<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMember extends Model
{
    protected $fillable = ['group_id', 'user_id', 'can_see_past_messages','is_active','current_membership_id'];

     protected $casts = [
        'is_active' => 'boolean',
    ];

    public function group() {
        return $this->belongsTo(Group::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }


    public function currentMembership()
    {
        return $this->belongsTo(GroupMembership::class, 'current_membership_id');
    }
}
