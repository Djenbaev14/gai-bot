<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GayApplication extends Model
{
    use HasFactory,SoftDeletes;
    protected $guarded=['id'];

    public function customer(){
        return $this->belongsTo(Customer::class);
    }
    public function status(){
        return $this->belongsTo(Status::class);
    }
    public function queueNumber(){
        return $this->hasOne(QueueNumber::class);
    }
}
