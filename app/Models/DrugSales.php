<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrugSales extends Model
//class DrugSales extends Pivot
{
    protected $fillable = ['sales_id','drug_id','drug_batch_id','quantity','dosage','duration','route','instruction','status','unit_price'];


    public function drug(){
        return $this->belongsTo(Drug::class);
    }

    public function drugBatch(){
        return $this->belongsTo(DrugBatch::class);
    }

    public function sale(){
        return $this->belongsTo(Sales::class);
    }

}
