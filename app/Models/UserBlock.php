<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserBlock extends Model
{
    protected $table = 'user_blocks';

    protected $fillable = ['blocker_id', 'blocked_id', 'is_active', 'blocked_at', 'unblocked_at'];


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
        return $this->belongsTo(User::class, 'blocked_id')->withoutGlobalScopes();
    }
    public static function isBlocked($blockerId, $blockedId)
    {
        return self::where('blocker_id', $blockerId)
            ->where('blocked_id', $blockedId)
            ->where('is_active', true)
            ->exists();
    }

    public static function unblock($blockerId, $blockedId)
    {
        self::where('blocker_id', $blockerId)
            ->where('blocked_id', $blockedId)
            ->update([
                'is_active' => false,
                'unblocked_at' => Carbon::now(),
            ]);
    }

    public static function block($blockerId, $blockedId)
    {
        self::updateOrCreate(
            ['blocker_id' => $blockerId, 'blocked_id' => $blockedId],
            ['is_active' => true, 'blocked_at' => Carbon::now(), 'unblocked_at' => null]
        );
    }
}
