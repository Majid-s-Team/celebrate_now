<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait ExcludeBlockedUsersScope
{
    protected static function bootExcludeBlockedUsersScope()
    {
        static::addGlobalScope('exclude_blocked_users', function (Builder $builder) {
            $authId = auth()->id();

            if (!$authId) {
                return;
            }

            $model = new static;
            $table = $model->getTable();

            if ($table === 'users') {
                // For users table exclude blocked user ids from 'id'
                $builder->whereNotIn('id', function ($query) use ($authId) {
                    $query->select('blocked_id')
                          ->from('user_blocks')
                          ->where('blocker_id', $authId);
                });
            } else {
                // For other tables exclude records whose user_id is blocked
                $builder->whereNotIn('user_id', function ($query) use ($authId) {
                    $query->select('blocked_id')
                          ->from('user_blocks')
                          ->where('blocker_id', $authId);
                });
            }
        });
    }
}
