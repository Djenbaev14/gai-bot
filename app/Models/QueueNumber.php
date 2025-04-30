<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QueueNumber extends Model
{
    use HasFactory,SoftDeletes;
    protected $guarded=['id'];
    public function application()
    {
        return $this->belongsTo(GayApplication::class,'gay_application_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
