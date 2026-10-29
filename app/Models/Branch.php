<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['branch_name','address','pharmacy_id','phone_no'];


    public function pharmacy(){
        return $this->belongsTo(Pharmacy::class);
    }

    public function user(){
        return $this->hasMany(User::class);
    }

    public function sales(){
        return $this->hasMany(Sales::class);
    }

    public function payment(){
        return $this->hasMany(Payment::class);
    }

    public function drug(){
        return $this->hasMany(Drug::class);
    }

    public function restock(){
        return $this->hasMany(Restock::class);
    }






}
