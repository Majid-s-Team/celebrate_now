<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    // $card = Card::first();
    // $realNumber = decrypt($card->card_number);
    // $realCvv = $card->getDecryptedCvv();

    protected $fillable = [
        'user_id', 'card_holder_name', 'card_number', 'expiry_month', 'expiry_year', 'cvv', 'card_type'
    ];

    protected $hidden = ['card_number', 'cvv']; 


    protected $appends = ['masked_card_number'];

    public function getMaskedCardNumberAttribute()
    {
        try {
            $number = decrypt($this->card_number);
        } catch (\Exception $e) {
            return null; 
        }

        $last4 = substr($number, -4);
        return '**** **** **** ' . $last4;
    }

    public function getDecryptedCvv()
    {
        try {
            return decrypt($this->cvv);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
