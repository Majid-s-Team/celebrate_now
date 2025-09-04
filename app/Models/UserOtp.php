<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ExcludeBlockedUsersScope;


class UserOtp extends Model
{
    use HasFactory,ExcludeBlockedUsersScope;
    protected $fillable = ['user_id', 'otp', 'expires_at'];
}
