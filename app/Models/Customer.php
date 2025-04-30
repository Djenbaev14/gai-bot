<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $guarded=['id'];

    public function gayApplications(){
        return $this->hasMany(GayApplication::class);
    }
    public function region(){
        return $this->belongsTo(Region::class);
    }
    public function branch(){
        return $this->belongsTo(Branch::class);
    }
    
}
