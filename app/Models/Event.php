<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'date', 'start_time', 'end_time', 'location',
        'description', 'cover_photo_url', 'event_type_id',
        'mode', 'physical_type', 'funding_type',
        'surprise_contribution', 'created_by',
        'donation_goal', 'is_show_donation', 'donation_deadline'

    ];

    public function category()
    {
        return $this->belongsTo(EventCategory::class, 'event_type_id');
    }

    public function members()
    {
        return $this->hasMany(EventMember::class);
    }

    public function polls()
    {
        return $this->hasMany(Poll::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function donations()
    {
        return $this->hasMany(EventDonation::class);
    }

     protected static function boot()
    {
        parent::boot();

        static::deleting(function ($event) {

            $event->members()->delete();


            foreach ($event->polls as $poll) {
                $poll->delete();
            }
        });
    }
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

}
