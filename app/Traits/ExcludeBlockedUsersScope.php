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
                // Exclude both directions for users
                $builder->whereNotIn('id', function ($query) use ($authId) {
                    $query->select('blocked_id')
                        ->from('user_blocks')
                        ->where('blocker_id', $authId)
                        ->where('is_active', 1);
                })
                ->whereNotIn('id', function ($query) use ($authId) {
                    $query->select('blocker_id')
                        ->from('user_blocks')
                        ->where('blocked_id', $authId)
                        ->where('is_active', 1);
                });
            }

            elseif ($table === 'events') {
                $builder->whereNotIn('created_by', function ($query) use ($authId) {
                    $query->select('blocked_id')
                        ->from('user_blocks')
                        ->where('blocker_id', $authId)
                        ->where('is_active', 1);
                })
                ->whereNotIn('created_by', function ($query) use ($authId) {
                    $query->select('blocker_id')
                        ->from('user_blocks')
                        ->where('blocked_id', $authId)
                        ->where('is_active', 1);
                });
            }

            elseif ($table === 'polls') {
                $builder->whereNotIn('created_by', function ($query) use ($authId) {
                    $query->select('blocked_id')
                        ->from('user_blocks')
                        ->where('blocker_id', $authId)
                        ->where('is_active', 1);
                })
                ->whereNotIn('created_by', function ($query) use ($authId) {
                    $query->select('blocker_id')
                        ->from('user_blocks')
                        ->where('blocked_id', $authId)
                        ->where('is_active', 1);
                });
            }

            elseif ($table === 'follows') {
                $builder->whereNotIn('follower_id', function ($query) use ($authId) {
                    $query->select('blocked_id')
                        ->from('user_blocks')
                        ->where('blocker_id', $authId)
                        ->where('is_active', 1);
                })
                ->whereNotIn('follower_id', function ($query) use ($authId) {
                    $query->select('blocker_id')
                        ->from('user_blocks')
                        ->where('blocked_id', $authId)
                        ->where('is_active', 1);
                })
                ->whereNotIn('following_id', function ($query) use ($authId) {
                    $query->select('blocked_id')
                        ->from('user_blocks')
                        ->where('blocker_id', $authId)
                        ->where('is_active', 1);
                })
                ->whereNotIn('following_id', function ($query) use ($authId) {
                    $query->select('blocker_id')
                        ->from('user_blocks')
                        ->where('blocked_id', $authId)
                        ->where('is_active', 1);
                });
            }

            else {
                // Default: use user_id
                $builder->whereNotIn('user_id', function ($query) use ($authId) {
                    $query->select('blocked_id')
                        ->from('user_blocks')
                        ->where('blocker_id', $authId)
                        ->where('is_active', 1);
                })
                ->whereNotIn('user_id', function ($query) use ($authId) {
                    $query->select('blocker_id')
                        ->from('user_blocks')
                        ->where('blocked_id', $authId)
                        ->where('is_active', 1);
                });
            }
        });
    }
}
