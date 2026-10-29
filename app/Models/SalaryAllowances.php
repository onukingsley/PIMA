<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryAllowances extends Model
{
    protected $fillable = ['user_id','payment_id','month','year'];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function payment(){
        return $this->hasMany(Payment::class);
    }
}
