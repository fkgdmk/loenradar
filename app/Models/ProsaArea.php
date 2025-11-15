<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProsaArea extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'area_name',
    ];

    /**
     * Get the regions for this PROSA area
     */
    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'prosa_area_regions', 'area_id', 'region_id');
    }

    /**
     * Get the salary stats for this PROSA area
     */
    public function salaryStats(): HasMany
    {
        return $this->hasMany(ProsaSalaryStat::class, 'prosa_area_id');
    }
}
