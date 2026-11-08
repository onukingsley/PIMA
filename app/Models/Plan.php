<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['plan_name','plan_amount','period','feature','max_branch','max_user','duration'];

    protected function casts(): array
    {
        return [
            'feature' => 'array',

        ];
    }


    public function subscription(){
        return $this->hasMany(Subscription::class);
    }

    public function pharmacy(){
        return $this->hasMany(Pharmacy::class);
    }


}
