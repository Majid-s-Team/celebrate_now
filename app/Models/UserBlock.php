<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBlock extends Model
{
    protected $table = 'user_blocks';

    protected $fillable = [
        'blocker_id',
        'blocked_id',
    ];

    /**
     * Jo user ne block kiya hai (blocker)
     */
    public function blocker()
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    /**
     * Jo user block hua hai (blocked)
     */
    public function blocked()
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }
}
