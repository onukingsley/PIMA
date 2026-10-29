<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sales extends Model
{
    protected $fillable = ['user_id','branch_id','payment_id','total_amount','payment_status','delivery_status'];

    public function drug(){
        return $this->belongsToMany(drug::class,'drug_sales','sales_id','drug_id')
            ->withPivot('quantity','unit_price','dosage','status','duration','route','instruction')->withTimestamps();
    }


    public function drugSale(){
        return $this->hasMany(DrugSales::class);
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

    public function getTotalAttribute()
    {
        return $this->drugStock->sum(function ($drug) {
            return $drug->pivot->amount;
        });
    }
}
