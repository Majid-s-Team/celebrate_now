<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait ExcludeDeletedUsersScope
{
    protected static function bootExcludeDeletedUsersScope()
    {
        static::addGlobalScope('exclude_deleted_users', function (Builder $builder) {
            $model = new static;
            $table = $model->getTable();

            if ($table === 'users') {
                $builder->whereNull("$table.deleted_at");
            }

            elseif ($table === 'events') {
                $builder->whereNotIn('created_by', function ($query) {
                    $query->select('id')
                        ->from('users')
                        ->whereNotNull('deleted_at');
                });
            }

            elseif ($table === 'polls') {
                $builder->whereNotIn('created_by', function ($query) {
                    $query->select('id')
                        ->from('users')
                        ->whereNotNull('deleted_at');
                });
            }

            elseif ($table === 'follows') {
                $builder->whereNotIn('follower_id', function ($query) {
                    $query->select('id')
                        ->from('users')
                        ->whereNotNull('deleted_at');
                })
                ->whereNotIn('following_id', function ($query) {
                    $query->select('id')
                        ->from('users')
                        ->whereNotNull('deleted_at');
                });
            }

            else {
                if (Schema::hasColumn($table, 'user_id')) {
                    $builder->whereNotIn('user_id', function ($query) {
                        $query->select('id')
                            ->from('users')
                            ->whereNotNull('deleted_at');
                    });
                } elseif (Schema::hasColumn($table, 'created_by')) {
                    $builder->whereNotIn('created_by', function ($query) {
                        $query->select('id')
                            ->from('users')
                            ->whereNotNull('deleted_at');
                    });
                }
            }
        });
    }
}
