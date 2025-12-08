<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportGroup extends Model
{
    use HasFactory;

    protected $table = 'report_group';

    protected $fillable = [
        'report_reason_id',
        'group_id',
        'reported_by',
        'is_active',
        'reported_at',
        'un_reported_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'reported_at' => 'datetime',
        'un_reported_at' => 'datetime',
    ];

    /**
     * Report Reason Relationship
     */
    public function reason()
    {
        return $this->belongsTo(ReportReason::class, 'report_reason_id');
    }

    /**
     * Group Relationship
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    /**
     * User who reported the group
     */
    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
