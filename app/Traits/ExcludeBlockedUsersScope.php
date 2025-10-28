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
                // For users table exclude both (you blocked them) and (they blocked you)
                $builder->whereNotIn('id', function ($query) use ($authId) {
                    $query->select('blocked_id')
                        ->from('user_blocks')
                        ->where('blocker_id', $authId);
                })
                ->whereNotIn('id', function ($query) use ($authId) {
                    $query->select('blocker_id')
                        ->from('user_blocks')
                        ->where('blocked_id', $authId);
                });
            }

            elseif ($table === 'events') {
                // For events table use created_by and handle both directions
                $builder->whereNotIn('created_by', function ($query) use ($authId) {
                    $query->select('blocked_id')
                        ->from('user_blocks')
                        ->where('blocker_id', $authId);
                })
                ->whereNotIn('created_by', function ($query) use ($authId) {
                    $query->select('blocker_id')
                        ->from('user_blocks')
                        ->where('blocked_id', $authId);
                });
            }

            elseif ($table === 'polls') {
                // For events table use created_by and handle both directions
                $builder->whereNotIn('created_by', function ($query) use ($authId) {
                    $query->select('blocked_id')
                        ->from('user_blocks')
                        ->where('blocker_id', $authId);
                })
                ->whereNotIn('created_by', function ($query) use ($authId) {
                    $query->select('blocker_id')
                        ->from('user_blocks')
                        ->where('blocked_id', $authId);
                });
            }

            elseif ($table === 'follows') {
                // For follows table, check both follower_id and following_id
                $builder->whereNotIn('follower_id', function ($query) use ($authId) {
                    $query->select('blocked_id')
                        ->from('user_blocks')
                        ->where('blocker_id', $authId);
                })
                ->whereNotIn('follower_id', function ($query) use ($authId) {
                    $query->select('blocker_id')
                        ->from('user_blocks')
                        ->where('blocked_id', $authId);
                })
                ->whereNotIn('following_id', function ($query) use ($authId) {
                    $query->select('blocked_id')
                        ->from('user_blocks')
                        ->where('blocker_id', $authId);
                })
                ->whereNotIn('following_id', function ($query) use ($authId) {
                    $query->select('blocker_id')
                        ->from('user_blocks')
                        ->where('blocked_id', $authId);
                });
            }

            else {
                // For other tables use user_id and handle both directions
                $builder->whereNotIn('user_id', function ($query) use ($authId) {
                    $query->select('blocked_id')
                        ->from('user_blocks')
                        ->where('blocker_id', $authId);
                })
                ->whereNotIn('user_id', function ($query) use ($authId) {
                    $query->select('blocker_id')
                        ->from('user_blocks')
                        ->where('blocked_id', $authId);
                });
            }
        });
    }
}
