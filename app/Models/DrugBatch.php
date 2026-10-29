<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrugBatch extends Model
{
    protected $fillable = ['drug_id','batch_no','cost_price','selling_price','expiring_date','quantity'];



    public function drug(){
        return $this->belongsTo(Drug::class);
    }

    public function drugBatch(){
        return $this->hasMany(DrugSales::class);
    }




}
