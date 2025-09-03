<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PollOption extends Model
{
    use HasFactory;

    protected $fillable = ['poll_id', 'option_text', 'added_by'];

    public function poll()
    {
        return $this->belongsTo(Poll::class);
    }
    public function options()
    {
        return $this->hasMany(PollOption::class);
    }


    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
