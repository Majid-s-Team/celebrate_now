<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PollVote extends Model
{
    use HasFactory;

    protected $fillable = ['poll_id', 'voter_id', 'candidate_id'];
    

    public function poll()
    {
        return $this->belongsTo(Poll::class);
    }

    public function voter()
    {
        return $this->belongsTo(User::class, 'voter_id');
    }
    public function votes()
    {
        return $this->hasMany(PollVote::class);
    }


    public function candidate()
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }
}
