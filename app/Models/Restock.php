<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restock extends Model
{
    protected $fillable = ['drug_id','payment_id','user_id','quantity','unit_price','title','status','notes'];

    public function drug(){
        return $this->belongsTo(Drug::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
    public function payment(){
        return $this->belongsTo(Payment::class);
    }

    public function branch(){
        return $this->belongsTo(Branch::class);
    }
}
