<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

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
    public function getQueueNumberValueAttribute()
    {
        return $this->queueNumber?->queue_number;
    }
}
