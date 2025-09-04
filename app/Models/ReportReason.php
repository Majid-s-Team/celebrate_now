<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ExcludeBlockedUsersScope;


class ReportReason extends Model
{
    use ExcludeBlockedUsersScope;
    protected $fillable = ['reason'];
}
