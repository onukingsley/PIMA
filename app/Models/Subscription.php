<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['pharmacy_id','subscription_name','amount','duration','expiring_date'];

    public function pharmacy (){
        return $this->belongsTo(Pharmacy::class);
    }
}
