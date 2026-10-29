<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name','description','type'];



    public function drug(){
        return $this->hasMany(Drug::class);
    }
}
