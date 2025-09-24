<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinPackage extends Model
{
    protected $fillable = ['coins', 'price', 'currency'];

    public function transactions()
    {
        return $this->hasMany(CoinTransaction::class);
    }
    
}
