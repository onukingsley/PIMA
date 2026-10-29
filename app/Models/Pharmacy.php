<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pharmacy extends Model
{
    protected $fillable = ['pharmacy_name','reg_ID','subscription_date','subscription_name','PIMA_ID','email','phone_no'];

    public function branch(){
        return $this->hasMany(Branch::class);
    }

    public function subscription(){
        return $this->hasMany(Subscription::class);
    }
}
