<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Drug extends Model
{

    protected $fillable = ['category_id','name','dosage','generic','amount','status','quantity','description','expiry_data_range','branch_id'];

    public function sales(){
        return $this->belongsToMany(Sales::class,'drug_sales','drug_id','sales_id')
            ->withPivot('quantity','unit_Price','dosage','status','duration','route','instruction');
    }

    public function category(){
        return $this->belongsTo(Category::class);
    }
    public function branch(){
        return $this->belongsTo(Branch::class);
    }



    public function restock(){
        return $this->hasMany(Restock::class);
    }

    public function drugSale(){
        return $this->hasMany(DrugSales::class);
    }

    public function drugBatch(){
        return $this->hasMany(DrugBatch::class);
    }



}
