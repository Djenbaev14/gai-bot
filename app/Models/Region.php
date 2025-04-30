<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    use HasFactory;

    protected $guarded=['id'];

    public function branch()
    {
        return $this->belongsToMany(Branch::class, 'branch_regions', 'region_id', 'branch_id');
    }
    public function branchRegions()
    {
        return $this->hasMany(BranchRegion::class);
    }
}
