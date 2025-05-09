<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchRegion extends Model
{
    use HasFactory;

    public function region()
    {
        return $this->belongsTo(Region::class,'region_id');
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
