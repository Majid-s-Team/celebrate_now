<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Poll extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'created_by', 'status', 'poll_date', 'question', 'allow_member_add_option', 'allow_multiple_selection'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($poll) {
            $poll->votes()->delete();
            $poll->candidates()->delete();
            $poll->options()->delete();
        });
    }


    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function votes()
    {
        return $this->hasMany(PollVote::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function candidates()
    {
        return $this->hasMany(PollCandidate::class);
    }
    public function options()
    {
        return $this->hasMany(PollOption::class);
    }

}
