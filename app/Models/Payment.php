<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['user_id','branch_in','payment_type','title','description','invoice_id','amount','status','completion_status','outStanding_balance'];

    public function salaryAllowance(){
        return $this->hasMany(SalaryAllowances::class);
    }


    public function sales(){
        return $this->hasMany(Sales::class);
    }




    public function restock(){
        return $this->hasMany(Restock::class);
    }
    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch(){
        return $this->belongsTo(Branch::class);
    }



}
