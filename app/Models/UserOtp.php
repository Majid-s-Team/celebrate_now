<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ExcludeBlockedUsersScope;
use App\Traits\ExcludeDeletedUsersScope;


class UserOtp extends Model
{
    use HasFactory,ExcludeBlockedUsersScope,ExcludeDeletedUsersScope;
    protected $fillable = ['user_id', 'otp', 'expires_at'];
}
