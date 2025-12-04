<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class GroupMembership extends Model
{
    use HasFactory;

    protected $table = 'group_memberships';

    protected $fillable = [
        'group_id',
        'user_id',
        'joined_at',
        'left_at',
        'role',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at'   => 'datetime',
    ];

    // Auto-set timestamps on create / update
    protected static function boot()
    {
        parent::boot();

        // When creating a membership → auto-set joined_at
        static::creating(function ($model) {
            if (empty($model->joined_at)) {
                $model->joined_at = Carbon::now();
            }
            // On create, user is joining → left_at must be null
            $model->left_at = null;
        });

        // When updating (i.e. user leaving)
        static::updating(function ($model) {
            // If user is leaving and left_at is null → set it
            if ($model->isDirty('left_at') && $model->left_at === null) {
                // ignore
            }
        });
    }

    // Leave group
    public function leave()
    {
        $this->left_at = Carbon::now();
        $this->save();
    }

    // Check if currently active
    public function isActive()
    {
        return $this->left_at === null;
    }

    // Relationship
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
