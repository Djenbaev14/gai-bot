<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;
    protected $guarded=['id'];
    public function regions()
    {
        return $this->belongsToMany(Region::class, 'branch_regions', 'branch_id', 'region_id');
    }
    public function branchRegions()
    {
        return $this->hasMany(BranchRegion::class);
    }
}
