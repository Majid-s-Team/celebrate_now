<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ExcludeBlockedUsersScope;
use App\Traits\ExcludeDeletedUsersScope;


class Role extends Model
{
    use HasFactory,ExcludeBlockedUsersScope,ExcludeDeletedUsersScope;
}
